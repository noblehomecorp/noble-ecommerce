<?php
// handler/stock-reserve-helper.php

if (!function_exists('deductStockForItems')):

if (!defined('NH_PENDING_ORDER_TTL_MINUTES')) {
    // How long a checkout may hold reserved stock before it is released.
    define('NH_PENDING_ORDER_TTL_MINUTES', 30);
}

/**
 * Deduct stock for every item. Must be called INSIDE an open transaction —
 * the caller rolls back when this returns false.
 * Quantities of the same variant are combined first so one variant is checked once.
 */
function deductStockForItems(mysqli $conn, array $cartItems): array
{
    $perVariant = [];
    $labels = [];
    foreach ($cartItems as $item) {
        $vId = intval($item['variant_id'] ?? 0);
        $qty = intval($item['quantity'] ?? 0);
        if ($vId <= 0 || $qty <= 0) {
            return [false, ['ok' => false, 'error' => 'Your cart has an invalid item quantity. Please update your cart.']];
        }
        $perVariant[$vId] = ($perVariant[$vId] ?? 0) + $qty;
        $labels[$vId] = ($item['product_name'] ?? 'Item')
            . (!empty($item['colorname']) && $item['colorname'] !== 'Default' ? ' — ' . $item['colorname'] : '')
            . (!empty($item['sizename']) && $item['sizename'] !== 'Default' ? ' / ' . $item['sizename'] : '');
    }

    $stmt = $conn->prepare("UPDATE nobleproductvariant SET stock = stock - ? WHERE id = ? AND stock >= ?");
    if (!$stmt) {
        throw new \RuntimeException('Prepare failed (stock deduct): ' . $conn->error);
    }
    foreach ($perVariant as $vId => $qty) {
        $stmt->bind_param("iii", $qty, $vId, $qty);
        $stmt->execute();
        if ($stmt->affected_rows !== 1) {
            $stmt->close();
            return [false, [
                'ok' => false,
                'error' => 'Sorry, "' . $labels[$vId] . '" no longer has enough stock.',
                'out_of_stock' => true,
                'variant_id' => $vId,
            ]];
        }
    }
    $stmt->close();
    return [true, null];
}

/** Backward-compatible wrapper with its own transaction. */
function reserveStockForCartItems(mysqli $conn, array $cartItems): array
{
    $conn->begin_transaction();
    try {
        [$ok, $err] = deductStockForItems($conn, $cartItems);
        if (!$ok) {
            $conn->rollback();
            return [false, $err];
        }
        $conn->commit();
        return [true, null];
    } catch (\Throwable $e) {
        $conn->rollback();
        error_log('Stock reserve error: ' . $e->getMessage());
        return [false, ['ok' => false, 'error' => 'Failed to reserve stock. Please try again.']];
    }
}

/** Plain restore (no flag check). Only use right after a failed reserve in the same request. */
function restoreStockForCartItems(mysqli $conn, array $cartItems): void
{
    $restore = $conn->prepare("UPDATE nobleproductvariant SET stock = stock + ? WHERE id = ?");
    if (!$restore) {
        error_log('Prepare failed (restoreStockForCartItems): ' . $conn->error);
        return;
    }
    foreach ($cartItems as $item) {
        $qty = intval($item['quantity'] ?? 0);
        $vId = intval($item['variant_id'] ?? 0);
        if ($qty <= 0 || $vId <= 0) continue;
        $restore->bind_param("ii", $qty, $vId);
        $restore->execute();
    }
    $restore->close();
}

/**
 * Restore the stock held by an UNPAID pending order. Safe against double calls:
 * the row is locked and stock_reserved is flipped 1 → 0 only once.
 * Optionally sets payment_status ('cancelled' / 'expired') in the same transaction.
 * Returns true when stock was actually restored.
 */
