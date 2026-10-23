<?php
// user/ui-page/page-5/success.php
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

require_once ROOT_PATH . '/user/ui-page/backend/backend-page-5/helper/finalize-order-helper.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/google');
    exit;
}
$userId = intval($_SESSION['user_id']);

// The success_url is /success?pending=ID — show THIS checkout, not just the latest order.
$pendingOrderId = intval($_GET['pending'] ?? 0);
if ($pendingOrderId <= 0) {
    // Old links without ?pending → fall back to the user's most recent checkout
    $s = $conn->prepare("SELECT id FROM noblependingorder WHERE user_id = ? ORDER BY id DESC LIMIT 1");
    $s->bind_param("i", $userId);
    $s->execute();
    $pendingOrderId = intval($s->get_result()->fetch_assoc()['id'] ?? 0);
    $s->close();
}

// ── Localhost bypass (no webhook on localhost) — uses the SAME finalize logic ──
$isLocalhost = false;
if ($isLocalhost && $pendingOrderId > 0) {
    finalizePendingOrder($conn, $pendingOrderId, $userId);
}

// ── Look up the pending order + its final order ──────────────────────────────
$pending = null;
if ($pendingOrderId > 0) {
    $s = $conn->prepare("SELECT id, payment_status, final_order_id, used FROM noblependingorder WHERE id = ? AND user_id = ?");
    $s->bind_param("ii", $pendingOrderId, $userId);
    $s->execute();
    $pending = $s->get_result()->fetch_assoc();
    $s->close();
}

if (!$pending) {
    header('Location: ' . BASE_URL . '/');
    exit;
}

