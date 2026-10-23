<?php
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_PRODUCTSPECIALIST];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

$supplierId = intval($_GET['supplier_id'] ?? 0);
if (!$supplierId) {
    header('Location: ' . BASE_URL . '/ps-posupplier');
    exit;
}

// Fetch supplier info
$stmtSup = $conn->prepare("SELECT * FROM noblecompanysupplier WHERE id = ? LIMIT 1");
$stmtSup->bind_param("i", $supplierId);
$stmtSup->execute();
$supplier = $stmtSup->get_result()->fetch_assoc();
$stmtSup->close();

if (!$supplier) {
    header('Location: ' . BASE_URL . '/ps-posupplier');
    exit;
}

// Fetch all products with their color variants
$products = [];
$resP = $conn->query("
    SELECT p.id, p.name, p.imageproduct, p.category,
           COUNT(pc.id) AS color_count
    FROM nobleproduct p
    LEFT JOIN nobleproductcolor pc ON pc.product_id = p.id
    GROUP BY p.id
    ORDER BY p.name ASC
");
while ($row = $resP->fetch_assoc()) {
    $products[] = $row;
}

// Fetch existing links for this supplier
$linkedMap = [];
$stmtL = $conn->prepare("SELECT product_id, link_type FROM nobleproductsupplierlink WHERE supplier_id = ?");
$stmtL->bind_param("i", $supplierId);
$stmtL->execute();
$resL = $stmtL->get_result();
while ($row = $resL->fetch_assoc()) {
    $linkedMap[$row['product_id']] = $row['link_type'];
}
$stmtL->close();

$countPrimary = 0;
$countSecondary = 0;
foreach ($products as $p) {
    $l = $linkedMap[$p['id']] ?? null;
    if ($l === 'primary') $countPrimary++;
    elseif ($l === 'secondary') $countSecondary++;
}
$countNone = count($products) - $countPrimary - $countSecondary;

$categoriesList = array_values(array_unique(array_filter(array_map(fn($p) => $p['category'] ?? '', $products))));
sort($categoriesList, SORT_NATURAL | SORT_FLAG_CASE);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Products</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <style>
        .nh-scroll { scrollbar-width: thin; scrollbar-color: #d1d5db transparent; }
        .nh-scroll::-webkit-scrollbar { width: 8px; }
        .nh-scroll::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 9999px; }

        /* Segmented link control */
        .nh-seg { display: inline-flex; border: 1px solid #d1d5db; border-radius: 6px; overflow: hidden; background: #fff; }
        .nh-seg button { height: 30px; padding: 0 12px; font-size: 12px; font-weight: 600; color: #4b5563; transition: background-color .15s, color .15s; }
        .nh-seg button + button { border-left: 1px solid #d1d5db; }
        .nh-seg button:hover { background: #f9fafb; color: #111827; }
        .nh-seg button.is-primary { background: #f97316; color: #fff; }
        .nh-seg button.is-secondary { background: #111827; color: #fff; }
        .nh-seg button:disabled { opacity: .6; cursor: wait; }

        .nh-tab.is-active { background: #111827; color: #fff; border-color: #111827; }
        .nh-tab.is-active .nh-tab-count { background: rgba(255, 255, 255, .2); color: #fff; }
    </style>
</head>

<body class="bg-gray-50 text-gray-900">
    <?php include ROOT_PATH . '/admin/navigation/navbar.php'; ?>

    <div class="ml-60 min-h-screen bg-gray-50 px-8 py-7">

        <!-- Header -->
        <nav class="flex items-center gap-1.5 text-[11px] text-gray-500 mb-3" aria-label="Breadcrumb">
            <span>Product Specialist</span>
            <span class="text-gray-300">/</span>
            <a href="<?= BASE_URL ?>/ps-posupplier" class="hover:text-gray-900">Suppliers</a>
            <span class="text-gray-300">/</span>
            <span class="text-gray-900 font-medium">Linked Products</span>
        </nav>

        <div class="bg-white border border-gray-200 rounded-lg p-4 mb-4 flex items-center gap-4">
            <a href="<?= BASE_URL ?>/ps-posupplier" title="Back to suppliers"
                class="w-9 h-9 shrink-0 rounded-md border border-gray-300 flex items-center justify-center text-gray-500 hover:bg-gray-50 hover:text-gray-900">
                <i class="fa-solid fa-arrow-left text-sm"></i>
            </a>
            <div class="w-12 h-12 shrink-0 rounded-md border border-gray-200 bg-white flex items-center justify-center overflow-hidden">
                <?php if (!empty($supplier['suplogoimagecompany'])): ?>
                    <img src="<?= BASE_URL ?>/uploads/<?= htmlspecialchars($supplier['suplogoimagecompany']) ?>"
                        class="w-full h-full object-contain p-0.5" alt="">
                <?php else: ?>
                    <i class="fa-solid fa-building text-gray-300 text-lg"></i>
                <?php endif; ?>
            </div>
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                    <h1 class="text-lg font-bold tracking-tight text-gray-900 truncate"><?= htmlspecialchars($supplier['supname']) ?></h1>
                    <?php if (!empty($supplier['suptype'])): ?>
                        <span class="shrink-0 px-2 py-0.5 rounded border border-gray-200 bg-gray-50 text-[11px] font-medium text-gray-700">
                            <?= htmlspecialchars($supplier['suptype']) ?>
                        </span>
                    <?php endif; ?>
                </div>
                <p class="text-xs text-gray-500 mt-0.5 truncate">
                    Link products to this supplier as their <strong class="font-semibold text-gray-700">Primary</strong>
                    or <strong class="font-semibold text-gray-700">Secondary</strong> source.
                    <?php if (!empty($supplier['suppersonname'])): ?>
                        <span class="text-gray-300 mx-1">·</span>Contact: <?= htmlspecialchars($supplier['suppersonname']) ?>
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <?php if (empty($products)): ?>
            <div class="bg-white rounded-lg border border-gray-200 p-12 text-center">
                <span class="mx-auto w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center">
                    <i class="fa-solid fa-box-open text-gray-400"></i>
                </span>
                <p class="mt-3 text-sm font-semibold text-gray-900">No products found</p>
                <p class="text-xs text-gray-500 mt-1">Add products first, then come back to link them.</p>
            </div>
        <?php else: ?>

            <!-- Toolbar -->
            <div class="bg-white rounded-lg border border-gray-200 p-3 mb-4 flex flex-wrap items-center gap-3">
                <div class="flex items-center gap-1.5" role="tablist">
                    <?php foreach ([
                        ['all', 'All', count($products)],
                        ['primary', 'Primary', $countPrimary],
                        ['secondary', 'Secondary', $countSecondary],
                        ['none', 'Not linked', $countNone],
                    ] as [$key, $label, $cnt]): ?>
                        <button type="button" data-tab="<?= $key ?>"
                            class="nh-tab h-8 px-3 rounded-md border border-gray-300 text-xs font-semibold text-gray-700 hover:bg-gray-50 inline-flex items-center gap-1.5 <?= $key === 'all' ? 'is-active' : '' ?>">
                            <?php if ($key === 'primary'): ?><span class="w-2 h-2 rounded-full bg-orange-500"></span><?php endif; ?>
                            <?php if ($key === 'secondary'): ?><span class="w-2 h-2 rounded-full bg-gray-900 ring-1 ring-white/60"></span><?php endif; ?>
                            <?= $label ?>
                            <span class="nh-tab-count min-w-[20px] h-[18px] px-1 rounded bg-gray-100 text-gray-600 text-[10px] font-bold inline-flex items-center justify-center"
                                id="count-<?= $key ?>"><?= $cnt ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>

                <div class="relative flex-1 min-w-[220px] max-w-sm ml-auto">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-xs text-gray-400"></i>
                    <input type="text" id="search-input" placeholder="Search products…" oninput="filterProducts()"
                        class="w-full h-9 pl-8 pr-3 text-sm border border-gray-300 rounded-md focus:outline-none focus:border-gray-900">
                </div>
                <?php if (!empty($categoriesList)): ?>
                    <select id="category-filter" onchange="filterProducts()"
                        class="h-9 px-3 text-sm border border-gray-300 rounded-md bg-white focus:outline-none focus:border-gray-900 max-w-[200px]">
                        <option value="">All categories</option>
                        <?php foreach ($categoriesList as $c): ?>
                            <option value="<?= htmlspecialchars(mb_strtolower($c)) ?>"><?= htmlspecialchars($c) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </div>

            <!-- Product table -->
            <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
                <div class="nh-scroll overflow-auto" style="max-height: calc(100vh - 300px);">
                    <table class="w-full text-sm">
                        <thead class="sticky top-0 z-10">
                            <tr class="bg-gray-50 text-[11px] font-semibold text-gray-500 border-b border-gray-200">
                                <th class="text-left px-4 py-2.5">Product</th>
                                <th class="text-left px-4 py-2.5">Category</th>
                                <th class="text-center px-4 py-2.5">Colors</th>
                                <th class="text-left px-4 py-2.5">Status</th>
                                <th class="text-right px-4 py-2.5">Link as</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100" id="product-grid">
                            <?php foreach ($products as $p):
                                $pid = (int) $p['id'];
                                $linked = $linkedMap[$p['id']] ?? null;
                                $isPrimary = $linked === 'primary';
                                $isSecond = $linked === 'secondary';
                                ?>
                                <tr class="product-card hover:bg-gray-50/70 <?= $isPrimary ? 'bg-orange-50/40' : '' ?>" id="card-<?= $pid ?>"
                                    data-name="<?= htmlspecialchars(mb_strtolower($p['name'])) ?>"
                                    data-category="<?= htmlspecialchars(mb_strtolower($p['category'] ?? '')) ?>"
                                    data-link="<?= $linked ?: 'none' ?>">
                                    <td class="px-4 py-2.5">
                                        <div class="flex items-center gap-3 min-w-0">
                                            <div class="w-10 h-10 rounded-md overflow-hidden flex items-center justify-center shrink-0 border border-gray-200 bg-white">
                                                <?php if (!empty($p['imageproduct'])): ?>
                                                    <img src="<?= BASE_URL ?>/uploads/<?= htmlspecialchars($p['imageproduct']) ?>"
                                                        class="w-full h-full object-contain" alt="" loading="lazy">
                                                <?php else: ?>
                                                    <i class="fa-solid fa-image text-gray-300"></i>
                                                <?php endif; ?>
                                            </div>
                                            <p class="font-medium text-gray-900 truncate max-w-[320px]" title="<?= htmlspecialchars($p['name']) ?>">
                                                <?= htmlspecialchars($p['name']) ?>
                                            </p>
                                        </div>
                                    </td>
                                    <td class="px-4 py-2.5 text-xs text-gray-600"><?= htmlspecialchars($p['category'] ?: '—') ?></td>
                                    <td class="px-4 py-2.5 text-center text-xs text-gray-600"><?= (int) $p['color_count'] ?></td>
                                    <td class="px-4 py-2.5">
                                        <span id="badge-<?= $pid ?>"><?= '' ?></span>
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <div class="nh-seg">
                                                <button type="button" onclick="setLink(<?= $pid ?>, 'primary')" id="btn-primary-<?= $pid ?>"
                                                    class="<?= $isPrimary ? 'is-primary' : '' ?>">Primary</button>
                                                <button type="button" onclick="setLink(<?= $pid ?>, 'secondary')" id="btn-secondary-<?= $pid ?>"
                                                    class="<?= $isSecond ? 'is-secondary' : '' ?>">Secondary</button>
                                            </div>
                                            <button type="button" onclick="removeLink(<?= $pid ?>)" id="btn-remove-<?= $pid ?>" title="Remove link"
                                                class="w-[30px] h-[30px] rounded-md border border-gray-300 items-center justify-center text-gray-400 hover:bg-red-50 hover:border-red-300 hover:text-red-600"
                                                style="display: <?= $linked ? 'inline-flex' : 'none' ?>;">
                                                <i class="fa-solid fa-xmark text-xs"></i>
                                            </button>
                                            <span id="btn-spacer-<?= $pid ?>" class="w-[30px]" style="display: <?= $linked ? 'none' : 'inline-block' ?>;"></span>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div id="no-results" class="hidden p-10 text-center">
                        <p class="text-sm font-semibold text-gray-900">No products match</p>
                        <p class="text-xs text-gray-500 mt-1">Try a different keyword, category or tab.</p>
                    </div>
                </div>
                <div class="px-4 py-2.5 border-t border-gray-100 bg-gray-50 text-[11px] text-gray-500 flex items-center justify-between">
                    <span id="results-label"><?= count($products) ?> products</span>
                    <span>Changes are saved automatically.</span>
                </div>
            </div>
        <?php endif; ?>

    </div>

    <!-- Toast -->
    <div id="nhToast" class="fixed bottom-5 right-5 z-[80] hidden items-center gap-2 px-4 py-3 rounded-md shadow-lg text-sm text-white bg-gray-900">
        <i id="nhToastIcon" class="fa-solid fa-circle-info"></i>
        <span id="nhToastText"></span>
    </div>

    <script>
        const supplierId = <?= $supplierId ?>;
        const baseUrl = '<?= BASE_URL ?>';
        let activeTab = 'all';

        let toastTimer = null;
        function toast(msg, type = 'error') {
            const el = document.getElementById('nhToast');
            el.classList.remove('bg-gray-900', 'bg-red-600', 'bg-green-600');
            el.classList.add(type === 'success' ? 'bg-green-600' : type === 'error' ? 'bg-red-600' : 'bg-gray-900');
            document.getElementById('nhToastIcon').className = 'fa-solid ' +
                (type === 'success' ? 'fa-circle-check' : type === 'error' ? 'fa-circle-exclamation' : 'fa-circle-info');
            document.getElementById('nhToastText').textContent = msg;
            el.classList.remove('hidden'); el.classList.add('flex');
            clearTimeout(toastTimer);
            toastTimer = setTimeout(() => { el.classList.add('hidden'); el.classList.remove('flex'); }, 2500);
        }

        const BADGES = {
            primary: '<span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded bg-orange-50 border border-orange-200 text-[11px] font-semibold text-orange-700"><span class="w-1.5 h-1.5 rounded-full bg-orange-500"></span>Primary</span>',
            secondary: '<span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded bg-gray-100 border border-gray-300 text-[11px] font-semibold text-gray-800"><span class="w-1.5 h-1.5 rounded-full bg-gray-900"></span>Secondary</span>',
            none: '<span class="text-[11px] text-gray-400">Not linked</span>',
        };

        // Render one row's state (badge, buttons, highlight)
        function renderRow(productId, state) {
            const card = document.getElementById('card-' + productId);
            card.dataset.link = state;
            card.classList.toggle('bg-orange-50/40', state === 'primary');
            document.getElementById('badge-' + productId).innerHTML = BADGES[state];
            document.getElementById('btn-primary-' + productId).classList.toggle('is-primary', state === 'primary');
            document.getElementById('btn-secondary-' + productId).classList.toggle('is-secondary', state === 'secondary');
            document.getElementById('btn-remove-' + productId).style.display = state === 'none' ? 'none' : 'inline-flex';
            document.getElementById('btn-spacer-' + productId).style.display = state === 'none' ? 'inline-block' : 'none';
        }

        function updateCounts() {
            const cards = document.querySelectorAll('.product-card');
            const c = { all: cards.length, primary: 0, secondary: 0, none: 0 };
            cards.forEach(card => c[card.dataset.link]++);
            Object.keys(c).forEach(k => { const el = document.getElementById('count-' + k); if (el) el.textContent = c[k]; });
        }

        function setRowBusy(productId, busy) {
            ['btn-primary-', 'btn-secondary-', 'btn-remove-'].forEach(p => {
                const b = document.getElementById(p + productId);
                if (b) b.disabled = busy;
            });
        }

        function saveLink(productId, type) {
            setRowBusy(productId, true);
            return fetch(baseUrl + '/ps-backendsupplierlink-save', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `product_id=${productId}&supplier_id=${supplierId}&link_type=${type}`
            })
                .then(r => r.json())
                .then(res => {
                    setRowBusy(productId, false);
                    if (!res.success) { toast(res.error ?? 'Error'); return false; }
                    return true;
                })
                .catch(() => { setRowBusy(productId, false); toast('Something went wrong.'); return false; });
        }

        function setLink(productId, type) {
            const current = document.getElementById('card-' + productId).dataset.link;
            if (current === type) return;
            saveLink(productId, type).then(ok => {
                if (!ok) return;
                renderRow(productId, type);
                updateCounts();
                filterProducts();
                toast(type === 'primary' ? 'Set as primary supplier.' : 'Set as secondary supplier.', 'success');
            });
        }

        function removeLink(productId) {
            saveLink(productId, 'remove').then(ok => {
                if (!ok) return;
                renderRow(productId, 'none');
                updateCounts();
                filterProducts();
                toast('Link removed.', 'info');
            });
        }

        function filterProducts() {
            const q = (document.getElementById('search-input')?.value ?? '').toLowerCase().trim();
            const cat = document.getElementById('category-filter')?.value ?? '';
            const cards = document.querySelectorAll('.product-card');
            let shown = 0;
            cards.forEach(card => {
                const name = card.dataset.name ?? '';
                const category = card.dataset.category ?? '';
                const ok = (!q || name.includes(q) || category.includes(q))
                    && (!cat || category === cat)
                    && (activeTab === 'all' || card.dataset.link === activeTab);
                card.style.display = ok ? '' : 'none';
                if (ok) shown++;
            });
            const label = document.getElementById('results-label');
            if (label) label.textContent = shown === cards.length ? `${cards.length} products` : `${shown} of ${cards.length} products`;
            const empty = document.getElementById('no-results');
            if (empty) empty.classList.toggle('hidden', shown > 0);
        }

        document.querySelectorAll('.nh-tab').forEach(tab => {
            tab.addEventListener('click', () => {
                activeTab = tab.dataset.tab;
                document.querySelectorAll('.nh-tab').forEach(t => t.classList.toggle('is-active', t === tab));
                filterProducts();
            });
        });

        // Initial badges
        document.querySelectorAll('.product-card').forEach(card => {
            const id = card.id.replace('card-', '');
            document.getElementById('badge-' + id).innerHTML = BADGES[card.dataset.link] ?? BADGES.none;
        });
    </script>

</body>

</html>