function restoreStockForPendingOrder(mysqli $conn, int $pendingOrderId, ?string $newStatus = null): bool
{
    $conn->begin_transaction();
    try {
        $s = $conn->prepare("
            SELECT cart_items_json, stock_reserved
            FROM noblependingorder
            WHERE id = ? AND used = 0
            FOR UPDATE
        ");
        if (!$s) {
            throw new \RuntimeException('Prepare failed (select pending order): ' . $conn->error);
        }
        $s->bind_param("i", $pendingOrderId);
        $s->execute();
        $row = $s->get_result()->fetch_assoc();
        $s->close();

        if (!$row) {                       // already paid (used = 1) or missing → never touch stock
            $conn->commit();
            return false;
        }

        $restored = false;
        if (intval($row['stock_reserved']) === 1) {
            $cartItems = json_decode($row['cart_items_json'], true) ?: [];
            restoreStockForCartItems($conn, $cartItems);
            $restored = true;
        }

        if ($newStatus !== null) {
            $upd = $conn->prepare("UPDATE noblependingorder SET stock_reserved = 0, payment_status = ? WHERE id = ?");
            $upd->bind_param("si", $newStatus, $pendingOrderId);
        } else {
            $upd = $conn->prepare("UPDATE noblependingorder SET stock_reserved = 0 WHERE id = ?");
            $upd->bind_param("i", $pendingOrderId);
        }
        if (!$upd) {
            throw new \RuntimeException('Prepare failed (update pending order): ' . $conn->error);
        }
        $upd->execute();
        $upd->close();

        $conn->commit();
        return $restored;
    } catch (\Throwable $e) {
        $conn->rollback();
        error_log('Restore stock error (pending order ' . $pendingOrderId . '): ' . $e->getMessage());
        return false;
    }
}

/** Best-effort: expire a PayMongo checkout session so the customer can no longer pay it. */
function expirePaymongoSession(?string $sessionId): void
{
    if (empty($sessionId)) return;
    $secretKey = $_ENV['PAYMONGO_SECRET_KEY'] ?? getenv('PAYMONGO_SECRET_KEY');
    if (empty($secretKey)) return;

    $ch = curl_init('https://api.paymongo.com/v1/checkout_sessions/' . rawurlencode($sessionId) . '/expire');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => '',
        CURLOPT_HTTPHEADER     => ['Authorization: Basic ' . base64_encode($secretKey . ':')],
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => nhPaymongoVerifySsl(),
    ]);
    curl_exec($ch);
    curl_close($ch);
}

/** SSL verification is ON unless PAYMONGO_SSL_VERIFY=0 (only for local XAMPP without a CA bundle). */
function nhPaymongoVerifySsl(): bool
{
    $v = $_ENV['PAYMONGO_SSL_VERIFY'] ?? getenv('PAYMONGO_SSL_VERIFY');
    return !($v === '0' || $v === 'false');
}

/** Release one unpaid pending order: expire its PayMongo session, restore stock, set status. */
function cancelPendingOrder(mysqli $conn, int $pendingOrderId, string $status = 'cancelled'): void
{
    $s = $conn->prepare("SELECT paymongo_session_id FROM noblependingorder WHERE id = ? AND used = 0");
    $s->bind_param("i", $pendingOrderId);
    $s->execute();
    $row = $s->get_result()->fetch_assoc();
    $s->close();
    if (!$row) return;

    expirePaymongoSession($row['paymongo_session_id'] ?? null);
    restoreStockForPendingOrder($conn, $pendingOrderId, $status);
}

/** A user can only have one open checkout at a time (prevents double-tab / double-click reservations). */
function cancelOpenPendingOrdersForUser(mysqli $conn, int $userId): void
{
    $s = $conn->prepare("SELECT id FROM noblependingorder WHERE user_id = ? AND used = 0 AND stock_reserved = 1");
    $s->bind_param("i", $userId);
    $s->execute();
    $ids = array_column($s->get_result()->fetch_all(MYSQLI_ASSOC), 'id');
    $s->close();
    foreach ($ids as $id) {
        cancelPendingOrder($conn, intval($id), 'cancelled');
    }
}

/** Release stock of abandoned checkouts older than the TTL (any user). */
function expireStalePendingOrders(mysqli $conn, int $limit = 20): void
{
    $ttl = intval(NH_PENDING_ORDER_TTL_MINUTES);
    $s = $conn->prepare("
        SELECT id FROM noblependingorder
        WHERE used = 0 AND stock_reserved = 1
          AND reserved_at < (NOW() - INTERVAL $ttl MINUTE)
        ORDER BY id ASC
        LIMIT ?
    ");
    $s->bind_param("i", $limit);
    $s->execute();
    $ids = array_column($s->get_result()->fetch_all(MYSQLI_ASSOC), 'id');
    $s->close();
    foreach ($ids as $id) {
        cancelPendingOrder($conn, intval($id), 'expired');
    }
}

endif;