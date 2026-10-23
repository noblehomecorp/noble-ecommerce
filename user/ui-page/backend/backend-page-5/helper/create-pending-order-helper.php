<?php
// handler/create-pending-order-helper.php

if (!function_exists('nhStartPaymongoCheckout')):

require_once __DIR__ . '/stock-reserve-helper.php';

if (!defined('NH_MIN_AMOUNT_CENTAVOS')) define('NH_MIN_AMOUNT_CENTAVOS', 2000); // PayMongo minimum: ₱20.00

function nhHaversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
{
    $r = 6371;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
    return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

/**
 * Adds the FINAL price per unit (variant/promo discount + quantity tier) to every item,
 * so the webhook stores line items that add up to the subtotal.
 */
function nhPriceCartItems(array $cartItems, array $promosByProduct, array $productQtyMap, array $tiersPerProduct): array
{
    foreach ($cartItems as &$item) {
        $price = floatval($item['pricesize']);
        $pid = $item['product_id'];
        $discount = max(
            floatval($item['discountvariant']),
            resolvePromoDiscount($promosByProduct, $pid, $item['color_id'], $item['sizename'])
        );
        $unit = $discount > 0 ? $price * (1 - $discount / 100) : $price;
        $tier = resolveQtyDiscountPercent($tiersPerProduct[$pid] ?? [], $productQtyMap[$pid] ?? intval($item['quantity']));
        $final = round($unit * (1 - $tier / 100), 2);

        $item['final_unit_price'] = $final;
        $item['effective_discount_pct'] = $price > 0 ? round((1 - $final / $price) * 100, 2) : 0;
        $item['final_line_total'] = round($final * intval($item['quantity']), 2);
    }
    unset($item);
    return $cartItems;
}

function nhJsonOut(array $data): void
{
    echo json_encode($data);
    exit;
}

/**
 * Full flow: validate → release old checkouts → compute fee server-side →
 * reserve stock + save pending order (one transaction) → create PayMongo session.
 */
function nhStartPaymongoCheckout(mysqli $conn, int $userId, array $body, string $paymentType, array $methodTypes, array $ctx): void
{
    // Values computed by checkout-data.php (passed explicitly so this works even if the
    // router includes the handler from inside a function).
    $cartItems       = $ctx['cartItems'];
    $subtotal        = floatval($ctx['subtotal']);
    $vatAmount       = floatval($ctx['vatAmount']);
    $grandTotal      = floatval($ctx['grandTotal']);
    $assignedTruck   = $ctx['assignedTruck'];
    $storeLat        = $ctx['storeLat'];
    $storeLng        = $ctx['storeLng'];
    $promosByProduct = $ctx['promosByProduct'];
    $productQtyMap   = $ctx['productQtyMap'];
    $tiersPerProduct = $ctx['tiersPerProduct'];

    if (!empty($ctx['hasStockIssue'])) {
        nhJsonOut(['ok' => false, 'error' => 'Some items in your cart are out of stock or exceed available stock. Please update your cart.', 'out_of_stock' => true]);
    }

    // ── 1. Validate input ────────────────────────────────────────────────────
    $contactName  = trim($body['contact_name'] ?? '');
    $contactEmail = trim($body['contact_email'] ?? '');
    $contactPhone = trim($body['contact_phone'] ?? '');
    $addressId    = intval($body['address_id'] ?? 0);
    $method       = in_array($body['method'] ?? '', ['pickup', 'delivery'], true) ? $body['method'] : null;

    if (!$contactName || !$contactPhone || !$method || !filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
        nhJsonOut(['ok' => false, 'error' => 'Please complete your contact details and fulfillment method.']);
    }
    if (mb_strlen($contactName) > 150 || mb_strlen($contactPhone) > 30) {
        nhJsonOut(['ok' => false, 'error' => 'Contact details are too long.']);
    }

    // ── 2. Release abandoned checkouts + this user's previous open checkout ──
    expireStalePendingOrders($conn);
    cancelOpenPendingOrdersForUser($conn, $userId);

    // ── 3. Address + delivery fee — ALWAYS computed on the server ────────────
    $addrFull = $addrBarangay = $addrCity = $addrPostal = null;
    $addrLat = 0.0;
    $addrLng = 0.0;
    $truckId = 0;
    $truckName = null;
    $truckMaxVol = 0.0;
    $truckMaxWt = 0.0;
    $deliveryFee = 0.0;
    $distanceKm = 0.0;

    if ($method === 'delivery') {
        if ($addressId <= 0) {
            nhJsonOut(['ok' => false, 'error' => 'Please select a delivery address.']);
        }
        $s = $conn->prepare("
            SELECT address, barangay, city, postalcode, latitude, longitude
            FROM nobleuserinformation WHERE id = ? AND user_id = ?
        ");
        $s->bind_param("ii", $addressId, $userId);
        $s->execute();
        $row = $s->get_result()->fetch_assoc();
        $s->close();
        if (!$row || $row['latitude'] === null || $row['longitude'] === null) {
            nhJsonOut(['ok' => false, 'error' => 'The selected address was not found. Please choose another address.']);
        }
        if (!$assignedTruck) {
            nhJsonOut(['ok' => false, 'error' => 'No delivery vehicle is available right now. Please choose store pickup.']);
        }

        $addrFull     = $row['address'];
        $addrBarangay = $row['barangay'];
        $addrCity     = $row['city'];
        $addrPostal   = $row['postalcode'];
        $addrLat      = floatval($row['latitude']);
        $addrLng      = floatval($row['longitude']);

        // Same formula as calcDelivery() in checkout.php
        $distanceKm  = round(nhHaversineKm(floatval($storeLat), floatval($storeLng), $addrLat, $addrLng), 2);
        $addFare     = round($distanceKm * floatval($assignedTruck['perkmrate']), 2);
        $deliveryFee = round(floatval($assignedTruck['basefare']) + $addFare, 2);

        $truckId     = intval($assignedTruck['id']);
        $truckName   = ucfirst($assignedTruck['nametruck']) . ' — ' . $assignedTruck['trucktype'];
        $truckMaxVol = floatval($assignedTruck['maxcubicmeter']);
        $truckMaxWt  = floatval($assignedTruck['maxweightcapacity']);

        $clientFee = floatval($body['delivery_fee'] ?? 0);
        if (abs($clientFee - $deliveryFee) > 0.01) {
            error_log("Checkout: client delivery fee $clientFee differs from server fee $deliveryFee (user $userId)");
        }
    } else {
        $addressId = 0;
    }

    // ── 4. Final amount ──────────────────────────────────────────────────────
    $finalGrand     = round($grandTotal + $deliveryFee, 2);
    $amountCentavos = intval(round($finalGrand * 100));
    if ($amountCentavos < NH_MIN_AMOUNT_CENTAVOS) {
        nhJsonOut(['ok' => false, 'error' => 'Order total is below the minimum payable amount (₱20.00).']);
    }

    $pricedItems = nhPriceCartItems($cartItems, $promosByProduct, $productQtyMap, $tiersPerProduct);
    $cartJson = json_encode($pricedItems);

    // ── 5. Reserve stock + save pending order in ONE transaction ─────────────
    $conn->begin_transaction();
    try {
        [$reserved, $errorPayload] = deductStockForItems($conn, $cartItems);
        if (!$reserved) {
            $conn->rollback();
            nhJsonOut($errorPayload);
        }

        $ins = $conn->prepare("
            INSERT INTO noblependingorder
              (user_id,
               contact_name, contact_email, contact_phone,
               address_id, address_full, address_barangay, address_city, address_postal,
               address_lat, address_lng,
               method,
               truck_id, truck_name, truck_max_vol, truck_max_wt,
               distance_km, delivery_fee,
               subtotal, vat_amount, grand_total,
               cart_items_json, payment_type, stock_reserved, reserved_at)
            VALUES (?,?,?,?,  ?,?,?,?,?,  ?,?,  ?,  ?,?,?,?,  ?,?,  ?,?,?,  ?,?, 1, NOW())
        ");
        if (!$ins) {
            throw new \RuntimeException('Prepare failed (pending insert): ' . $conn->error);
        }
        $ins->bind_param(
            'isssissssddsisdddddddss',
            $userId,
            $contactName, $contactEmail, $contactPhone,
            $addressId, $addrFull, $addrBarangay, $addrCity, $addrPostal,
            $addrLat, $addrLng,
            $method,
            $truckId, $truckName, $truckMaxVol, $truckMaxWt,
            $distanceKm, $deliveryFee,
            $subtotal, $vatAmount, $finalGrand,
            $cartJson, $paymentType
        );
        if (!$ins->execute()) {
            throw new \RuntimeException('Execute failed (pending insert): ' . $ins->error);
        }
        $pendingOrderId = intval($conn->insert_id);
        $ins->close();

        $conn->commit();
    } catch (\Throwable $e) {
        $conn->rollback();
        error_log('Checkout: failed to create pending order: ' . $e->getMessage());
        nhJsonOut(['ok' => false, 'error' => 'We could not start your checkout. Please try again.']);
    }

    // ── 6. Create PayMongo Checkout Session ─────────────────────────────────
    $secretKey = $_ENV['PAYMONGO_SECRET_KEY'] ?? getenv('PAYMONGO_SECRET_KEY');

    $payload = [
        'data' => [
            'attributes' => [
                'amount'     => $amountCentavos,
                'currency'   => 'PHP',
                'line_items' => [[
                    'name'        => 'NobleHome Order',
                    'quantity'    => 1,
                    'amount'      => $amountCentavos,
                    'currency'    => 'PHP',
                    'description' => 'Subtotal: ₱' . number_format($subtotal, 2)
                        . ' + VAT: ₱' . number_format($vatAmount, 2)
                        . ($deliveryFee > 0 ? ' + Delivery: ₱' . number_format($deliveryFee, 2) : ''),
                ]],
                'payment_method_types' => $methodTypes,
                'success_url'          => BASE_URL . '/success?pending=' . $pendingOrderId,
                'cancel_url'           => BASE_URL . '/checkoutcancel?pending=' . $pendingOrderId,
                'description'          => 'NobleHome Order',
                'metadata' => [
                    'user_id'          => strval($userId),
                    'pending_order_id' => strval($pendingOrderId),
                ],
            ],
        ],
    ];

    $ch = curl_init('https://api.paymongo.com/v1/checkout_sessions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode($secretKey . ':'),
        ],
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_SSL_VERIFYPEER => nhPaymongoVerifySsl(),
        CURLOPT_TIMEOUT        => 30,
    ]);
    $response   = curl_exec($ch);
    $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr    = curl_error($ch);
    curl_close($ch);

    $pmData = json_decode((string) $response, true);

    // ── 7. PayMongo failed → release stock + remove pending order ───────────
    if ($httpStatus !== 200 || empty($pmData['data']['id'])) {
        error_log("PayMongo create session failed (HTTP $httpStatus) $curlErr: " . substr((string) $response, 0, 500));
        restoreStockForPendingOrder($conn, $pendingOrderId);
        $del = $conn->prepare("DELETE FROM noblependingorder WHERE id = ? AND used = 0");
        $del->bind_param("i", $pendingOrderId);
        $del->execute();
        $del->close();

        nhJsonOut([
            'ok'    => false,
            'error' => 'Payment service error: ' . ($pmData['errors'][0]['detail'] ?? 'Unable to connect. Please try again.'),
        ]);
    }

    // ── 8. Save session ID ──────────────────────────────────────────────────
    $sessionId  = $pmData['data']['id'];
    $sessionUrl = $pmData['data']['attributes']['checkout_url'];

    $upd = $conn->prepare("UPDATE noblependingorder SET paymongo_session_id = ? WHERE id = ?");
    $upd->bind_param("si", $sessionId, $pendingOrderId);
    $upd->execute();
    $upd->close();

    nhJsonOut(['ok' => true, 'checkout_url' => $sessionUrl]);
}

endif;