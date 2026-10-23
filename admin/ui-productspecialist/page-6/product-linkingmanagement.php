<?php
// product-linkingmanagement.php
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_PRODUCTSPECIALIST];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

// Fetch all products
$products = $conn->query("SELECT id, name, imageproduct FROM nobleproduct ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

// Fetch all categories with their subcategories
$catResult = $conn->query("
    SELECT c.id as cat_id, c.name as cat_name,
           s.id as sub_id, s.name as sub_name, s.image as sub_image
    FROM noblecategory c
    LEFT JOIN noblesubcategory s ON s.category_id = c.id
    ORDER BY c.name, s.name
");
$categories = [];
while ($row = $catResult->fetch_assoc()) {
    $cid = $row['cat_id'];
    if (!isset($categories[$cid])) {
        $categories[$cid] = ['name' => $row['cat_name'], 'subs' => []];
    }
    if ($row['sub_id']) {
        $categories[$cid]['subs'][] = ['id' => $row['sub_id'], 'name' => $row['sub_name'], 'image' => $row['sub_image']];
    }
}

// Fetch all existing links, and build lookups
$linkResult = $conn->query("SELECT product_id, subcategory_id FROM nobleproduct_subcategory");
$productSubIds = []; // product_id => [subcategory_id, ...]
foreach ($products as $p) {
    $productSubIds[$p['id']] = [];
}
$subNameLookup = [];
foreach ($categories as $cat) {
    foreach ($cat['subs'] as $sub) {
        $subNameLookup[$sub['id']] = $sub['name'];
    }
}
while ($row = $linkResult->fetch_assoc()) {
    if (isset($productSubIds[$row['product_id']])) {
        $productSubIds[$row['product_id']][] = (int) $row['subcategory_id'];
    }
}

$unlinkedCount = count(array_filter($productSubIds, fn($s) => empty($s)));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Linking — NobleHome</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <style>
        .nh-thin-scroll { scrollbar-width: thin; scrollbar-color: #d1d5db transparent; }
        .nh-thin-scroll::-webkit-scrollbar { width: 6px; }
        .nh-thin-scroll::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 9999px; }
        .nh-clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    </style>
</head>

<body class="bg-gray-50 text-gray-900">
    <?php include ROOT_PATH . '/admin/navigation/navbar.php'; ?>

    <div class="ml-60 min-h-screen bg-gray-50 px-8 py-7 pb-28">

        <!-- Header -->
        <div class="flex items-end justify-between gap-4 flex-wrap mb-6">
            <div>
                <nav class="flex items-center gap-1.5 text-[11px] text-gray-500 mb-1.5" aria-label="Breadcrumb">
                    <span>Product Specialist</span>
                    <span class="text-gray-300">/</span>
                    <span class="text-gray-900 font-medium">Product Linking</span>
                </nav>
                <h1 class="text-xl font-bold tracking-tight text-gray-900">Link Products</h1>
                <p class="text-xs text-gray-500 mt-1">
                    <?= count($products) ?> products
                    <?php if ($unlinkedCount): ?>
                        <span class="text-gray-300 mx-1">·</span>
                        <span class="text-red-600 font-semibold"><?= $unlinkedCount ?> without a subcategory</span>
                    <?php endif; ?>
                </p>
            </div>
            <div class="relative w-full sm:w-80">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs pointer-events-none"></i>
                <input type="text" id="productSearch" placeholder="Search products…" oninput="onSearchInput()"
                    class="w-full pl-8 pr-3 h-10 rounded-md border border-gray-300 bg-white text-xs text-gray-900 focus:outline-none focus:border-gray-900 focus:ring-2 focus:ring-gray-900/5">
            </div>
        </div>

        <div class="grid grid-cols-[260px_1fr] gap-5 items-start">

            <!-- ================= SUBCATEGORY PICKER ================= -->
            <aside class="bg-white rounded-lg border border-gray-200 sticky top-6 flex flex-col max-h-[calc(100vh-48px)] overflow-hidden">
                <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 shrink-0">
                    <div class="flex items-center gap-2">
                        <span class="w-5 h-5 rounded-full bg-gray-900 text-white text-[10px] font-bold flex items-center justify-center">1</span>
                        <p class="text-xs font-semibold text-gray-900">Target subcategories</p>
                    </div>
                    <button type="button" onclick="clearSubSelection()"
                        class="text-[11px] font-semibold text-gray-500 hover:text-gray-900">Clear</button>
                </div>

                <label class="flex items-center justify-between gap-3 px-4 py-2.5 border-b border-gray-100 cursor-pointer hover:bg-gray-50 shrink-0">
                    <span class="flex items-center gap-2 text-xs font-medium text-gray-700">
                        <i class="fa-solid fa-link-slash text-[11px] text-red-500"></i> Unlinked products only
                    </span>
                    <input type="checkbox" id="unlinkedOnly" onchange="renderProducts(true)" class="w-4 h-4 accent-red-600">
                </label>

                <div class="flex-1 overflow-y-auto nh-thin-scroll px-2 py-2">
                    <?php foreach ($categories as $catId => $cat): ?>
                        <?php if (empty($cat['subs'])) continue; ?>
                        <div class="mb-1">
                            <button type="button" onclick="toggleCatFilter(<?= (int) $catId ?>)"
                                class="w-full flex items-center justify-between px-2 py-2 rounded-md text-[11px] font-semibold uppercase tracking-wider text-gray-500 hover:bg-gray-50">
                                <span class="truncate"><?= htmlspecialchars($cat['name']) ?></span>
                                <i class="fa-solid fa-chevron-down text-[9px] transition-transform" id="filter-chevron-<?= (int) $catId ?>"></i>
                            </button>
                            <div id="filter-body-<?= (int) $catId ?>" class="pb-1">
                                <?php foreach ($cat['subs'] as $sub): ?>
                                    <label class="flex items-center gap-2.5 px-2 py-1.5 rounded-md text-xs text-gray-700 cursor-pointer hover:bg-gray-50 has-[:checked]:bg-orange-50 has-[:checked]:text-orange-800 has-[:checked]:font-semibold">
                                        <input type="checkbox" class="sub-filter w-4 h-4 accent-orange-600 shrink-0"
                                            value="<?= (int) $sub['id'] ?>" onchange="onSubFilterChange()">
                                        <span class="truncate"><?= htmlspecialchars($sub['name']) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </aside>

            <!-- ================= PRODUCTS ================= -->
            <section class="min-w-0">
                <div id="pickSubHint" class="flex items-start gap-3 bg-white border border-gray-200 rounded-lg px-4 py-3 mb-4">
                    <i class="fa-solid fa-circle-info text-gray-400 text-sm mt-0.5"></i>
                    <div class="text-xs text-gray-600 leading-relaxed">
                        <span class="font-semibold text-gray-900">How it works:</span>
                        ① Select one or more subcategories on the left,
                        ② click the products you want to link,
                        ③ press <span class="font-semibold text-gray-900">Apply</span> at the bottom.
                    </div>
                </div>

                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <span class="w-5 h-5 rounded-full bg-gray-900 text-white text-[10px] font-bold flex items-center justify-center">2</span>
                        <p class="text-xs font-semibold text-gray-900">Select products</p>
                    </div>
                    <p id="visibleLabel" class="text-[11px] text-gray-500"></p>
                </div>

                <div id="productGrid" class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3 scroll-mt-6"></div>

                <!-- Pagination -->
                <div id="pagination" class="hidden mt-5 flex flex-wrap items-center justify-between gap-3">
                    <p id="pageInfo" class="text-[11px] text-gray-500"></p>
                    <div class="flex items-center gap-3">
                        <label class="flex items-center gap-1.5 text-[11px] text-gray-500">
                            Per page
                            <select id="pageSize" onchange="changePageSize(this.value)"
                                class="h-8 px-2 rounded-md border border-gray-300 bg-white text-xs text-gray-900 focus:outline-none focus:border-gray-900">
                                <option value="12">12</option>
                                <option value="24" selected>24</option>
                                <option value="48">48</option>
                                <option value="96">96</option>
                            </select>
                        </label>
                        <nav id="pageButtons" class="flex items-center gap-1" aria-label="Pagination"></nav>
                    </div>
                </div>
                <div id="noProductsMsg" class="hidden bg-white border border-gray-200 rounded-lg py-12 text-center">
                    <i class="fa-solid fa-box-open text-2xl text-gray-300"></i>
                    <p class="text-xs text-gray-500 mt-2">No products match your search or filter.</p>
                </div>
            </section>
        </div>

        <!-- Sticky apply bar -->
        <div id="applyBar"
            class="hidden fixed bottom-0 left-60 right-0 bg-white border-t border-gray-200 px-8 py-3 flex items-center justify-between shadow-[0_-4px_16px_rgba(17,24,39,0.06)] z-40">
            <div class="flex items-center gap-3 text-xs text-gray-600">
                <span class="inline-flex items-center gap-1.5 h-7 px-2.5 rounded-md bg-gray-100">
                    <i class="fa-solid fa-box text-[10px] text-gray-500"></i>
                    <span id="selectedCount" class="font-bold text-gray-900">0</span> product(s)
                </span>
                <i class="fa-solid fa-arrow-right text-[10px] text-gray-400"></i>
                <span class="inline-flex items-center gap-1.5 h-7 px-2.5 rounded-md bg-orange-50">
                    <i class="fa-solid fa-tags text-[10px] text-orange-600"></i>
                    <span id="selectedSubCount" class="font-bold text-orange-700">0</span> subcategor(y/ies)
                </span>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" onclick="clearProductSelection()"
                    class="h-9 px-4 text-xs font-semibold rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">
                    Cancel
                </button>
                <button type="button" id="applyBtn" onclick="applyLinks()"
                    class="h-9 px-5 bg-orange-600 hover:bg-orange-700 text-white text-xs font-semibold rounded-md flex items-center gap-2">
                    <i class="fa-solid fa-check"></i> Apply
                </button>
            </div>
        </div>
    </div>

    <!-- Toast -->
    <div id="toast"
        class="hidden fixed bottom-20 right-6 bg-gray-900 text-white text-xs font-medium px-4 py-2.5 rounded-md shadow-lg z-50 max-w-sm"></div>

    <!-- Linked subcategories modal -->
    <div id="tagsModal" class="hidden fixed inset-0 bg-gray-900/40 z-50 flex items-center justify-center p-4"
        onclick="if(event.target===this) closeTagsModal()">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md max-h-[80vh] flex flex-col overflow-hidden">
            <div class="h-1 shrink-0" style="background: repeating-linear-gradient(-45deg,#f97316 0 8px,#111827 8px 16px);"></div>
            <div class="flex items-start justify-between px-5 py-4 border-b border-gray-100">
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold text-gray-500">Linked subcategories</p>
                    <p id="tagsModalProductName" class="text-sm font-bold text-gray-900 truncate"></p>
                </div>
                <button type="button" onclick="closeTagsModal()" aria-label="Close"
                    class="w-8 h-8 rounded-md flex items-center justify-center text-gray-400 hover:bg-gray-100 hover:text-gray-700">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div id="tagsModalBody" class="p-5 overflow-y-auto flex flex-wrap content-start gap-2"></div>
            <p class="px-5 pb-4 text-[11px] text-gray-500">Click ✕ to remove a link.</p>
        </div>
    </div>

    <script>
        // ---- Data from PHP ----
        const products = <?= json_encode($products, JSON_HEX_TAG) ?>;              // [{id, name, imageproduct}]
        const productSubIds = <?= json_encode($productSubIds) ?>;                   // {product_id: [sub_id,...]}
        const subNames = <?= json_encode($subNameLookup, JSON_HEX_TAG) ?>;          // {sub_id: name}
        const baseUrl = '<?= rtrim(BASE_URL, '/') ?>';
        const selectedProducts = new Set();

        // ---- Pagination state (only the current page is rendered → no lag with many products) ----
        let currentPage = 1;
        let pageSize = 24;
        try { pageSize = parseInt(localStorage.getItem('nh_link_page_size'), 10) || 24; } catch (e) { }

        function escHtml(str) {
            return String(str ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        function resolveImageSrc(path) {
            if (!path) return null;
            if (/^https?:\/\//i.test(path)) return path; // already a full URL
            const clean = path.replace(/^\/+/, '');
            // filenames in the DB have no folder prefix -- actual files live in /uploads/
            const withFolder = clean.startsWith('uploads/') ? clean : 'uploads/' + clean;
            return baseUrl + '/' + withFolder;
        }

        function showToast(msg) {
            const toast = document.getElementById('toast');
            toast.textContent = msg;
            toast.classList.remove('hidden');
            clearTimeout(window._toastTimer);
            window._toastTimer = setTimeout(() => toast.classList.add('hidden'), 2800);
        }

        function toggleCatFilter(catId) {
            document.getElementById('filter-body-' + catId).classList.toggle('hidden');
            document.getElementById('filter-chevron-' + catId).classList.toggle('-rotate-90');
        }

        function getCheckedSubIds() {
            return Array.from(document.querySelectorAll('.sub-filter:checked')).map(cb => cb.value);
        }

        function clearSubSelection() {
            document.querySelectorAll('.sub-filter:checked').forEach(cb => cb.checked = false);
            onSubFilterChange();
        }

        function clearProductSelection() {
            selectedProducts.clear();
            renderProducts();
            updateApplyBar();
        }

        let searchTimer = null;
        function onSearchInput() {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => renderProducts(true), 200); // debounce typing
        }

        function changePageSize(v) {
            pageSize = parseInt(v, 10) || 24;
            try { localStorage.setItem('nh_link_page_size', String(pageSize)); } catch (e) { }
            renderProducts(true);
        }

        function goToPage(n) {
            currentPage = n;
            renderProducts();
            document.getElementById('productGrid').scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        function pageList(cur, total) {
            // e.g. 1 … 4 5 6 … 20
            const out = [];
            const add = v => { if (out[out.length - 1] !== v) out.push(v); };
            for (let i = 1; i <= total; i++) {
                if (i === 1 || i === total || Math.abs(i - cur) <= 1) add(i);
                else add('…');
            }
            return out;
        }

        function renderPagination(total, start, end, totalPages) {
            const wrap = document.getElementById('pagination');
            wrap.classList.toggle('hidden', total === 0);
            document.getElementById('pageInfo').textContent = total
                ? `Showing ${start + 1}–${end} of ${total}` : '';

            const btn = (label, page, { active = false, disabled = false, aria = '' } = {}) => `
                <button type="button" ${disabled ? 'disabled' : `onclick="goToPage(${page})"`} ${aria ? `aria-label="${aria}"` : ''}
                    class="min-w-8 h-8 px-2 rounded-md text-xs font-semibold border transition
                           ${active ? 'bg-gray-900 border-gray-900 text-white'
                    : disabled ? 'border-gray-200 text-gray-300 cursor-not-allowed bg-white'
                        : 'border-gray-300 text-gray-700 bg-white hover:bg-gray-50'}">${label}</button>`;

            let html = btn('<i class="fa-solid fa-chevron-left text-[10px]"></i>', currentPage - 1, { disabled: currentPage <= 1, aria: 'Previous page' });
            pageList(currentPage, totalPages).forEach(p => {
                html += p === '…'
                    ? '<span class="px-1 text-xs text-gray-400">…</span>'
                    : btn(p, p, { active: p === currentPage });
            });
            html += btn('<i class="fa-solid fa-chevron-right text-[10px]"></i>', currentPage + 1, { disabled: currentPage >= totalPages, aria: 'Next page' });
            document.getElementById('pageButtons').innerHTML = html;
        }

        function onSubFilterChange() {
            renderProducts();
            updateApplyBar();
        }

        // ---- Validation helpers ----
        function alreadyLinkedChecked(productId) {
            const checkedSubs = getCheckedSubIds().map(Number);
            const linked = productSubIds[productId] || [];
            return checkedSubs.filter(sid => linked.includes(sid));
        }

        function isFullyLinkedToChecked(productId) {
            const checkedSubs = getCheckedSubIds().map(Number);
            if (checkedSubs.length === 0) return false;
            return alreadyLinkedChecked(productId).length === checkedSubs.length;
        }

        // Compact summary sa card
        function tagSummaryFor(productId) {
            const subs = productSubIds[productId] || [];
            if (subs.length === 0) {
                return '<span class="inline-flex items-center gap-1 text-[11px] font-semibold text-red-600"><i class="fa-solid fa-link-slash text-[10px]"></i> No subcategory yet</span>';
            }
            const firstName = escHtml(subNames[subs[0]] || subs[0]);
            const label = subs.length === 1
                ? firstName
                : `${firstName} <span class="text-gray-400 font-normal">+${subs.length - 1}</span>`;
            return `
                <button type="button" onclick="event.stopPropagation(); openTagsModal(${Number(productId)})"
                    class="inline-flex items-center gap-1.5 max-w-full text-[11px] font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 px-2 py-1 rounded">
                    <i class="fa-solid fa-tags text-[10px] text-gray-400"></i>
                    <span class="truncate">${label}</span>
                </button>`;
        }

        // ---- Modal: full list of a product's linked subcategories ----
        let activeModalProductId = null;

        function renderTagsModalBody(productId) {
            const subs = productSubIds[productId] || [];
            const body = document.getElementById('tagsModalBody');
            if (subs.length === 0) {
                body.innerHTML = '<p class="text-xs text-gray-500">No subcategories linked yet.</p>';
                return;
            }
            body.innerHTML = subs.map(sid => `
                <span class="inline-flex items-center gap-2 text-xs bg-gray-100 text-gray-800 pl-2.5 pr-1.5 h-8 rounded-md">
                    ${escHtml(subNames[sid] || sid)}
                    <button type="button" onclick="unlinkOne(${Number(productId)}, ${Number(sid)})" aria-label="Remove link"
                        class="w-5 h-5 rounded flex items-center justify-center text-gray-400 hover:bg-red-50 hover:text-red-600">
                        <i class="fa-solid fa-xmark text-[10px]"></i>
                    </button>
                </span>`
            ).join('');
        }

        function openTagsModal(productId) {
            activeModalProductId = productId;
            const product = products.find(p => String(p.id) === String(productId));
            document.getElementById('tagsModalProductName').textContent = product ? product.name : '';
            renderTagsModalBody(productId);
            document.getElementById('tagsModal').classList.remove('hidden');
        }

        function closeTagsModal() {
            activeModalProductId = null;
            document.getElementById('tagsModal').classList.add('hidden');
        }
        document.addEventListener('keydown', e => { if (e.key === 'Escape') closeTagsModal(); });

        function renderProducts(resetPage = false) {
            const query = document.getElementById('productSearch').value.trim().toLowerCase();
            const unlinkedOnly = document.getElementById('unlinkedOnly').checked;
            const grid = document.getElementById('productGrid');
            const checkedSubs = getCheckedSubIds().map(Number);

            // 1) Filter
            const filtered = products.filter(p => {
                const nameMatch = String(p.name).toLowerCase().includes(query);
                const linkMatch = !unlinkedOnly || (productSubIds[p.id] || []).length === 0;
                return nameMatch && linkMatch;
            });
            const visibleCount = filtered.length;

            // 2) Paginate
            if (resetPage) currentPage = 1;
            const totalPages = Math.max(1, Math.ceil(visibleCount / pageSize));
            currentPage = Math.min(Math.max(1, currentPage), totalPages);
            const start = (currentPage - 1) * pageSize;
            const end = Math.min(start + pageSize, visibleCount);

            // 3) Render only this page
            const frag = document.createDocumentFragment();
            filtered.slice(start, end).forEach(p => {

                const checked = selectedProducts.has(String(p.id));
                const matchedLinked = alreadyLinkedChecked(p.id);
                const fullyLinked = checkedSubs.length > 0 && matchedLinked.length === checkedSubs.length;
                const partiallyLinked = checkedSubs.length > 0 && matchedLinked.length > 0 && !fullyLinked;

                const card = document.createElement('div');
                let cls = 'group relative bg-white rounded-lg border overflow-hidden transition ';
                if (fullyLinked) cls += 'border-green-200 opacity-60 cursor-not-allowed';
                else if (checked) cls += 'cursor-pointer border-orange-500 ring-2 ring-orange-500/20';
                else cls += 'cursor-pointer border-gray-200 hover:border-gray-400';
                card.className = cls;
                card.onclick = () => toggleProductSelect(p.id, card);

                const resolvedImg = resolveImageSrc(p.imageproduct);
                const placeholder = '<div class="w-full h-full flex items-center justify-center"><i class="fa-solid fa-box text-gray-300 text-2xl"></i></div>';
                const imageBlock = resolvedImg
                    ? `<img src="${escHtml(resolvedImg)}" alt="" loading="lazy" class="w-full h-full object-cover"
                           onerror="this.replaceWith(Object.assign(document.createElement('div'),{className:'w-full h-full flex items-center justify-center',innerHTML:'<i class=&quot;fa-solid fa-box text-gray-300 text-2xl&quot;></i>'}))">`
                    : placeholder;

                let statusBadge = '';
                if (fullyLinked) {
                    statusBadge = '<span class="absolute top-2 left-2 inline-flex items-center gap-1 text-[10px] font-semibold text-green-700 bg-white/95 border border-green-200 px-1.5 py-0.5 rounded"><i class="fa-solid fa-circle-check"></i> Already linked</span>';
                } else if (partiallyLinked) {
                    statusBadge = `<span class="absolute top-2 left-2 inline-flex items-center gap-1 text-[10px] font-semibold text-amber-700 bg-white/95 border border-amber-200 px-1.5 py-0.5 rounded"><i class="fa-solid fa-triangle-exclamation"></i> ${matchedLinked.length}/${checkedSubs.length} linked</span>`;
                }

                // Custom checkbox sa kanang itaas ng larawan
                const checkBox = fullyLinked ? '' : `
                    <span class="absolute top-2 right-2 w-5 h-5 rounded border flex items-center justify-center shadow-sm
                                 ${checked ? 'bg-orange-600 border-orange-600' : 'bg-white border-gray-300 group-hover:border-gray-500'}">
                        ${checked ? '<i class="fa-solid fa-check text-[10px] text-white"></i>' : ''}
                    </span>`;

                card.innerHTML = `
                    <div class="relative aspect-[4/3] bg-gray-100">
                        ${imageBlock}
                        ${statusBadge}
                        ${checkBox}
                    </div>
                    <div class="p-3 border-t border-gray-100">
                        <p class="nh-clamp-2 text-xs font-semibold text-gray-900 leading-snug min-h-[2rem]">${escHtml(p.name)}</p>
                        <div class="mt-2 flex">${tagSummaryFor(p.id)}</div>
                    </div>`;
                frag.appendChild(card);
            });
            grid.replaceChildren(frag);

            document.getElementById('noProductsMsg').classList.toggle('hidden', visibleCount !== 0);
            document.getElementById('visibleLabel').textContent =
                `${visibleCount} of ${products.length} products` + (selectedProducts.size ? ` · ${selectedProducts.size} selected` : '');
            renderPagination(visibleCount, start, end, totalPages);
        }

        function toggleProductSelect(productId, card) {
            const key = String(productId);

            // Bawal piliin ang product na naka-link na sa LAHAT ng napiling subcategory
            if (!selectedProducts.has(key) && isFullyLinkedToChecked(productId)) {
                showToast('This product is already linked to the selected subcategory/ies.');
                return;
            }

            if (selectedProducts.has(key)) selectedProducts.delete(key);
            else selectedProducts.add(key);

            renderProducts();
            updateApplyBar();
        }

        function updateApplyBar() {
            const subCount = getCheckedSubIds().length;
            const prodCount = selectedProducts.size;
            document.getElementById('selectedCount').textContent = prodCount;
            document.getElementById('selectedSubCount').textContent = subCount;
            document.getElementById('applyBar').classList.toggle('hidden', !(subCount > 0 && prodCount > 0));
            document.getElementById('pickSubHint').classList.toggle('hidden', subCount > 0 || prodCount > 0);
        }

        function saveLink(productId, subcategoryId, linked) {
            return fetch('<?= BASE_URL ?>/ps-backendtoggle-subcategory', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ product_id: productId, subcategory_id: subcategoryId, linked: linked })
            })
                .then(r => r.json())
                .catch(() => ({ success: false }));
        }

        function unlinkOne(productId, subId) {
            saveLink(productId, subId, false).then(d => {
                if (d.success) {
                    productSubIds[productId] = (productSubIds[productId] || []).filter(id => id !== subId);
                    renderProducts();
                    if (activeModalProductId !== null && String(activeModalProductId) === String(productId)) {
                        renderTagsModalBody(productId);
                    }
                    showToast('Link removed.');
                } else {
                    showToast('Could not remove that link. Please try again.');
                }
            });
        }

        function applyLinks() {
            const subIds = getCheckedSubIds();
            const prodIds = Array.from(selectedProducts);
            if (subIds.length === 0 || prodIds.length === 0) return;

            const applyBtn = document.getElementById('applyBtn');
            const resetBtn = () => {
                applyBtn.disabled = false;
                applyBtn.classList.remove('opacity-60');
                applyBtn.innerHTML = '<i class="fa-solid fa-check"></i> Apply';
            };
            applyBtn.disabled = true;
            applyBtn.classList.add('opacity-60');
            applyBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Applying…';

            const jobs = [];
            let skippedCount = 0;
            prodIds.forEach(pid => {
                subIds.forEach(sid => {
                    if (!(productSubIds[pid] || []).includes(Number(sid))) jobs.push({ pid, sid });
                    else skippedCount++;
                });
            });

            if (jobs.length === 0) {
                resetBtn();
                showToast('Nothing to apply — all selected products are already linked to those subcategories.');
                return;
            }

            Promise.all(jobs.map(j => saveLink(j.pid, j.sid, true).then(d => ({ ...j, success: d.success }))))
                .then(results => {
                    let failCount = 0;
                    results.forEach(r => {
                        if (r.success) {
                            if (!productSubIds[r.pid]) productSubIds[r.pid] = [];
                            productSubIds[r.pid].push(Number(r.sid));
                        } else {
                            failCount++;
                        }
                    });

                    resetBtn();

                    let msg = failCount > 0 ? failCount + ' link(s) failed to save. Please retry.' : 'Linked successfully!';
                    if (skippedCount > 0) msg += ' (' + skippedCount + ' pair(s) skipped — already linked)';
                    showToast(msg);

                    selectedProducts.clear();
                    document.querySelectorAll('.sub-filter:checked').forEach(cb => cb.checked = false);
                    renderProducts();
                    updateApplyBar();
                });
        }

        // initial render
        document.getElementById('pageSize').value = String(pageSize);
        if (!document.getElementById('pageSize').value) { pageSize = 24; document.getElementById('pageSize').value = '24'; }
        renderProducts();
        updateApplyBar();
    </script>

</body>

</html>