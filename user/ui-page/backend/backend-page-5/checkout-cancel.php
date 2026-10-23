<?php
// checkout-cancel.php
// Hit when the user cancels/backs out of PayMongo's hosted checkout page.
// Releases the stock reserved for this pending order and marks it cancelled.
// Safe to open/refresh many times — stock is restored only once.

include ROOT_PATH . '/network/connect.php';
require_once ROOT_PATH . '/user/ui-page/backend/backend-page-5/helper/stock-reserve-helper.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/google');
    exit;
}

$userId         = intval($_SESSION['user_id']);
$pendingOrderId = intval($_GET['pending'] ?? 0);

if ($pendingOrderId > 0) {
    // Verify ownership before restoring
    $s = $conn->prepare("SELECT id FROM noblependingorder WHERE id = ? AND user_id = ? AND used = 0");
    $s->bind_param("ii", $pendingOrderId, $userId);
    $s->execute();
    $owned = $s->get_result()->fetch_assoc();
    $s->close();

    if ($owned) {
        // Expires the PayMongo session (so it can't be paid later), restores stock once,
        // and sets payment_status = 'cancelled'.
        cancelPendingOrder($conn, $pendingOrderId, 'cancelled');
    }
}

header('Location: ' . BASE_URL . '/checkout?cancelled=1');
exit;