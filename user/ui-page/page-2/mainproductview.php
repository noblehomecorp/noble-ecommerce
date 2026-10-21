<?php
// mainproductview.php

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/user/ui-page/backend/backend-page-2/productqtydiscount-helper.php'; // getProductQtyLimit(), getProductQtyMin(), getProductQtyTiers()

$uploadUrl = BASE_URL . '/uploads/';

$productId = intval($_GET['id'] ?? 0);
if (!$productId) {
    header('Location: ' . BASE_URL . '/');
    exit;
}

$stmt = $conn->prepare("SELECT * FROM nobleproduct WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $productId);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    header('Location: ' . BASE_URL . '/');
    exit;
}

// Load colors
$colors = [];
$colorRes = $conn->prepare("SELECT * FROM nobleproductcolor WHERE product_id = ? ORDER BY id ASC");
$colorRes->bind_param("i", $productId);
$colorRes->execute();
$colorResult = $colorRes->get_result();
while ($c = $colorResult->fetch_assoc()) {
    $c['variants'] = [];
    $colors[$c['id']] = $c;
}
$colorRes->close();

// Load variants per color
if (!empty($colors)) {
    $colorIds = implode(',', array_keys($colors));
    $varRes = $conn->query("SELECT * FROM nobleproductvariant WHERE color_id IN ($colorIds) ORDER BY pricesize ASC");
    while ($v = $varRes->fetch_assoc()) {
        $colors[$v['color_id']]['variants'][] = $v;
    }
}
$colors = array_values($colors);

// ─── Detect "simple product" — single color named 'Default' with a single
// 'Default' variant. Set by specialist-insertproduct-handler.php when the
// specialist checks "Simple product — no colors or sizes". ────────────────
$isSimpleProduct = (
    count($colors) === 1
    && $colors[0]['colorname'] === 'Default'
    && count($colors[0]['variants']) === 1
    && $colors[0]['variants'][0]['sizename'] === 'Default'
);

// Collect unique size names across all colors (for display)
$allSizes = [];
foreach ($colors as $color) {
    foreach ($color['variants'] as $v) {
        if (!isset($allSizes[$v['sizename']])) {
            $allSizes[$v['sizename']] = $v['sizename'];
        }
    }
}
$allSizes = array_values($allSizes);

