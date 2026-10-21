<?php
// user/ui-page/page-4/profile-main.php
include ROOT_PATH . '/network/connect.php';

// Account page — kailangang naka-login
if (empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/');
    exit;
}

$uid = (int) $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'Customer';
$userEmail = $_SESSION['user_email'] ?? '';
$userAvatar = $_SESSION['user_avatar'] ?? '';
$initial = strtoupper(mb_substr(trim($userName) ?: 'U', 0, 1));
$uploadUrl = BASE_URL . '/uploads/';

// ─── Quick stats ───────────────────────────────────────────────
function nhCount(mysqli $conn, string $sql, int $uid): int
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) return 0;
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_row();
    $stmt->close();
    return (int) ($row[0] ?? 0);
}

$cartCount = nhCount($conn, "SELECT COALESCE(SUM(quantity),0) FROM noblecart WHERE user_id = ?", $uid);
$savedCount = nhCount($conn, "SELECT COUNT(*) FROM noblesavedproduct WHERE user_id = ?", $uid);
$recentCount = nhCount($conn, "SELECT COUNT(*) FROM noblerecentview WHERE user_id = ?", $uid);

// ─── Recently viewed products (latest 5) ───────────────────────
$recentProducts = [];
$rvStmt = $conn->prepare("
    SELECT p.id, p.name, p.imageproduct, p.unit, rv.viewed_at,
           MIN(v.pricesize) AS min_price,
           MAX(v.pricesize) AS max_price
    FROM noblerecentview rv
    INNER JOIN nobleproduct p ON p.id = rv.product_id
    LEFT JOIN nobleproductcolor c ON c.product_id = p.id
    LEFT JOIN nobleproductvariant v ON v.color_id = c.id
    WHERE rv.user_id = ?
    GROUP BY p.id, p.name, p.imageproduct, p.unit, rv.viewed_at
    ORDER BY rv.viewed_at DESC
    LIMIT 5
");
if ($rvStmt) {
    $rvStmt->bind_param('i', $uid);
    $rvStmt->execute();
    $res = $rvStmt->get_result();
    while ($row = $res->fetch_assoc()) $recentProducts[] = $row;
    $rvStmt->close();
}

// ─── Sidebar navigation ────────────────────────────────────────
$accountNav = [
    ['label' => 'Account Overview', 'icon' => 'fa-user', 'href' => BASE_URL . '/profile', 'active' => true],
    ['label' => 'My Orders', 'icon' => 'fa-box-open', 'href' => BASE_URL . '/orders'],
    ['label' => 'Addresses', 'icon' => 'fa-location-dot', 'href' => BASE_URL . '/profilemap'],
    ['label' => 'Receipts', 'icon' => 'fa-receipt', 'href' => BASE_URL . '/recent'],
    ['label' => 'Recently Viewed', 'icon' => 'fa-clock-rotate-left', 'href' => BASE_URL . '/recent'],
    ['label' => 'Saved Items', 'icon' => 'fa-bookmark', 'href' => BASE_URL . '/saved'],
    ['label' => 'Notifications', 'icon' => 'fa-bell', 'href' => BASE_URL . '/system-notifications'],
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Account — NobleHome</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
</head>

<body class="bg-gray-50 min-h-screen flex flex-col">

    <?php include ROOT_PATH . '/user/navigation/top.php'; ?>

    <main class="max-w-screen-xl mx-auto px-3 md:px-4 py-4 md:py-6 pb-24 md:pb-10 flex-1 w-full">

        <!-- Breadcrumb -->
        <nav class="flex items-center gap-1.5 text-xs md:text-sm text-gray-400 mb-4" aria-label="Breadcrumb">
            <a href="<?= BASE_URL ?>" class="hover:text-gray-700 transition-colors"><i class="fa-solid fa-house text-[11px]"></i></a>
            <i class="fa-solid fa-chevron-right text-[9px]"></i>
            <span class="text-gray-700 font-medium">My Account</span>
        </nav>

        <h1 class="text-2xl font-bold text-gray-900 mb-5">My Account</h1>

        <div class="grid grid-cols-1 lg:grid-cols-[260px_1fr] gap-5 lg:gap-6 items-start">

            <!-- ================= SIDEBAR ================= -->
            <aside class="bg-white rounded-lg border border-gray-200 overflow-hidden lg:sticky lg:top-36">
                <div class="flex items-center gap-3 px-4 py-4 border-b border-gray-200">
                    <div class="w-10 h-10 rounded-full bg-gray-100 overflow-hidden shrink-0 flex items-center justify-center text-sm font-semibold text-gray-600">
                        <?php if ($userAvatar): ?>
                            <img src="<?= htmlspecialchars($userAvatar) ?>" alt="" referrerpolicy="no-referrer" class="w-full h-full object-cover"
                                onerror="this.replaceWith(document.createTextNode(<?= htmlspecialchars(json_encode($initial), ENT_QUOTES) ?>))">
                        <?php else: ?>
                            <?= htmlspecialchars($initial) ?>
                        <?php endif; ?>
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-gray-900 truncate"><?= htmlspecialchars($userName) ?></p>
                        <p class="text-xs text-gray-500 truncate"><?= htmlspecialchars($userEmail) ?></p>
                    </div>
                </div>

                <nav class="py-2">
                    <?php foreach ($accountNav as $item): $on = !empty($item['active']); ?>
                        <a href="<?= $item['href'] ?>"
                            class="flex items-center gap-3 px-4 py-2.5 text-sm border-l-2 transition-colors
                                   <?= $on ? 'border-gray-900 bg-gray-50 font-semibold text-gray-900' : 'border-transparent text-gray-600 hover:bg-gray-50 hover:text-gray-900' ?>">
                            <i class="fa-solid <?= $item['icon'] ?> w-4 text-center <?= $on ? 'text-gray-900' : 'text-gray-400' ?>"></i>
                            <?= $item['label'] ?>
                        </a>
                    <?php endforeach; ?>
                </nav>

                <div class="border-t border-gray-200 py-2">
                    <a href="<?= BASE_URL ?>/logout"
                        class="flex items-center gap-3 px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 transition-colors border-l-2 border-transparent">
                        <i class="fa-solid fa-arrow-right-from-bracket w-4 text-center"></i> Sign Out
                    </a>
                </div>
            </aside>

            <!-- ================= MAIN ================= -->
            <div class="space-y-5 min-w-0">

                <!-- Profile -->
                <section class="bg-white rounded-lg border border-gray-200 p-5 md:p-6">
                    <div class="flex flex-col sm:flex-row sm:items-center gap-4 sm:gap-5">
                        <div class="w-16 h-16 md:w-20 md:h-20 rounded-full bg-gray-100 overflow-hidden shrink-0 flex items-center justify-center text-2xl font-semibold text-gray-600 border border-gray-200">
                            <?php if ($userAvatar): ?>
                                <img src="<?= htmlspecialchars($userAvatar) ?>" alt="Profile photo" referrerpolicy="no-referrer" class="w-full h-full object-cover"
                                    onerror="this.replaceWith(document.createTextNode(<?= htmlspecialchars(json_encode($initial), ENT_QUOTES) ?>))">
                            <?php else: ?>
                                <?= htmlspecialchars($initial) ?>
                            <?php endif; ?>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm text-gray-500">Welcome back,</p>
                            <h2 class="text-xl md:text-2xl font-bold text-gray-900 truncate"><?= htmlspecialchars($userName) ?></h2>
                            <span class="inline-flex items-center gap-1.5 mt-2 text-xs text-gray-600 border border-gray-200 rounded-full px-2.5 py-1">
                                <i class="fa-brands fa-google text-[11px]"></i> Signed in with Google
                            </span>
                        </div>
                    </div>

                    <dl class="mt-6 grid grid-cols-1 sm:grid-cols-2 border border-gray-200 rounded-md divide-y sm:divide-y-0 sm:divide-x divide-gray-200 text-sm">
                        <div class="px-4 py-3">
                            <dt class="text-xs text-gray-500">Full name</dt>
                            <dd class="font-medium text-gray-900 mt-0.5 truncate"><?= htmlspecialchars($userName) ?></dd>
                        </div>
                        <div class="px-4 py-3">
                            <dt class="text-xs text-gray-500">Email address</dt>
                            <dd class="font-medium text-gray-900 mt-0.5 truncate"><?= htmlspecialchars($userEmail ?: '—') ?></dd>
                        </div>
                    </dl>
                </section>

                <!-- Quick stats -->
                <section class="grid grid-cols-1 sm:grid-cols-3 gap-3 md:gap-4">
                    <?php
                    $stats = [
                        ['Items in Cart', $cartCount, 'fa-cart-shopping', BASE_URL . '/cartview', 'View cart'],
                        ['Saved Items', $savedCount, 'fa-bookmark', BASE_URL . '/saved', 'View saved'],
                        ['Recently Viewed', $recentCount, 'fa-clock-rotate-left', BASE_URL . '/recent', 'View history'],
                    ];
                    foreach ($stats as [$label, $value, $icon, $href, $cta]): ?>
                        <a href="<?= $href ?>"
                            class="group bg-white rounded-lg border border-gray-200 p-4 md:p-5 flex items-center justify-between hover:border-gray-400 transition-colors">
                            <div>
                                <p class="text-xs font-medium text-gray-500"><?= $label ?></p>
                                <p class="text-2xl font-bold text-gray-900 mt-1"><?= number_format($value) ?></p>
                                <p class="text-xs text-gray-500 mt-1 group-hover:text-gray-900 transition-colors">
                                    <?= $cta ?> <i class="fa-solid fa-arrow-right text-[10px] ml-0.5"></i>
                                </p>
                            </div>
                            <span class="w-11 h-11 rounded-full bg-gray-100 flex items-center justify-center shrink-0">
                                <i class="fa-solid <?= $icon ?> text-gray-600"></i>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </section>

                <!-- Recently viewed -->
                <section class="bg-white rounded-lg border border-gray-200 p-5 md:p-6">
                    <div class="flex items-end justify-between gap-4 mb-4">
                        <div>
                            <h3 class="text-base font-bold text-gray-900">Recently Viewed</h3>
                            <p class="text-xs text-gray-500 mt-0.5">Products you looked at recently</p>
                        </div>
                        <?php if (!empty($recentProducts)): ?>
                            <a href="<?= BASE_URL ?>/recent" class="text-sm font-semibold text-gray-900 hover:text-orange-600 transition-colors shrink-0">
                                View all <i class="fa-solid fa-arrow-right text-xs ml-1"></i>
                            </a>
                        <?php endif; ?>
                    </div>

                    <?php if (empty($recentProducts)): ?>
                        <div class="text-center py-10 border border-dashed border-gray-200 rounded-md">
                            <i class="fa-regular fa-eye text-2xl text-gray-300"></i>
                            <p class="text-sm font-medium text-gray-700 mt-2">No recently viewed products</p>
                            <a href="<?= BASE_URL ?>/shop"
                                class="inline-flex items-center gap-2 mt-4 px-4 h-10 rounded-md bg-orange-600 hover:bg-orange-700 text-white text-sm font-semibold transition-colors">
                                Start shopping
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-x-3 gap-y-5">
                            <?php foreach ($recentProducts as $p):
                                $min = floatval($p['min_price'] ?? 0);
                                $max = floatval($p['max_price'] ?? 0); ?>
                                <a href="<?= BASE_URL ?>/mainproductview?id=<?= (int) $p['id'] ?>" class="group flex flex-col">
                                    <div class="aspect-square rounded-md overflow-hidden bg-gray-100 border border-gray-200">
                                        <?php if (!empty($p['imageproduct'])): ?>
                                            <img src="<?= $uploadUrl . htmlspecialchars($p['imageproduct']) ?>" alt="<?= htmlspecialchars($p['name']) ?>"
                                                loading="lazy" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                                        <?php else: ?>
                                            <div class="w-full h-full flex items-center justify-center"><i class="fa-regular fa-image text-2xl text-gray-300"></i></div>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-sm font-medium text-gray-900 leading-snug line-clamp-2 mt-2 group-hover:underline underline-offset-2">
                                        <?= htmlspecialchars($p['name']) ?>
                                    </p>
                                    <p class="text-sm mt-1">
                                        <?php if ($min > 0 || $max > 0): ?>
                                            <?php if ($min !== $max): ?><span class="text-xs text-gray-500">From </span><?php endif; ?>
                                            <span class="font-bold text-gray-900">₱<?= number_format($min, 2) ?></span>
                                        <?php else: ?>
                                            <span class="text-xs text-gray-500">Price not set</span>
                                        <?php endif; ?>
                                    </p>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

            </div>
        </div>
    </main>

    <?php include ROOT_PATH . '/user/navigation/bottom.php'; ?>
</body>

</html>