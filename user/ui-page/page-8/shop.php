<?php
// user/ui-page/page-8/shop.php
include ROOT_PATH . '/network/connect.php';

$uploadUrl = BASE_URL . '/uploads/';
$searchQuery = trim($_GET['search'] ?? '');
$saleOnly = isset($_GET['sale_only']) && $_GET['sale_only'] === '1';

// Read current filter values (for pre-checking UI only — products are fetched via AJAX)
$selectedCategories = array_filter(array_map('trim', (array) ($_GET['categories'] ?? [])));
$selectedColors = array_filter(array_map('trim', (array) ($_GET['colors'] ?? [])));
$selectedSizes = array_filter(array_map('trim', (array) ($_GET['sizes'] ?? [])));
$minPriceFilter = isset($_GET['min_price']) && $_GET['min_price'] !== '' ? floatval($_GET['min_price']) : null;
$maxPriceFilter = isset($_GET['max_price']) && $_GET['max_price'] !== '' ? floatval($_GET['max_price']) : null;
$selectedCategoriesLower = array_map('mb_strtolower', $selectedCategories);
$selectedColorsLower = array_map('mb_strtolower', $selectedColors);
$selectedSizesLower = array_map('mb_strtolower', $selectedSizes);

// ── Filter options (sidebar only — lightweight queries) ──────────────────────
$availableCategories = [];
$seenCategories = [];
$catRes = $conn->query("SELECT DISTINCT category FROM nobleproduct WHERE category IS NOT NULL AND category != '' ORDER BY category ASC");
while ($row = $catRes->fetch_assoc()) {
    $name = trim($row['category']);
    $key = mb_strtolower($name);
    if ($key === '' || isset($seenCategories[$key]))
        continue;
    $seenCategories[$key] = true;
    $availableCategories[] = $name;
}

$availableColors = [];
$seenColors = [];
$colorRes = $conn->query("SELECT DISTINCT colorname FROM nobleproductcolor WHERE colorname != '' ORDER BY colorname ASC");
while ($row = $colorRes->fetch_assoc()) {
    $name = trim($row['colorname']);
    $key = mb_strtolower($name);
    if ($key === '' || isset($seenColors[$key]))
        continue;
    $seenColors[$key] = true;
    $availableColors[] = $name;
}

$availableSizes = [];
$seenSizes = [];
$sizeRes = $conn->query("SELECT DISTINCT sizename FROM nobleproductvariant WHERE sizename != '' ORDER BY sizename ASC");
while ($row = $sizeRes->fetch_assoc()) {
    $name = trim($row['sizename']);
    $key = mb_strtolower($name);
    if ($key === '' || isset($seenSizes[$key]))
        continue;
    $seenSizes[$key] = true;
    $availableSizes[] = $name;
}

$priceBounds = $conn->query("SELECT MIN(pricesize) AS lo, MAX(pricesize) AS hi FROM nobleproductvariant")->fetch_assoc();
$priceLo = floatval($priceBounds['lo'] ?? 0);
$priceHi = floatval($priceBounds['hi'] ?? 0);


?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shop - NobleHome</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
</head>

