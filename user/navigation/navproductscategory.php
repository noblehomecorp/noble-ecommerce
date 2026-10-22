<?php
// navproductscategory.php
// "Shop by Category" mega menu (side-tab style, e-commerce pattern).
// Included inside top.php's category bar. The panel is anchored to the sticky
// #site-header, so it always sits right below the header kahit lumiit ito on scroll.
//
// Layout:  [ Categories (left tabs) ] [ Subcategories ] [ Recommended products ]
// - Hover/focus a category  -> right side switches to that category
// - Hover/focus a subcategory -> product cards switch to that subcategory
// - Fixed height, walang floating preview, kaya wala nang scroll na sumisira sa layout

$categories = [];
$cat_result = $conn->query("SELECT id, name FROM noblecategory ORDER BY name ASC");
while ($cat = $cat_result->fetch_assoc()) {
    $categories[$cat['id']] = ['name' => $cat['name'], 'subcategories' => []];
}

$sub_result = $conn->query("SELECT id, category_id, name FROM noblesubcategory ORDER BY name ASC");
while ($sub = $sub_result->fetch_assoc()) {
    $cid = $sub['category_id'];
    if (isset($categories[$cid])) {
        $categories[$cid]['subcategories'][$sub['id']] = ['name' => $sub['name'], 'products' => []];
    }
}

