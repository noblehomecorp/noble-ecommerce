<?php
// paymongo.php — PayMongo webhook (checkout_session.payment.paid)

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';
require_once ROOT_PATH . '/user/ui-page/backend/backend-page-5/helper/finalize-order-helper.php';

$payload   = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_PAYMONGO_SIGNATURE'] ?? '';
$secret    = $_ENV['PAYMONGO_WEBHOOK_SECRET'] ?? getenv('PAYMONGO_WEBHOOK_SECRET');

// ── Secret must exist — otherwise anyone could sign with an empty key ─────────
if (empty($secret)) {
    error_log('PayMongo webhook: PAYMONGO_WEBHOOK_SECRET is not configured.');
    http_response_code(500);
    exit('Webhook not configured');
}

// ── Verify signature ──────────────────────────────────────────────────────────
$parts = [];
foreach (explode(',', $sigHeader) as $part) {
    $kv = explode('=', trim($part), 2);
    if (count($kv) === 2) {
        $parts[$kv[0]] = $kv[1];
    }
}

$timestamp = $parts['t'] ?? '';
$expected  = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
// Live mode signs with "li", test mode with "te"
$received  = ($parts['li'] ?? '') !== '' ? $parts['li'] : ($parts['te'] ?? '');

if ($timestamp === '' || $received === '' || !hash_equals($expected, $received)) {
    error_log('PayMongo webhook: invalid signature.');
    http_response_code(401);
    exit('Invalid signature');
}

$event = json_decode($payload, true);
$type  = $event['data']['attributes']['type'] ?? '';

// Only handle successful payment
if ($type !== 'checkout_session.payment.paid') {
    http_response_code(200);
    exit('Ignored');
}

// ── Extract session + metadata ────────────────────────────────────────────────
$session   = $event['data']['attributes']['data'] ?? [];
$sessionId = $session['id'] ?? null;
$metadata  = $session['attributes']['metadata'] ?? $event['data']['attributes']['metadata'] ?? [];

$userId         = intval($metadata['user_id'] ?? 0);
$pendingOrderId = intval($metadata['pending_order_id'] ?? 0);

if (!$userId || !$pendingOrderId) {
    error_log('PayMongo webhook: missing metadata.');
    http_response_code(200);
    exit('Missing metadata');
}

// Total actually paid (sum of paid payments), if PayMongo included it
$amountPaid = null;
foreach ($session['attributes']['payments'] ?? [] as $p) {
    if (($p['attributes']['status'] ?? '') === 'paid') {
        $amountPaid = ($amountPaid ?? 0) + intval($p['attributes']['amount'] ?? 0);
    }
}

$opts = ['session_id' => $sessionId];
if ($amountPaid !== null) {
    $opts['amount_centavos'] = $amountPaid;
}

// ── Finalize exactly once (row lock + used flag inside the helper) ────────────
$result = finalizePendingOrder($conn, $pendingOrderId, $userId, $opts);

switch ($result['status']) {
    case 'ok':
    case 'already':        // duplicate / retried webhook — already handled
        http_response_code(200);
        exit('OK');

    case 'not_found':
    case 'mismatch':
    case 'invalid':        // not retryable — log and acknowledge
        error_log("PayMongo webhook: {$result['status']} for pending $pendingOrderId (user $userId)");
        http_response_code(200);
        exit('Not processed: ' . $result['status']);

    default:               // DB error → 500 so PayMongo retries later
        http_response_code(500);
        exit('Error');
}