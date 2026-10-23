<?php
// handler/finalize-order-helper.php

require_once __DIR__ . '/stock-reserve-helper.php';

if (!function_exists('finalizePendingOrder')):

/**
 * @param array $opts ['session_id' => PayMongo checkout session id, 'amount_centavos' => int paid]
 * @return array ['status' => 'ok'|'already'|'not_found'|'mismatch'|'invalid'|'error', 'order_id' => ?int]
 */
function finalizePendingOrder(mysqli $conn, int $pendingOrderId, int $userId, array $opts = []): array
{
    $conn->begin_transaction();
    try {
        // ── 1. Lock the pending order ───────────────────────────────────────
        $s = $conn->prepare("SELECT * FROM noblependingorder WHERE id = ? AND user_id = ? FOR UPDATE");
        if (!$s) throw new \RuntimeException('Prepare failed (lock pending): ' . $conn->error);
        $s->bind_param("ii", $pendingOrderId, $userId);
        $s->execute();
        $pending = $s->get_result()->fetch_assoc();
        $s->close();

        if (!$pending) {
            $conn->commit();
            return ['status' => 'not_found', 'order_id' => null];
        }
        if (intval($pending['used']) === 1) {
            $conn->commit();
            return ['status' => 'already', 'order_id' => intval($pending['final_order_id'] ?? $pending['order_id'] ?? 0)];
        }

        // ── 2. Session must match the one we created for this order ─────────
        $sessionId = $opts['session_id'] ?? null;
        if ($sessionId && !empty($pending['paymongo_session_id']) && $pending['paymongo_session_id'] !== $sessionId) {
            $conn->commit();
            error_log("Finalize: session mismatch for pending $pendingOrderId ($sessionId vs {$pending['paymongo_session_id']})");
            return ['status' => 'mismatch', 'order_id' => null];
        }

        $cartItems = json_decode($pending['cart_items_json'], true);
        if (!is_array($cartItems) || count($cartItems) === 0) {
            $conn->commit();
            error_log("Finalize: invalid cart_items_json for pending $pendingOrderId");
            return ['status' => 'invalid', 'order_id' => null];
        }

        $reviewNotes = [];

        // ── 3. Amount check (only when PayMongo sent it) ────────────────────
        $expectedCentavos = intval(round(floatval($pending['grand_total']) * 100));
        if (isset($opts['amount_centavos']) && intval($opts['amount_centavos']) !== $expectedCentavos) {
            $reviewNotes[] = 'amount paid ₱' . number_format($opts['amount_centavos'] / 100, 2)
                . ' does not match order total ₱' . number_format($expectedCentavos / 100, 2);
        }

        // ── 4. Stock: normally already reserved. If the reservation was released
        //       (cancelled / expired) re-reserve it now, all-or-nothing. ───────
        if (intval($pending['stock_reserved']) !== 1) {
            $conn->query("SAVEPOINT nh_rereserve");
            [$ok] = deductStockForItems($conn, $cartItems);
            if (!$ok) {
                $conn->query("ROLLBACK TO SAVEPOINT nh_rereserve");
                $reviewNotes[] = 'paid after the reservation was released and some items are now out of stock — review / refund needed';
            }
        }

        // ── 5. Sold counter ─────────────────────────────────────────────────
        $soldStmt = $conn->prepare("UPDATE nobleproductvariant SET sold = sold + ? WHERE id = ?");
        if (!$soldStmt) throw new \RuntimeException('Prepare failed (sold update): ' . $conn->error);
        foreach ($cartItems as $item) {
            $vId = intval($item['variant_id'] ?? 0);
            $qty = intval($item['quantity'] ?? 0);
            if ($vId <= 0 || $qty <= 0) continue;
            $soldStmt->bind_param("ii", $qty, $vId);
            $soldStmt->execute();
        }
        $soldStmt->close();

        // ── 6. Insert order (temporary unique ref, replaced right after) ────
        $ins = $conn->prepare("
            INSERT INTO noblepaidproductlist
              (nhccreference, user_id, contact_name, contact_email, contact_phone,
               address_id, address_full, address_barangay, address_city, address_postalcode,
               address_lat, address_lng,
               delivery_method, truck_id, truck_name, truck_max_cubic_meter, truck_max_weight_capacity,
               delivery_distance_km, delivery_fee,
               subtotal, vat_amount, grand_total,
               payment_status, payment_method)
            VALUES
              (?,?,?,?,?,  ?,?,?,?,?,  ?,?,  ?,?,?,?,?,  ?,?,  ?,?,?,  'paid', ?)
        ");
        if (!$ins) throw new \RuntimeException('Prepare failed (order insert): ' . $conn->error);

        $bTmpRef     = 'TMP-' . $pendingOrderId;
        $bUserId     = $userId;
        $bCname      = $pending['contact_name'] ?? '';
        $bCemail     = $pending['contact_email'] ?? '';
        $bCphone     = $pending['contact_phone'] ?? '';
        $bAddrId     = intval($pending['address_id'] ?? 0);
        $bAddrFull   = $pending['address_full'] ?? '';
        $bAddrBgy    = $pending['address_barangay'] ?? '';
        $bAddrCity   = $pending['address_city'] ?? '';
        $bAddrPostal = $pending['address_postal'] ?? '';
        $bAddrLat    = floatval($pending['address_lat'] ?? 0);
        $bAddrLng    = floatval($pending['address_lng'] ?? 0);
        $bMethod     = $pending['method'] ?? 'pickup';
        $bTruckId    = intval($pending['truck_id'] ?? 0);
        $bTruckName  = $pending['truck_name'] ?? '';
        $bTruckVol   = floatval($pending['truck_max_vol'] ?? 0);
        $bTruckWt    = floatval($pending['truck_max_wt'] ?? 0);
        $bDistKm     = floatval($pending['distance_km'] ?? 0);
        $bDelivFee   = floatval($pending['delivery_fee'] ?? 0);
        $bSubtotal   = floatval($pending['subtotal'] ?? 0);
        $bVat        = floatval($pending['vat_amount'] ?? 0);
        $bGrand      = floatval($pending['grand_total'] ?? 0);
        $bPayMethod  = $pending['payment_type'] ?: 'paymongo';

        $ins->bind_param(
            "sisssissssddsisddddddds",
            $bTmpRef, $bUserId, $bCname, $bCemail, $bCphone,
            $bAddrId, $bAddrFull, $bAddrBgy, $bAddrCity, $bAddrPostal,
            $bAddrLat, $bAddrLng,
            $bMethod, $bTruckId, $bTruckName, $bTruckVol, $bTruckWt,
            $bDistKm, $bDelivFee,
            $bSubtotal, $bVat, $bGrand,
            $bPayMethod
        );
        if (!$ins->execute()) throw new \RuntimeException('Execute failed (order insert): ' . $ins->error);
        $orderId = intval($conn->insert_id);
        $ins->close();

        // NHCC-2026-000123 — based on the order ID, so it can never collide.
        // (6 digits so it never clashes with the old 3-digit references.)
        $nhccReference = 'NHCC-' . date('Y') . '-' . str_pad((string) $orderId, 6, '0', STR_PAD_LEFT);
        $refUpd = $conn->prepare("UPDATE noblepaidproductlist SET nhccreference = ? WHERE id = ?");
        $refUpd->bind_param("si", $nhccReference, $orderId);
        if (!$refUpd->execute()) throw new \RuntimeException('Execute failed (ref update): ' . $refUpd->error);
        $refUpd->close();

        // ── 7. Line items (use the final prices saved at checkout) ──────────
        $insItem = $conn->prepare("
            INSERT INTO noblepaidproductitems
              (order_id, product_id, product_name, color_id, colorname, variant_id, sizename, unit,
               quantity, unit_price, discount_pct, line_total)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
        ");
        if (!$insItem) throw new \RuntimeException('Prepare failed (items insert): ' . $conn->error);

        foreach ($cartItems as $idx => $item) {
            $pid   = intval($item['product_id'] ?? 0);
            $pname = $item['product_name'] ?? '';
            $cid   = intval($item['color_id'] ?? 0);
            $cname = $item['colorname'] ?? '';
            $vid   = intval($item['variant_id'] ?? 0);
            $sname = $item['sizename'] ?? '';
            $punit = $item['product_unit'] ?? '';
            $qty   = intval($item['quantity'] ?? 1);
            $price = floatval($item['pricesize'] ?? 0);

            if (isset($item['final_unit_price'])) {
                $unitPrice = floatval($item['final_unit_price']);
                $disc      = floatval($item['effective_discount_pct'] ?? 0);
                $lineTotal = floatval($item['final_line_total'] ?? round($unitPrice * $qty, 2));
            } else { // orders started before this update
                $disc      = floatval($item['discountvariant'] ?? 0);
                $unitPrice = $disc > 0 ? round($price * (1 - $disc / 100), 2) : $price;
                $lineTotal = round($unitPrice * $qty, 2);
            }

            $insItem->bind_param("iisisissiddd", $orderId, $pid, $pname, $cid, $cname, $vid, $sname, $punit, $qty, $unitPrice, $disc, $lineTotal);
            if (!$insItem->execute()) throw new \RuntimeException("Execute failed (item $idx): " . $insItem->error);
        }
        $insItem->close();

        // ── 8. Remove ONLY the cart rows that were in this order ────────────
        $cartIds = array_values(array_filter(array_map(fn($i) => intval($i['cart_id'] ?? 0), $cartItems)));
        if ($cartIds) {
            $ph = implode(',', array_fill(0, count($cartIds), '?'));
            $del = $conn->prepare("DELETE FROM noblecart WHERE user_id = ? AND id IN ($ph)");
            $types = 'i' . str_repeat('i', count($cartIds));
            $del->bind_param($types, $userId, ...$cartIds);
            $del->execute();
            $del->close();
        }

        // ── 9. Mark pending order as paid / used ────────────────────────────
        $upd = $conn->prepare("
            UPDATE noblependingorder
            SET used = 1, order_id = ?, final_order_id = ?, payment_status = 'paid', stock_reserved = 0
            WHERE id = ?
        ");
        $upd->bind_param("iii", $orderId, $orderId, $pendingOrderId);
        if (!$upd->execute()) throw new \RuntimeException('Execute failed (pending update): ' . $upd->error);
        $upd->close();

        // ── 10. Notify Accounting ───────────────────────────────────────────
        $needsReview = !empty($reviewNotes);
        $notifTitle  = ($needsReview ? '[REVIEW] ' : '') . 'New Order ' . $nhccReference;
        $notifMsg    = 'Order from ' . ($pending['contact_name'] ?: 'Customer') . ' — ₱' . number_format($bGrand, 2)
            . ($needsReview ? ' | ' . implode('; ', $reviewNotes) : '');
        $notifLink   = BASE_URL . '/accounting';
        $forRole     = ROLE_ACCOUNTING;
        $forPosition = POSITION_HEAD;
        $forUserId   = null;

        $ns = $conn->prepare("INSERT INTO noblenotification (for_role, for_position, for_user_id, title, message, link) VALUES (?, ?, ?, ?, ?, ?)");
        if (!$ns) throw new \RuntimeException('Prepare failed (notification): ' . $conn->error);
        $ns->bind_param("ssisss", $forRole, $forPosition, $forUserId, $notifTitle, $notifMsg, $notifLink);
        if (!$ns->execute()) throw new \RuntimeException('Execute failed (notification): ' . $ns->error);
        $ns->close();

        $conn->commit();

        if ($needsReview) {
            error_log("Finalize: order $nhccReference needs review: " . implode('; ', $reviewNotes));
        }
        return ['status' => 'ok', 'order_id' => $orderId];

    } catch (\Throwable $e) {
        $conn->rollback();
        error_log('Finalize order error (pending ' . $pendingOrderId . '): ' . $e->getMessage() . ' @' . $e->getFile() . ':' . $e->getLine());
        return ['status' => 'error', 'order_id' => null];
    }
}

endif;