// Products WITH price + discount
$prod_result = $conn->query("
    SELECT
        p.id, p.name, p.imageproduct, p.description, ps.subcategory_id,
        MIN(v.pricesize) AS min_price,
        MAX(v.pricesize) AS max_price,
        MAX(v.discountvariant) AS max_discount,
        MIN(v.pricesize - (v.pricesize * v.discountvariant / 100)) AS min_discounted_price,
        MAX(v.pricesize - (v.pricesize * v.discountvariant / 100)) AS max_discounted_price
    FROM nobleproduct p
    INNER JOIN nobleproduct_subcategory ps ON ps.product_id = p.id
    LEFT JOIN nobleproductcolor c ON c.product_id = p.id
    LEFT JOIN nobleproductvariant v ON v.color_id = c.id
    GROUP BY p.id, ps.subcategory_id
    ORDER BY p.name ASC
");
while ($prod = $prod_result->fetch_assoc()) {
    $sid = $prod['subcategory_id'];
    foreach ($categories as $cid => &$cat) {
        if (isset($cat['subcategories'][$sid])) {
            $cat['subcategories'][$sid]['products'][] = $prod;
            break;
        }
    }
    unset($cat);
}

// Categories na may laman lang ang ipapakita
$menuCats = array_filter($categories, fn($c) => !empty($c['subcategories']));
$firstCid = array_key_first($menuCats);
$productsTriggerActive = !empty($isShopActive);

if (!function_exists('nhMenuName')) {
    // Lahat ng category/subcategory name ay CAPITAL LETTERS.
    // May display fix din para sa mga pangalang walang space sa DB (hal. BATHROOMFIXTURES).
    function nhMenuName(string $name): string
    {
        $name = mb_strtolower(trim($name));
        $name = preg_replace('/(?<=[a-z])(fixtures?|materials?|supplies|accessories)$/u', ' $1', $name);
        return mb_strtoupper($name);
    }
}

if (!function_exists('nhMenuIsSale')) {
    function nhMenuIsSale(array $prod): bool
    {
        return !empty($prod['max_discount']) && $prod['max_discount'] > 0;
    }
}

if (!function_exists('nhMenuProductCard')) {
    // Server-side product card (escaped). Same price/discount logic as before.
    function nhMenuProductCard(array $prod): string
    {
        $e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

        $min = floatval($prod['min_price'] ?? 0);
        $max = floatval($prod['max_price'] ?? 0);
        $isSale = nhMenuIsSale($prod);

        $priceLabel = ($min > 0 || $max > 0)
            ? '₱' . number_format($min, 2) . ($min !== $max ? ' – ₱' . number_format($max, 2) : '')
            : '';

        $discLabel = '';
        $discPct = '';
        if ($isSale) {
            $dMin = floatval($prod['min_discounted_price'] ?? 0);
            $dMax = floatval($prod['max_discounted_price'] ?? 0);
            $discLabel = '₱' . number_format($dMin, 2) . ($dMin !== $dMax ? ' – ₱' . number_format($dMax, 2) : '');
            $discPct = rtrim(rtrim(number_format($prod['max_discount'], 2), '0'), '.');
        }

        $img = !empty($prod['imageproduct']) ? BASE_URL . '/uploads/' . $prod['imageproduct'] : '';

        ob_start(); ?>
        <a href="<?= BASE_URL ?>/mainproductview/<?= (int) $prod['id'] ?>"
            class="nh-menu-card block bg-white rounded-lg border border-gray-100 overflow-hidden hover:border-orange-300 hover:shadow-md transition-all duration-150">
            <div class="relative h-28 bg-gray-50 flex items-center justify-center p-2">
                <?php if ($isSale): ?>
                    <span class="absolute top-1.5 left-1.5 bg-red-500 text-white text-[9px] font-bold px-1.5 py-0.5 rounded">-<?= $e($discPct) ?>%</span>
                <?php endif; ?>
                <?php if ($img): ?>
                    <img src="<?= $e($img) ?>" alt="<?= $e($prod['name']) ?>" loading="lazy"
                        class="max-h-full max-w-full object-contain" onerror="this.style.display='none'">
                <?php else: ?>
                    <i class="fa-regular fa-image text-2xl text-gray-300"></i>
                <?php endif; ?>
            </div>
            <div class="p-2.5">
                <p class="nh-menu-card-name text-xs font-semibold text-gray-900 leading-snug line-clamp-2 min-h-[2rem]">
                    <?= $e($prod['name']) ?>
                </p>
                <div class="mt-1">
                    <?php if ($isSale && $discLabel): ?>
                        <span class="text-xs font-bold text-red-500"><?= $e($discLabel) ?></span>
                        <span class="block text-[10px] text-gray-400 line-through"><?= $e($priceLabel) ?></span>
                    <?php elseif ($priceLabel): ?>
                        <span class="text-xs font-bold text-gray-900"><?= $e($priceLabel) ?></span>
                    <?php else: ?>
                        <span class="text-[11px] text-gray-400 italic">Price not set</span>
                    <?php endif; ?>
                </div>
            </div>
        </a>
        <?php
        return ob_get_clean();
    }
}

if (!function_exists('nhMenuProductGrid')) {
    function nhMenuProductGrid(array $products, string $gridKey, bool $hidden): string
    {
        ob_start(); ?>
        <div data-grid="<?= htmlspecialchars($gridKey, ENT_QUOTES) ?>" class="<?= $hidden ? 'hidden' : '' ?>">
            <?php if (empty($products)): ?>
                <div class="h-40 flex flex-col items-center justify-center text-center rounded-lg border border-dashed border-gray-200">
                    <i class="fa-solid fa-box-open text-xl text-gray-300"></i>
                    <p class="text-xs text-gray-400 mt-2">No regular-priced items to show.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-2 xl:grid-cols-4 gap-3">
                    <?php foreach ($products as $p) echo nhMenuProductCard($p); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}
?>

<style>
    /* Panel height: fixed, pero hindi lalampas sa screen */
    .nh-mega-body {
        height: min(440px, calc(100vh - 180px));
    }

    .nh-thin-scroll {
        scrollbar-width: thin;
        scrollbar-color: #e5e7eb transparent;
    }

    .nh-thin-scroll::-webkit-scrollbar { width: 6px; }
    .nh-thin-scroll::-webkit-scrollbar-thumb { background: #e5e7eb; border-radius: 9999px; }

    /* Left category tabs */
    .nh-cat-item {
        border-left: 3px solid transparent;
    }

    .nh-cat-item[data-active="true"] {
        background: #fff;
        color: #ea580c;
        border-left-color: #f97316;
        font-weight: 600;
    }

    .nh-cat-item[data-active="true"] .nh-cat-initial {
        background: #f97316;
        color: #fff;
    }

    .nh-cat-item[data-active="true"] .nh-cat-chev {
        opacity: 1;
        color: #f97316;
    }

    /* Subcategory rows */
    .nh-sub-item[data-active="true"] {
        background: #fff7ed;
        color: #ea580c;
    }

    .nh-sub-item[data-active="true"] .nh-sub-count {
        background: #f97316;
        color: #fff;
    }

    .nh-menu-card:hover .nh-menu-card-name {
        color: #ea580c;
    }

    /* Dim the page while the mega menu is open (hover sa trigger o panel) */
    #site-header:has(#desktop-products-dropdown:hover) #page-dim-backdrop {
        display: block;
    }
</style>

<!-- Page dim: nasa LABAS ng hover group, kaya pag tinamaan ng mouse, sasara ang menu -->
<div id="page-dim-backdrop" class="hidden absolute left-0 right-0 top-full h-screen bg-gray-900/40 z-40"></div>

<!-- Walang `relative` dito on purpose: ang panel ay naka-anchor sa sticky #site-header -->
<div class="group h-full flex items-center" id="desktop-products-dropdown">

    <a href="<?= BASE_URL ?>/shop"
        class="flex items-center gap-2 h-full text-sm font-semibold transition-colors duration-150 focus:outline-none
               <?= $productsTriggerActive ? 'text-orange-500' : 'text-gray-900 hover:text-orange-500 group-hover:text-orange-500' ?>">
        <i class="fa-solid fa-bars text-xs"></i>
        Shop by Category
        <i class="fa-solid fa-caret-down text-xs transition-transform duration-200 group-hover:rotate-180"></i>
    </a>

    <!-- Mega panel -->
    <div class="absolute left-0 right-0 top-full bg-white border-t-2 border-orange-500 shadow-2xl
                opacity-0 invisible group-hover:opacity-100 group-hover:visible
                transition-all duration-150 z-50">

        <?php if (empty($menuCats)): ?>
            <div class="max-w-screen-xl mx-auto px-4 py-10 text-center text-sm text-gray-400">
                No categories yet.
            </div>
        <?php else: ?>
            <div class="nh-mega-body max-w-screen-xl mx-auto flex border-x border-gray-100">

                <!-- LEFT: Category tabs -->
                <div class="w-60 shrink-0 bg-gray-50 border-r border-gray-100 flex flex-col">
                    <p class="px-4 pt-4 pb-2 text-[10px] font-bold uppercase tracking-widest text-gray-400">
                        All Categories
                    </p>
                    <nav class="flex-1 overflow-y-auto nh-thin-scroll pb-2">
                        <?php foreach ($menuCats as $cid => $cat): ?>
                            <a href="<?= BASE_URL ?>/productcategory?id=<?= (int) $cid ?>"
                                class="nh-cat-item flex items-center gap-3 px-4 py-2.5 text-[13px] font-medium text-gray-700 hover:text-orange-600 transition-colors"
                                data-cat-item="<?= (int) $cid ?>" data-active="<?= $cid === $firstCid ? 'true' : 'false' ?>">
                                <span class="nh-cat-initial w-7 h-7 rounded-md bg-white border border-gray-200 flex items-center justify-center text-[11px] font-bold text-gray-500 shrink-0 transition-colors">
                                    <?= htmlspecialchars(mb_strtoupper(mb_substr($cat['name'], 0, 1))) ?>
                                </span>
                                <span class="flex-1 truncate tracking-wide"><?= htmlspecialchars(nhMenuName($cat['name'])) ?></span>
                                <i class="nh-cat-chev fa-solid fa-chevron-right text-[10px] text-gray-300 opacity-0 transition-opacity"></i>
                            </a>
                        <?php endforeach; ?>
                    </nav>
                    <a href="<?= BASE_URL ?>/shop"
                        class="flex items-center justify-center gap-2 m-3 py-2 rounded-md bg-gray-900 text-white text-xs font-semibold hover:bg-orange-500 transition-colors">
                        <i class="fa-solid fa-store"></i> View All Products
                    </a>
                </div>

                <!-- RIGHT: one pane per category -->
                <div class="flex-1 min-w-0">
                    <?php foreach ($menuCats as $cid => $cat):
                        // Default grid ng category: first 4 non-sale products across its subcategories
                        $catProducts = [];
                        $seen = [];
                        foreach ($cat['subcategories'] as $sub) {
                            foreach ($sub['products'] as $p) {
                                if (nhMenuIsSale($p) || isset($seen[$p['id']])) continue;
                                $seen[$p['id']] = true;
                                $catProducts[] = $p;
                                if (count($catProducts) >= 4) break 2;
                            }
                        }
                        $catNameNice = nhMenuName($cat['name']);
                        ?>
                        <div class="nh-cat-pane h-full flex-col <?= $cid === $firstCid ? 'flex' : 'hidden' ?>"
                            data-cat-pane="<?= (int) $cid ?>">

                            <!-- Pane header -->
                            <div class="flex items-center justify-between px-6 py-3.5 border-b border-gray-100 shrink-0">
                                <div>
                                    <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400">Category</p>
                                    <h3 class="text-base font-extrabold tracking-wide text-gray-900 leading-tight">
                                        <?= htmlspecialchars($catNameNice) ?>
                                    </h3>
                                </div>
                                <a href="<?= BASE_URL ?>/productcategory?id=<?= (int) $cid ?>"
                                    class="text-xs font-semibold text-orange-500 hover:text-orange-600 transition-colors">
                                    Shop all <?= htmlspecialchars($catNameNice) ?> →
                                </a>
                            </div>

                            <div class="flex flex-1 min-h-0">
                                <!-- Subcategories -->
                                <div class="w-56 shrink-0 border-r border-gray-100 overflow-y-auto nh-thin-scroll p-3">
                                    <p class="px-2 pb-2 text-[10px] font-bold uppercase tracking-widest text-gray-400">
                                        Subcategories
                                    </p>
                                    <ul class="space-y-0.5">
                                        <?php foreach ($cat['subcategories'] as $sid => $sub): ?>
                                            <li>
                                                <a href="<?= BASE_URL ?>/productcategory?id=<?= (int) $cid ?>&sub=<?= (int) $sid ?>"
                                                    class="nh-sub-item flex items-center justify-between gap-2 px-2.5 py-2 rounded-md text-[13px] font-medium text-gray-600 hover:bg-orange-50 hover:text-orange-600 transition-colors"
                                                    data-sub-item="<?= (int) $sid ?>"
                                                    data-sub-name="<?= htmlspecialchars(nhMenuName($sub['name'])) ?>">
                                                    <span class="truncate tracking-wide"><?= htmlspecialchars(nhMenuName($sub['name'])) ?></span>
                                                    <span class="nh-sub-count shrink-0 text-[10px] font-semibold px-1.5 py-0.5 rounded-full bg-gray-100 text-gray-500 transition-colors">
                                                        <?= count($sub['products']) ?>
                                                    </span>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>

                                <!-- Recommended products -->
                                <div class="flex-1 min-w-0 overflow-y-auto nh-thin-scroll p-5">
                                    <div class="flex items-center justify-between mb-3">
                                        <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400">
                                            Recommended <span class="nh-rec-label normal-case tracking-normal text-gray-500 font-semibold"></span>
                                        </p>
                                    </div>

                                    <?= nhMenuProductGrid($catProducts, 'cat', false) ?>

                                    <?php foreach ($cat['subcategories'] as $sid => $sub):
                                        $subProducts = array_slice(
                                            array_values(array_filter($sub['products'], fn($p) => !nhMenuIsSale($p))),
                                            0,
                                            4
                                        );
                                        echo nhMenuProductGrid($subProducts, 'sub-' . (int) $sid, true);
                                    endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    (function () {
        const root = document.getElementById('desktop-products-dropdown');
        if (!root) return;

        const catItems = root.querySelectorAll('[data-cat-item]');
        const panes = root.querySelectorAll('[data-cat-pane]');
        if (!catItems.length) return;

        const firstCid = catItems[0].dataset.catItem;
        let hoverTimer = null;

        function showGrid(pane, key, label) {
            pane.querySelectorAll('[data-grid]').forEach(g => {
                g.classList.toggle('hidden', g.dataset.grid !== key);
            });
            const labelEl = pane.querySelector('.nh-rec-label');
            if (labelEl) labelEl.textContent = label ? '· ' + label : '';
        }

        function resetSubs(pane) {
            pane.querySelectorAll('[data-sub-item]').forEach(s => s.dataset.active = 'false');
            showGrid(pane, 'cat', '');
        }

        function activateCat(cid) {
            catItems.forEach(i => i.dataset.active = String(i.dataset.catItem === cid));
            panes.forEach(p => {
                const on = p.dataset.catPane === cid;
                p.classList.toggle('hidden', !on);
                p.classList.toggle('flex', on);
                if (on) resetSubs(p);
            });
        }

        // Category tabs — maliit na delay (hover intent) para hindi magpalit-palit
        // ang category habang pa-diagonal ang galaw ng mouse papunta sa kanan.
        catItems.forEach(item => {
            item.addEventListener('mouseenter', () => {
                clearTimeout(hoverTimer);
                hoverTimer = setTimeout(() => activateCat(item.dataset.catItem), 90);
            });
            item.addEventListener('mouseleave', () => clearTimeout(hoverTimer));
            item.addEventListener('focus', () => activateCat(item.dataset.catItem));
        });

        // Subcategory hover — palitan ang product cards
        panes.forEach(pane => {
            pane.querySelectorAll('[data-sub-item]').forEach(sub => {
                const activate = () => {
                    pane.querySelectorAll('[data-sub-item]').forEach(s => s.dataset.active = String(s === sub));
                    showGrid(pane, 'sub-' + sub.dataset.subItem, sub.dataset.subName);
                };
                sub.addEventListener('mouseenter', activate);
                sub.addEventListener('focus', activate);
            });
        });

        // Pag sumara ang menu, balik sa unang category para fresh sa susunod na bukas
        root.addEventListener('mouseleave', () => {
            clearTimeout(hoverTimer);
            setTimeout(() => {
                if (!root.matches(':hover')) activateCat(firstCid);
            }, 200);
        });
    })();

    // ---- Mobile toggles (kept for pages that render mobile product menus) ----
    document.querySelectorAll('.mobile-sub-toggle').forEach(btn => {
        btn.addEventListener('click', function () {
            const target = document.getElementById(this.dataset.target);
            const chevron = this.querySelector('.mobile-chevron');
            if (!target) return;
            target.classList.toggle('hidden');
            chevron?.classList.toggle('rotate-180');
        });
    });

    (function () {
        const toggle = document.getElementById('mobile-products-toggle');
        const menu = document.getElementById('mobile-products-menu');
        const chevron = document.getElementById('products-chevron');
        if (toggle) {
            toggle.addEventListener('click', () => {
                menu?.classList.toggle('hidden');
                chevron?.classList.toggle('rotate-180');
            });
        }
    })();
</script>