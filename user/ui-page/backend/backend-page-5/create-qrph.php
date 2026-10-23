<?php
// handler/create-qrph.php  — QR Ph only
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'error' => 'Please sign in to continue.']);
    exit;
}
$userId = intval($_SESSION['user_id']);

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/user/ui-page/backend/backend-page-5/checkout-data.php';
require_once ROOT_PATH . '/user/ui-page/backend/backend-page-5/helper/create-pending-order-helper.php';

$body = json_decode(file_get_contents('php://input'), true) ?: [];

$ctx = compact('cartItems', 'subtotal', 'vatAmount', 'grandTotal', 'assignedTruck', 'storeLat', 'storeLng',
    'promosByProduct', 'productQtyMap', 'tiersPerProduct', 'hasStockIssue');

nhStartPaymongoCheckout($conn, $userId, $body, 'qrph', ['qrph'], $ctx);