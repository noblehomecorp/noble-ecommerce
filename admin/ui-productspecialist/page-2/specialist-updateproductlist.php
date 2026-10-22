<?php
// specialist-updateproductlist.php

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_PRODUCTSPECIALIST];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

// ── Toggle "Most Popular" flag (AJAX) ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_popular'])) {
    header('Content-Type: application/json');

    $productId = intval($_POST['product_id'] ?? 0);
    $isPopular = intval($_POST['is_popular'] ?? 0) ? 1 : 0;

    if (!$productId) {
        echo json_encode(['ok' => false, 'msg' => 'Invalid product.']);
        exit;
    }

    $stmt = $conn->prepare("UPDATE nobleproduct SET is_popular = ? WHERE id = ?");
    $stmt->bind_param("ii", $isPopular, $productId);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['ok' => true]);
    exit;
}

// ── Load all products with color + variant counts ──────────────────────────
$currentUserId = $_SESSION['account_id'] ?? 0;

$products = [];
$stmt = $conn->prepare("
    SELECT
        p.id,
        p.name,
        p.imageproduct,
        p.category,
        p.description,
        creator.name AS created_by_name,
        p.is_popular,
        COUNT(DISTINCT c.id)  AS color_count,
        COUNT(DISTINCT v.id)  AS variant_count
    FROM nobleproduct p
    LEFT JOIN nobleproductcolor   c ON c.product_id = p.id
    LEFT JOIN nobleproductvariant v ON v.color_id   = c.id
    LEFT JOIN noblerole creator      ON creator.id  = p.created_by
    WHERE p.created_by = ?
    GROUP BY p.id
    ORDER BY p.name ASC
");
$stmt->bind_param("i", $currentUserId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc())
    $products[] = $row;
$stmt->close();

// ── Promo/discount timer status per product ─────────────────────────────────
// Para sa bawat product, tinitignan natin ang lahat ng promo rows nito sa
// nobleproductpromo at pinipili ang "pinaka-relevant": active muna (may
// pinakamalapit na end_date), kung wala, upcoming (may pinakamalapit na
// start_date). Expired na promos ay hindi na kasama dito.
$promoStatusByProduct = []; // product_id => ['status','discount_percent','start_date','end_date']
if (!empty($products)) {
    $productIds = array_column($products, 'id');
    $idPh = implode(',', array_fill(0, count($productIds), '?'));
    $idTypes = str_repeat('i', count($productIds));

    $promoStmt = $conn->prepare("
        SELECT product_id, discount_percent, start_date, end_date,
               CASE
                 WHEN NOW() BETWEEN start_date AND end_date THEN 'active'
                 WHEN NOW() < start_date THEN 'upcoming'
                 ELSE 'expired'
               END AS status
        FROM nobleproductpromo
        WHERE product_id IN ($idPh)
    ");
    $promoStmt->bind_param($idTypes, ...$productIds);
    $promoStmt->execute();
    $promoRows = $promoStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $promoStmt->close();

    foreach ($promoRows as $row) {
        if ($row['status'] === 'expired') {
            continue; // wala nang silbi ipakita sa listahan
        }

        $pid = $row['product_id'];
        $existing = $promoStatusByProduct[$pid] ?? null;
        $isBetter = false;

        if (!$existing) {
            $isBetter = true;
        } elseif ($row['status'] === 'active' && $existing['status'] !== 'active') {
            // active laging mas prioritized kaysa upcoming
            $isBetter = true;
        } elseif ($row['status'] === $existing['status']) {
            // parehong status — piliin yung pinaka-agad na mangyayari
            $compareField = $row['status'] === 'active' ? 'end_date' : 'start_date';
            if (strtotime($row[$compareField]) < strtotime($existing[$compareField])) {
                $isBetter = true;
            }
        }

        if ($isBetter) {
            $promoStatusByProduct[$pid] = $row;
        }
    }
}

// ── Distinct categories for filter dropdown ────────────────────────────────
$categories = [];
$catRes = $conn->query("SELECT DISTINCT category FROM nobleproduct WHERE category != '' ORDER BY category ASC");
while ($row = $catRes->fetch_assoc())
    $categories[] = $row['category'];

$uploadUrl = BASE_URL . '/uploads/'; // public URL prefix for product images
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products — NobleHome</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
</head>

<body class="bg-gray-50 text-gray-900">
    <?php include ROOT_PATH . '/admin/navigation/navbar.php'; ?>

    <div class="ml-60 min-h-screen bg-gray-50 px-8 py-7">

        <!-- Header -->
        <div class="flex items-end justify-between gap-4 mb-6">
            <div>
                <nav class="flex items-center gap-1.5 text-[11px] text-gray-500 mb-1.5" aria-label="Breadcrumb">
                    <span>Product Specialist</span>
                    <span class="text-gray-300">/</span>
                    <span class="text-gray-900 font-medium">Products</span>
                </nav>
                <h1 class="text-xl font-bold tracking-tight text-gray-900">Products</h1>
                <p class="text-xs text-gray-500 mt-1">
                    <?= count($products) ?> product<?= count($products) !== 1 ? 's' : '' ?> total
                </p>
            </div>
            <a href="<?= BASE_URL ?>/ps-insertproduct"
                class="flex items-center gap-2 h-10 px-4 bg-orange-600 hover:bg-orange-700 text-white text-xs font-semibold rounded-md transition">
                <i class="fa-solid fa-plus text-xs"></i> Add Product
            </a>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-lg border border-gray-200 p-3 mb-4">
            <div class="flex flex-wrap items-center gap-3">

                <!-- Search -->
                <div class="relative flex-1 min-w-56">
                    <i
                        class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs pointer-events-none"></i>
                    <input type="text" id="search-input" placeholder="Search by name or description…"
                        class="w-full pl-8 pr-3 h-10 text-xs border border-gray-300 rounded-md bg-white focus:outline-none focus:border-gray-900 focus:ring-2 focus:ring-gray-900/5 transition">
                </div>

                <!-- Category filter -->
                <select id="category-filter"
                    class="px-3 h-10 text-xs border border-gray-300 rounded-md bg-white focus:outline-none focus:border-gray-900 transition min-w-44">
                    <option value="">All categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                    <?php endforeach; ?>
                </select>

                <!-- Clear -->
                <button type="button" id="clear-filters"
                    class="px-3 h-10 text-xs font-medium text-gray-600 hover:text-gray-900 border border-gray-300 rounded-md bg-white hover:bg-gray-50 transition hidden">
                    <i class="fa-solid fa-xmark mr-1"></i> Clear
                </button>

                <!-- Results count -->
                <span id="results-label" class="text-xs text-gray-400 ml-auto"></span>
            </div>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
            <table class="w-full text-xs" id="products-table">
                <thead>
                    <tr
                        class="border-b border-gray-200 bg-gray-50 text-[11px] font-semibold text-gray-500">
                        <th class="text-left px-5 py-3 w-12 font-semibold">#</th>
                        <th class="text-left px-5 py-3">Product</th>
                        <th class="text-left px-5 py-3">Category</th>
                        <th class="text-center px-5 py-3">Colors</th>
                        <th class="text-center px-5 py-3">Variants</th>
                        <th class="text-left px-5 py-3">Added by</th>
                        <th class="text-center px-5 py-3">Popular</th>
                        <th class="text-center px-5 py-3">Promo</th>
                        <th class="text-right px-5 py-3">Action</th>
                    </tr>
                </thead>
                <tbody id="products-tbody">
                    <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="9" class="px-5 py-12 text-center text-gray-400 text-sm">
                                <i class="fa-solid fa-box-open text-3xl mb-3 block text-gray-300"></i>
                                No products yet. <a href="<?= BASE_URL ?>/ps-insertproduct"
                                    class="text-orange-600 font-semibold hover:underline">Add one</a>.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($products as $i => $p): ?>
                            <tr class="product-row border-b border-gray-100 last:border-0 hover:bg-gray-50 transition group"
                                data-name="<?= htmlspecialchars(strtolower($p['name'])) ?>"
                                data-desc="<?= htmlspecialchars(strtolower($p['description'] ?? '')) ?>"
                                data-category="<?= htmlspecialchars($p['category']) ?>">

                                <!-- Row number -->
                                <td class="px-5 py-3.5 text-gray-400 text-xs"><?= $i + 1 ?></td>

                                <!-- Name + image + description -->
                                <td class="px-5 py-3.5">
                                    <div class="flex items-center gap-3">
                                        <?php if (!empty($p['imageproduct'])): ?>
                                            <img src="<?= $uploadUrl . htmlspecialchars($p['imageproduct']) ?>"
                                                alt="<?= htmlspecialchars($p['name']) ?>"
                                                class="w-11 h-11 rounded-md object-cover border border-gray-200 shrink-0">
                                        <?php else: ?>
                                            <div
                                                class="w-11 h-11 rounded-md bg-gray-100 flex items-center justify-center shrink-0">
                                                <i class="fa-solid fa-image text-gray-400 text-sm"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <p class="font-semibold text-gray-900 leading-snug">
                                                <?= htmlspecialchars($p['name']) ?></p>
                                            <?php if (!empty($p['description'])): ?>
                                                <p class="text-xs text-gray-400 mt-0.5 line-clamp-1 max-w-xs">
                                                    <?= htmlspecialchars($p['description']) ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>

                                <!-- Category -->
                                <td class="px-5 py-3.5">
                                    <?php if (!empty($p['category'])): ?>
                                        <span
                                            class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-gray-100 text-gray-700">
                                            <?= htmlspecialchars($p['category']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-gray-300 text-xs italic">—</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Colors -->
                                <td class="px-5 py-3.5 text-center">
                                    <span
                                        class="inline-flex items-center justify-center w-7 h-7 rounded-full text-xs font-bold
                                         <?= $p['color_count'] > 0 ? 'bg-gray-100 text-gray-900' : 'bg-gray-50 text-gray-400' ?>">
                                        <?= $p['color_count'] ?>
                                    </span>
                                </td>

                                <!-- Variants -->
                                <td class="px-5 py-3.5 text-center">
                                    <span
                                        class="inline-flex items-center justify-center w-7 h-7 rounded-full text-xs font-bold
                                         <?= $p['variant_count'] > 0 ? 'bg-gray-100 text-gray-900' : 'bg-gray-50 text-gray-400' ?>">
                                        <?= $p['variant_count'] ?>
                                    </span>
                                </td>

                                <!-- Added by -->
                                <td class="px-5 py-3.5">
                                    <?php if (!empty($p['created_by_name'])): ?>
                                        <span class="text-xs text-gray-500"><?= htmlspecialchars($p['created_by_name']) ?></span>
                                    <?php else: ?>
                                        <span class="text-gray-300 text-xs italic">Unknown</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Popular toggle -->
                                <td class="px-5 py-3.5 text-center">
                                    <label class="relative inline-block w-9 h-5 cursor-pointer">
                                        <input type="checkbox" class="sr-only peer popular-toggle"
                                            data-product-id="<?= $p['id'] ?>"
                                            <?= $p['is_popular'] ? 'checked' : '' ?>>
                                        <div
                                            class="w-9 h-5 bg-gray-200 rounded-full peer-checked:bg-orange-600 transition-colors duration-200">
                                        </div>
                                        <div
                                            class="absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform duration-200 peer-checked:translate-x-4">
                                        </div>
                                    </label>
                                </td>

                                <!-- Promo / Discount Timer status -->
                                <td class="px-5 py-3.5 text-center">
                                    <?php $promo = $promoStatusByProduct[$p['id']] ?? null; ?>
                                    <?php if ($promo && $promo['status'] === 'active'): ?>
                                        <?php $discountLabel = rtrim(rtrim($promo['discount_percent'], '0'), '.'); ?>
                                        <a href="<?= BASE_URL ?>/ps-promotiontimer"
                                            class="inline-flex flex-col items-center gap-0.5" title="View promo timer">
                                            <span
                                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold bg-red-50 text-red-600 border border-red-100">
                                                <i class="fa-solid fa-bolt"></i> Active -<?= $discountLabel ?>%
                                            </span>
                                            <span class="promo-countdown text-[10px] text-red-600 font-medium"
                                                data-mode="end" data-end="<?= htmlspecialchars($promo['end_date']) ?>">
                                                &nbsp;
                                            </span>
                                        </a>
                                    <?php elseif ($promo && $promo['status'] === 'upcoming'): ?>
                                        <?php $discountLabel = rtrim(rtrim($promo['discount_percent'], '0'), '.'); ?>
                                        <a href="<?= BASE_URL ?>/ps-promotiontimer"
                                            class="inline-flex flex-col items-center gap-0.5" title="View promo timer">
                                            <span
                                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                                <i class="fa-solid fa-clock"></i> Upcoming -<?= $discountLabel ?>%
                                            </span>
                                            <span class="promo-countdown text-[10px] text-amber-700 font-medium"
                                                data-mode="start" data-start="<?= htmlspecialchars($promo['start_date']) ?>">
                                                &nbsp;
                                            </span>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-gray-300 text-xs italic">—</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Action -->
                                <td class="px-5 py-3.5 text-right">
                                    <a href="<?= BASE_URL ?>/ps-updateproductlist?id=<?= $p['id'] ?>" class="inline-flex items-center gap-1.5 h-8 px-3 text-xs font-semibold
                                      text-gray-800 bg-white hover:bg-gray-50 border border-gray-300
                                      rounded-md transition">
                                        <i class="fa-solid fa-pen-to-square text-[10px]"></i> Edit
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Empty-filter state -->
            <div id="no-results" class="hidden px-5 py-12 text-center text-gray-400 text-sm">
                <i class="fa-solid fa-magnifying-glass text-3xl mb-3 block text-gray-300"></i>
                No products match your search.
            </div>
        </div>

    </div>

    <script>
        const searchInput = document.getElementById('search-input');
        const categoryFilter = document.getElementById('category-filter');
        const clearBtn = document.getElementById('clear-filters');
        const resultsLabel = document.getElementById('results-label');
        const noResults = document.getElementById('no-results');
        const rows = document.querySelectorAll('.product-row');

        function filterTable() {
            const q = searchInput.value.trim().toLowerCase();
            const cat = categoryFilter.value;
            let visible = 0;

            rows.forEach(row => {
                const nameMatch = row.dataset.name.includes(q) || row.dataset.desc.includes(q);
                const catMatch = !cat || row.dataset.category === cat;
                const show = nameMatch && catMatch;
                row.classList.toggle('hidden', !show);
                if (show) visible++;
            });

            noResults.classList.toggle('hidden', visible > 0);
            resultsLabel.textContent = q || cat
                ? `${visible} of ${rows.length} shown`
                : '';
            clearBtn.classList.toggle('hidden', !q && !cat);
        }

        searchInput.addEventListener('input', filterTable);
        categoryFilter.addEventListener('change', filterTable);
        clearBtn.addEventListener('click', () => {
            searchInput.value = '';
            categoryFilter.value = '';
            filterTable();
        });

        // ── Popular toggle (AJAX) ────────────────────────────────────────────
        document.querySelectorAll('.popular-toggle').forEach(toggle => {
            toggle.addEventListener('change', async function () {
                const productId = this.dataset.productId;
                const isPopular = this.checked ? 1 : 0;

                try {
                    const fd = new FormData();
                    fd.append('toggle_popular', '1');
                    fd.append('product_id', productId);
                    fd.append('is_popular', isPopular);

                    const res = await fetch(window.location.pathname + window.location.search, {
                        method: 'POST',
                        body: fd
                    });
                    const data = await res.json();

                    if (!data.ok) {
                        // revert on failure
                        this.checked = !this.checked;
                        alert(data.msg || 'Failed to update.');
                    }
                } catch (e) {
                    this.checked = !this.checked;
                    alert('Something went wrong.');
                }
            });
        });

        // ── Promo countdown ticker (Active → ends in…, Upcoming → starts in…) ──
        function formatPromoCountdown(ms) {
            if (ms <= 0) return null; // tapos na / nagsimula na — hayaan ang page mag-refresh sa susunod na load
            const totalSec = Math.floor(ms / 1000);
            const d = Math.floor(totalSec / 86400);
            const h = Math.floor((totalSec % 86400) / 3600);
            const m = Math.floor((totalSec % 3600) / 60);
            const s = totalSec % 60;
            if (d > 0) return `${d}d ${h}h left`;
            if (h > 0) return `${h}h ${m}m left`;
            if (m > 0) return `${m}m ${s}s left`;
            return `${s}s left`;
        }

        function tickPromoCountdowns() {
            const now = Date.now();
            document.querySelectorAll('.promo-countdown').forEach(el => {
                const mode = el.dataset.mode;
                const targetRaw = mode === 'start' ? el.dataset.start : el.dataset.end;
                if (!targetRaw) return;
                const target = new Date(targetRaw).getTime();
                const diff = target - now;
                const label = formatPromoCountdown(diff);
                el.textContent = label
                    ? (mode === 'start' ? `starts in ${label}` : `ends in ${label}`)
                    : '\u00A0';
            });
        }
        tickPromoCountdowns();
        setInterval(tickPromoCountdowns, 1000);
    </script>
</body>

</html>