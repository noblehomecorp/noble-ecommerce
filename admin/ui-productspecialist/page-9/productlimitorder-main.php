<?php
// productlimitorder-main.php

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_PRODUCTSPECIALIST];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

$uploadUrl = BASE_URL . '/uploads/';

// ── Load products kasama ang current limit + tier count ──
$products = [];
$sql = "
    SELECT p.id, p.name, p.category, p.imageproduct,
           l.min_qty_per_order, l.max_qty_per_order,
           (SELECT COUNT(*) FROM nobleproductqtytier t WHERE t.product_id = p.id) AS tier_count
    FROM nobleproduct p
    LEFT JOIN nobleproductlimit l ON l.product_id = p.id
    ORDER BY p.name ASC
";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}

// Summary counts
$withMin = count(array_filter($products, fn($p) => (int) ($p['min_qty_per_order'] ?? 0) > 1));
$withMax = count(array_filter($products, fn($p) => (int) ($p['max_qty_per_order'] ?? 0) > 0));
$withTiers = count(array_filter($products, fn($p) => (int) $p['tier_count'] > 0));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quantity Limit — NobleHome</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <style>
        .nh-table-scroll { scrollbar-width: thin; scrollbar-color: #d1d5db transparent; }
        .nh-table-scroll::-webkit-scrollbar { width: 8px; }
        .nh-table-scroll::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 9999px; }
    </style>
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
                    <span class="text-gray-900 font-medium">Quantity Limit</span>
                </nav>
                <h1 class="text-xl font-bold tracking-tight text-gray-900">Quantity Limit &amp; Discount</h1>
                <p class="text-xs text-gray-500 mt-1">
                    Set the min/max quantity per order and tiered discounts for bulk purchases.
                </p>
            </div>
        </div>

        <!-- Summary -->
        <div class="grid grid-cols-4 gap-3 mb-4">
            <?php foreach ([
                ['Products', count($products), 'fa-box'],
                ['With minimum', $withMin, 'fa-arrow-down-1-9'],
                ['With max limit', $withMax, 'fa-ruler-combined'],
                ['With discount tiers', $withTiers, 'fa-tags'],
            ] as [$label, $value, $icon]): ?>
                <div class="bg-white border border-gray-200 rounded-lg px-4 py-3.5 flex items-center justify-between">
                    <div>
                        <p class="text-[11px] font-medium text-gray-500"><?= $label ?></p>
                        <p class="text-lg font-bold text-gray-900 mt-0.5"><?= number_format($value) ?></p>
                    </div>
                    <span class="w-9 h-9 rounded-md bg-gray-100 flex items-center justify-center">
                        <i class="fa-solid <?= $icon ?> text-sm text-gray-600"></i>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (empty($products)): ?>
            <div class="bg-white rounded-lg border border-gray-200 p-12 text-center">
                <i class="fa-solid fa-box-open text-3xl text-gray-300"></i>
                <p class="text-sm font-semibold text-gray-900 mt-3">No products found</p>
                <p class="text-xs text-gray-500 mt-1">Add a product first in Insert Product.</p>
            </div>
        <?php else: ?>

            <!-- Toolbar -->
            <div class="bg-white rounded-lg border border-gray-200 p-3 mb-4 flex flex-wrap items-center gap-3">
                <div class="relative flex-1 min-w-56">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs pointer-events-none"></i>
                    <input type="text" id="limit-search" placeholder="Search product or category…"
                        class="w-full pl-8 pr-3 h-10 text-xs border border-gray-300 rounded-md bg-white focus:outline-none focus:border-gray-900 focus:ring-2 focus:ring-gray-900/5 transition">
                </div>
                <select id="limit-filter"
                    class="px-3 h-10 text-xs border border-gray-300 rounded-md bg-white focus:outline-none focus:border-gray-900 transition min-w-44">
                    <option value="">All products</option>
                    <option value="configured">With settings</option>
                    <option value="none">No settings yet</option>
                </select>
                <span id="limit-results" class="text-xs text-gray-500 ml-auto"></span>
            </div>

            <!-- Table -->
            <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
                <div class="nh-table-scroll overflow-y-auto" style="max-height: calc(100vh - 330px); min-height: 240px;">
                <table class="w-full text-xs">
                    <thead class="sticky top-0 z-10 bg-gray-50 shadow-[0_1px_0_#e5e7eb]">
                        <tr class="bg-gray-50 text-[11px] font-semibold text-gray-500">
                            <th class="px-5 py-3 text-left">Product</th>
                            <th class="px-5 py-3 text-left">Category</th>
                            <th class="px-5 py-3 text-center">Min Qty / Order</th>
                            <th class="px-5 py-3 text-center">Max Qty / Order</th>
                            <th class="px-5 py-3 text-center">Discount Tiers</th>
                            <th class="px-5 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100" id="product-table-body">
                        <?php foreach ($products as $p):
                            $hasMin = (int) ($p['min_qty_per_order'] ?? 0) > 1;
                            $hasMax = (int) ($p['max_qty_per_order'] ?? 0) > 0;
                            $hasTier = (int) $p['tier_count'] > 0;
                            ?>
                            <tr class="limit-row hover:bg-gray-50 transition" data-product-id="<?= (int) $p['id'] ?>"
                                data-search="<?= htmlspecialchars(strtolower($p['name'] . ' ' . $p['category'])) ?>"
                                data-configured="<?= ($hasMin || $hasMax || $hasTier) ? '1' : '0' ?>">
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-3">
                                        <?php if (!empty($p['imageproduct'])): ?>
                                            <img src="<?= $uploadUrl . htmlspecialchars($p['imageproduct']) ?>" alt=""
                                                class="w-10 h-10 rounded-md object-cover border border-gray-200 shrink-0">
                                        <?php else: ?>
                                            <div class="w-10 h-10 rounded-md bg-gray-100 flex items-center justify-center text-gray-400 shrink-0">
                                                <i class="fa-solid fa-image text-xs"></i>
                                            </div>
                                        <?php endif; ?>
                                        <span class="font-semibold text-gray-900"><?= htmlspecialchars($p['name']) ?></span>
                                    </div>
                                </td>
                                <td class="px-5 py-3">
                                    <?php if (!empty($p['category'])): ?>
                                        <span class="inline-flex px-2 py-0.5 rounded text-[11px] font-medium bg-gray-100 text-gray-700">
                                            <?= htmlspecialchars($p['category']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-gray-300">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-5 py-3 text-center min-qty-cell">
                                    <?= $hasMin
                                        ? '<span class="font-semibold text-gray-900">' . (int) $p['min_qty_per_order'] . '</span>'
                                        : '<span class="text-gray-400">No minimum</span>' ?>
                                </td>
                                <td class="px-5 py-3 text-center max-qty-cell">
                                    <?= $hasMax
                                        ? '<span class="font-semibold text-gray-900">' . (int) $p['max_qty_per_order'] . '</span>'
                                        : '<span class="text-gray-400">No limit</span>' ?>
                                </td>
                                <td class="px-5 py-3 text-center tier-count-cell">
                                    <?php if ($hasTier): ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-semibold bg-orange-50 text-orange-700">
                                            <i class="fa-solid fa-tag text-[9px]"></i>
                                            <?= (int) $p['tier_count'] ?> tier<?= $p['tier_count'] > 1 ? 's' : '' ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-gray-400">None</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <button type="button" data-name="<?= htmlspecialchars($p['name']) ?>"
                                        onclick="openManageModal(<?= (int) $p['id'] ?>, this.dataset.name)"
                                        class="inline-flex items-center gap-1.5 h-8 px-3 text-xs font-semibold rounded-md border border-gray-300 bg-white text-gray-800 hover:bg-gray-50 transition">
                                        <i class="fa-solid fa-sliders text-[10px]"></i> Manage
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>

                <div id="limit-empty" class="hidden px-5 py-12 text-center">
                    <i class="fa-solid fa-magnifying-glass text-2xl text-gray-300"></i>
                    <p class="text-xs text-gray-500 mt-2">No products match your search.</p>
                </div>
            </div>
        <?php endif; ?>

    </div>

    <!-- ════════ Manage Modal ════════ -->
    <div id="manage-modal" class="fixed inset-0 z-50 bg-gray-900/40 hidden items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-lg max-h-[90vh] flex flex-col overflow-hidden">

            <div class="h-1 shrink-0" style="background: repeating-linear-gradient(-45deg,#f97316 0 8px,#111827 8px 16px);"></div>

            <div class="flex items-start justify-between px-5 py-4 border-b border-gray-100 shrink-0">
                <div class="min-w-0">
                    <h2 class="text-sm font-bold text-gray-900">Limit &amp; Discount Settings</h2>
                    <p id="modal-product-name" class="text-xs text-gray-500 mt-0.5 truncate"></p>
                </div>
                <button type="button" onclick="closeManageModal()" aria-label="Close"
                    class="w-8 h-8 rounded-md flex items-center justify-center text-gray-400 hover:bg-gray-100 hover:text-gray-700">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="p-5 overflow-y-auto">

                <!-- Min / Max -->
                <p class="text-xs font-semibold text-gray-900 mb-3">Quantity per order</p>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="input-min-qty" class="block text-[11px] font-semibold text-gray-600 mb-1">Minimum</label>
                        <input type="number" id="input-min-qty" min="1" placeholder="1"
                            class="w-full px-3 h-10 text-xs border border-gray-300 rounded-md bg-white focus:outline-none focus:border-gray-900 focus:ring-2 focus:ring-gray-900/5">
                        <p class="text-[11px] text-gray-500 mt-1">1 = no minimum</p>
                    </div>
                    <div>
                        <label for="input-max-qty" class="block text-[11px] font-semibold text-gray-600 mb-1">Maximum</label>
                        <input type="number" id="input-max-qty" min="0" placeholder="0"
                            class="w-full px-3 h-10 text-xs border border-gray-300 rounded-md bg-white focus:outline-none focus:border-gray-900 focus:ring-2 focus:ring-gray-900/5">
                        <p class="text-[11px] text-gray-500 mt-1">0 = no limit</p>
                    </div>
                </div>
                <div class="mt-3 flex gap-2 text-[11px] text-gray-600 bg-gray-50 border border-gray-200 rounded-md px-3 py-2">
                    <i class="fa-solid fa-circle-info text-gray-400 mt-0.5"></i>
                    <span>Example: Min 10 means every "Add to Cart" must be at least 10 pcs, not smaller batches (5, 5…).</span>
                </div>

                <!-- Tiers -->
                <div class="mt-6 mb-2 flex items-center justify-between">
                    <p class="text-xs font-semibold text-gray-900">Quantity discount tiers</p>
                    <button type="button" onclick="addTierRow()"
                        class="inline-flex items-center gap-1.5 h-8 px-3 text-xs font-semibold rounded-md border border-gray-300 bg-white text-gray-800 hover:bg-gray-50">
                        <i class="fa-solid fa-plus text-[10px]"></i> Add Tier
                    </button>
                </div>

                <div class="grid grid-cols-[1fr_auto_1fr_1.2fr_28px] items-center gap-2 px-1 mb-1.5 text-[11px] font-semibold text-gray-500">
                    <span>Min qty</span><span></span><span>Max qty</span><span>Discount</span><span></span>
                </div>
                <div id="tier-rows" class="space-y-2"></div>

                <p class="text-[11px] text-gray-500 mt-2">
                    Example: 3–4 pcs = 1% off, 5–5 pcs = 1.2% off. Ranges must not overlap
                    and must not exceed the Maximum above.
                </p>

                <div id="modal-error" class="hidden mt-4 text-xs text-red-700 bg-red-50 border border-red-200 rounded-md px-3 py-2"></div>
            </div>

            <div class="flex justify-end gap-2 px-5 py-4 border-t border-gray-100 bg-gray-50 shrink-0">
                <button type="button" onclick="closeManageModal()"
                    class="h-9 px-4 text-xs font-semibold rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">
                    Cancel
                </button>
                <button type="button" onclick="saveManageModal()" id="save-btn"
                    class="h-9 px-4 text-xs font-semibold rounded-md bg-orange-600 hover:bg-orange-700 text-white disabled:opacity-60">
                    <i class="fa-solid fa-floppy-disk mr-1.5"></i> Save
                </button>
            </div>
        </div>
    </div>

    <script>
        const fetchUrl = <?= json_encode(BASE_URL . '/productlimitorder-fetch') ?>;
        const saveUrl = <?= json_encode(BASE_URL . '/productlimitorder-save') ?>;

        let currentProductId = null;
        let tierCounter = 0;

        const INPUT_CLS = 'w-full px-2.5 h-9 text-xs border border-gray-300 rounded-md bg-white focus:outline-none focus:border-gray-900 focus:ring-2 focus:ring-gray-900/5';

        /* ── Search / filter ─────────────────────────────── */
        (function () {
            const search = document.getElementById('limit-search');
            const filter = document.getElementById('limit-filter');
            const label = document.getElementById('limit-results');
            const empty = document.getElementById('limit-empty');
            const rows = document.querySelectorAll('.limit-row');
            if (!search) return;

            function apply() {
                const q = search.value.trim().toLowerCase();
                const f = filter.value;
                let shown = 0;
                rows.forEach(r => {
                    const okQ = !q || r.dataset.search.includes(q);
                    const okF = !f || (f === 'configured' ? r.dataset.configured === '1' : r.dataset.configured === '0');
                    const show = okQ && okF;
                    r.classList.toggle('hidden', !show);
                    if (show) shown++;
                });
                label.textContent = (q || f) ? `${shown} of ${rows.length} shown` : '';
                empty.classList.toggle('hidden', shown > 0);
            }
            search.addEventListener('input', apply);
            filter.addEventListener('change', apply);
        })();

        /* ── Modal ───────────────────────────────────────── */
        function openManageModal(productId, productName) {
            currentProductId = productId;
            document.getElementById('modal-product-name').textContent = productName;
            document.getElementById('modal-error').classList.add('hidden');
            document.getElementById('tier-rows').innerHTML = '';
            document.getElementById('input-min-qty').value = '';
            document.getElementById('input-max-qty').value = '';
            tierCounter = 0;

            document.getElementById('manage-modal').classList.remove('hidden');
            document.getElementById('manage-modal').classList.add('flex');
            document.body.style.overflow = 'hidden';

            fetch(`${fetchUrl}?product_id=${productId}`)
                .then(r => r.json())
                .then(data => {
                    if (!data.ok) return;
                    document.getElementById('input-min-qty').value = data.min_qty_per_order ?? 1;
                    document.getElementById('input-max-qty').value = data.max_qty_per_order ?? 0;
                    (data.tiers || []).forEach(t => addTierRow(t.min_qty, t.max_qty, t.discount_percent));
                    if (!data.tiers || data.tiers.length === 0) addTierRow();
                })
                .catch(() => addTierRow());
        }

        function closeManageModal() {
            document.getElementById('manage-modal').classList.add('hidden');
            document.getElementById('manage-modal').classList.remove('flex');
            document.body.style.overflow = '';
            currentProductId = null;
        }

        document.getElementById('manage-modal').addEventListener('click', e => {
            if (e.target.id === 'manage-modal') closeManageModal();
        });
        document.addEventListener('keydown', e => { if (e.key === 'Escape') closeManageModal(); });

        function addTierRow(minQty = '', maxQty = '', discountPercent = '') {
            tierCounter++;
            const id = 'tier-' + tierCounter;
            const wrapper = document.createElement('div');
            wrapper.id = id;
            wrapper.className = 'grid grid-cols-[1fr_auto_1fr_1.2fr_28px] items-center gap-2';
            wrapper.innerHTML = `
                <input type="number" min="1" placeholder="e.g. 3" value="${minQty}" class="tier-min ${INPUT_CLS}">
                <span class="text-gray-400 text-xs">to</span>
                <input type="number" min="1" placeholder="e.g. 4" value="${maxQty}" class="tier-max ${INPUT_CLS}">
                <div class="relative">
                    <input type="number" step="0.01" min="0" max="100" placeholder="0" value="${discountPercent}"
                        class="tier-discount ${INPUT_CLS} pr-7">
                    <span class="absolute right-2.5 top-1/2 -translate-y-1/2 text-[11px] text-gray-500">%</span>
                </div>
                <button type="button" onclick="document.getElementById('${id}').remove()" aria-label="Remove tier"
                    class="w-7 h-7 rounded-md border border-gray-200 bg-white text-gray-400 hover:text-red-600 hover:border-red-200 flex items-center justify-center transition">
                    <i class="fa-solid fa-xmark text-[10px]"></i>
                </button>`;
            document.getElementById('tier-rows').appendChild(wrapper);
        }

        function collectTiers() {
            const rows = document.querySelectorAll('#tier-rows > div');
            const tiers = [];
            for (const row of rows) {
                const min = row.querySelector('.tier-min').value;
                const max = row.querySelector('.tier-max').value;
                const disc = row.querySelector('.tier-discount').value;
                if (min === '' && max === '' && disc === '') continue; // skip totally empty rows
                tiers.push({
                    min_qty: parseInt(min, 10),
                    max_qty: parseInt(max, 10),
                    discount_percent: parseFloat(disc) || 0
                });
            }
            return tiers;
        }

        function showModalError(msg) {
            const el = document.getElementById('modal-error');
            el.textContent = msg;
            el.classList.remove('hidden');
        }

        async function saveManageModal() {
            document.getElementById('modal-error').classList.add('hidden');

            const minQty = parseInt(document.getElementById('input-min-qty').value, 10) || 1;
            const maxQty = parseInt(document.getElementById('input-max-qty').value, 10) || 0;
            const tiers = collectTiers();

            if (minQty < 1) {
                showModalError('Min quantity must be at least 1.');
                return;
            }
            if (maxQty > 0 && minQty > maxQty) {
                showModalError('Min quantity cannot be greater than max quantity.');
                return;
            }
            for (const t of tiers) {
                if (!t.min_qty || !t.max_qty || t.min_qty > t.max_qty) {
                    showModalError('One of the tiers has an invalid min/max quantity.');
                    return;
                }
                if (maxQty > 0 && t.max_qty > maxQty) {
                    showModalError('A tier cannot exceed the max quantity per order.');
                    return;
                }
            }

            const btn = document.getElementById('save-btn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1.5"></i> Saving…';

            try {
                const res = await fetch(saveUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        product_id: currentProductId,
                        min_qty_per_order: minQty,
                        max_qty_per_order: maxQty,
                        tiers
                    })
                });
                const data = await res.json();

                if (!data.ok) {
                    showModalError(data.msg || 'Failed to save.');
                } else {
                    updateRowDisplay(currentProductId, minQty, maxQty, tiers.length);
                    closeManageModal();
                }
            } catch (e) {
                showModalError('Something went wrong. Please try again.');
            }

            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-floppy-disk mr-1.5"></i> Save';
        }

        function updateRowDisplay(productId, minQty, maxQty, tierCount) {
            const row = document.querySelector(`tr[data-product-id="${productId}"]`);
            if (!row) return;
            row.querySelector('.min-qty-cell').innerHTML = minQty > 1
                ? `<span class="font-semibold text-gray-900">${minQty}</span>`
                : '<span class="text-gray-400">No minimum</span>';
            row.querySelector('.max-qty-cell').innerHTML = maxQty > 0
                ? `<span class="font-semibold text-gray-900">${maxQty}</span>`
                : '<span class="text-gray-400">No limit</span>';
            row.querySelector('.tier-count-cell').innerHTML = tierCount > 0
                ? `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-semibold bg-orange-50 text-orange-700"><i class="fa-solid fa-tag text-[9px]"></i> ${tierCount} tier${tierCount > 1 ? 's' : ''}</span>`
                : '<span class="text-gray-400">None</span>';
            row.dataset.configured = (minQty > 1 || maxQty > 0 || tierCount > 0) ? '1' : '0';
        }
    </script>

</body>

</html>