// Price range
$priceStmt = $conn->prepare("
    SELECT MIN(v.pricesize) as min_price, MAX(v.pricesize) as max_price
    FROM nobleproductvariant v
    JOIN nobleproductcolor c ON c.id = v.color_id
    WHERE c.product_id = ?
");
$priceStmt->bind_param("i", $productId);
$priceStmt->execute();
$priceRange = $priceStmt->get_result()->fetch_assoc();
$priceStmt->close();

// ─── Total sold for this product ────────────────────────────────────────
$soldStmt = $conn->prepare("
    SELECT COALESCE(SUM(v.sold), 0) as total_sold
    FROM nobleproductvariant v
    JOIN nobleproductcolor c ON c.id = v.color_id
    WHERE c.product_id = ?
");
$soldStmt->bind_param("i", $productId);
$soldStmt->execute();
$totalSold = intval($soldStmt->get_result()->fetch_assoc()['total_sold'] ?? 0);
$soldStmt->close();

// ─── Active promos (date-based discount timer, per-color/size or general) ──
$productPromos = [];
$promoStmt = $conn->prepare("
    SELECT color_id, sizename, discount_percent, end_date
    FROM nobleproductpromo
    WHERE product_id = ? AND NOW() BETWEEN start_date AND end_date
");
$promoStmt->bind_param("i", $productId);
$promoStmt->execute();
$promoResult = $promoStmt->get_result();
while ($row = $promoResult->fetch_assoc()) {
    $productPromos[] = [
        'color_id' => $row['color_id'] !== null ? intval($row['color_id']) : null, // null = all colors
        'sizename' => $row['sizename'], // null = all sizes
        'discount_percent' => floatval($row['discount_percent']),
        'end_date' => $row['end_date'],
    ];
}
$promoStmt->close();

// ─── Fetch reviews for this product ────────────────────────────────────────
$reviews = [];
$avgRating = 0;
$totalReviews = 0;

$revStmt = $conn->prepare("
    SELECT r.rating, r.comment, r.created_at, u.name, u.avatar
    FROM noblereview r
    INNER JOIN nobleuseraccount u ON u.id = r.user_id
    WHERE r.product_id = ?
    ORDER BY r.created_at DESC
");
$revStmt->bind_param("i", $productId);
$revStmt->execute();
$revResult = $revStmt->get_result();
while ($r = $revResult->fetch_assoc()) {
    $reviews[] = $r;
}
$revStmt->close();

$totalReviews = count($reviews);
if ($totalReviews > 0) {
    $sum = array_sum(array_column($reviews, 'rating'));
    $avgRating = round($sum / $totalReviews, 1);
}

// Rating breakdown (5★ to 1★ counts) — para sa bar distribution
$ratingBreakdown = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
foreach ($reviews as $r) {
    $rt = (int) $r['rating'];
    if (isset($ratingBreakdown[$rt])) {
        $ratingBreakdown[$rt]++;
    }
}

$isLoggedIn = !empty($_SESSION['user_id']);

// ─── I-record ang recent view ng user para sa product na ito ──────────────
if ($isLoggedIn) {
    $rvStmt = $conn->prepare("
        INSERT INTO noblerecentview (user_id, product_id, viewed_at)
        VALUES (?, ?, NOW())
        ON DUPLICATE KEY UPDATE viewed_at = NOW()
    ");
    $rvStmt->bind_param("ii", $_SESSION['user_id'], $productId);
    $rvStmt->execute();
    $rvStmt->close();
}

$min = floatval($priceRange['min_price'] ?? 0);
$max = floatval($priceRange['max_price'] ?? 0);

// ─── Qty limit + min + tiered discount (PER PRODUCT — same sa lahat ng color/size) ──
$productQtyLimit = getProductQtyLimit($conn, $productId);   // 0 = walang max limit
$productQtyMin = getProductQtyMin($conn, $productId);     // 1 = walang minimum (default)
$qtyTiers = getProductQtyTiers($conn, $productId);  // [{min_qty,max_qty,discount_percent}, ...]

// Ilan na ba ang nasa cart ng user para sa product na ito (across lahat ng color/size)?
$currentProductCartQty = 0;
if ($isLoggedIn) {
    $cartQtyStmt = $conn->prepare("SELECT COALESCE(SUM(quantity), 0) as total FROM noblecart WHERE user_id = ? AND product_id = ?");
    $cartQtyStmt->bind_param("ii", $_SESSION['user_id'], $productId);
    $cartQtyStmt->execute();
    $currentProductCartQty = intval($cartQtyStmt->get_result()->fetch_assoc()['total'] ?? 0);
    $cartQtyStmt->close();
}

// ─── Cart qty PER VARIANT ──
$variantCartQty = []; // variant_id => qty already in cart
if ($isLoggedIn) {
    $vcStmt = $conn->prepare("
        SELECT variant_id, SUM(quantity) as qty
        FROM noblecart
        WHERE user_id = ? AND product_id = ?
        GROUP BY variant_id
    ");
    $vcStmt->bind_param("ii", $_SESSION['user_id'], $productId);
    $vcStmt->execute();
    $vcRes = $vcStmt->get_result();
    while ($row = $vcRes->fetch_assoc()) {
        $variantCartQty[intval($row['variant_id'])] = intval($row['qty']);
    }
    $vcStmt->close();
}

if (!function_exists('formatSoldCount')) {
    function formatSoldCount($n)
    {
        $n = intval($n);
        if ($n >= 1000) {
            return rtrim(rtrim(number_format($n / 1000, 1), '0'), '.') . 'K';
        }
        return number_format($n);
    }
}

$savedIds = [];
if ($isLoggedIn) {
    $savedResult = $conn->query("SELECT product_id FROM noblesavedproduct WHERE user_id = " . intval($_SESSION['user_id']));
    if ($savedResult) {
        while ($row = $savedResult->fetch_assoc()) {
            $savedIds[] = (int) $row['product_id'];
        }
    }
}

// ─── Gallery thumbnails: main image + color images (UI lang) ──────────────
$thumbs = [];
if (!empty($product['imageproduct'])) {
    $thumbs[] = ['file' => $product['imageproduct'], 'colorIndex' => null, 'label' => $product['name']];
}
if (!$isSimpleProduct) {
    foreach ($colors as $i => $c) {
        if (!empty($c['imagecolor'])) {
            $thumbs[] = ['file' => $c['imagecolor'], 'colorIndex' => $i, 'label' => $c['colorname']];
        }
    }
}

$PRICE_CLS = 'text-3xl font-bold tracking-tight text-gray-900';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($product['name']) ?> — NobleHome</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/user/navigation/top.php'; ?>
    <style>
        /* Option buttons (color / size) */
        .color-btn,
        .size-btn {
            min-width: 3rem;
        }

        .color-btn.selected,
        .size-btn.selected {
            border-color: #111827;
            background-color: #ffffff;
            color: #111827;
            font-weight: 600;
            box-shadow: inset 0 0 0 1px #111827;
        }

        .size-btn.unavailable {
            opacity: 0.4;
            cursor: not-allowed;
            text-decoration: line-through;
            background: #f9fafb;
        }

        #toast {
            transition: opacity 0.3s ease, transform 0.3s ease;
        }

        .qty-btn:disabled {
            opacity: 0.35;
            cursor: not-allowed;
        }

        .nh-thumb[data-active="true"] {
            border-color: #111827;
            box-shadow: 0 0 0 1px #111827;
        }
    </style>
</head>

<body class="bg-gray-50">

    <div id="toast" class="fixed top-4 right-4 md:top-6 md:right-6 z-50 opacity-0 pointer-events-none translate-y-2
                flex items-center gap-2 md:gap-3 px-3 py-2 md:px-4 md:py-3 rounded-xl shadow-lg
                text-xs md:text-sm font-medium bg-white border border-gray-100 text-gray-800 min-w-40 md:min-w-56">
        <span id="toast-icon"></span>
        <span id="toast-msg"></span>
    </div>

    <!-- Zoom result box (nasa body level para tama ang position kahit sticky ang image column) -->
    <div id="zoom-result"
        class="hidden absolute z-30 border border-gray-200 rounded-xl shadow-xl bg-white overflow-hidden pointer-events-none"
        style="width:300px; height:300px; background-repeat:no-repeat;">
    </div>

    <div class="max-w-screen-xl mx-auto px-3 md:px-4 py-4 md:py-6 pb-24 md:pb-8">

        <!-- Breadcrumb -->
        <nav class="flex items-center gap-1.5 text-xs md:text-sm text-gray-400 mb-4 min-w-0" aria-label="Breadcrumb">
            <a href="<?= BASE_URL ?>" class="hover:text-orange-500 transition-colors shrink-0">
                <i class="fa-solid fa-house text-[11px]"></i>
            </a>
            <i class="fa-solid fa-chevron-right text-[9px] shrink-0"></i>
            <a href="<?= BASE_URL ?>/shop" class="hover:text-orange-500 transition-colors shrink-0">Shop</a>
            <?php if (!empty($product['category'])): ?>
                <i class="fa-solid fa-chevron-right text-[9px] shrink-0"></i>
                <span class="shrink-0"><?= htmlspecialchars(ucwords(strtolower($product['category']))) ?></span>
            <?php endif; ?>
            <i class="fa-solid fa-chevron-right text-[9px] shrink-0"></i>
            <span class="text-gray-700 font-medium truncate"><?= htmlspecialchars($product['name']) ?></span>
        </nav>

        <div class="bg-white rounded-lg border border-gray-200 p-4 md:p-8">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 lg:gap-12">

                <!-- ================= GALLERY ================= -->
                <div class="lg:sticky lg:top-36 self-start">
                    <div class="relative aspect-square bg-white border border-gray-200 rounded-lg flex items-center justify-center p-4 md:p-8 overflow-hidden">
                        <?php if (!empty($product['imageproduct'])): ?>
                            <div id="img-zoom-container" class="relative inline-block overflow-hidden cursor-crosshair select-none">
                                <img id="main-image" src="<?= $uploadUrl . htmlspecialchars($product['imageproduct']) ?>"
                                    alt="<?= htmlspecialchars($product['name']) ?>"
                                    class="block max-w-full max-h-[300px] md:max-h-[480px] object-contain transition-opacity duration-200"
                                    draggable="false">
                                <!-- Lens overlay -->
                                <div id="zoom-lens"
                                    class="hidden absolute border border-gray-400 bg-white/20 pointer-events-none"
                                    style="width:90px; height:90px; border-radius:50%; box-shadow:0 0 0 9999px rgba(0,0,0,0.06);">
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="text-gray-300 text-center">
                                <i class="fa-regular fa-image text-5xl md:text-6xl mb-2 block"></i>
                                <span class="text-xs md:text-sm">No image available</span>
                            </div>
                        <?php endif; ?>

                        <?php if ($productPromos): ?>
                            <span class="absolute top-3 left-3 bg-red-600 text-white text-[11px] font-semibold uppercase tracking-wide px-2 py-1 rounded-sm">
                                Sale
                            </span>
                        <?php endif; ?>
                    </div>

                    <?php if (count($thumbs) > 1): ?>
                        <div class="flex gap-2 mt-3 overflow-x-auto pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                            <?php foreach ($thumbs as $t): ?>
                                <button type="button"
                                    class="nh-thumb shrink-0 w-16 h-16 md:w-20 md:h-20 rounded-md border border-gray-200 bg-white p-1.5 overflow-hidden hover:border-gray-400 transition"
                                    data-src="<?= htmlspecialchars($t['file']) ?>"
                                    data-color-index="<?= $t['colorIndex'] === null ? '' : (int) $t['colorIndex'] ?>"
                                    title="<?= htmlspecialchars($t['label']) ?>">
                                    <img src="<?= $uploadUrl . htmlspecialchars($t['file']) ?>" alt="<?= htmlspecialchars($t['label']) ?>"
                                        class="w-full h-full object-contain" loading="lazy">
                                </button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- ================= DETAILS ================= -->
                <div class="flex flex-col min-w-0">

                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <?php if (!empty($product['category'])): ?>
                                <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">
                                    <?= htmlspecialchars($product['category']) ?>
                                </p>
                            <?php endif; ?>
                            <h1 class="text-2xl md:text-[28px] font-bold text-gray-900 leading-tight mt-1.5">
                                <?= htmlspecialchars($product['name']) ?>
                            </h1>
                        </div>
                        <div class="flex items-center gap-1 shrink-0">
                            <button type="button" id="save-btn"
                                class="w-9 h-9 rounded-md border border-gray-200 bg-white flex items-center justify-center hover:bg-gray-50 transition"
                                data-product-id="<?= $productId ?>" aria-label="Save to favorites" title="Save">
                                <i class="<?= in_array($productId, $savedIds ?? []) ? 'fa-solid text-red-500' : 'fa-regular text-gray-500' ?> fa-bookmark text-sm"></i>
                            </button>
                            <button type="button" id="share-btn"
                                class="w-9 h-9 rounded-md border border-gray-200 bg-white flex items-center justify-center hover:bg-gray-50 transition"
                                data-product-id="<?= $productId ?>"
                                data-product-name="<?= htmlspecialchars($product['name'], ENT_QUOTES) ?>"
                                aria-label="Share product" title="Share">
                                <i class="fa-regular fa-share-from-square text-gray-500 text-sm"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Rating / sold -->
                    <?php if ($totalReviews > 0 || $totalSold > 0): ?>
                        <div class="flex items-center flex-wrap gap-x-3 gap-y-1 mt-2.5 text-sm text-gray-600">
                            <?php if ($totalReviews > 0): ?>
                                <button type="button"
                                    onclick="document.getElementById('tab-reviews')?.click(); document.getElementById('panel-reviews')?.scrollIntoView({behavior:'smooth', block:'start'});"
                                    class="inline-flex items-center gap-1.5 hover:text-gray-900">
                                    <span class="flex items-center gap-0.5">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fa-star text-xs <?= $i <= round($avgRating) ? 'fa-solid text-amber-400' : 'fa-regular text-gray-300' ?>"></i>
                                        <?php endfor; ?>
                                    </span>
                                    <span class="font-semibold text-gray-900"><?= number_format($avgRating, 1) ?></span>
                                    <span class="underline underline-offset-2 decoration-gray-300"><?= $totalReviews ?> review<?= $totalReviews !== 1 ? 's' : '' ?></span>
                                </button>
                            <?php endif; ?>
                            <?php if ($totalReviews > 0 && $totalSold > 0): ?>
                                <span class="w-px h-3.5 bg-gray-300"></span>
                            <?php endif; ?>
                            <?php if ($totalSold > 0): ?>
                                <span><?= formatSoldCount($totalSold) ?> sold</span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Price -->
                    <div class="mt-5 pt-5 border-t border-gray-200">
                        <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1">
                            <div id="price-display" class="flex flex-wrap items-baseline gap-2">
                                <?php if ($min > 0 || $max > 0): ?>
                                    <span class="<?= $PRICE_CLS ?>">
                                        ₱<?= number_format($min, 2) ?><?= $min !== $max ? ' – ₱' . number_format($max, 2) : '' ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-sm text-gray-500">Price not set</span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($product['unit'])): ?>
                                <span class="text-sm text-gray-500">/ <?= htmlspecialchars($product['unit']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-2">
                            <span id="promo-timer" style="display:none;"
                                class="items-center gap-1.5 text-xs font-semibold text-red-600">
                                <i class="fa-regular fa-clock"></i> Sale ends in <span id="promo-timer-text">--:--:--</span>
                            </span>
                            <p id="stock-info" class="text-sm"></p>
                        </div>
                    </div>

                    <?php if (!empty($product['description'])): ?>
                        <p class="text-sm text-gray-600 leading-relaxed mt-5">
                            <?= nl2br(htmlspecialchars($product['description'])) ?>
                        </p>
                    <?php endif; ?>

                    <?php if (!empty($colors)): ?>
                        <div class="border-t border-gray-200 mt-5 pt-5 space-y-5">

                            <!-- Colors -->
                            <div id="color-section">
                                <p class="text-sm font-semibold text-gray-900 mb-2">
                                    Color<span class="text-gray-400 font-normal">:</span>
                                    <span id="selected-color-label" class="text-gray-600 font-normal"></span>
                                </p>
                                <div class="flex flex-wrap gap-2">
                                    <?php foreach ($colors as $i => $color): ?>
                                        <button type="button" onclick="selectColor(<?= $i ?>)" id="color-btn-<?= $i ?>"
                                            class="color-btn px-4 py-2 text-sm rounded-md border border-gray-300 bg-white text-gray-800 hover:border-gray-900 transition">
                                            <?= htmlspecialchars($color['colorname']) ?>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Sizes -->
                            <div id="size-section">
                                <p class="text-sm font-semibold text-gray-900 mb-2">
                                    Size<span class="text-gray-400 font-normal">:</span>
                                    <span id="selected-size-label" class="text-gray-600 font-normal"></span>
                                </p>
                                <div class="flex flex-wrap gap-2" id="size-buttons-wrapper">
                                    <?php foreach ($allSizes as $sizeName): ?>
                                        <button type="button" onclick="selectSize('<?= htmlspecialchars($sizeName) ?>')"
                                            id="size-btn-<?= htmlspecialchars($sizeName) ?>"
                                            data-size="<?= htmlspecialchars($sizeName) ?>"
                                            class="size-btn px-4 py-2 text-sm rounded-md border border-gray-300 bg-white text-gray-800 hover:border-gray-900 transition">
                                            <?= htmlspecialchars($sizeName) ?>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Quantity -->
                            <div id="qty-section">
                                <p class="text-sm font-semibold text-gray-900 mb-2">
                                    Quantity <span id="qty-limit-note" class="text-xs text-gray-500 font-normal"></span>
                                </p>
                                <div class="flex items-center gap-3">
                                    <div class="inline-flex items-center border border-gray-300 rounded-md overflow-hidden bg-white">
                                        <button type="button" onclick="changeQty(-1)" id="qty-minus"
                                            class="qty-btn w-10 h-10 flex items-center justify-center text-gray-700 hover:bg-gray-50 transition">
                                            <i class="fa-solid fa-minus text-xs"></i>
                                        </button>
                                        <span id="qty-display"
                                            class="w-12 h-10 flex items-center justify-center border-x border-gray-300 text-sm font-semibold text-gray-900 select-none">1</span>
                                        <button type="button" onclick="changeQty(1)" id="qty-plus"
                                            class="qty-btn w-10 h-10 flex items-center justify-center text-gray-700 hover:bg-gray-50 transition">
                                            <i class="fa-solid fa-plus text-xs"></i>
                                        </button>
                                    </div>
                                    <span id="qty-max-label" class="text-xs text-gray-500"></span>
                                </div>
                                <p id="qty-min-note" class="hidden text-xs text-gray-600 mt-2">
                                    <i class="fa-solid fa-circle-info mr-1 text-gray-400"></i>
                                    <span id="qty-min-note-text"></span>
                                </p>
                            </div>

                            <!-- Quantity Discount Hint -->
                            <div id="qty-discount-hint"
                                class="hidden text-xs bg-gray-50 border border-gray-200 rounded-md px-3 py-2 text-gray-700">
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Total -->
                    <div id="final-price-display"
                        class="hidden flex items-center justify-between border-t border-gray-200 mt-5 pt-4">
                    </div>

                    <!-- Add to Cart + Buy Now -->
                    <div class="mt-4">
                        <?php if ($isLoggedIn): ?>
                            <p id="select-hint" class="text-xs text-gray-500 mb-2.5">
                                <i class="fa-solid fa-circle-info mr-1 text-gray-400"></i> Select a color and size to continue.
                            </p>
                            <div class="flex flex-col sm:flex-row gap-2.5">
                                <button type="button" id="add-to-cart-btn" onclick="addToCart()" disabled
                                    class="flex-1 h-12 rounded-md text-sm font-semibold transition bg-gray-100 text-gray-400 cursor-not-allowed"
                                    data-product-id="<?= $productId ?>">
                                    <i class="fa-solid fa-cart-plus mr-2"></i> Add to Cart
                                </button>
                                <button type="button" id="checkout-now-btn" onclick="buyNow()" disabled
                                    style="background-color:#f3f4f6; border-color:#e5e7eb;"
                                    class="flex-1 h-12 rounded-md text-sm font-semibold transition text-gray-400 cursor-not-allowed border">
                                    Buy Now
                                </button>
                            </div>
                        <?php else: ?>
                            <button type="button"
                                class="google-login-trigger w-full h-12 rounded-md text-sm font-semibold text-center transition bg-orange-600 hover:bg-orange-700 text-white">
                                Sign in to Add to Cart
                            </button>
                        <?php endif; ?>
                    </div>

                    <!-- Assurance -->
                    <ul class="mt-6 border border-gray-200 rounded-md divide-y divide-gray-200 text-sm text-gray-700">
                        <li class="flex items-center gap-3 px-4 py-3">
                            <i class="fa-solid fa-lock w-4 text-center text-gray-500"></i>
                            Secure checkout powered by PayMongo
                        </li>
                        <li class="flex items-center gap-3 px-4 py-3">
                            <i class="fa-solid fa-qrcode w-4 text-center text-gray-500"></i>
                            QR Ph payments accepted
                        </li>
                        <li class="flex items-center gap-3 px-4 py-3">
                            <i class="fa-solid fa-circle-check w-4 text-center text-gray-500"></i>
                            Quality-inspected products
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        
<?php include ROOT_PATH . '/user/ui-page/page-2/producttabs.php'; ?>
        <?php include ROOT_PATH . '/user/ui-page/page-2/relatedproducts.php'; ?>

    </div>

    <script>
        // ─── Price formatting (UI) ─────────────────────────────────────
        const PRICE_CLS = <?= json_encode($PRICE_CLS) ?>;

        function fmtPeso(n) {
            return Number(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function buildPriceHtml(discounted, original, pct) {
            if (pct > 0) {
                return `<span class="text-2xl md:text-3xl font-extrabold text-red-600">₱${fmtPeso(discounted)}</span>`
                    + `<span class="text-sm md:text-base text-gray-400 line-through">₱${fmtPeso(original)}</span>`
                    + `<span class="text-[11px] md:text-xs font-bold text-white bg-red-500 px-1.5 py-0.5 rounded">-${pct}%</span>`;
            }
            return `<span class="${PRICE_CLS}">₱${fmtPeso(original)}</span>`;
        }

        // ─── Image Zoom Lens ───────────────────────────────────────────
        function initZoom(imgEl) {
            const container = document.getElementById('img-zoom-container');
            const lens = document.getElementById('zoom-lens');
            const result = document.getElementById('zoom-result');
            if (!container || !lens || !result || !imgEl) return;

            const ZOOM = 2.8;

            function getPos(e) {
                const r = imgEl.getBoundingClientRect();
                let x = (e.touches ? e.touches[0].clientX : e.clientX) - r.left;
                let y = (e.touches ? e.touches[0].clientY : e.clientY) - r.top;
                x = Math.max(lens.offsetWidth / 2, Math.min(x, r.width - lens.offsetWidth / 2));
                y = Math.max(lens.offsetHeight / 2, Math.min(y, r.height - lens.offsetHeight / 2));
                return { x, y, r };
            }

            function move(e) {
                e.preventDefault();
                const { x, y, r } = getPos(e);
                lens.style.left = (x - lens.offsetWidth / 2) + 'px';
                lens.style.top = (y - lens.offsetHeight / 2) + 'px';
                const cRect = container.getBoundingClientRect();
                result.style.top = cRect.top + window.scrollY + 'px';
                result.style.left = (cRect.right + window.scrollX + 12) + 'px';
                const bx = -(x * ZOOM - result.offsetWidth / 2);
                const by = -(y * ZOOM - result.offsetHeight / 2);
                result.style.backgroundImage = `url(${imgEl.src})`;
                result.style.backgroundSize = `${r.width * ZOOM}px ${r.height * ZOOM}px`;
                result.style.backgroundPosition = `${bx}px ${by}px`;
            }

            function show() {
                if (window.innerWidth < 1024) return; // desktop lang ang zoom box
                lens.classList.remove('hidden'); result.classList.remove('hidden');
            }
            function hide() { lens.classList.add('hidden'); result.classList.add('hidden'); }

            container.addEventListener('mouseenter', show);
            container.addEventListener('mouseleave', hide);
            container.addEventListener('mousemove', move);
            container.addEventListener('touchmove', move, { passive: false });
            container.addEventListener('touchend', hide);
        }

        // ─── Gallery thumbnails ────────────────────────────────────────
        function syncThumbs() {
            const img = document.getElementById('main-image');
            if (!img) return;
            document.querySelectorAll('.nh-thumb').forEach(t => {
                t.dataset.active = String(img.src.endsWith('/' + t.dataset.src) || img.src.endsWith(encodeURI(t.dataset.src)));
            });
        }

        function initThumbs() {
            const img = document.getElementById('main-image');
            if (!img) return;
            img.addEventListener('load', syncThumbs);
            syncThumbs();

            document.querySelectorAll('.nh-thumb').forEach(t => {
                t.addEventListener('click', () => {
                    const ci = t.dataset.colorIndex;
                    if (ci !== '') {
                        const idx = parseInt(ci, 10);
                        if (selectedColorIndex !== idx) selectColor(idx); // pipiliin ang kulay na iyon
                    } else {
                        img.style.opacity = '0.6';
                        img.src = uploadUrl + t.dataset.src;
                        img.onload = () => img.style.opacity = '1';
                        patchZoomOnImgChange();
                    }
                });
            });
        }

        window.addEventListener('DOMContentLoaded', () => {
            const img = document.getElementById('main-image');
            if (img) {
                if (img.complete) initZoom(img);
                else img.addEventListener('load', () => initZoom(img), { once: true });
            }
            initThumbs();

            if (isSimpleProduct) {
                document.getElementById('color-section')?.classList.add('hidden');
                document.getElementById('size-section')?.classList.add('hidden');
                if (colors.length) {
                    selectColor(0);
                    selectSize('Default');
                }
            } else {
                showDefaultPreview(); // price/stock preview lang — walang selected na color/size
            }
        });

        // ─── Display-only preview (WALANG select) ──────────────────────────────
        function showDefaultPreview() {
            if (!colors.length) return;

            let colorIndex = colors.findIndex(c => c.variants.some(v => getAvailableStock(v) > 0));
            if (colorIndex === -1) colorIndex = 0;

            const colorVariants = variantMap[colorIndex] || {};
            const sizeNames = Object.keys(colorVariants);
            if (!sizeNames.length) return;

            let sizeName = sizeNames.find(sn => getAvailableStock(colorVariants[sn]) > 0);
            if (!sizeName) sizeName = sizeNames[0];

            const variant = colorVariants[sizeName];
            if (!variant || !(variant.pricesize > 0)) return;

            const colorId = colors[colorIndex].id;
            const stock = getAvailableStock(variant);
            const originalPrice = parseFloat(variant.pricesize);
            const baseDiscount = getEffectiveDiscount(variant.discountvariant, colorId, sizeName);
            const tierDiscount = resolveTierDiscount(1);
            const effectiveDiscount = Math.max(baseDiscount, tierDiscount);
            const discounted = effectiveDiscount > 0 ? originalPrice * (1 - effectiveDiscount / 100) : originalPrice;

            const priceEl = document.getElementById('price-display');
            if (priceEl) {
                priceEl.innerHTML = buildPriceHtml(discounted, originalPrice, effectiveDiscount);
            }

            const stockEl = document.getElementById('stock-info');
            if (stockEl) {
                if (stock <= 0) {
                    stockEl.innerHTML = '<span class="text-red-500 font-medium"><i class="fa-solid fa-circle-exclamation mr-1"></i>Out of stock</span>';
                } else if (stock <= LOW_STOCK_THRESHOLD) {
                    stockEl.innerHTML = `<span class="text-amber-600 font-medium"><i class="fa-solid fa-triangle-exclamation mr-1"></i>Only ${stock} left in stock</span>`;
                } else {
                    stockEl.innerHTML = `<span class="text-green-600"><i class="fa-solid fa-circle-check mr-1"></i>In stock — ${stock} available</span>`;
                }
            }
        }

        function patchZoomOnImgChange() {
            const img = document.getElementById('main-image');
            if (!img) return;
            img.addEventListener('load', () => {
                const result = document.getElementById('zoom-result');
                if (result) result.style.backgroundImage = `url(${img.src})`;
            }, { once: true });
        }
        // ───────────────────────────────────────────────────────────────

        const colors = <?= json_encode(array_values($colors), JSON_HEX_TAG) ?>;
        const isSimpleProduct = <?= json_encode($isSimpleProduct) ?>;
        const uploadUrl = <?= json_encode($uploadUrl) ?>;
        const addCartUrl = <?= json_encode(BASE_URL . '/cartadd') ?>;
        const cartVariantQtyUrl = <?= json_encode(BASE_URL . '/cart-variant-qty') ?>;
        const productId = <?= json_encode($productId) ?>;
        const defaultImg = <?= json_encode($product['imageproduct'] ?? '') ?>;
        const defaultPriceHtml = <?= json_encode(
            ($min > 0 || $max > 0)
            ? '<span class="' . $PRICE_CLS . '">₱' . ($min === $max
                ? number_format($min, 2)
                : number_format($min, 2) . ' – ₱' . number_format($max, 2)) . '</span>'
            : '<span class="text-sm text-gray-400">Price not set</span>'
        ) ?>;

        // ─── Per-product qty limit + min + tiered discount data (from PHP) ───
        const productQtyLimit = <?= json_encode($productQtyLimit) ?>;   // 0 = no max limit
        const productQtyMin = <?= json_encode($productQtyMin) ?>;     // 1 = no minimum
        const qtyTiers = <?= json_encode($qtyTiers) ?>;                  // [{min_qty,max_qty,discount_percent}, ...]
        let productCartQty = <?= json_encode($currentProductCartQty) ?>; // mutable — updated after successful add
        // ───────────────────────────────────────────────────────────────────

        const variantCartQty = <?= json_encode($variantCartQty) ?>; // variant_id => qty already in cart

        function getAvailableStock(variant) {
            if (!variant) return 0;
            const raw = parseInt(variant.stock, 10) || 0;
            const inCart = variantCartQty[variant.id] || 0;
            return Math.max(0, raw - inCart);
        }

        let isRefreshingCartQty = false;

        async function refreshVariantCartQty() {
            if (isRefreshingCartQty) return;
            isRefreshingCartQty = true;
            try {
                const res = await fetch(`${cartVariantQtyUrl}?product_id=${productId}`, { credentials: 'same-origin' });
                const data = await res.json();
                if (!data.ok) return;

                Object.keys(variantCartQty).forEach(k => delete variantCartQty[k]);
                Object.entries(data.variant_qty || {}).forEach(([k, v]) => { variantCartQty[k] = v; });

                productCartQty = data.product_qty || 0;

                if (selectedColorIndex !== null && selectedSizeName !== null) {
                    resolveVariant();
                } else if (selectedColorIndex === null && !isSimpleProduct) {
                    showDefaultPreview();
                }
                updateCartBtn();
            } catch (e) {
                // Tahimik lang palyahin
            } finally {
                isRefreshingCartQty = false;
            }
        }

        window.addEventListener('noblecart:changed', refreshVariantCartQty);
        window.addEventListener('focus', refreshVariantCartQty);
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) refreshVariantCartQty();
        });

        let selectedColorIndex = null;
        let selectedColorId = null;
        let selectedSizeName = null;
        let selectedVariantId = null;
        let selectedVariantStock = 0;    // available = raw stock minus quantity already in user's cart
        let selectedVariantRawStock = 0; // totoong stock sa DB
        let selectedQty = 1;

        const LOW_STOCK_THRESHOLD = 5;

        const variantMap = {};
        colors.forEach((color, i) => {
            variantMap[i] = {};
            color.variants.forEach(v => { variantMap[i][v.sizename] = v; });
        });

        // ─── Active promos ──
        const productPromos = <?= json_encode($productPromos) ?>;

        function findApplicablePromo(colorId, sizeName) {
            let best = null;
            productPromos.forEach(promo => {
                const colorMatches = promo.color_id === null || promo.color_id === colorId;
                const sizeMatches = promo.sizename === null || promo.sizename === sizeName;
                if (colorMatches && sizeMatches && (!best || promo.discount_percent > best.discount_percent)) {
                    best = promo;
                }
            });
            return best;
        }

        function getEffectiveDiscount(variantDiscount, colorId, sizeName) {
            const promo = findApplicablePromo(colorId, sizeName);
            const promoDiscount = promo ? parseFloat(promo.discount_percent) : 0;
            return Math.max(parseFloat(variantDiscount) || 0, promoDiscount);
        }

        // ─── Qty Limit / Min / Tier Discount helpers ──────────────────────────
        function isLimitReached() {
            return productQtyLimit > 0 && productCartQty >= productQtyLimit;
        }

        function canMeetMinimum() {
            if (productQtyMin <= 1) return true;
            if (selectedVariantStock < productQtyMin) return false;
            if (productQtyLimit > 0) {
                const remainingByLimit = Math.max(0, productQtyLimit - productCartQty);
                if (remainingByLimit < productQtyMin) return false;
            }
            return true;
        }

        function getEffectiveMaxQty() {
            const stockMax = selectedVariantStock > 0 ? selectedVariantStock : 1;
            if (productQtyLimit <= 0) return stockMax;
            const remainingByLimit = Math.max(0, productQtyLimit - productCartQty);
            if (remainingByLimit <= 0) return 1;
            return Math.min(stockMax, remainingByLimit);
        }

        function resolveTierDiscount(qty) {
            for (const t of qtyTiers) {
                if (qty >= parseInt(t.min_qty, 10) && qty <= parseInt(t.max_qty, 10)) {
                    return parseFloat(t.discount_percent) || 0;
                }
            }
            return 0;
        }

        function refreshDiscountHint() {
            const hint = document.getElementById('qty-discount-hint');
            if (!hint) return;
            hint.classList.add('hidden'); // always hide, don't show it anymore
        }
        // ──────────────────────────────────────────────────────────────────

        let currentVariantOriginalPrice = 0;
        let currentVariantBaseDiscountPercent = 0;

        function renderPriceDisplay() {
            const priceDisplay = document.getElementById('price-display');
            if (!priceDisplay) return;

            if (!currentVariantOriginalPrice) {
                priceDisplay.innerHTML = defaultPriceHtml;
                return;
            }

            const tierDiscountPercent = resolveTierDiscount(selectedQty);
            const effectiveDiscount = Math.max(currentVariantBaseDiscountPercent, tierDiscountPercent);
            const original = currentVariantOriginalPrice;
            const discounted = effectiveDiscount > 0 ? original * (1 - effectiveDiscount / 100) : original;

            priceDisplay.innerHTML = buildPriceHtml(discounted, original, effectiveDiscount);
        }

        function updateFinalPrice() {
            const el = document.getElementById('final-price-display');
            if (!el) return;
            if (!currentVariantOriginalPrice || selectedVariantStock <= 0) { el.classList.add('hidden'); return; }

            renderPriceDisplay();

            const subtotal = currentVariantOriginalPrice * selectedQty;
            const tierDiscountPercent = resolveTierDiscount(selectedQty);
            const effectiveDiscount = Math.max(currentVariantBaseDiscountPercent, tierDiscountPercent);
            const discountAmount = subtotal * (effectiveDiscount / 100);
            const total = subtotal - discountAmount;

            el.classList.remove('hidden');
            el.innerHTML = `<span class="text-sm text-gray-700 font-semibold">Total <span class="text-gray-400 font-normal">(${selectedQty} pc${selectedQty > 1 ? 's' : ''})</span></span>`
                + `<span class="text-xl md:text-2xl font-extrabold text-gray-900">₱${fmtPeso(total)}</span>`;
        }

        // ─── Quantity ──────────────────────────────────────────────────
        function showQtySection() {
            selectedQty = productQtyMin > 1 ? Math.min(productQtyMin, getEffectiveMaxQtyRaw()) : 1;
            refreshQtyUI();
            refreshDiscountHint();
        }

        function getEffectiveMaxQtyRaw() {
            const stockMax = selectedVariantStock > 0 ? selectedVariantStock : 1;
            if (productQtyLimit <= 0) return stockMax;
            const remainingByLimit = Math.max(0, productQtyLimit - productCartQty);
            return Math.min(stockMax, Math.max(remainingByLimit, 1));
        }

        function hideQtySection() {
            selectedQty = 1;
            const disp = document.getElementById('qty-display');
            if (disp) disp.textContent = '1';
            const lbl = document.getElementById('qty-max-label');
            if (lbl) lbl.textContent = '';
            const minus = document.getElementById('qty-minus');
            const plus = document.getElementById('qty-plus');
            if (minus) minus.disabled = true;
            if (plus) plus.disabled = true;
            const fp = document.getElementById('final-price-display');
            if (fp) fp.classList.add('hidden');
            const hint = document.getElementById('qty-discount-hint');
            if (hint) hint.classList.add('hidden');
            const minNote = document.getElementById('qty-min-note');
            if (minNote) minNote.classList.add('hidden');
        }

        function changeQty(delta) {
            const max = getEffectiveMaxQty();
            const floor = productQtyMin > 1 ? productQtyMin : 1;
            selectedQty = Math.max(floor, Math.min(selectedQty + delta, max));
            refreshQtyUI();
            refreshDiscountHint();
            updateFinalPrice();
        }

        function refreshQtyUI() {
            const max = getEffectiveMaxQty();
            const floor = productQtyMin > 1 ? productQtyMin : 1;
            const disp = document.getElementById('qty-display');
            const minus = document.getElementById('qty-minus');
            const plus = document.getElementById('qty-plus');
            const lbl = document.getElementById('qty-max-label');
            const limitNote = document.getElementById('qty-limit-note');
            const minNote = document.getElementById('qty-min-note');
            const minNoteText = document.getElementById('qty-min-note-text');

            if (disp) disp.textContent = selectedQty;
            if (minus) minus.disabled = selectedQty <= floor;
            if (plus) plus.disabled = selectedQty >= max;
            if (lbl) lbl.textContent = max > floor ? `${max} available` : '';

            if (limitNote) {
                if (productQtyLimit > 0) {
                    limitNote.textContent = productCartQty > 0
                        ? `· max ${productQtyLimit} per order (${productCartQty} already in cart)`
                        : `· max ${productQtyLimit} per order`;
                } else {
                    limitNote.textContent = '';
                }
            }

            if (minNote && minNoteText) {
                if (productQtyMin > 1) {
                    minNoteText.textContent = `This product requires ${productQtyMin} pcs or more per add-to-cart.`;
                    minNote.classList.remove('hidden');
                } else {
                    minNote.classList.add('hidden');
                }
            }
        }
        // ──────────────────────────────────────────────────────────────

        function resetImage() {
            const mainImg = document.getElementById('main-image');
            if (mainImg && defaultImg) {
                mainImg.style.opacity = '0.6';
                mainImg.src = uploadUrl + defaultImg;
                mainImg.onload = () => mainImg.style.opacity = '1';
            }
        }

        function clearStockInfo() {
            selectedVariantStock = 0;
            selectedVariantRawStock = 0;
            const el = document.getElementById('stock-info');
            if (el) el.innerHTML = '';
        }

        function updateStockLabel() {
            const el = document.getElementById('stock-info');
            if (!el) return;

            if (!selectedVariantId) { el.innerHTML = ''; return; }

            const inCart = variantCartQty[selectedVariantId] || 0;

            if (selectedVariantRawStock <= 0) {
                el.innerHTML = '<span class="text-red-500 font-medium"><i class="fa-solid fa-circle-exclamation mr-1"></i>Out of stock</span>';
            } else if (selectedVariantStock <= 0) {
                el.innerHTML = `<span class="text-amber-600 font-medium"><i class="fa-solid fa-cart-shopping mr-1"></i>You already have ${inCart} pcs of this in your cart (max stock reached)</span>`;
            } else if (!canMeetMinimum()) {
                el.innerHTML = `<span class="text-amber-600 font-medium"><i class="fa-solid fa-triangle-exclamation mr-1"></i>Only ${selectedVariantStock} left — not enough for the min. order of ${productQtyMin} pcs</span>`;
            } else if (selectedVariantStock <= LOW_STOCK_THRESHOLD) {
                el.innerHTML = `<span class="text-amber-600 font-medium"><i class="fa-solid fa-triangle-exclamation mr-1"></i>Only ${selectedVariantStock} left in stock</span>` +
                    (inCart > 0 ? ` <span class="text-gray-400 font-normal">(${inCart} already in your cart)</span>` : '');
            } else {
                el.innerHTML = `<span class="text-green-600"><i class="fa-solid fa-circle-check mr-1"></i>In stock — ${selectedVariantStock} available</span>` +
                    (inCart > 0 ? ` <span class="text-gray-400 font-normal">(${inCart} already in your cart)</span>` : '');
            }
        }

        function selectColor(index) {
            if (selectedColorIndex === index && !isSimpleProduct) {
                // ── UNSELECT COLOR ────────
                document.getElementById('color-btn-' + index).classList.remove('selected');
                selectedColorIndex = null;
                selectedColorId = null;
                selectedSizeName = null;
                selectedVariantId = null;
                currentVariantOriginalPrice = 0;
                currentVariantBaseDiscountPercent = 0;
                clearStockInfo();
                hideQtySection();
                document.getElementById('selected-color-label').textContent = '';
                document.getElementById('selected-size-label').textContent = '';
                resetImage();

                document.querySelectorAll('.size-btn').forEach(btn => {
                    btn.style.display = '';
                    btn.classList.remove('selected', 'unavailable');
                    btn.disabled = false;
                });

                showDefaultPreview();
                updateCartBtn();
                return;
            }

            // ── SELECT COLOR ────────────────────────────────────────────────
            colors.forEach((_, i) => {
                document.getElementById('color-btn-' + i)?.classList.toggle('selected', i === index);
            });

            selectedColorIndex = index;
            selectedColorId = colors[index].id;
            selectedSizeName = null;
            selectedVariantId = null;
            currentVariantOriginalPrice = 0;
            currentVariantBaseDiscountPercent = 0;
            clearStockInfo();
            hideQtySection();
            updatePromoTimer(null);
            const colorLabel = document.getElementById('selected-color-label');
            if (colorLabel) colorLabel.textContent = colors[index].colorname;
            const sizeLabel = document.getElementById('selected-size-label');
            if (sizeLabel) sizeLabel.textContent = '';

            const color = colors[index];
            if (color.imagecolor) {
                const mainImg = document.getElementById('main-image');
                if (mainImg) {
                    mainImg.style.opacity = '0.6';
                    mainImg.src = uploadUrl + color.imagecolor;
                    mainImg.onload = () => mainImg.style.opacity = '1';
                }
            }

            const colorVariants = variantMap[index] || {};
            document.querySelectorAll('.size-btn').forEach(btn => {
                const sizeName = btn.dataset.size;
                const variant = colorVariants[sizeName];
                if (variant) {
                    btn.style.display = '';
                    btn.classList.remove('selected');
                    const rawStock = parseInt(variant.stock, 10) || 0;
                    const outOfStock = rawStock <= 0;
                    btn.classList.toggle('unavailable', outOfStock);
                    btn.disabled = outOfStock;
                } else {
                    btn.style.display = 'none';
                    btn.classList.remove('selected', 'unavailable');
                    btn.disabled = false;
                }
            });
            patchZoomOnImgChange();
            updateCartBtn();
        }

        function selectSize(sizeName) {
            const sizeBtn = document.getElementById('size-btn-' + sizeName);
            if (sizeBtn && sizeBtn.classList.contains('unavailable')) return;

            if (selectedSizeName === sizeName && !isSimpleProduct) {
                document.getElementById('size-btn-' + sizeName)?.classList.remove('selected');
                selectedSizeName = null;
                selectedVariantId = null;
                currentVariantOriginalPrice = 0;
                currentVariantBaseDiscountPercent = 0;
                clearStockInfo();
                hideQtySection();
                document.getElementById('selected-size-label').textContent = '';

                if (selectedColorIndex !== null) {
                    const prices = Object.values(variantMap[selectedColorIndex] || {})
                        .map(v => parseFloat(v.pricesize)).filter(p => p > 0);
                    if (prices.length) {
                        const cMin = Math.min(...prices);
                        const cMax = Math.max(...prices);
                        document.getElementById('price-display').innerHTML = cMin === cMax
                            ? `<span class="${PRICE_CLS}">₱${fmtPeso(cMin)}</span>`
                            : `<span class="${PRICE_CLS}">₱${fmtPeso(cMin)} – ₱${fmtPeso(cMax)}</span>`;
                    }
                    updatePromoTimer(null);
                } else {
                    showDefaultPreview();
                }
                patchZoomOnImgChange();
                updateCartBtn();
                return;
            }

            document.querySelectorAll('.size-btn').forEach(b => b.classList.remove('selected'));
            document.getElementById('size-btn-' + sizeName)?.classList.add('selected');

            selectedSizeName = sizeName;
            const sizeLabel = document.getElementById('selected-size-label');
            if (sizeLabel) sizeLabel.textContent = sizeName;

            if (selectedColorIndex !== null) resolveVariant();

            updateCartBtn();
        }

        function resolveVariant() {
            const variant = variantMap[selectedColorIndex]?.[selectedSizeName] ?? null;
            if (variant) {
                selectedVariantId = variant.id;
                selectedVariantRawStock = parseInt(variant.stock, 10) || 0;
                selectedVariantStock = getAvailableStock(variant);

                if (variant.pricesize > 0) {
                    currentVariantOriginalPrice = parseFloat(variant.pricesize);
                    currentVariantBaseDiscountPercent = getEffectiveDiscount(variant.discountvariant, selectedColorId, selectedSizeName);

                    renderPriceDisplay();
                    updateFinalPrice();
                    updatePromoTimer(selectedColorId, selectedSizeName);
                }

                updateStockLabel();
                if (selectedVariantStock > 0 && !isLimitReached() && canMeetMinimum()) {
                    showQtySection();
                } else {
                    hideQtySection();
                }
            } else {
                selectedVariantId = null;
                currentVariantOriginalPrice = 0;
                currentVariantBaseDiscountPercent = 0;
                clearStockInfo();
                hideQtySection();
                updatePromoTimer(null);
            }
        }

        function updateCartBtn() {
            const btn = document.getElementById('add-to-cart-btn');
            const checkoutBtn = document.getElementById('checkout-now-btn');
            if (!btn) return;

            const base = 'flex-1 h-12 rounded-md text-sm font-semibold transition';

            if (selectedColorId && selectedSizeName && selectedVariantId) {
                if (selectedVariantStock <= 0) {
                    btn.disabled = true;
                    if (selectedVariantRawStock <= 0) {
                        btn.className = `${base} bg-red-50 text-red-500 cursor-not-allowed`;
                        btn.innerHTML = '<i class="fa-solid fa-ban mr-2"></i> Out of stock';
                    } else {
                        const inCart = variantCartQty[selectedVariantId] || 0;
                        btn.className = `${base} bg-amber-50 text-amber-700 cursor-not-allowed`;
                        btn.innerHTML = `<i class="fa-solid fa-cart-shopping mr-2"></i> Already in your cart (${inCart} pcs — max stock)`;
                    }
                } else if (isLimitReached()) {
                    btn.disabled = true;
                    btn.className = `${base} bg-amber-50 text-amber-700 cursor-not-allowed`;
                    btn.innerHTML = `<i class="fa-solid fa-circle-exclamation mr-2"></i> Max ${productQtyLimit} per order reached`;
                } else if (!canMeetMinimum()) {
                    btn.disabled = true;
                    btn.className = `${base} bg-amber-50 text-amber-700 cursor-not-allowed`;
                    btn.innerHTML = `<i class="fa-solid fa-circle-exclamation mr-2"></i> Min. order is ${productQtyMin} pcs`;
                } else {
                    btn.disabled = false;
                    btn.className = `${base} bg-orange-600 hover:bg-orange-700 text-white cursor-pointer`;
                    btn.innerHTML = '<i class="fa-solid fa-cart-plus mr-2"></i> Add to Cart';
                }
            } else if (selectedColorId && selectedSizeName && !selectedVariantId) {
                btn.disabled = true;
                btn.className = `${base} bg-red-50 text-red-500 cursor-not-allowed`;
                btn.innerHTML = '<i class="fa-solid fa-circle-xmark mr-2"></i> Combination not available';
            } else if (!selectedColorId) {
                btn.disabled = true;
                btn.className = `${base} bg-gray-100 text-gray-400 cursor-not-allowed`;
                btn.innerHTML = '<i class="fa-solid fa-cart-plus mr-2"></i> Add to Cart';
            } else {
                btn.disabled = true;
                btn.className = `${base} bg-gray-100 text-gray-400 cursor-not-allowed`;
                btn.innerHTML = '<i class="fa-solid fa-cart-plus mr-2"></i> Select a size';
            }

            // Hint sa itaas ng buttons — mawawala kapag may color + size na
            const hint = document.getElementById('select-hint');
            if (hint) hint.classList.toggle('hidden', !!(selectedColorId && selectedSizeName));

            if (checkoutBtn) {
                if (!btn.disabled) {
                    checkoutBtn.disabled = false;
                    checkoutBtn.className = `${base} text-white cursor-pointer border`;
                    checkoutBtn.style.opacity = '1';
                    checkoutBtn.style.backgroundColor = '#111827';
                    checkoutBtn.style.borderColor = '#111827';
                    checkoutBtn.onmouseenter = () => { checkoutBtn.style.backgroundColor = '#000000'; checkoutBtn.style.borderColor = '#000000'; };
                    checkoutBtn.onmouseleave = () => { checkoutBtn.style.backgroundColor = '#111827'; checkoutBtn.style.borderColor = '#111827'; };
                    checkoutBtn.innerHTML = 'Buy Now';
                } else {
                    checkoutBtn.disabled = true;
                    checkoutBtn.className = `${base} text-gray-400 cursor-not-allowed border`;
                    checkoutBtn.style.opacity = '1';
                    checkoutBtn.style.backgroundColor = '#f3f4f6';
                    checkoutBtn.style.borderColor = '#e5e7eb';
                    checkoutBtn.onmouseenter = null;
                    checkoutBtn.onmouseleave = null;
                    checkoutBtn.innerHTML = 'Buy Now';
                }
            }
        }

        async function addToCart() {
            const btn = document.getElementById('add-to-cart-btn');

            if (selectedVariantStock <= 0) {
                if (selectedVariantRawStock <= 0) {
                    showToast('error', 'This item is out of stock.');
                } else {
                    showToast('warning', `You already have all available stock (${variantCartQty[selectedVariantId] || 0} pcs) of this in your cart.`);
                }
                updateCartBtn();
                return;
            }

            if (isLimitReached()) {
                showToast('warning', `Only ${productQtyLimit} pcs can be purchased per order for this product.`);
                updateCartBtn();
                return;
            }

            const qty = selectedQty || 1;

            if (productQtyMin > 1 && qty < productQtyMin) {
                showToast('warning', `You need ${productQtyMin} pcs or more per add-to-cart for this product.`);
                updateCartBtn();
                return;
            }

            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i> Adding…';

            try {
                const fd = new FormData();
                fd.append('product_id', btn.dataset.productId);
                fd.append('color_id', selectedColorId);
                fd.append('variant_id', selectedVariantId);
                fd.append('qty', qty);

                const res = await fetch(addCartUrl, { method: 'POST', body: fd });
                const data = await res.json();

                if (data.ok) {
                    showToast(data.limit_reached ? 'warning' : 'success', data.msg || 'Added to cart!');
                    window.dispatchEvent(new CustomEvent('noblecart:changed'));

                    if (data.product_qty_in_cart !== undefined) {
                        productCartQty = data.product_qty_in_cart;
                    }

                    if (data.remaining_stock !== undefined) {
                        const variant = variantMap[selectedColorIndex]?.[selectedSizeName];
                        if (variant) {
                            const rawStock = parseInt(variant.stock, 10) || 0;
                            variantCartQty[selectedVariantId] = Math.max(0, rawStock - data.remaining_stock);
                        }

                        selectedVariantStock = data.remaining_stock;
                        updateStockLabel();

                        if (data.remaining_stock <= 0) {
                            hideQtySection();
                        } else if (isLimitReached() || !canMeetMinimum()) {
                            hideQtySection();
                        } else {
                            selectedQty = productQtyMin > 1 ? Math.min(productQtyMin, getEffectiveMaxQtyRaw()) : 1;
                            refreshQtyUI();
                            refreshDiscountHint();
                        }
                    }
                } else {
                    showToast('error', data.msg || 'Failed to add to cart.');
                    if (data.out_of_stock) {
                        selectedVariantStock = 0;
                        updateStockLabel();
                        hideQtySection();
                        const sizeBtn = document.getElementById('size-btn-' + selectedSizeName);
                        if (sizeBtn) { sizeBtn.classList.add('unavailable'); sizeBtn.disabled = true; }
                    } else if (data.limit_reached) {
                        if (data.product_qty_in_cart !== undefined) productCartQty = data.product_qty_in_cart;
                        hideQtySection();
                    }
                }
            } catch (e) {
                showToast('error', 'Something went wrong.');
            }

            updateCartBtn();
        }

        async function buyNow() {
            const btn = document.getElementById('checkout-now-btn');
            if (!btn || btn.disabled) return;

            if (selectedVariantStock <= 0) {
                showToast(selectedVariantRawStock <= 0 ? 'error' : 'warning',
                    selectedVariantRawStock <= 0 ? 'This item is out of stock.' : 'You already have all available stock of this in your cart.');
                return;
            }
            if (isLimitReached()) {
                showToast('warning', `Only ${productQtyLimit} pcs can be purchased per order for this product.`);
                return;
            }

            const qty = selectedQty || 1;
            if (productQtyMin > 1 && qty < productQtyMin) {
                showToast('warning', `You need ${productQtyMin} pcs or more per add-to-cart for this product.`);
                return;
            }

            const addBtn = document.getElementById('add-to-cart-btn');
            btn.disabled = true;
            addBtn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i> Processing…';

            try {
                const fd = new FormData();
                fd.append('product_id', productId);
                fd.append('color_id', selectedColorId);
                fd.append('variant_id', selectedVariantId);
                fd.append('qty', qty);

                const res = await fetch(addCartUrl, { method: 'POST', body: fd });
                const data = await res.json();

                if (data.ok) {
                    window.dispatchEvent(new CustomEvent('noblecart:changed'));
                    window.location.href = <?= json_encode(BASE_URL . '/checkout') ?>;
                } else {
                    showToast('error', data.msg || 'Failed to proceed to checkout.');
                    updateCartBtn();
                }
            } catch (e) {
                showToast('error', 'Something went wrong.');
                updateCartBtn();
            }
        }

        function showToast(type, msg) {
            const toast = document.getElementById('toast');
            const icon = document.getElementById('toast-icon');
            const text = document.getElementById('toast-msg');

            icon.innerHTML = type === 'success'
                ? '<i class="fa-solid fa-circle-check text-green-500"></i>'
                : type === 'warning'
                    ? '<i class="fa-solid fa-triangle-exclamation text-amber-500"></i>'
                    : '<i class="fa-solid fa-circle-exclamation text-red-500"></i>';
            text.textContent = msg;

            toast.classList.remove('opacity-0', 'pointer-events-none', 'translate-y-2');
            toast.classList.add('opacity-100', 'translate-y-0');

            setTimeout(() => {
                toast.classList.add('opacity-0', 'pointer-events-none', 'translate-y-2');
                toast.classList.remove('opacity-100', 'translate-y-0');
            }, 3000);
        }

        function goBackSafe() {
            if (window.history.length > 1) {
                window.history.back();
            } else {
                window.location.href = <?= json_encode(BASE_URL . '/') ?>;
            }
        }

        document.getElementById('save-btn')?.addEventListener('click', function () {
            const icon = this.querySelector('i');
            fetch('<?= BASE_URL ?>/savedproduct', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'product_id=' + encodeURIComponent(productId)
            })
                .then(res => res.json())
                .then(data => {
                    if (!data.success) { showToast('error', data.message || 'Something went wrong.'); return; }
                    if (data.saved) {
                        icon.classList.remove('fa-regular', 'text-gray-500');
                        icon.classList.add('fa-solid', 'text-red-500');
                        showToast('success', 'Saved to favorites!');
                    } else {
                        icon.classList.remove('fa-solid', 'text-red-500');
                        icon.classList.add('fa-regular', 'text-gray-500');
                        showToast('success', 'Removed from favorites.');
                    }
                })
                .catch(() => showToast('error', 'Something went wrong.'));
        });

        document.getElementById('share-btn')?.addEventListener('click', function () {
            const productName = this.dataset.productName;
            const shareUrl = window.location.href;

            if (navigator.share) {
                navigator.share({ title: productName, url: shareUrl }).catch(() => { });
            } else {
                navigator.clipboard.writeText(shareUrl)
                    .then(() => showToast('success', 'Link copied to clipboard!'))
                    .catch(() => showToast('error', 'Could not copy link.'));
            }
        });

        let promoTimerInterval = null;

        function updatePromoTimer(colorId, sizeName) {
            const el = document.getElementById('promo-timer');
            const textEl = document.getElementById('promo-timer-text');
            if (!el || !textEl) return;

            if (promoTimerInterval) { clearInterval(promoTimerInterval); promoTimerInterval = null; }

            const promo = (colorId && sizeName) ? findApplicablePromo(colorId, sizeName) : null;
            if (!promo) {
                el.style.display = 'none';
                return;
            }

            const end = new Date(promo.end_date.replace(' ', 'T')).getTime();
            el.style.display = 'inline-flex';
            el.classList.remove('opacity-50');

            function tick() {
                const diff = end - Date.now();
                if (diff <= 0) {
                    el.classList.add('opacity-50');
                    textEl.textContent = 'Promo ended';
                    clearInterval(promoTimerInterval);
                    return;
                }
                const d = Math.floor(diff / 86400000);
                const h = Math.floor((diff % 86400000) / 3600000);
                const m = Math.floor((diff % 3600000) / 60000);
                const s = Math.floor((diff % 60000) / 1000);
                textEl.textContent = d > 0
                    ? `${d}d ${h}h`
                    : `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
            }
            tick();
            promoTimerInterval = setInterval(tick, 1000);
        }
    </script>

    <?php include ROOT_PATH . '/user/navigation/bottom.php'; ?>

</body>

</html>