<body class="bg-gray-50 min-h-screen flex flex-col">

    <?php include ROOT_PATH . '/user/navigation/top.php'; ?>

    <!-- Toast -->
    <div id="toast" class="fixed top-6 right-6 z-[90] opacity-0 pointer-events-none translate-y-2
            flex items-center gap-3 px-4 py-3 rounded-lg shadow-lg text-sm font-medium
            bg-white border border-gray-200 text-gray-800 min-w-56">
        <span id="toast-icon"></span>
        <span id="toast-msg"></span>
    </div>

    <!-- Mobile filter backdrop -->
    <div id="filterOverlay" class="fixed inset-0 bg-gray-900/40 z-[55] hidden md:hidden" onclick="closeFilterDrawer()"></div>

    <main class="max-w-screen-xl w-full mx-auto px-3 md:px-4 py-4 md:py-6 pb-24 md:pb-10 flex-1">

        <!-- Page header -->
        <header class="mb-5 pb-5 border-b border-gray-200">
            <nav class="flex items-center gap-1.5 text-xs md:text-sm text-gray-500" aria-label="Breadcrumb">
                <a href="<?= BASE_URL ?>" class="hover:text-gray-900 transition-colors">Home</a>
                <span class="text-gray-300">/</span>
                <span class="text-gray-900">Shop</span>
            </nav>
            <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-3 mt-3">
                <div>
                    <h1 class="text-2xl md:text-[32px] font-bold tracking-tight text-gray-900 leading-tight">
                        <?= $searchQuery !== '' ? 'Results for “' . htmlspecialchars($searchQuery) . '”' : 'Shop All Products' ?>
                    </h1>
                    <p class="text-sm text-gray-500 mt-1">Building materials &amp; home essentials</p>
                </div>

                <!-- Realtime search -->
                <div class="relative w-full md:w-80">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs pointer-events-none"></i>
                    <input type="text" id="searchInput" value="<?= htmlspecialchars($searchQuery) ?>"
                        placeholder="Search products…" aria-label="Search products"
                        class="w-full h-10 pl-8 pr-9 text-sm border border-gray-300 rounded-md bg-white focus:outline-none focus:border-gray-900 focus:ring-2 focus:ring-gray-900/5">
                    <span id="searchSpinner" class="hidden absolute right-3 top-1/2 -translate-y-1/2">
                        <i class="fa-solid fa-circle-notch fa-spin text-gray-400 text-xs"></i>
                    </span>
                </div>
            </div>
        </header>

        <div class="flex flex-col md:flex-row gap-6 items-start">

            <!-- ═══════════════ SIDEBAR FILTERS ═══════════════ -->
            <!-- Mobile: bottom sheet. Desktop (md+): sticky sidebar card. -->
            <aside id="filterSidebar" aria-label="Filters"
                class="fixed md:sticky inset-x-0 bottom-0 md:inset-auto md:top-36 z-[60] md:z-auto
                       w-full md:w-64 shrink-0 max-h-[85vh] md:max-h-[calc(100vh-10rem)]
                       bg-white rounded-t-xl md:rounded-lg md:border md:border-gray-200
                       translate-y-full md:translate-y-0 transition-transform duration-300 ease-in-out
                       flex flex-col overflow-hidden shadow-2xl md:shadow-none">

                <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 shrink-0">
                    <h2 class="flex items-center gap-2 text-sm font-semibold text-gray-900">Filters
                        <span id="activeFilterCount" class="hidden min-w-[18px] h-[18px] px-1 rounded-full bg-gray-900 text-white text-[10px] font-bold items-center justify-center"></span>
                    </h2>
                    <div class="flex items-center gap-2">
                        <button type="button" id="clearFiltersBtn"
                            class="text-xs font-medium text-gray-600 hover:text-gray-900 underline underline-offset-2">Clear all</button>
                        <button type="button" id="closeFilterBtn" onclick="closeFilterDrawer()" title="Close filters"
                            class="md:hidden w-8 h-8 flex items-center justify-center rounded-md text-gray-500 hover:bg-gray-100">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                </div>

                <div class="nh-thin-scroll flex-1 overflow-y-auto">

                    <!-- Sale Only -->
                    <label class="flex items-center justify-between gap-3 px-4 py-3 border-b border-gray-100 cursor-pointer hover:bg-gray-50">
                        <span class="flex items-center gap-2 text-sm font-medium text-gray-800">
                            <i class="fa-solid fa-tag text-xs text-red-500"></i> On sale only
                        </span>
                        <span class="relative shrink-0">
                            <input type="checkbox" id="filter_sale_only" <?= $saleOnly ? 'checked' : '' ?> class="sr-only peer filter-input">
                            <span class="block w-10 h-5 bg-gray-200 rounded-full peer-checked:bg-gray-900 transition-colors duration-200"></span>
                            <span class="absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform duration-200 peer-checked:translate-x-5"></span>
                        </span>
                    </label>

                    <?php if (!empty($availableCategories)): ?>
                        <div class="nh-filter-sec border-b border-gray-100" data-open="0">
                            <button type="button" class="nh-filter-head w-full flex items-center justify-between px-4 py-3 hover:bg-gray-50" aria-expanded="false">
                                <span class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-gray-600">
                                    Category
                                    <span class="nh-sec-count hidden min-w-[18px] h-[18px] px-1 rounded-full bg-gray-900 text-white text-[10px] font-bold normal-case tracking-normal items-center justify-center"></span>
                                </span>
                                <i class="nh-chev fa-solid fa-chevron-up text-[10px] text-gray-400 transition-transform duration-200"></i>
                            </button>
                            <div class="nh-filter-body nh-thin-scroll max-h-52 overflow-y-auto px-2 pb-3">
                                <?php foreach ($availableCategories as $opt): ?>
                                    <label class="flex items-center gap-2.5 px-2 py-1.5 rounded-md text-sm text-gray-700 cursor-pointer hover:bg-gray-50">
                                        <input type="checkbox" name="categories[]" value="<?= htmlspecialchars($opt) ?>"
                                            <?= in_array(mb_strtolower($opt), $selectedCategoriesLower) ? 'checked' : '' ?>
                                            class="w-4 h-4 rounded border-gray-300 accent-gray-900 filter-input shrink-0">
                                        <span class="truncate"><?= htmlspecialchars($opt) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($availableColors)): ?>
                        <div class="nh-filter-sec border-b border-gray-100" data-open="0">
                            <button type="button" class="nh-filter-head w-full flex items-center justify-between px-4 py-3 hover:bg-gray-50" aria-expanded="false">
                                <span class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-gray-600">
                                    Color
                                    <span class="nh-sec-count hidden min-w-[18px] h-[18px] px-1 rounded-full bg-gray-900 text-white text-[10px] font-bold normal-case tracking-normal items-center justify-center"></span>
                                </span>
                                <i class="nh-chev fa-solid fa-chevron-up text-[10px] text-gray-400 transition-transform duration-200"></i>
                            </button>
                            <div class="nh-filter-body nh-thin-scroll max-h-52 overflow-y-auto px-2 pb-3">
                                <?php foreach ($availableColors as $opt): ?>
                                    <label class="flex items-center gap-2.5 px-2 py-1.5 rounded-md text-sm text-gray-700 cursor-pointer hover:bg-gray-50">
                                        <input type="checkbox" name="colors[]" value="<?= htmlspecialchars($opt) ?>"
                                            <?= in_array(mb_strtolower($opt), $selectedColorsLower) ? 'checked' : '' ?>
                                            class="w-4 h-4 rounded border-gray-300 accent-gray-900 filter-input shrink-0">
                                        <span class="truncate"><?= htmlspecialchars($opt) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($availableSizes)): ?>
                        <div class="nh-filter-sec border-b border-gray-100" data-open="0">
                            <button type="button" class="nh-filter-head w-full flex items-center justify-between px-4 py-3 hover:bg-gray-50" aria-expanded="false">
                                <span class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-gray-600">
                                    Size
                                    <span class="nh-sec-count hidden min-w-[18px] h-[18px] px-1 rounded-full bg-gray-900 text-white text-[10px] font-bold normal-case tracking-normal items-center justify-center"></span>
                                </span>
                                <i class="nh-chev fa-solid fa-chevron-up text-[10px] text-gray-400 transition-transform duration-200"></i>
                            </button>
                            <div class="nh-filter-body nh-thin-scroll max-h-52 overflow-y-auto px-2 pb-3">
                                <?php foreach ($availableSizes as $opt): ?>
                                    <label class="flex items-center gap-2.5 px-2 py-1.5 rounded-md text-sm text-gray-700 cursor-pointer hover:bg-gray-50">
                                        <input type="checkbox" name="sizes[]" value="<?= htmlspecialchars($opt) ?>"
                                            <?= in_array(mb_strtolower($opt), $selectedSizesLower) ? 'checked' : '' ?>
                                            class="w-4 h-4 rounded border-gray-300 accent-gray-900 filter-input shrink-0">
                                        <span class="truncate"><?= htmlspecialchars($opt) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Price -->
                    <div class="nh-filter-sec" data-open="0">
                        <button type="button" class="nh-filter-head w-full flex items-center justify-between px-4 py-3 hover:bg-gray-50" aria-expanded="false">
                            <span class="text-xs font-semibold uppercase tracking-wider text-gray-600">Price</span>
                            <i class="nh-chev fa-solid fa-chevron-up text-[10px] text-gray-400 transition-transform duration-200"></i>
                        </button>
                        <div class="nh-filter-body px-4 pb-4">
                            <div class="grid grid-cols-[1fr_auto_1fr] items-center gap-2">
                                <label class="block">
                                    <span class="block text-[11px] text-gray-500 mb-1">Min</span>
                                    <span class="relative block">
                                        <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-xs text-gray-400">₱</span>
                                        <input type="number" id="filter_min_price" aria-label="Minimum price" placeholder="0"
                                            value="<?= $minPriceFilter !== null ? htmlspecialchars((string) $minPriceFilter) : '' ?>"
                                            min="0" step="0.01"
                                            class="nh-num w-full h-10 pl-6 pr-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:border-gray-900 filter-input">
                                    </span>
                                </label>
                                <span class="text-gray-400 text-sm mt-5">–</span>
                                <label class="block">
                                    <span class="block text-[11px] text-gray-500 mb-1">Max</span>
                                    <span class="relative block">
                                        <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-xs text-gray-400">₱</span>
                                        <input type="number" id="filter_max_price" aria-label="Maximum price" placeholder="Any"
                                            value="<?= $maxPriceFilter !== null ? htmlspecialchars((string) $maxPriceFilter) : '' ?>"
                                            min="0" step="0.01"
                                            class="nh-num w-full h-10 pl-6 pr-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:border-gray-900 filter-input">
                                    </span>
                                </label>
                            </div>
                            <p class="text-[11px] text-gray-500 mt-2">
                                Prices from ₱<?= number_format($priceLo, 0) ?> to ₱<?= number_format($priceHi, 0) ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Mobile: confirm & close -->
                <div class="md:hidden p-4 border-t border-gray-200 shrink-0">
                    <button type="button" onclick="closeFilterDrawer()"
                        class="w-full h-11 bg-gray-900 hover:bg-black text-white text-sm font-semibold rounded-md transition">
                        Show results
                    </button>
                </div>
            </aside>

            <!-- ═══════════════ PRODUCT AREA ═══════════════ -->
            <section class="flex-1 flex flex-col min-w-0 w-full">

                <!-- Toolbar -->
                <div class="flex items-center justify-between gap-3 bg-white border border-gray-200 rounded-lg px-3 md:px-4 py-2.5 mb-4">
                    <button type="button" id="openFilterBtn"
                        class="md:hidden inline-flex items-center gap-2 h-9 px-3 rounded-md border border-gray-300 text-sm font-medium text-gray-800">
                        <i class="fa-solid fa-sliders text-xs"></i> Filters
                        <span id="activeFilterCountMobile" class="hidden min-w-[18px] h-[18px] px-1 rounded-full bg-gray-900 text-white text-[10px] font-bold items-center justify-center"></span>
                    </button>
                    <p id="resultsLabel" class="hidden md:block text-sm text-gray-600">Loading products…</p>
                </div>

                <!-- Active filter chips -->
                <div id="activeChips" class="hidden flex-wrap items-center gap-2 mb-4"></div>

                <!-- Product grid (populated by AJAX) -->
                <div id="products-area"
                    class="flex-1 grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-x-3 gap-y-6 md:gap-x-4 content-start">
                </div>

                <!-- Pagination -->
                <div id="pagination-area" class="mt-8"></div>
            </section>
        </div>
    </main>

    <!-- ═══════════════ ADD TO CART MODAL ═══════════════ -->
    <div id="addToCartModal" class="fixed inset-0 z-[80] hidden items-end sm:items-center justify-center bg-gray-900/50 p-0 sm:p-4"
        onclick="closeModalBackdrop(event)">
        <div class="bg-white rounded-t-xl sm:rounded-lg w-full sm:max-w-md shadow-xl relative overflow-hidden" onclick="event.stopPropagation()">

            <div class="flex gap-4 p-5 border-b border-gray-100">
                <div id="modalProductImage"
                    class="w-20 h-20 rounded-md bg-gray-100 overflow-hidden flex items-center justify-center border border-gray-200 shrink-0">
                </div>
                <div class="min-w-0 flex-1 pr-6">
                    <h3 id="modalProductName" class="text-sm font-semibold text-gray-900 leading-snug"></h3>
                    <div class="flex flex-wrap items-center gap-2 mt-1.5">
                        <p id="modalProductPrice" class="text-lg font-bold text-gray-900"></p>
                        <span id="modalPromoTimer" style="display:none;"
                            class="items-center gap-1 text-red-600 text-[11px] font-semibold">
                            <i class="fa-regular fa-clock"></i> <span id="modalPromoTimerText">--:--:--</span>
                        </span>
                    </div>
                    <p id="modalStockLabel" class="text-xs mt-1"></p>
                </div>
                <button type="button" onclick="closeAddToCartModal()" title="Close" aria-label="Close"
                    class="absolute top-3 right-3 w-8 h-8 rounded-md flex items-center justify-center text-gray-400 hover:bg-gray-100 hover:text-gray-700">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="p-5 space-y-4">
                <div id="modalColorSection">
                    <p class="text-sm font-semibold text-gray-900 mb-2">Color</p>
                    <div id="modalColorOptions" class="flex flex-wrap gap-2"></div>
                </div>
                <div id="modalSizeSection">
                    <p class="text-sm font-semibold text-gray-900 mb-2">Size</p>
                    <div id="modalSizeOptions" class="flex flex-wrap gap-2">
                        <span class="text-xs text-gray-500">Select a color first</span>
                    </div>
                </div>
                <div>
                    <p class="text-sm font-semibold text-gray-900 mb-2">Quantity</p>
                    <div class="inline-flex items-center border border-gray-300 rounded-md overflow-hidden bg-white">
                        <button type="button" onclick="modalChangeQty(-1)" aria-label="Decrease quantity"
                            class="w-10 h-10 flex items-center justify-center text-gray-700 hover:bg-gray-50 transition">
                            <i class="fa-solid fa-minus text-xs"></i>
                        </button>
                        <input type="number" id="modalQtyInput" value="1" min="1" inputmode="numeric" aria-label="Quantity"
                            class="qty-input w-14 h-10 text-center text-sm font-semibold text-gray-900 border-x border-gray-300 focus:outline-none focus:bg-gray-50">
                        <button type="button" onclick="modalChangeQty(1)" aria-label="Increase quantity"
                            class="w-10 h-10 flex items-center justify-center text-gray-700 hover:bg-gray-50 transition">
                            <i class="fa-solid fa-plus text-xs"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="flex gap-2 px-5 pb-5">
                <a id="modalViewLink" href="#"
                    class="flex-1 h-11 inline-flex items-center justify-center rounded-md border border-gray-300 text-sm font-semibold text-gray-800 hover:bg-gray-50 transition">
                    View details
                </a>
                <button id="modalAddBtn" type="button" onclick="confirmAddToCart()" disabled
                    class="flex-1 h-11 bg-orange-600 hover:bg-orange-700 disabled:bg-gray-200 disabled:text-gray-400 disabled:cursor-not-allowed text-white text-sm font-semibold rounded-md transition">
                    <i class="fa-solid fa-cart-plus mr-1.5"></i> Add to Cart
                </button>
            </div>
        </div>
    </div>

    <?php include ROOT_PATH . '/user/navigation/bottom.php'; ?>

    <style>
        .qty-input::-webkit-inner-spin-button,
        .qty-input::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        .qty-input {
            -moz-appearance: textfield;
        }

        #toast {
            transition: opacity .3s ease, transform .3s ease;
        }

        .nh-num::-webkit-inner-spin-button,
        .nh-num::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
        .nh-num { -moz-appearance: textfield; appearance: textfield; }

        .nh-filter-sec[data-open="0"] .nh-filter-body { display: none; }
        .nh-filter-sec[data-open="0"] .nh-chev { transform: rotate(180deg); }

        .nh-thin-scroll { scrollbar-width: thin; scrollbar-color: #d1d5db transparent; }
        .nh-thin-scroll::-webkit-scrollbar { width: 6px; }
        .nh-thin-scroll::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 9999px; }

        /* Selected option buttons sa modal */
        #addToCartModal .opt-selected {
            border-color: #111827 !important;
            box-shadow: inset 0 0 0 1px #111827;
            color: #111827;
            font-weight: 600;
        }
    </style>

    <script>
        // ── Config ────────────────────────────────────────────────────────────────────
        const ADD_TO_CART_URL = <?= json_encode(BASE_URL . '/cartadd') ?>;
        const PRODUCTS_URL = <?= json_encode(BASE_URL . '/shop-products') ?>;   // AJAX endpoint
        const SAVE_PRODUCT_URL = <?= json_encode(BASE_URL . '/savedproduct') ?>;
        const LOW_STOCK_THRESHOLD = 5;
        let promoInterval = null;

        // Merged from every AJAX response — persists across page changes
        let PRODUCT_DATA = {};

        // ── State ─────────────────────────────────────────────────────────────────────
        let currentPage = <?= max(1, intval($_GET['page'] ?? 1)) ?>;
        let fetchController = null;   // AbortController for in-flight requests

        function startPromoTimers() {
            if (promoInterval) clearInterval(promoInterval);

            function tick() {
                const now = Date.now();
                document.querySelectorAll('.promo-timer').forEach(el => {
                    const end = new Date(el.dataset.end).getTime();
                    const diff = end - now;

                    if (diff <= 0) {
                        el.textContent = 'Ended';
                        el.classList.add('opacity-50');
                        return;
                    }

                    const d = Math.floor(diff / 86400000);
                    const h = Math.floor((diff % 86400000) / 3600000);
                    const m = Math.floor((diff % 3600000) / 60000);
                    const s = Math.floor((diff % 60000) / 1000);

                    el.textContent = d > 0
                        ? `${d}d ${h}h left`
                        : `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
                });
            }
            tick();
            promoInterval = setInterval(tick, 1000);
        }

        // ── Mobile filter drawer ──────────────────────────────────────────────────────
        function openFilterDrawer() {
            document.getElementById('filterSidebar').classList.remove('translate-y-full');
            document.getElementById('filterOverlay').classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        }

        function closeFilterDrawer() {
            document.getElementById('filterSidebar').classList.add('translate-y-full');
            document.getElementById('filterOverlay').classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }

        document.getElementById('openFilterBtn').addEventListener('click', openFilterDrawer);

        // Close drawer with Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') { closeFilterDrawer(); closeAddToCartModal(); }
        });

        // ── Collect filters into a URLSearchParams ────────────────────────────────────
        function collectFilters(page = 1) {
            const params = new URLSearchParams();

            const search = document.getElementById('searchInput').value.trim();
            if (search) params.set('search', search);

            if (document.getElementById('filter_sale_only').checked) params.set('sale_only', '1');

            document.querySelectorAll('input[name="categories[]"]:checked')
                .forEach(el => params.append('categories[]', el.value));
            document.querySelectorAll('input[name="colors[]"]:checked')
                .forEach(el => params.append('colors[]', el.value));
            document.querySelectorAll('input[name="sizes[]"]:checked')
                .forEach(el => params.append('sizes[]', el.value));

            const minP = document.getElementById('filter_min_price').value.trim();
            const maxP = document.getElementById('filter_max_price').value.trim();
            if (minP) params.set('min_price', minP);
            if (maxP) params.set('max_price', maxP);

            params.set('page', page);
            return params;
        }

        // ── Skeleton cards ────────────────────────────────────────────────────────────
        function renderSkeletons(n = 8) {
            let html = '';
            for (let i = 0; i < n; i++) {
                html += `
    <div class="animate-pulse">
        <div class="aspect-square bg-gray-200 rounded-xl"></div>
        <div class="pt-3 space-y-2">
            <div class="h-3.5 bg-gray-200 rounded w-11/12"></div>
            <div class="h-3.5 bg-gray-200 rounded w-2/3"></div>
            <div class="h-4 bg-gray-200 rounded w-1/3 mt-3"></div>
        </div>
    </div>`;
            }
            return html;
        }
        // ── Main fetch function ───────────────────────────────────────────────────────
        async function fetchProducts(page = 1, showSkeleton = true) {
            // Abort any in-flight request
            if (fetchController) fetchController.abort();
            fetchController = new AbortController();

            const params = collectFilters(page);
            const area = document.getElementById('products-area');
            const spinner = document.getElementById('searchSpinner');

            const MIN_SKELETON_TIME = 250; // maikli lang para hindi kumislap
            const skeletonStart = Date.now();

            if (showSkeleton) {
                area.innerHTML = renderSkeletons(8);
                document.getElementById('pagination-area').innerHTML = '';
            }
            spinner.classList.remove('hidden');

            // Keep URL in sync (browser history) without reload
            const newUrl = window.location.pathname + '?' + params.toString();
            history.replaceState(null, '', newUrl);

            try {
                const res = await fetch(PRODUCTS_URL + '?' + params.toString(), {
                    signal: fetchController.signal
                });
                const data = await res.json();

                // Ensure skeleton shows for at least MIN_SKELETON_TIME
                if (showSkeleton) {
                    const elapsed = Date.now() - skeletonStart;
                    if (elapsed < MIN_SKELETON_TIME) {
                        await new Promise(resolve => setTimeout(resolve, MIN_SKELETON_TIME - elapsed));
                    }
                }

                // Merge new product data into global store
                Object.assign(PRODUCT_DATA, data.product_data ?? {});

                area.innerHTML = data.html;
                startPromoTimers();
                updateResultsUi(data);

                const paginationArea = document.getElementById('pagination-area');
                paginationArea.innerHTML = data.pagination ?? '';

                // Bind pagination buttons
                paginationArea.querySelectorAll('.page-btn').forEach(btn => {
                    btn.addEventListener('click', () => {
                        const pg = parseInt(btn.dataset.page);
                        if (!isNaN(pg)) {
                            currentPage = pg;
                            fetchProducts(pg, true);
                            window.scrollTo({ top: 0, behavior: 'smooth' });
                        }
                    });
                });
            } catch (err) {
                if (err.name === 'AbortError') return;   // superseded request — ignore
                area.innerHTML = `<div class="col-span-full text-center py-16 bg-white border border-gray-200 rounded-lg">
    <i class="fa-solid fa-triangle-exclamation text-3xl text-gray-300"></i>
    <p class="text-sm font-semibold text-gray-900 mt-3">Failed to load products</p>
    <p class="text-xs text-gray-500 mt-1">Please refresh the page.</p></div>`;
            } finally {
                spinner.classList.add('hidden');
            }
        }

        // ── Debounce helper ───────────────────────────────────────────────────────────
        function debounce(fn, delay) {
            let timer;
            return (...args) => { clearTimeout(timer); timer = setTimeout(() => fn(...args), delay); };
        }

        // ── Wire up filter listeners ──────────────────────────────────────────────────
        const debouncedFetch = debounce(() => { currentPage = 1; fetchProducts(1); }, 350);

        // Checkboxes + sale toggle: instant (but still debounced to batch rapid clicks)
        document.querySelectorAll('.filter-input').forEach(el => {
            el.addEventListener('change', debouncedFetch);
        });

        // Search: 350ms debounce while typing
        document.getElementById('searchInput').addEventListener('input', debouncedFetch);

        // Price fields: 600ms debounce (user finishes typing the number)
        const debouncedPriceFetch = debounce(() => { currentPage = 1; fetchProducts(1); }, 600);
        document.getElementById('filter_min_price').addEventListener('input', debouncedPriceFetch);
        document.getElementById('filter_max_price').addEventListener('input', debouncedPriceFetch);

        // Clear all filters
        document.getElementById('clearFiltersBtn').addEventListener('click', () => {
            document.querySelectorAll('.filter-input').forEach(el => {
                if (el.type === 'checkbox') el.checked = false;
                else el.value = '';
            });
            document.getElementById('searchInput').value = '';
            currentPage = 1;
            fetchProducts(1);
        });

        // ── Filter sections (accordion) + selected counts ─────────────────────────
        document.querySelectorAll('.nh-filter-sec').forEach(sec => {
            const head = sec.querySelector('.nh-filter-head');
            // Closed by default; auto-open only if this section already has an active filter
            const hasActive = sec.querySelector('input[type="checkbox"]:checked') ||
                Array.from(sec.querySelectorAll('input[type="number"]')).some(i => i.value !== '');
            if (hasActive) { sec.dataset.open = '1'; head.setAttribute('aria-expanded', 'true'); }
            head.addEventListener('click', () => {
                const open = sec.dataset.open !== '1';
                sec.dataset.open = open ? '1' : '0';
                head.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
        });

        function updateSectionCounts() {
            document.querySelectorAll('.nh-filter-sec').forEach(sec => {
                const badge = sec.querySelector('.nh-sec-count');
                if (!badge) return;
                const n = sec.querySelectorAll('input[type="checkbox"]:checked').length;
                badge.textContent = n;
                badge.classList.toggle('hidden', n === 0);
                badge.classList.toggle('inline-flex', n > 0);
            });
        }
        document.querySelectorAll('.nh-filter-sec input[type="checkbox"]').forEach(el => el.addEventListener('change', updateSectionCounts));
        updateSectionCounts();

        // ── Results label, active filter count & chips ─────────────────────────────
        function updateResultsUi(data) {
            const label = document.getElementById('resultsLabel');
            const total = data && (data.total ?? data.total_products ?? data.count);
            if (label) label.textContent = (total !== undefined && total !== null)
                ? `${Number(total).toLocaleString()} product${Number(total) === 1 ? '' : 's'}`
                : 'Products';
            renderActiveChips();
        }

        function renderActiveChips() {
            const chips = [];
            const add = (text, clear) => chips.push({ text, clear });

            if (document.getElementById('filter_sale_only').checked)
                add('On sale', () => { document.getElementById('filter_sale_only').checked = false; });
            ['categories', 'colors', 'sizes'].forEach(group => {
                document.querySelectorAll(`input[name="${group}[]"]:checked`).forEach(el =>
                    add(el.value, () => { el.checked = false; }));
            });
            const minP = document.getElementById('filter_min_price').value.trim();
            const maxP = document.getElementById('filter_max_price').value.trim();
            if (minP || maxP) add(`₱${minP || 0} – ${maxP ? '₱' + maxP : 'up'}`, () => {
                document.getElementById('filter_min_price').value = '';
                document.getElementById('filter_max_price').value = '';
            });

            const wrap = document.getElementById('activeChips');
            wrap.innerHTML = '';
            chips.forEach(c => {
                const b = document.createElement('button');
                b.type = 'button';
                b.className = 'inline-flex items-center gap-2 h-8 px-3 rounded-full bg-white border border-gray-300 text-xs font-medium text-gray-800 hover:border-gray-900';
                b.innerHTML = '<span></span><i class="fa-solid fa-xmark text-[10px] text-gray-500"></i>';
                b.firstChild.textContent = c.text;
                b.onclick = () => { c.clear(); currentPage = 1; fetchProducts(1); };
                wrap.appendChild(b);
            });
            if (chips.length) {
                const clr = document.createElement('button');
                clr.type = 'button';
                clr.className = 'text-xs font-medium text-gray-600 hover:text-gray-900 underline underline-offset-2 ml-1';
                clr.textContent = 'Clear all';
                clr.onclick = () => document.getElementById('clearFiltersBtn').click();
                wrap.appendChild(clr);
            }
            wrap.classList.toggle('hidden', chips.length === 0);
            wrap.classList.toggle('flex', chips.length > 0);
            updateSectionCounts();

            ['activeFilterCount', 'activeFilterCountMobile'].forEach(id => {
                const el = document.getElementById(id);
                if (!el) return;
                el.textContent = chips.length;
                el.classList.toggle('hidden', chips.length === 0);
                el.classList.toggle('inline-flex', chips.length > 0);
            });
        }

        // ── Initial load ──────────────────────────────────────────────────────────────
        fetchProducts(currentPage, true);


        // ═════════════════════════════════════════════════════════════════════════════
        // ADD TO CART MODAL
        // ═════════════════════════════════════════════════════════════════════════════
        let currentModalState = { productId: null, colorId: null, variantId: null };
        let modalPromoTimerInterval = null;

        // Simple product detection — kaparehong logic ng isSimpleProduct sa
        // mainproductview.php: single "Default" color na may single "Default"
        // variant lang (set ng specialist-insertproduct-handler.php kapag
        // "Simple product — no colors or sizes" ang checkbox na na-tick).
        // "Sizes only" product: isang color na "Default" pero maraming size
        function isSizesOnlyData(product) {
            return !!product && Array.isArray(product.colors)
                && product.colors.length === 1 && product.colors[0].colorname === 'Default'
                && !isSimpleProductData(product);
        }

        function isSimpleProductData(product) {
            return !!product
                && Array.isArray(product.colors)
                && product.colors.length === 1
                && product.colors[0].colorname === 'Default'
                && Array.isArray(product.colors[0].variants)
                && product.colors[0].variants.length === 1
                && product.colors[0].variants[0].sizename === 'Default';
        }

        function openAddToCart(productId) {
            const product = PRODUCT_DATA[productId];
            if (!product) return;

            currentModalState = { productId, colorId: null, variantId: null };

            document.getElementById('modalProductName').textContent = product.name;
            const imgWrap = document.getElementById('modalProductImage');
            imgWrap.innerHTML = product.image
                ? `<img src="${product.image}" alt="" class="w-full h-full object-cover" loading="lazy">`
                : `<i class="fa-regular fa-image text-2xl text-gray-300"></i>`;

            document.getElementById('modalProductPrice').textContent = '';
            document.getElementById('modalStockLabel').textContent = '';
            document.getElementById('modalSizeOptions').innerHTML =
                '<span class="text-xs text-gray-500">Select a color first</span>';
            document.getElementById('modalQtyInput').value = 1;
            document.getElementById('modalAddBtn').disabled = true;
            hideModalPromoTimer();

            // Simple product: itago ang Color/Size pickers, auto-select ang
            // hidden Default/Default variant — kagaya sa mainproductview.php.
            const simple = isSimpleProductData(product);
            const sizesOnly = isSizesOnlyData(product);
            document.getElementById('modalColorSection')?.classList.toggle('hidden', simple || sizesOnly);
            document.getElementById('modalSizeSection')?.classList.toggle('hidden', simple);

            const viewLink = document.getElementById('modalViewLink');
            if (viewLink) viewLink.href = <?= json_encode(BASE_URL . '/mainproductview?id=') ?> + encodeURIComponent(productId);

            renderColorOptions(product.colors);

            const modal = document.getElementById('addToCartModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.style.overflow = 'hidden';

            if (simple) {
                selectColor(product.colors[0].id);
                selectSize(product.colors[0].variants[0].id);
            } else if (sizesOnly) {
                selectColor(product.colors[0].id);
            }
        }

        function closeAddToCartModal() {
            const modal = document.getElementById('addToCartModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.style.overflow = '';
            hideModalPromoTimer();
        }

        function closeModalBackdrop(e) {
            if (e.target.id === 'addToCartModal') closeAddToCartModal();
        }

        function renderColorOptions(colors) {
            const wrap = document.getElementById('modalColorOptions');
            wrap.innerHTML = '';
            if (!colors || colors.length === 0) {
                wrap.innerHTML = '<span class="text-xs text-gray-400">No colors available</span>';
                return;
            }
            colors.forEach(color => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.textContent = color.colorname;
                btn.className = 'color-option h-9 px-3.5 text-sm rounded-md border border-gray-300 bg-white text-gray-800 hover:border-gray-900 transition';
                btn.dataset.colorId = color.id;
                btn.onclick = () => selectColor(color.id);
                wrap.appendChild(btn);
            });
        }

        function selectColor(colorId) {
            currentModalState.colorId = colorId;
            currentModalState.variantId = null;

            document.querySelectorAll('#modalColorOptions .color-option').forEach(btn => {
                const sel = parseInt(btn.dataset.colorId) === colorId;
                btn.classList.toggle('opt-selected', sel);
            });

            const product = PRODUCT_DATA[currentModalState.productId];
            const color = product.colors.find(c => c.id === colorId);
            const sizeWrap = document.getElementById('modalSizeOptions');
            sizeWrap.innerHTML = '';

            document.getElementById('modalProductPrice').textContent = '';
            document.getElementById('modalStockLabel').textContent = '';
            document.getElementById('modalAddBtn').disabled = true;
            hideModalPromoTimer(); // walang size pa — itago muna ang timer

            if (!color || color.variants.length === 0) {
                sizeWrap.innerHTML = '<span class="text-xs text-gray-500">No sizes available</span>';
                return;
            }
            color.variants.forEach(v => {
                const oos = v.stock <= 0;
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.textContent = v.sizename + (oos ? ' (Out of stock)' : '');
                btn.disabled = oos;
                btn.className = 'size-option h-9 px-3.5 text-sm rounded-md border border-gray-300 bg-white text-gray-800 hover:border-gray-900 transition disabled:opacity-40 disabled:line-through disabled:cursor-not-allowed disabled:hover:border-gray-300';
                btn.dataset.variantId = v.id;
                btn.onclick = () => selectSize(v.id);
                sizeWrap.appendChild(btn);
            });
        }

        function selectSize(variantId) {
            currentModalState.variantId = variantId;

            document.querySelectorAll('#modalSizeOptions .size-option').forEach(btn => {
                const sel = parseInt(btn.dataset.variantId) === variantId;
                btn.classList.toggle('opt-selected', sel);
            });

            const product = PRODUCT_DATA[currentModalState.productId];
            const color = product.colors.find(c => c.id === currentModalState.colorId);
            const variant = color.variants.find(v => v.id === variantId);
            if (!variant) return;

            const finalPrice = variant.discount > 0
                ? variant.price * (1 - variant.discount / 100)
                : variant.price;

            const priceLabel = document.getElementById('modalProductPrice');
            priceLabel.innerHTML = variant.discount > 0
                ? `<span class="text-red-600">₱${formatPrice(finalPrice)}</span> <span class="text-xs font-normal text-gray-400 line-through ml-1">₱${formatPrice(variant.price)}</span> <span class="text-[11px] font-bold text-white bg-red-600 px-1.5 py-0.5 rounded ml-1 align-middle">-${variant.discount}%</span>`
                : `₱${formatPrice(finalPrice)}`;

            // ─── Promo countdown — para sa EKSAKTONG napiling variant lang ───
            // Ito ang parehong resolution na ginagamit ng product page: kung
            // may date-based na promo talaga ang specific na color+size na
            // 'to (variant.promo_end), doon lang lalabas ang countdown.
            if (variant.discount > 0 && variant.promo_end) {
                showModalPromoTimer(variant.promo_end);
            } else {
                hideModalPromoTimer();
            }

            const qtyInput = document.getElementById('modalQtyInput');
            qtyInput.max = variant.stock > 0 ? variant.stock : 1;
            qtyInput.value = 1;

            const stockLabel = document.getElementById('modalStockLabel');
            if (variant.stock <= 0) {
                stockLabel.innerHTML = '<span class="text-red-500 font-medium"><i class="fa-solid fa-circle-exclamation mr-1"></i>Out of stock</span>';
            } else if (variant.stock <= LOW_STOCK_THRESHOLD) {
                stockLabel.innerHTML = `<span class="text-amber-600 font-medium"><i class="fa-solid fa-triangle-exclamation mr-1"></i>Only ${variant.stock} left in stock</span>`;
            } else {
                stockLabel.innerHTML = `<span class="text-green-600"><i class="fa-solid fa-circle-check mr-1"></i>${variant.stock} in stock</span>`;
            }
            document.getElementById('modalAddBtn').disabled = variant.stock <= 0;
        }

        // ─── Modal promo countdown helpers ─────────────────────────────────────
        function showModalPromoTimer(endDateStr) {
            const el = document.getElementById('modalPromoTimer');
            const textEl = document.getElementById('modalPromoTimerText');
            if (!el || !textEl) return;

            if (modalPromoTimerInterval) { clearInterval(modalPromoTimerInterval); modalPromoTimerInterval = null; }

            const end = new Date(endDateStr.replace(' ', 'T')).getTime();
            el.style.display = 'inline-flex';
            el.classList.remove('opacity-50');

            function tick() {
                const diff = end - Date.now();
                if (diff <= 0) {
                    el.classList.add('opacity-50');
                    textEl.textContent = 'Promo ended';
                    clearInterval(modalPromoTimerInterval);
                    return;
                }
                const d = Math.floor(diff / 86400000);
                const h = Math.floor((diff % 86400000) / 3600000);
                const m = Math.floor((diff % 3600000) / 60000);
                const s = Math.floor((diff % 60000) / 1000);
                textEl.textContent = d > 0
                    ? `${d}d ${h}h left`
                    : `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
            }
            tick();
            modalPromoTimerInterval = setInterval(tick, 1000);
        }

        function hideModalPromoTimer() {
            if (modalPromoTimerInterval) { clearInterval(modalPromoTimerInterval); modalPromoTimerInterval = null; }
            const el = document.getElementById('modalPromoTimer');
            if (el) el.style.display = 'none';
        }
        // ─────────────────────────────────────────────────────────────────────

        function modalChangeQty(delta) {
            const input = document.getElementById('modalQtyInput');
            const max = parseInt(input.max) || 99;
            input.value = Math.max(1, Math.min(parseInt(input.value || '1') + delta, max));
        }

        // Typed quantity: itinatama sa 1..stock pag-alis sa field
        (function () {
            const input = document.getElementById('modalQtyInput');
            if (!input) return;
            input.addEventListener('blur', () => {
                const max = parseInt(input.max) || 99;
                const v = parseInt(input.value, 10);
                input.value = isNaN(v) ? 1 : Math.max(1, Math.min(v, max));
            });
            input.addEventListener('keydown', e => {
                if (['e', 'E', '+', '-', '.', ','].includes(e.key)) e.preventDefault();
                if (e.key === 'Enter') { e.preventDefault(); input.blur(); }
            });
        })();

        async function confirmAddToCart() {
            const { productId, colorId, variantId } = currentModalState;
            if (!productId || !colorId || !variantId) {
                showToast('error', 'Please select a color and size.');
                return;
            }
            const qty = parseInt(document.getElementById('modalQtyInput').value) || 1;
            try {
                const fd = new FormData();
                fd.append('product_id', productId);
                fd.append('color_id', colorId);
                fd.append('variant_id', variantId);
                fd.append('qty', qty);

                const res = await fetch(ADD_TO_CART_URL, { method: 'POST', body: fd });
                const data = await res.json();

                if (data.ok) {
                    showToast(data.limit_reached ? 'warning' : 'success', data.msg || 'Added to cart!');
                    window.dispatchEvent(new CustomEvent('noblecart:changed'));
                    closeAddToCartModal();
                } else {
                    showToast('error', data.msg || 'Failed to add to cart.');
                    if (data.out_of_stock !== undefined) selectSize(variantId);
                }
            } catch {
                showToast('error', 'Something went wrong.');
            }
        }

        function formatPrice(num) {
            return parseFloat(num).toLocaleString('en-PH', { minimumFractionDigits: 2 });
        }

        function showToast(type, msg) {
            const toast = document.getElementById('toast');
            document.getElementById('toast-icon').innerHTML = type === 'success'
                ? '<i class="fa-solid fa-circle-check text-green-500"></i>'
                : type === 'warning'
                    ? '<i class="fa-solid fa-triangle-exclamation text-amber-500"></i>'
                    : '<i class="fa-solid fa-circle-exclamation text-red-500"></i>';
            document.getElementById('toast-msg').textContent = msg;

            toast.classList.remove('opacity-0', 'pointer-events-none', 'translate-y-2');
            toast.classList.add('opacity-100', 'translate-y-0');
            setTimeout(() => {
                toast.classList.add('opacity-0', 'pointer-events-none', 'translate-y-2');
                toast.classList.remove('opacity-100', 'translate-y-0');
            }, 3000);
        }

        // ── Save / Unsave (bookmark) — event delegation kasi AJAX-loaded ang cards ────
document.getElementById('products-area').addEventListener('click', function (e) {
    const btn = e.target.closest('.save-btn');
    if (!btn) return;
    e.preventDefault();
    e.stopPropagation();

    const icon = btn.querySelector('i');
    const productId = btn.dataset.productId;

    fetch(SAVE_PRODUCT_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'product_id=' + encodeURIComponent(productId)
    })
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                showToast('error', data.message || 'Something went wrong. Please try again.');
                return;
            }
            if (data.saved) {
                icon.classList.remove('fa-regular', 'text-gray-500');
                icon.classList.add('fa-solid', 'text-red-500');
            } else {
                icon.classList.remove('fa-solid', 'text-red-500');
                icon.classList.add('fa-regular', 'text-gray-500');
            }
        })
        .catch(() => showToast('error', 'Something went wrong. Please try again.'));
});
    </script>

</body>

</html>