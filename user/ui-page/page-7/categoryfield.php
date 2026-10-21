<?php
// user/ui-page/page-7/categoryfield.php
include ROOT_PATH . '/network/connect.php';

$uploadUrl = BASE_URL . '/uploads/';

// ── 1. Get category ID from URL ─────────────────────────────────────────────
$categoryId = intval($_GET['id'] ?? 0);
$activeSubId = intval($_GET['sub'] ?? 0); // optional subcategory filter

if (!$categoryId) {
    header('Location: ' . BASE_URL);
    exit;
}

// ── 2. Fetch the category itself ────────────────────────────────────────────
$stmt = $conn->prepare("SELECT id, name, image FROM noblecategory WHERE id = ?");
$stmt->bind_param("i", $categoryId);
$stmt->execute();
$category = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$category) {
    header('Location: ' . BASE_URL);
    exit;
}

// ── 3. Fetch subcategories (with product counts) ────────────────────────────
$subcategories = [];
$stmt = $conn->prepare("
    SELECT s.id, s.name, s.image, COUNT(DISTINCT ps.product_id) AS product_count
    FROM noblesubcategory s
    LEFT JOIN nobleproduct_subcategory ps ON ps.subcategory_id = s.id
    WHERE s.category_id = ?
    GROUP BY s.id, s.name, s.image
    ORDER BY s.name ASC
");
$stmt->bind_param("i", $categoryId);
$stmt->execute();
$subResult = $stmt->get_result();
while ($row = $subResult->fetch_assoc())
    $subcategories[] = $row;
$stmt->close();

$activeSubName = '';
foreach ($subcategories as $sub) {
    if ((int) $sub['id'] === $activeSubId) $activeSubName = $sub['name'];
}

// ── 4. Filter & sort inputs ──────────────────────────────────────────────────
$sortOptions = [
    'newest' => 'p.created_at DESC',
    'price_asc' => 'min_price ASC',
    'price_desc' => 'max_price DESC',
    'name_asc' => 'p.name ASC',
];
$sort = $_GET['sort'] ?? 'newest';
if (!array_key_exists($sort, $sortOptions))
    $sort = 'newest';

$minPrice = (isset($_GET['minp']) && $_GET['minp'] !== '') ? floatval($_GET['minp']) : null;
$maxPrice = (isset($_GET['maxp']) && $_GET['maxp'] !== '') ? floatval($_GET['maxp']) : null;
$saleOnly = isset($_GET['sale']) && $_GET['sale'] === '1';

$hasActiveFilters = $minPrice !== null || $maxPrice !== null || $sort !== 'newest' || $saleOnly;
$activeFilterCount = ($minPrice !== null || $maxPrice !== null ? 1 : 0) + ($saleOnly ? 1 : 0) + ($activeSubId ? 1 : 0);

// ── 4b. Pagination inputs ────────────────────────────────────────────────────
$perPage = 12;
$currentPage = intval($_GET['page'] ?? 1);
if ($currentPage < 1)
    $currentPage = 1;
$offset = ($currentPage - 1) * $perPage;

// ── 5. Build the shared filtered/grouped query (reused for count + page) ────
$baseSql = "SELECT
            p.id, p.name, p.imageproduct, p.description, p.unit, p.created_at,
            MIN(v.pricesize) AS min_price,
            MAX(v.pricesize) AS max_price,
            MAX(v.discountvariant) AS max_discount,
            MIN(v.pricesize - (v.pricesize * v.discountvariant / 100)) AS min_discounted_price,
            MAX(v.pricesize - (v.pricesize * v.discountvariant / 100)) AS max_discounted_price,
            (SELECT AVG(r.rating) FROM noblereview r WHERE r.product_id = p.id) AS avg_rating,
            (SELECT COUNT(*) FROM noblereview r WHERE r.product_id = p.id) AS review_count,
            (SELECT COALESCE(SUM(v2.sold), 0) FROM nobleproductvariant v2
                JOIN nobleproductcolor c2 ON c2.id = v2.color_id WHERE c2.product_id = p.id) AS total_sold
        FROM nobleproduct p
        INNER JOIN nobleproduct_subcategory ps ON ps.product_id = p.id";

if (!$activeSubId) {
    $baseSql .= " INNER JOIN noblesubcategory s ON s.id = ps.subcategory_id";
}

$baseSql .= " LEFT JOIN nobleproductcolor c ON c.product_id = p.id
          LEFT JOIN nobleproductvariant v ON v.color_id = c.id
          WHERE " . ($activeSubId ? "ps.subcategory_id = ?" : "s.category_id = ?");

$types = "i";
$params = [$activeSubId ?: $categoryId];

$baseSql .= " GROUP BY p.id";

$having = [];
if ($minPrice !== null) {
    $having[] = "MAX(v.pricesize) >= ?";
    $types .= "d";
    $params[] = $minPrice;
}
if ($maxPrice !== null) {
    $having[] = "MIN(v.pricesize) <= ?";
    $types .= "d";
    $params[] = $maxPrice;
}
if ($saleOnly) {
    $having[] = "MAX(v.discountvariant) > 0";
}
if ($having)
    $baseSql .= " HAVING " . implode(" AND ", $having);

// ── 5a. Total count for pagination ──────────────────────────────────────────
$totalProducts = 0;
$countSql = "SELECT COUNT(*) AS total FROM ($baseSql) AS filtered";
$stmt = $conn->prepare($countSql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$countRow = $stmt->get_result()->fetch_assoc();
$totalProducts = intval($countRow['total'] ?? 0);
$stmt->close();

$totalPages = max(1, (int) ceil($totalProducts / $perPage));
if ($currentPage > $totalPages)
    $currentPage = $totalPages;
$offset = ($currentPage - 1) * $perPage;

// ── 5b. Fetch products for the current page ──────────────────────────────────
$products = [];
$sql = $baseSql . " ORDER BY " . $sortOptions[$sort] . " LIMIT ? OFFSET ?";
$pageTypes = $types . "ii";
$pageParams = array_merge($params, [$perPage, $offset]);

$stmt = $conn->prepare($sql);
$stmt->bind_param($pageTypes, ...$pageParams);
$stmt->execute();
$prodResult = $stmt->get_result();
while ($row = $prodResult->fetch_assoc())
    $products[] = $row;
$stmt->close();

$showingFrom = $totalProducts ? $offset + 1 : 0;
$showingTo = min($offset + $perPage, $totalProducts);

// ── Helper: rebuild query string while overriding specific params ──────────
if (!function_exists('buildUrl')) {
    function buildUrl(array $overrides = [], bool $resetPage = true): string
    {
        $base = [
            'id' => $_GET['id'] ?? '',
            'sub' => $_GET['sub'] ?? '',
            'sort' => $_GET['sort'] ?? '',
            'minp' => $_GET['minp'] ?? '',
            'maxp' => $_GET['maxp'] ?? '',
            'sale' => $_GET['sale'] ?? '',
            'page' => $_GET['page'] ?? '',
        ];
        $merged = array_merge($base, $overrides);
        if ($resetPage && !array_key_exists('page', $overrides)) {
            $merged['page'] = '';
        }
        $merged = array_filter($merged, fn($v) => $v !== '' && $v !== null);
        return BASE_URL . '/productcategory?' . http_build_query($merged);
    }
}

if (!function_exists('nhNiceName')) {
    function nhNiceName(string $name): string
    {
        $name = mb_strtolower(trim($name));
        $name = preg_replace('/(?<=[a-z])(fixtures?|materials?|supplies|accessories)$/u', ' $1', $name);
        return ucwords($name);
    }
}

$sortLabels = [
    'newest' => 'Newest',
    'price_asc' => 'Price: Low to High',
    'price_desc' => 'Price: High to Low',
    'name_asc' => 'Name: A–Z',
];

$categoryName = nhNiceName($category['name']);
$pageTitle = $activeSubName ? nhNiceName($activeSubName) : $categoryName;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> — NobleHome</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <style>
        .nh-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
    </style>
</head>

<body class="bg-gray-50 min-h-screen flex flex-col">

    <?php include ROOT_PATH . '/user/navigation/top.php'; ?>

    <main class="max-w-screen-xl mx-auto px-3 md:px-4 py-4 md:py-6 pb-24 md:pb-10 flex-1 w-full">

        <!-- ================= PAGE HEADER ================= -->
        <header class="mb-6 pb-5 border-b border-gray-200">
            <nav class="flex items-center gap-1.5 text-xs md:text-sm text-gray-500 overflow-x-auto whitespace-nowrap" aria-label="Breadcrumb">
                <a href="<?= BASE_URL ?>" class="hover:text-gray-900 transition-colors">Home</a>
                <span class="text-gray-300">/</span>
                <a href="<?= BASE_URL ?>/shop" class="hover:text-gray-900 transition-colors">Shop</a>
                <span class="text-gray-300">/</span>
                <?php if ($activeSubId): ?>
                    <a href="<?= buildUrl(['sub' => '']) ?>" class="hover:text-gray-900 transition-colors"><?= htmlspecialchars($categoryName) ?></a>
                    <span class="text-gray-300">/</span>
                    <span class="text-gray-900"><?= htmlspecialchars(nhNiceName($activeSubName)) ?></span>
                <?php else: ?>
                    <span class="text-gray-900"><?= htmlspecialchars($categoryName) ?></span>
                <?php endif; ?>
            </nav>

            <div class="flex items-end justify-between gap-4 mt-3">
                <div>
                    <h1 class="text-2xl md:text-[32px] font-bold tracking-tight text-gray-900 leading-tight"><?= htmlspecialchars($pageTitle) ?></h1>
                    <p class="text-sm text-gray-500 mt-1">
                        <?= number_format($totalProducts) ?> <?= $totalProducts === 1 ? 'product' : 'products' ?>
                        <?php if ($activeSubId): ?> in <?= htmlspecialchars($categoryName) ?><?php endif; ?>
                    </p>
                </div>
            </div>
        </header>

        <!-- Mobile: subcategory pills (sa desktop, nasa sidebar) -->
        <?php if (!empty($subcategories)): ?>
            <div class="lg:hidden flex gap-2 overflow-x-auto pb-3 mb-3 -mx-3 px-3 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                <a href="<?= buildUrl(['sub' => '']) ?>"
                    class="shrink-0 h-9 px-4 inline-flex items-center rounded-full border text-sm font-medium
                           <?= !$activeSubId ? 'bg-gray-900 border-gray-900 text-white' : 'bg-white border-gray-300 text-gray-700' ?>">
                    All
                </a>
                <?php foreach ($subcategories as $sub): $on = $activeSubId === (int) $sub['id']; ?>
                    <a href="<?= buildUrl(['sub' => $sub['id']]) ?>"
                        class="shrink-0 h-9 px-4 inline-flex items-center rounded-full border text-sm font-medium whitespace-nowrap
                               <?= $on ? 'bg-gray-900 border-gray-900 text-white' : 'bg-white border-gray-300 text-gray-700' ?>">
                        <?= htmlspecialchars(nhNiceName($sub['name'])) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-[250px_1fr] gap-6 items-start">

            <!-- ================= FILTER SIDEBAR (desktop) / BOTTOM SHEET (mobile) ================= -->
            <div id="filterBackdrop" class="hidden fixed inset-0 bg-gray-900/40 z-[55] lg:hidden"></div>

            <aside id="filterPanel"
                class="fixed inset-x-0 bottom-0 z-[60] max-h-[85vh] overflow-y-auto bg-white rounded-t-xl
                       translate-y-full invisible transition-transform duration-300
                       lg:z-auto lg:max-h-none lg:overflow-visible lg:translate-y-0 lg:visible
                       lg:rounded-lg lg:border lg:border-gray-200 lg:sticky lg:top-36"
                aria-label="Filters">

                <!-- Mobile sheet header -->
                <div class="lg:hidden sticky top-0 bg-white flex items-center justify-between px-4 py-3 border-b border-gray-200">
                    <p class="text-base font-semibold text-gray-900">Filters</p>
                    <button type="button" id="filterCloseBtn" aria-label="Close filters"
                        class="w-9 h-9 flex items-center justify-center rounded-md hover:bg-gray-100">
                        <i class="fa-solid fa-xmark text-gray-600"></i>
                    </button>
                </div>

                <!-- Category / subcategories -->
                <?php if (!empty($subcategories)): ?>
                    <div class="px-4 py-4 border-b border-gray-200">
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-2">Category</p>
                        <ul class="space-y-0.5 text-sm">
                            <li>
                                <a href="<?= buildUrl(['sub' => '']) ?>"
                                    class="flex items-center justify-between px-2 py-1.5 rounded-md
                                           <?= !$activeSubId ? 'bg-gray-100 font-semibold text-gray-900' : 'text-gray-700 hover:bg-gray-50' ?>">
                                    All <?= htmlspecialchars($categoryName) ?>
                                </a>
                            </li>
                            <?php foreach ($subcategories as $sub): $on = $activeSubId === (int) $sub['id']; ?>
                                <li>
                                    <a href="<?= buildUrl(['sub' => $sub['id']]) ?>"
                                        class="flex items-center justify-between gap-2 px-2 py-1.5 rounded-md
                                               <?= $on ? 'bg-gray-100 font-semibold text-gray-900' : 'text-gray-700 hover:bg-gray-50' ?>">
                                        <span class="truncate"><?= htmlspecialchars(nhNiceName($sub['name'])) ?></span>
                                        <span class="text-xs text-gray-400 shrink-0"><?= (int) $sub['product_count'] ?></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <!-- Price -->
                <div class="px-4 py-4 border-b border-gray-200">
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-3">Price</p>
                    <form method="GET" action="<?= BASE_URL ?>/productcategory" class="space-y-3">
                        <input type="hidden" name="id" value="<?= $categoryId ?>">
                        <?php if ($activeSubId): ?><input type="hidden" name="sub" value="<?= $activeSubId ?>"><?php endif; ?>
                        <?php if ($sort !== 'newest'): ?><input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>"><?php endif; ?>
                        <?php if ($saleOnly): ?><input type="hidden" name="sale" value="1"><?php endif; ?>

                        <div class="flex items-center gap-2">
                            <div class="relative flex-1">
                                <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-xs text-gray-400">₱</span>
                                <input type="number" name="minp" min="0" placeholder="Min" aria-label="Minimum price"
                                    value="<?= $minPrice !== null ? htmlspecialchars($minPrice) : '' ?>"
                                    class="w-full h-10 pl-6 pr-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:border-gray-900">
                            </div>
                            <span class="text-gray-400 text-sm">–</span>
                            <div class="relative flex-1">
                                <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-xs text-gray-400">₱</span>
                                <input type="number" name="maxp" min="0" placeholder="Max" aria-label="Maximum price"
                                    value="<?= $maxPrice !== null ? htmlspecialchars($maxPrice) : '' ?>"
                                    class="w-full h-10 pl-6 pr-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:border-gray-900">
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="submit"
                                class="flex-1 h-10 rounded-md bg-gray-900 hover:bg-black text-white text-sm font-semibold transition-colors">
                                Apply
                            </button>
                            <?php if ($minPrice !== null || $maxPrice !== null): ?>
                                <a href="<?= buildUrl(['minp' => '', 'maxp' => '']) ?>"
                                    class="h-10 px-3 inline-flex items-center text-sm text-gray-600 hover:text-gray-900">Reset</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <!-- Offers -->
                <div class="px-4 py-4">
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-2">Offers</p>
                    <a href="<?= buildUrl(['sale' => $saleOnly ? '' : '1']) ?>"
                        class="flex items-center gap-2.5 px-2 py-1.5 rounded-md text-sm text-gray-700 hover:bg-gray-50">
                        <span class="w-4 h-4 rounded-sm border flex items-center justify-center shrink-0
                                     <?= $saleOnly ? 'bg-gray-900 border-gray-900' : 'border-gray-400 bg-white' ?>">
                            <?php if ($saleOnly): ?><i class="fa-solid fa-check text-[9px] text-white"></i><?php endif; ?>
                        </span>
                        On sale only
                    </a>
                </div>

                <?php if ($hasActiveFilters || $activeSubId): ?>
                    <div class="px-4 pb-4">
                        <a href="<?= buildUrl(['sub' => '', 'sort' => '', 'minp' => '', 'maxp' => '', 'sale' => '']) ?>"
                            class="block text-center h-10 leading-10 rounded-md border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            Clear all filters
                        </a>
                    </div>
                <?php endif; ?>
            </aside>

            <!-- ================= RESULTS ================= -->
            <section class="min-w-0">

                <!-- Toolbar -->
                <div class="flex items-center justify-between gap-3 bg-white border border-gray-200 rounded-lg px-3 md:px-4 py-2.5 mb-4">
                    <div class="flex items-center gap-2 min-w-0">
                        <button type="button" id="filterOpenBtn"
                            class="lg:hidden inline-flex items-center gap-2 h-9 px-3 rounded-md border border-gray-300 text-sm font-medium text-gray-800">
                            <i class="fa-solid fa-sliders text-xs"></i> Filters
                            <?php if ($activeFilterCount): ?>
                                <span class="min-w-[18px] h-[18px] px-1 rounded-full bg-gray-900 text-white text-[10px] font-bold flex items-center justify-center">
                                    <?= $activeFilterCount ?>
                                </span>
                            <?php endif; ?>
                        </button>
                        <p class="hidden sm:block text-sm text-gray-600 truncate">
                            <?php if ($totalProducts): ?>
                                Showing <span class="font-semibold text-gray-900"><?= $showingFrom ?>–<?= $showingTo ?></span>
                                of <span class="font-semibold text-gray-900"><?= number_format($totalProducts) ?></span>
                            <?php else: ?>
                                No results
                            <?php endif; ?>
                        </p>
                    </div>

                    <form method="GET" action="<?= BASE_URL ?>/productcategory" class="flex items-center gap-2 shrink-0">
                        <input type="hidden" name="id" value="<?= $categoryId ?>">
                        <?php if ($activeSubId): ?><input type="hidden" name="sub" value="<?= $activeSubId ?>"><?php endif; ?>
                        <?php if ($minPrice !== null): ?><input type="hidden" name="minp" value="<?= htmlspecialchars($minPrice) ?>"><?php endif; ?>
                        <?php if ($maxPrice !== null): ?><input type="hidden" name="maxp" value="<?= htmlspecialchars($maxPrice) ?>"><?php endif; ?>
                        <?php if ($saleOnly): ?><input type="hidden" name="sale" value="1"><?php endif; ?>
                        <label for="sortSelect" class="hidden sm:block text-sm text-gray-500">Sort by</label>
                        <div class="relative">
                            <select id="sortSelect" name="sort" onchange="this.form.submit()"
                                class="appearance-none h-9 text-sm font-medium text-gray-900 bg-white border border-gray-300 rounded-md pl-3 pr-8 cursor-pointer focus:outline-none focus:border-gray-900">
                                <?php foreach ($sortLabels as $key => $label): ?>
                                    <option value="<?= $key ?>" <?= $sort === $key ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                            <i class="fa-solid fa-chevron-down text-[10px] text-gray-500 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                        </div>
                    </form>
                </div>

                <!-- Active filter chips -->
                <?php if ($hasActiveFilters): ?>
                    <div class="flex flex-wrap items-center gap-2 mb-4">
                        <?php if ($saleOnly): ?>
                            <a href="<?= buildUrl(['sale' => '']) ?>"
                                class="inline-flex items-center gap-2 h-8 px-3 rounded-full bg-white border border-gray-300 text-xs font-medium text-gray-800 hover:border-gray-900">
                                On sale <i class="fa-solid fa-xmark text-[10px] text-gray-500"></i>
                            </a>
                        <?php endif; ?>
                        <?php if ($minPrice !== null || $maxPrice !== null): ?>
                            <a href="<?= buildUrl(['minp' => '', 'maxp' => '']) ?>"
                                class="inline-flex items-center gap-2 h-8 px-3 rounded-full bg-white border border-gray-300 text-xs font-medium text-gray-800 hover:border-gray-900">
                                ₱<?= number_format($minPrice ?? 0, 0) ?> – <?= $maxPrice !== null ? '₱' . number_format($maxPrice, 0) : 'and up' ?>
                                <i class="fa-solid fa-xmark text-[10px] text-gray-500"></i>
                            </a>
                        <?php endif; ?>
                        <?php if ($sort !== 'newest'): ?>
                            <a href="<?= buildUrl(['sort' => '']) ?>"
                                class="inline-flex items-center gap-2 h-8 px-3 rounded-full bg-white border border-gray-300 text-xs font-medium text-gray-800 hover:border-gray-900">
                                <?= $sortLabels[$sort] ?> <i class="fa-solid fa-xmark text-[10px] text-gray-500"></i>
                            </a>
                        <?php endif; ?>
                        <a href="<?= buildUrl(['sort' => '', 'minp' => '', 'maxp' => '', 'sale' => '']) ?>"
                            class="text-xs font-medium text-gray-600 hover:text-gray-900 underline underline-offset-2 ml-1">
                            Clear all
                        </a>
                    </div>
                <?php endif; ?>

                <!-- Product grid -->
                <?php if (empty($products)): ?>
                    <div class="bg-white border border-gray-200 rounded-lg text-center py-16 px-4">
                        <i class="fa-solid fa-box-open text-4xl text-gray-300"></i>
                        <p class="text-base font-semibold text-gray-900 mt-3">No products found</p>
                        <p class="text-sm text-gray-500 mt-1">Try adjusting your filters or browse all <?= htmlspecialchars($categoryName) ?>.</p>
                        <?php if ($hasActiveFilters || $activeSubId): ?>
                            <a href="<?= buildUrl(['sub' => '', 'sort' => '', 'minp' => '', 'maxp' => '', 'sale' => '']) ?>"
                                class="inline-flex items-center h-10 px-5 mt-5 rounded-md bg-gray-900 hover:bg-black text-white text-sm font-semibold">
                                Clear filters
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-x-3 gap-y-7 md:gap-x-4">
                        <?php foreach ($products as $p):
                            $isNew = !empty($p['created_at']) && (strtotime($p['created_at']) >= strtotime('-14 days'));
                            $isSale = !empty($p['max_discount']) && $p['max_discount'] > 0;
                            $min = floatval($p['min_price'] ?? 0);
                            $max = floatval($p['max_price'] ?? 0);
                            $dMin = floatval($p['min_discounted_price'] ?? 0);
                            $dMax = floatval($p['max_discounted_price'] ?? 0);
                            $rc = (int) ($p['review_count'] ?? 0);
                            $sold = (int) ($p['total_sold'] ?? 0);
                            ?>
                            <a href="<?= BASE_URL ?>/mainproductview?id=<?= (int) $p['id'] ?>"
                                aria-label="View details for <?= htmlspecialchars($p['name']) ?>"
                                class="group flex flex-col">

                                <!-- Image: puno ang tile (kapareho ng Most Popular) -->
                                <div class="relative aspect-square rounded-xl overflow-hidden bg-gray-100">
                                    <?php if (!empty($p['imageproduct'])): ?>
                                        <img src="<?= $uploadUrl . htmlspecialchars($p['imageproduct']) ?>"
                                            alt="<?= htmlspecialchars($p['name']) ?>" loading="lazy"
                                            class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                                    <?php else: ?>
                                        <div class="w-full h-full flex items-center justify-center">
                                            <i class="fa-regular fa-image text-3xl md:text-4xl text-gray-300"></i>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($isSale): ?>
                                        <span class="absolute top-2 left-2 z-10 bg-red-500 text-white text-[10px] md:text-xs font-bold px-2 py-0.5 rounded-md shadow-sm">
                                            -<?= rtrim(rtrim(number_format($p['max_discount'], 2), '0'), '.') ?>%
                                        </span>
                                    <?php elseif ($isNew): ?>
                                        <span class="absolute top-2 left-2 z-10 bg-emerald-500 text-white text-[10px] md:text-xs font-bold px-2 py-0.5 rounded-md shadow-sm">
                                            NEW
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <!-- Info -->
                                <div class="flex-1 flex flex-col pt-2.5 px-0.5">
                                    <h3 class="nh-clamp-2 text-xs md:text-sm font-medium text-gray-800 leading-snug group-hover:text-orange-600 transition-colors">
                                        <?= htmlspecialchars($p['name']) ?>
                                    </h3>

                                    <?php if ($rc > 0 || $sold > 0): ?>
                                        <div class="flex items-center gap-1.5 mt-1 text-[10px] md:text-xs text-gray-400">
                                            <?php if ($rc > 0): ?>
                                                <i class="fa-solid fa-star text-amber-400"></i>
                                                <span class="font-semibold text-gray-700"><?= number_format((float) $p['avg_rating'], 1) ?></span>
                                                <span>(<?= $rc ?>)</span>
                                            <?php endif; ?>
                                            <?php if ($rc > 0 && $sold > 0): ?><span class="text-gray-300">|</span><?php endif; ?>
                                            <?php if ($sold > 0): ?><span><?= number_format($sold) ?> sold</span><?php endif; ?>
                                        </div>
                                    <?php endif; ?>

                                    <div class="mt-1.5 flex items-baseline gap-1.5 flex-wrap">
                                        <?php if ($isSale): ?>
                                            <?php if ($dMin !== $dMax): ?><span class="text-[10px] md:text-xs text-gray-400">From</span><?php endif; ?>
                                            <span class="text-sm md:text-base font-bold text-red-600">₱<?= number_format($dMin, 2) ?></span>
                                            <span class="text-[10px] md:text-xs text-gray-400 line-through">₱<?= number_format($min, 2) ?></span>
                                        <?php elseif ($min > 0 || $max > 0): ?>
                                            <?php if ($min !== $max): ?><span class="text-[10px] md:text-xs text-gray-400">From</span><?php endif; ?>
                                            <span class="text-sm md:text-base font-bold text-gray-900">₱<?= number_format($min, 2) ?></span>
                                        <?php else: ?>
                                            <span class="text-[11px] md:text-xs text-gray-400">Price not set</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1):
                        $windowStart = max(1, $currentPage - 1);
                        $windowEnd = min($totalPages, $currentPage + 1);
                        $pgBase = 'h-10 min-w-[40px] px-3 inline-flex items-center justify-center rounded-md border text-sm transition-colors';
                        $pgIdle = 'border-gray-300 bg-white text-gray-700 hover:border-gray-900';
                        ?>
                        <nav class="mt-10 flex items-center justify-center gap-1.5" aria-label="Pagination">
                            <a href="<?= $currentPage > 1 ? buildUrl(['page' => $currentPage - 1], false) : '#' ?>"
                                class="<?= $pgBase ?> <?= $currentPage > 1 ? $pgIdle : 'border-gray-200 text-gray-300 bg-white pointer-events-none' ?>"
                                <?= $currentPage <= 1 ? 'aria-disabled="true" tabindex="-1"' : '' ?>>
                                <i class="fa-solid fa-chevron-left text-xs"></i><span class="hidden sm:inline ml-2">Prev</span>
                            </a>

                            <?php if ($windowStart > 1): ?>
                                <a href="<?= buildUrl(['page' => 1], false) ?>" class="hidden sm:inline-flex <?= $pgBase ?> <?= $pgIdle ?>">1</a>
                                <?php if ($windowStart > 2): ?><span class="hidden sm:inline px-1 text-gray-400">…</span><?php endif; ?>
                            <?php endif; ?>

                            <?php for ($i = $windowStart; $i <= $windowEnd; $i++): $isCurrent = $i === $currentPage; ?>
                                <a href="<?= buildUrl(['page' => $i], false) ?>"
                                    class="hidden sm:inline-flex <?= $pgBase ?> <?= $isCurrent ? 'border-gray-900 bg-gray-900 text-white font-semibold' : $pgIdle ?>"
                                    <?= $isCurrent ? 'aria-current="page"' : '' ?>><?= $i ?></a>
                            <?php endfor; ?>

                            <?php if ($windowEnd < $totalPages): ?>
                                <?php if ($windowEnd < $totalPages - 1): ?><span class="hidden sm:inline px-1 text-gray-400">…</span><?php endif; ?>
                                <a href="<?= buildUrl(['page' => $totalPages], false) ?>" class="hidden sm:inline-flex <?= $pgBase ?> <?= $pgIdle ?>"><?= $totalPages ?></a>
                            <?php endif; ?>

                            <span class="sm:hidden px-3 text-sm text-gray-600">Page <?= $currentPage ?> of <?= $totalPages ?></span>

                            <a href="<?= $currentPage < $totalPages ? buildUrl(['page' => $currentPage + 1], false) : '#' ?>"
                                class="<?= $pgBase ?> <?= $currentPage < $totalPages ? $pgIdle : 'border-gray-200 text-gray-300 bg-white pointer-events-none' ?>"
                                <?= $currentPage >= $totalPages ? 'aria-disabled="true" tabindex="-1"' : '' ?>>
                                <span class="hidden sm:inline mr-2">Next</span><i class="fa-solid fa-chevron-right text-xs"></i>
                            </a>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        </div>
    </main>

    <?php include ROOT_PATH . '/user/navigation/bottom.php'; ?>

    <script>
        // Mobile filter bottom sheet (sa desktop, laging nakikita ang sidebar)
        (function () {
            const panel = document.getElementById('filterPanel');
            const backdrop = document.getElementById('filterBackdrop');
            const openBtn = document.getElementById('filterOpenBtn');
            const closeBtn = document.getElementById('filterCloseBtn');
            if (!panel || !openBtn) return;

            function open() {
                panel.classList.remove('translate-y-full', 'invisible');
                backdrop?.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            }
            function close() {
                panel.classList.add('translate-y-full');
                backdrop?.classList.add('hidden');
                document.body.style.overflow = '';
                setTimeout(() => { if (panel.classList.contains('translate-y-full')) panel.classList.add('invisible'); }, 300);
            }

            openBtn.addEventListener('click', open);
            closeBtn?.addEventListener('click', close);
            backdrop?.addEventListener('click', close);
            window.addEventListener('keydown', e => { if (e.key === 'Escape') close(); });
            window.addEventListener('resize', () => { if (window.innerWidth >= 1024) close(); });
        })();
    </script>

</body>

</html>