$order = null;
$finalOrderId = intval($pending['final_order_id'] ?? 0);
if ($finalOrderId > 0) {
    $s = $conn->prepare("
        SELECT o.*, COUNT(i.id) AS item_count
        FROM noblepaidproductlist o
        LEFT JOIN noblepaidproductitems i ON i.order_id = o.id
        WHERE o.id = ? AND o.user_id = ? AND o.payment_status = 'paid'
        GROUP BY o.id
    ");
    $s->bind_param("ii", $finalOrderId, $userId);
    $s->execute();
    $order = $s->get_result()->fetch_assoc();
    $s->close();
}

// Cancelled / expired checkout that was never paid
if (!$order && in_array($pending['payment_status'], ['cancelled', 'expired'], true) && intval($pending['used']) === 0) {
    header('Location: ' . BASE_URL . '/checkout?cancelled=1');
    exit;
}

$tries    = intval($_GET['t'] ?? 0);
$maxTries = 20; // ~60 seconds of auto-refresh

if (!$order) {
    // Still processing (webhook not received yet) — waiting screen
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Confirming Payment — NobleHome</title>
        <?php include ROOT_PATH . '/link/top.php'; ?>
    </head>
    <body class="bg-gray-50 min-h-screen text-gray-900">
        <?php include ROOT_PATH . '/user/navigation/top.php'; ?>

        <main class="max-w-lg mx-auto px-4 py-16">
            <div class="bg-white rounded-lg border border-gray-200 p-8 text-center">
                <?php if ($tries < $maxTries): ?>
                    <span class="mx-auto w-14 h-14 rounded-full bg-gray-100 flex items-center justify-center">
                        <i class="fa-solid fa-spinner fa-spin text-gray-700 text-xl"></i>
                    </span>
                    <h1 class="text-lg font-bold text-gray-900 mt-5">Confirming your payment…</h1>
                    <p class="text-sm text-gray-500 mt-2">
                        We're waiting for confirmation from PayMongo. This usually takes a few seconds.
                    </p>
                    <p class="text-xs text-gray-400 mt-4">This page refreshes automatically — please don't pay again.</p>
                    <script>
                        setTimeout(() => {
                            location.href = <?= json_encode(BASE_URL . '/success?pending=' . $pendingOrderId . '&t=' . ($tries + 1)) ?>;
                        }, 3000);
                    </script>
                <?php else: ?>
                    <span class="mx-auto w-14 h-14 rounded-full bg-amber-50 flex items-center justify-center">
                        <i class="fa-solid fa-clock text-amber-600 text-xl"></i>
                    </span>
                    <h1 class="text-lg font-bold text-gray-900 mt-5">Still confirming your payment</h1>
                    <p class="text-sm text-gray-500 mt-2">
                        This is taking longer than usual. If you completed the payment, your order will appear
                        in <strong class="font-semibold text-gray-700">My Orders</strong> once it's confirmed. Please don't pay again.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-2 justify-center mt-6">
                        <a href="<?= BASE_URL ?>/success?pending=<?= $pendingOrderId ?>"
                            class="h-11 px-5 inline-flex items-center justify-center rounded-md bg-gray-900 hover:bg-black text-white text-sm font-semibold">Check again</a>
                        <a href="<?= BASE_URL ?>/orders"
                            class="h-11 px-5 inline-flex items-center justify-center rounded-md border border-gray-300 text-sm font-semibold text-gray-700 hover:bg-gray-50">Go to My Orders</a>
                    </div>
                <?php endif; ?>
            </div>
        </main>

        <?php include ROOT_PATH . '/user/navigation/bottom.php'; ?>
    </body>
    </html>
    <?php
    exit;
}

// ── Order items for the confirmation ─────────────────────────────────────────
$orderItems = [];
$s = $conn->prepare("
    SELECT i.product_name, i.colorname, i.sizename, i.quantity, i.line_total,
           COALESCE(NULLIF(c.imagecolor, ''), p.imageproduct) AS image
    FROM noblepaidproductitems i
    LEFT JOIN nobleproduct p ON p.id = i.product_id
    LEFT JOIN nobleproductcolor c ON c.id = i.color_id
    WHERE i.order_id = ?
    ORDER BY i.id ASC
");
if ($s) {
    $oid = intval($order['id']);
    $s->bind_param("i", $oid);
    $s->execute();
    $orderItems = $s->get_result()->fetch_all(MYSQLI_ASSOC);
    $s->close();
}

$isPickup  = ($order['delivery_method'] ?? '') === 'pickup';
$payLabel  = ($order['payment_method'] ?? '') === 'qrph' ? 'QR Ph' : 'PayMongo (Cards & e-wallets)';
$addrLine  = trim(implode(', ', array_filter([$order['address_full'] ?? '', $order['address_barangay'] ?? '', $order['address_city'] ?? ''])));
$variantOf = function ($c, $sz) {
    $parts = [];
    if (trim((string) $c) !== '' && $c !== 'Default') $parts[] = $c;
    if (trim((string) $sz) !== '' && $sz !== 'Default') $parts[] = $sz;
    return implode(' · ', $parts);
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmed — NobleHome</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
</head>
<body class="bg-gray-50 min-h-screen text-gray-900">
    <?php include ROOT_PATH . '/user/navigation/top.php'; ?>

    <main class="max-w-3xl mx-auto px-4 sm:px-6 py-8 md:py-10">

        <!-- Confirmation header -->
        <div class="text-center mb-6">
            <span class="mx-auto w-14 h-14 rounded-full bg-green-50 border border-green-100 flex items-center justify-center">
                <i class="fa-solid fa-check text-green-600 text-xl"></i>
            </span>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900 mt-4">Thank you for your order!</h1>
            <p class="text-sm text-gray-500 mt-1.5">
                Hi <strong class="font-semibold text-gray-700"><?= htmlspecialchars($order['contact_name']) ?></strong>,
                your payment was received and your order is being prepared.
            </p>
            <p class="text-xs text-gray-500 mt-1">
                A confirmation will be sent to <?= htmlspecialchars($order['contact_email']) ?>.
            </p>
        </div>

        <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">

            <!-- Reference strip -->
            <div class="grid grid-cols-2 sm:grid-cols-4 divide-x divide-gray-100 border-b border-gray-100">
                <div class="px-4 py-3.5">
                    <p class="text-[11px] text-gray-500">Order number</p>
                    <p class="text-sm font-bold text-gray-900 mt-0.5 break-all"><?= htmlspecialchars($order['nhccreference']) ?></p>
                </div>
                <div class="px-4 py-3.5">
                    <p class="text-[11px] text-gray-500">Date</p>
                    <p class="text-sm font-semibold text-gray-900 mt-0.5">
                        <?= !empty($order['created_at']) ? date('M j, Y', strtotime($order['created_at'])) : date('M j, Y') ?>
                    </p>
                </div>
                <div class="px-4 py-3.5 border-t sm:border-t-0 border-gray-100">
                    <p class="text-[11px] text-gray-500">Payment</p>
                    <p class="text-sm font-semibold text-gray-900 mt-0.5"><?= htmlspecialchars($payLabel) ?></p>
                </div>
                <div class="px-4 py-3.5 border-t sm:border-t-0 border-gray-100">
                    <p class="text-[11px] text-gray-500">Status</p>
                    <p class="text-sm font-semibold text-green-700 mt-0.5"><i class="fa-solid fa-circle-check mr-1 text-xs"></i>Paid</p>
                </div>
            </div>

            <!-- Items -->
            <?php if (!empty($orderItems)): ?>
                <ul class="divide-y divide-gray-100 px-5">
                    <?php foreach ($orderItems as $it):
                        $v = $variantOf($it['colorname'], $it['sizename']); ?>
                        <li class="flex items-center gap-3 py-3.5">
                            <div class="w-14 h-14 shrink-0 rounded-md bg-gray-50 border border-gray-200 overflow-hidden flex items-center justify-center">
                                <?php if (!empty($it['image'])): ?>
                                    <img src="<?= BASE_URL ?>/uploads/<?= htmlspecialchars($it['image']) ?>" alt="" class="w-full h-full object-contain p-0.5" loading="lazy">
                                <?php else: ?>
                                    <i class="fa-solid fa-image text-gray-300"></i>
                                <?php endif; ?>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-gray-900 truncate"><?= htmlspecialchars($it['product_name']) ?></p>
                                <p class="text-xs text-gray-500 mt-0.5">
                                    <?= $v !== '' ? htmlspecialchars($v) . ' · ' : '' ?>Qty <?= intval($it['quantity']) ?>
                                </p>
                            </div>
                            <p class="text-sm font-semibold text-gray-900 shrink-0">₱<?= number_format(floatval($it['line_total']), 2) ?></p>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <!-- Fulfillment + totals -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 px-5 py-5 border-t border-gray-100 bg-gray-50/60">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500">
                        <?= $isPickup ? 'Store pickup' : 'Delivery address' ?>
                    </p>
                    <?php if ($isPickup): ?>
                        <p class="text-sm text-gray-900 mt-1.5">We'll notify you when your order is ready for pickup.</p>
                    <?php else: ?>
                        <p class="text-sm text-gray-900 mt-1.5"><?= htmlspecialchars($addrLine ?: '—') ?></p>
                        <?php if (!empty($order['truck_name'])): ?>
                            <p class="text-xs text-gray-500 mt-1"><i class="fa-solid fa-truck mr-1 text-gray-400"></i><?= htmlspecialchars($order['truck_name']) ?></p>
                        <?php endif; ?>
                    <?php endif; ?>
                    <p class="text-xs text-gray-500 mt-3">
                        <i class="fa-solid fa-phone mr-1 text-gray-400"></i><?= htmlspecialchars($order['contact_phone']) ?>
                    </p>
                </div>

                <dl class="space-y-1.5 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-gray-600">Subtotal</dt>
                        <dd class="text-gray-900">₱<?= number_format(floatval($order['subtotal']), 2) ?></dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-600">VAT (12%)</dt>
                        <dd class="text-gray-900">₱<?= number_format(floatval($order['vat_amount']), 2) ?></dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-600">Delivery</dt>
                        <dd class="text-gray-900"><?= floatval($order['delivery_fee']) > 0 ? '₱' . number_format(floatval($order['delivery_fee']), 2) : 'Free' ?></dd>
                    </div>
                    <div class="flex justify-between items-baseline border-t border-gray-200 pt-2">
                        <dt class="font-semibold text-gray-900">Total paid</dt>
                        <dd class="text-lg font-bold text-gray-900">₱<?= number_format(floatval($order['grand_total']), 2) ?></dd>
                    </div>
                </dl>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex flex-col sm:flex-row gap-3 mt-5">
            <a href="<?= BASE_URL ?>/orders"
                class="flex-1 h-12 inline-flex items-center justify-center gap-2 rounded-md bg-gray-900 hover:bg-black text-white text-sm font-semibold">
                <i class="fa-solid fa-truck-fast text-xs"></i> Track my order
            </a>
            <a href="<?= BASE_URL ?>/"
                class="flex-1 h-12 inline-flex items-center justify-center rounded-md border border-gray-300 bg-white text-sm font-semibold text-gray-700 hover:bg-gray-50">
                Continue shopping
            </a>
        </div>
    </main>

    <?php include ROOT_PATH . '/user/navigation/bottom.php'; ?>
</body>
</html>