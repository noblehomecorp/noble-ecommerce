<?php
// promotiontimer-main.php
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_PRODUCTSPECIALIST];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Promotion Timer — NobleHome</title>
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
                    <span class="text-gray-900 font-medium">Promotion Timer</span>
                </nav>
                <h1 class="text-xl font-bold tracking-tight text-gray-900">Promo / Discount Timer</h1>
                <p class="text-xs text-gray-500 mt-1">Schedule discounts for a whole product or a specific color/size.</p>
            </div>
            <button id="addPromoBtn" type="button"
                class="shrink-0 inline-flex items-center gap-2 h-10 px-4 bg-orange-600 hover:bg-orange-700 text-white text-xs font-semibold rounded-md transition">
                <i class="fa-solid fa-plus text-[11px]"></i> Add Promo
            </button>
        </div>

        <!-- Status tabs + counts -->
        <div class="flex flex-wrap items-center gap-2 mb-4" id="promoTabs">
            <?php foreach ([
                ['all', 'All'],
                ['active', 'Active'],
                ['upcoming', 'Upcoming'],
                ['expired', 'Expired'],
            ] as [$key, $label]): ?>
                <button type="button" data-tab="<?= $key ?>"
                    class="promo-tab inline-flex items-center gap-2 h-9 px-3.5 rounded-md border text-xs font-semibold transition
                           <?= $key === 'all' ? 'bg-gray-900 border-gray-900 text-white' : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50' ?>">
                    <?= $label ?>
                    <span class="promo-count min-w-[20px] h-5 px-1.5 rounded text-[10px] font-bold flex items-center justify-center
                                 <?= $key === 'all' ? 'bg-white/15 text-white' : 'bg-gray-100 text-gray-600' ?>" data-count="<?= $key ?>">0</span>
                </button>
            <?php endforeach; ?>

            <div class="relative ml-auto w-64">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs pointer-events-none"></i>
                <input type="text" id="promoSearch" placeholder="Search product…"
                    class="w-full pl-8 pr-3 h-9 text-xs border border-gray-300 rounded-md bg-white focus:outline-none focus:border-gray-900 focus:ring-2 focus:ring-gray-900/5">
            </div>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
            <table class="w-full text-xs">
                <thead class="bg-gray-50 border-b border-gray-200 text-[11px] font-semibold text-gray-500">
                    <tr>
                        <th class="text-left px-5 py-3">Product</th>
                        <th class="text-left px-5 py-3">Color / Size</th>
                        <th class="text-left px-5 py-3">Discount</th>
                        <th class="text-left px-5 py-3">Schedule</th>
                        <th class="text-left px-5 py-3">Status</th>
                        <th class="text-right px-5 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody id="promoTableBody" class="divide-y divide-gray-100">
                    <tr><td colspan="6" class="text-center py-12 text-gray-400">
                        <i class="fa-solid fa-spinner fa-spin mr-1.5"></i> Loading…
                    </td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ═══════ ADD/EDIT MODAL ═══════ -->
    <div id="promoModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-gray-900/40 p-4">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md overflow-hidden">
            <div class="h-1" style="background: repeating-linear-gradient(-45deg,#f97316 0 8px,#111827 8px 16px);"></div>

            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                <h3 id="promoModalTitle" class="text-sm font-bold text-gray-900">Add Promo</h3>
                <button type="button" onclick="closePromoModal()" aria-label="Close"
                    class="w-8 h-8 rounded-md flex items-center justify-center text-gray-400 hover:bg-gray-100 hover:text-gray-700">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="p-5 space-y-4">
                <input type="hidden" id="promoId" value="">

                <div>
                    <label for="promoProductId" class="block text-[11px] font-semibold text-gray-600 mb-1">Product</label>
                    <select id="promoProductId"
                        class="w-full px-3 h-10 text-xs border border-gray-300 rounded-md bg-white focus:outline-none focus:border-gray-900 focus:ring-2 focus:ring-gray-900/5">
                        <option value="">Select product…</option>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="promoColorId" class="block text-[11px] font-semibold text-gray-600 mb-1">Color <span class="font-normal text-gray-400">(optional)</span></label>
                        <select id="promoColorId"
                            class="w-full px-3 h-10 text-xs border border-gray-300 rounded-md bg-white focus:outline-none focus:border-gray-900 focus:ring-2 focus:ring-gray-900/5">
                            <option value="">All colors</option>
                        </select>
                    </div>
                    <div>
                        <label for="promoSize" class="block text-[11px] font-semibold text-gray-600 mb-1">Size <span class="font-normal text-gray-400">(optional)</span></label>
                        <select id="promoSize"
                            class="w-full px-3 h-10 text-xs border border-gray-300 rounded-md bg-white focus:outline-none focus:border-gray-900 focus:ring-2 focus:ring-gray-900/5">
                            <option value="">All sizes</option>
                        </select>
                    </div>
                </div>
                <p class="text-[11px] text-gray-500 -mt-2">Leave both on "All" for the whole product, or pick a specific color/size.</p>

                <div>
                    <label for="promoDiscount" class="block text-[11px] font-semibold text-gray-600 mb-1">Discount</label>
                    <div class="relative">
                        <input type="number" id="promoDiscount" min="1" max="100" step="0.01" placeholder="e.g. 10"
                            class="w-full px-3 pr-8 h-10 text-xs border border-gray-300 rounded-md bg-white focus:outline-none focus:border-gray-900 focus:ring-2 focus:ring-gray-900/5">
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-gray-500">%</span>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="promoStart" class="block text-[11px] font-semibold text-gray-600 mb-1">Start</label>
                        <input type="datetime-local" id="promoStart"
                            class="w-full px-3 h-10 text-xs border border-gray-300 rounded-md bg-white focus:outline-none focus:border-gray-900 focus:ring-2 focus:ring-gray-900/5">
                    </div>
                    <div>
                        <label for="promoEnd" class="block text-[11px] font-semibold text-gray-600 mb-1">End</label>
                        <input type="datetime-local" id="promoEnd"
                            class="w-full px-3 h-10 text-xs border border-gray-300 rounded-md bg-white focus:outline-none focus:border-gray-900 focus:ring-2 focus:ring-gray-900/5">
                    </div>
                </div>

                <p id="promoError" class="text-xs text-red-700 bg-red-50 border border-red-200 rounded-md px-3 py-2 hidden"></p>
            </div>

            <div class="flex justify-end gap-2 px-5 py-4 border-t border-gray-100 bg-gray-50">
                <button type="button" onclick="closePromoModal()"
                    class="h-9 px-4 text-xs font-semibold rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">
                    Cancel
                </button>
                <button type="button" onclick="savePromo()" id="promoSaveBtn"
                    class="h-9 px-4 text-xs font-semibold rounded-md bg-orange-600 hover:bg-orange-700 text-white disabled:opacity-60">
                    <i class="fa-solid fa-floppy-disk mr-1.5"></i> Save
                </button>
            </div>
        </div>
    </div>

    <script>
        const LIST_URL = <?= json_encode(BASE_URL . '/ps-backend-promotiontimer-list') ?>;
        const SAVE_URL = <?= json_encode(BASE_URL . '/ps-backend-promotiontimer-save') ?>;
        const DELETE_URL = <?= json_encode(BASE_URL . '/ps-backend-promotiontimer-delete') ?>;
        let sizesByProduct = {};
        let colorsByProduct = {};
        let currentPromos = [];
        let currentTab = 'all';
        let pollTimer = null;
        let tickTimer = null;

        function escHtml(str) {
            return String(str ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        async function loadPromoTable() {
            try {
                const res = await fetch(LIST_URL);
                const data = await res.json();

                sizesByProduct = data.sizes_by_product || {};
                colorsByProduct = data.colors_by_product || {};

                const select = document.getElementById('promoProductId');
                if (select.options.length <= 1) {
                    (data.products || []).forEach(p => {
                        const opt = document.createElement('option');
                        opt.value = p.id;
                        opt.textContent = p.name;
                        select.appendChild(opt);
                    });
                }

                currentPromos = data.promos || [];
                renderPromoTable();
            } catch (e) {
                document.getElementById('promoTableBody').innerHTML =
                    `<tr><td colspan="6" class="text-center py-12 text-red-600">Could not load promos. Please try again.</td></tr>`;
            }
        }

        function computeStatus(startRaw, endRaw) {
            const now = new Date();
            const start = new Date(startRaw);
            const end = new Date(endRaw);
            if (now < start) return 'upcoming';
            if (now > end) return 'expired';
            return 'active';
        }

        function formatCountdown(ms) {
            if (ms <= 0) return '';
            const totalSec = Math.floor(ms / 1000);
            const d = Math.floor(totalSec / 86400);
            const h = Math.floor((totalSec % 86400) / 3600);
            const m = Math.floor((totalSec % 3600) / 60);
            const s = totalSec % 60;
            if (d > 0) return `${d}d ${h}h ${m}m`;
            if (h > 0) return `${h}h ${m}m ${s}s`;
            if (m > 0) return `${m}m ${s}s`;
            return `${s}s`;
        }

        const STATUS_STYLE = {
            active: { pill: 'bg-green-50 text-green-700 border-green-200', dot: 'bg-green-500', label: 'Active', sub: 'text-green-700' },
            upcoming: { pill: 'bg-amber-50 text-amber-700 border-amber-200', dot: 'bg-amber-500', label: 'Upcoming', sub: 'text-amber-700' },
            expired: { pill: 'bg-gray-100 text-gray-500 border-gray-200', dot: 'bg-gray-400', label: 'Expired', sub: 'text-gray-400' },
        };

        function renderPromoTable() {
            const body = document.getElementById('promoTableBody');
            const now = new Date();
            const q = (document.getElementById('promoSearch').value || '').trim().toLowerCase();

            // counts per status
            const counts = { all: currentPromos.length, active: 0, upcoming: 0, expired: 0 };
            const withStatus = currentPromos.map(p => {
                const status = computeStatus(p.start_date_raw, p.end_date_raw);
                counts[status]++;
                return { p, status };
            });
            document.querySelectorAll('[data-count]').forEach(el => el.textContent = counts[el.dataset.count] ?? 0);

            const rows = withStatus.filter(({ p, status }) =>
                (currentTab === 'all' || status === currentTab) &&
                (!q || String(p.product_name || '').toLowerCase().includes(q))
            );

            if (currentPromos.length === 0) {
                body.innerHTML = `
                <tr><td colspan="6" class="text-center py-14">
                    <i class="fa-regular fa-clock text-3xl text-gray-300"></i>
                    <p class="text-sm font-semibold text-gray-900 mt-3">No promos yet</p>
                    <p class="text-xs text-gray-500 mt-1">Click "Add Promo" to create your first one.</p>
                </td></tr>`;
                return;
            }
            if (rows.length === 0) {
                body.innerHTML = `<tr><td colspan="6" class="text-center py-12 text-gray-500">No promos match this filter.</td></tr>`;
                return;
            }

            body.innerHTML = rows.map(({ p, status }) => {
                const st = STATUS_STYLE[status];
                const color = p.colorname || 'All colors';
                const size = p.sizename || 'All sizes';

                let countdown = '';
                if (status === 'upcoming') countdown = `starts in ${formatCountdown(new Date(p.start_date_raw) - now)}`;
                else if (status === 'active') countdown = `ends in ${formatCountdown(new Date(p.end_date_raw) - now)}`;

                return `
                <tr class="hover:bg-gray-50 transition ${status === 'expired' ? 'opacity-70' : ''}" data-id="${escHtml(p.id)}">
                    <td class="px-5 py-3 font-semibold text-gray-900">${escHtml(p.product_name)}</td>
                    <td class="px-5 py-3 text-gray-600">
                        <span class="inline-flex px-2 py-0.5 rounded bg-gray-100 text-gray-700 text-[11px]">${escHtml(color)}</span>
                        <span class="inline-flex px-2 py-0.5 rounded bg-gray-100 text-gray-700 text-[11px] ml-1">${escHtml(size)}</span>
                    </td>
                    <td class="px-5 py-3">
                        <span class="inline-flex px-2 py-0.5 rounded bg-red-50 text-red-700 text-[11px] font-bold">-${escHtml(p.discount_percent)}%</span>
                    </td>
                    <td class="px-5 py-3 text-gray-600 leading-relaxed">
                        <div><span class="text-gray-400">Start</span> ${escHtml(p.start_date)}</div>
                        <div><span class="text-gray-400">End&nbsp;&nbsp;</span> ${escHtml(p.end_date)}</div>
                    </td>
                    <td class="px-5 py-3">
                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded border text-[11px] font-semibold ${st.pill}">
                            <span class="w-1.5 h-1.5 rounded-full ${st.dot}"></span>${st.label}
                        </span>
                        ${countdown ? `<div class="text-[10px] mt-1 font-medium ${st.sub}">${countdown}</div>` : ''}
                    </td>
                    <td class="px-5 py-3 text-right whitespace-nowrap">
                        <button type="button" onclick="editPromoById(${Number(p.id)})" title="Edit"
                            class="inline-flex items-center gap-1.5 h-8 px-3 text-xs font-semibold rounded-md border border-gray-300 bg-white text-gray-800 hover:bg-gray-50">
                            <i class="fa-solid fa-pen text-[10px]"></i> Edit
                        </button>
                        <button type="button" onclick="deletePromo(${Number(p.id)})" title="Delete" aria-label="Delete"
                            class="inline-flex items-center justify-center w-8 h-8 ml-1 rounded-md border border-gray-200 bg-white text-gray-400 hover:text-red-600 hover:border-red-200">
                            <i class="fa-solid fa-trash text-[11px]"></i>
                        </button>
                    </td>
                </tr>`;
            }).join('');
        }

        /* ── Tabs + search ───────────────────────────────── */
        document.querySelectorAll('.promo-tab').forEach(btn => {
            btn.addEventListener('click', () => {
                currentTab = btn.dataset.tab;
                document.querySelectorAll('.promo-tab').forEach(b => {
                    const on = b === btn;
                    b.classList.toggle('bg-gray-900', on);
                    b.classList.toggle('border-gray-900', on);
                    b.classList.toggle('text-white', on);
                    b.classList.toggle('bg-white', !on);
                    b.classList.toggle('border-gray-300', !on);
                    b.classList.toggle('text-gray-700', !on);
                    const c = b.querySelector('.promo-count');
                    c.classList.toggle('bg-white/15', on);
                    c.classList.toggle('text-white', on);
                    c.classList.toggle('bg-gray-100', !on);
                    c.classList.toggle('text-gray-600', !on);
                });
                renderPromoTable();
            });
        });
        document.getElementById('promoSearch').addEventListener('input', renderPromoTable);

        /* ── Selects ─────────────────────────────────────── */
        function populateSizeOptions(productId, preselectSize = '') {
            const sizeSelect = document.getElementById('promoSize');
            sizeSelect.innerHTML = '<option value="">All sizes</option>';
            (sizesByProduct[productId] || []).forEach(sz => {
                const opt = document.createElement('option');
                opt.value = sz;
                opt.textContent = sz;
                if (sz === preselectSize) opt.selected = true;
                sizeSelect.appendChild(opt);
            });
        }

        function populateColorOptions(productId, preselectColorId = '') {
            const colorSelect = document.getElementById('promoColorId');
            colorSelect.innerHTML = '<option value="">All colors</option>';
            (colorsByProduct[productId] || []).forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.id;
                opt.textContent = c.colorname;
                if (String(c.id) === String(preselectColorId)) opt.selected = true;
                colorSelect.appendChild(opt);
            });
        }

        document.getElementById('promoProductId').addEventListener('change', (e) => {
            populateSizeOptions(e.target.value);
            populateColorOptions(e.target.value);
        });

        /* ── Modal ───────────────────────────────────────── */
        function openPromoModal() {
            document.getElementById('promoModal').classList.remove('hidden');
            document.getElementById('promoModal').classList.add('flex');
            document.body.style.overflow = 'hidden';
        }
        function closePromoModal() {
            document.getElementById('promoModal').classList.add('hidden');
            document.getElementById('promoModal').classList.remove('flex');
            document.getElementById('promoError').classList.add('hidden');
            document.body.style.overflow = '';
        }
        document.getElementById('promoModal').addEventListener('click', e => {
            if (e.target.id === 'promoModal') closePromoModal();
        });
        document.addEventListener('keydown', e => { if (e.key === 'Escape') closePromoModal(); });

        document.getElementById('addPromoBtn').addEventListener('click', () => {
            document.getElementById('promoModalTitle').textContent = 'Add Promo';
            document.getElementById('promoId').value = '';
            document.getElementById('promoProductId').value = '';
            document.getElementById('promoDiscount').value = '';
            document.getElementById('promoStart').value = '';
            document.getElementById('promoEnd').value = '';
            document.getElementById('promoSize').innerHTML = '<option value="">All sizes</option>';
            document.getElementById('promoColorId').innerHTML = '<option value="">All colors</option>';
            openPromoModal();
        });

        function editPromo(p) {
            document.getElementById('promoModalTitle').textContent = 'Edit Promo';
            document.getElementById('promoId').value = p.id;
            document.getElementById('promoProductId').value = p.product_id;
            document.getElementById('promoDiscount').value = p.discount_percent;
            document.getElementById('promoStart').value = p.start_date_raw;
            document.getElementById('promoEnd').value = p.end_date_raw;
            populateSizeOptions(p.product_id, p.sizename || '');
            populateColorOptions(p.product_id, p.color_id || '');
            openPromoModal();
        }

        // Hinahanap ang promo sa listahan (iwas sira kapag may ' o " sa pangalan ng product)
        function editPromoById(id) {
            const p = currentPromos.find(x => Number(x.id) === Number(id));
            if (p) editPromo(p);
        }

        async function savePromo() {
            const errEl = document.getElementById('promoError');
            errEl.classList.add('hidden');

            const payload = {
                id: document.getElementById('promoId').value,
                product_id: document.getElementById('promoProductId').value,
                discount_percent: document.getElementById('promoDiscount').value,
                start_date: document.getElementById('promoStart').value,
                end_date: document.getElementById('promoEnd').value,
                sizename: document.getElementById('promoSize').value || null,
                color_id: document.getElementById('promoColorId').value || null,
            };

            if (!payload.product_id || !payload.discount_percent || !payload.start_date || !payload.end_date) {
                errEl.textContent = 'Complete all fields.';
                errEl.classList.remove('hidden');
                return;
            }
            if (payload.end_date <= payload.start_date) {
                errEl.textContent = 'End date must be after start date.';
                errEl.classList.remove('hidden');
                return;
            }

            const btn = document.getElementById('promoSaveBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1.5"></i> Saving…';

            try {
                const res = await fetch(SAVE_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (!data.ok) {
                    errEl.textContent = data.msg || 'Failed to save.';
                    errEl.classList.remove('hidden');
                } else {
                    closePromoModal();
                    loadPromoTable(); // refresh agad after save
                }
            } catch (e) {
                errEl.textContent = 'Something went wrong. Please try again.';
                errEl.classList.remove('hidden');
            }

            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-floppy-disk mr-1.5"></i> Save';
        }

        async function deletePromo(id) {
            if (!confirm('Delete this promo?')) return;
            const fd = new FormData();
            fd.append('id', id);
            await fetch(DELETE_URL, { method: 'POST', body: fd });
            loadPromoTable();
        }

        loadPromoTable();
        pollTimer = setInterval(loadPromoTable, 10000);   // sync sa server kada 10s
        tickTimer = setInterval(renderPromoTable, 1000);  // update status/countdown kada 1s
    </script>

</body>

</html>