<?php
// producttabs.php
// Expects from mainproductview.php: $product, $reviews, $totalReviews, $avgRating, $ratingBreakdown, $uploadUrl

$specs = !empty($product['specifications']) ? json_decode($product['specifications'], true) : [];
$gallery = !empty($product['gallery']) ? json_decode($product['gallery'], true) : [];
$specs = is_array($specs) ? $specs : [];
$gallery = is_array($gallery) ? array_values($gallery) : [];

// Unang tab na bukas: Specifications kung meron, kung wala Reviews
$defaultTab = !empty($specs) ? 'specs' : 'reviews';

$tabActive = 'text-gray-900 border-gray-900';
$tabIdle = 'text-gray-500 border-transparent hover:text-gray-900';
?>

<!-- ════════ Tabs: Specifications / Gallery / Reviews ════════ -->
<div class="bg-white rounded-lg border border-gray-200 mt-4 md:mt-6">

    <!-- Tab Nav -->
    <div class="flex border-b border-gray-200 overflow-x-auto px-4 md:px-8 gap-6 md:gap-8
                [scrollbar-width:none] [&::-webkit-scrollbar]:hidden" role="tablist">
        <?php if (!empty($specs)): ?>
            <button type="button" onclick="switchTab('specs')" id="tab-specs" role="tab"
                class="tab-btn -mb-px py-4 text-sm font-semibold border-b-2 whitespace-nowrap transition <?= $defaultTab === 'specs' ? $tabActive : $tabIdle ?>">
                Specifications
            </button>
        <?php endif; ?>
        <?php if (!empty($gallery)): ?>
            <button type="button" onclick="switchTab('gallery')" id="tab-gallery" role="tab"
                class="tab-btn -mb-px py-4 text-sm font-semibold border-b-2 whitespace-nowrap transition <?= $tabIdle ?>">
                Gallery <span class="ml-1 text-xs font-normal text-gray-400">(<?= count($gallery) ?>)</span>
            </button>
        <?php endif; ?>
        <button type="button" onclick="switchTab('reviews')" id="tab-reviews" role="tab"
            class="tab-btn -mb-px py-4 text-sm font-semibold border-b-2 whitespace-nowrap transition <?= $defaultTab === 'reviews' ? $tabActive : $tabIdle ?>">
            Reviews <span class="ml-1 text-xs font-normal text-gray-400">(<?= (int) $totalReviews ?>)</span>
        </button>
    </div>

    <!-- Specifications -->
    <?php if (!empty($specs)): ?>
        <div id="panel-specs" class="p-4 md:p-8 <?= $defaultTab === 'specs' ? '' : 'hidden' ?>">
            <dl class="border border-gray-200 rounded-md divide-y divide-gray-200 overflow-hidden text-sm max-w-3xl">
                <?php foreach ($specs as $key => $val): ?>
                    <div class="grid grid-cols-[minmax(120px,35%)_1fr]">
                        <dt class="bg-gray-50 px-4 py-3 font-medium text-gray-600 border-r border-gray-200">
                            <?= htmlspecialchars($key) ?>
                        </dt>
                        <dd class="px-4 py-3 text-gray-900">
                            <?= htmlspecialchars(is_array($val) ? implode(', ', $val) : $val) ?>
                        </dd>
                    </div>
                <?php endforeach; ?>
            </dl>
        </div>
    <?php endif; ?>

    <!-- Gallery -->
    <?php if (!empty($gallery)): ?>
        <div id="panel-gallery" class="p-4 md:p-8 hidden">
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                <?php foreach ($gallery as $file): ?>
                    <button type="button" onclick="openLightbox(<?= htmlspecialchars(json_encode($uploadUrl . $file), ENT_QUOTES) ?>)"
                        class="group aspect-square rounded-md overflow-hidden border border-gray-200 bg-gray-50">
                        <img src="<?= $uploadUrl . htmlspecialchars($file) ?>" alt="<?= htmlspecialchars($product['name']) ?>"
                            loading="lazy" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Reviews -->
    <div id="panel-reviews" class="p-4 md:p-8 <?= $defaultTab === 'reviews' ? '' : 'hidden' ?>">

        <?php if ($totalReviews === 0): ?>
            <div class="text-center py-12">
                <i class="fa-regular fa-comment-dots text-3xl text-gray-300"></i>
                <p class="text-sm font-medium text-gray-700 mt-3">No reviews yet</p>
                <p class="text-xs text-gray-500 mt-1">Reviews from verified buyers will appear here.</p>
            </div>
        <?php else: ?>

            <div class="grid grid-cols-1 lg:grid-cols-[280px_1fr] gap-8">

                <!-- Summary -->
                <div class="lg:border-r lg:border-gray-200 lg:pr-8">
                    <p class="text-sm font-semibold text-gray-900">Customer Reviews</p>
                    <div class="flex items-end gap-2 mt-3">
                        <span class="text-4xl font-bold text-gray-900 leading-none"><?= number_format($avgRating, 1) ?></span>
                        <span class="text-sm text-gray-500 mb-0.5">out of 5</span>
                    </div>
                    <div class="flex items-center gap-0.5 mt-2">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="fa-star text-sm <?= $i <= round($avgRating) ? 'fa-solid text-amber-400' : 'fa-regular text-gray-300' ?>"></i>
                        <?php endfor; ?>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Based on <?= $totalReviews ?> review<?= $totalReviews !== 1 ? 's' : '' ?></p>

                    <div class="space-y-2 mt-5">
                        <?php for ($star = 5; $star >= 1; $star--):
                            $pct = $totalReviews > 0 ? round(($ratingBreakdown[$star] / $totalReviews) * 100) : 0; ?>
                            <div class="flex items-center gap-3 text-xs">
                                <span class="text-gray-600 w-10 shrink-0"><?= $star ?> star</span>
                                <div class="flex-1 h-2 bg-gray-100 rounded-sm overflow-hidden">
                                    <div class="h-full bg-amber-400" style="width: <?= $pct ?>%"></div>
                                </div>
                                <span class="text-gray-500 w-8 text-right shrink-0"><?= $pct ?>%</span>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>

                <!-- Review list -->
                <div class="divide-y divide-gray-200 -mt-5">
                    <?php foreach ($reviews as $r):
                        $initial = strtoupper(mb_substr(trim($r['name'] ?? '?'), 0, 1)); ?>
                        <article class="py-5">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-full bg-gray-100 overflow-hidden shrink-0 flex items-center justify-center text-sm font-semibold text-gray-600">
                                    <?php if (!empty($r['avatar'])): ?>
                                        <img src="<?= htmlspecialchars($r['avatar']) ?>" alt="" referrerpolicy="no-referrer"
                                            class="w-full h-full object-cover" loading="lazy"
                                            onerror="this.replaceWith(document.createTextNode(<?= htmlspecialchars(json_encode($initial), ENT_QUOTES) ?>))">
                                    <?php else: ?>
                                        <?= htmlspecialchars($initial) ?>
                                    <?php endif; ?>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold text-gray-900 truncate"><?= htmlspecialchars($r['name']) ?></p>
                                    <p class="text-xs text-gray-500"><?= htmlspecialchars(date('F j, Y', strtotime($r['created_at']))) ?></p>
                                </div>
                            </div>
                            <div class="flex items-center gap-0.5 mt-3">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fa-star text-xs <?= $i <= (int) $r['rating'] ? 'fa-solid text-amber-400' : 'fa-regular text-gray-300' ?>"></i>
                                <?php endfor; ?>
                            </div>
                            <?php if (!empty($r['comment'])): ?>
                                <p class="text-sm text-gray-700 leading-relaxed mt-2">
                                    <?= nl2br(htmlspecialchars($r['comment'])) ?>
                                </p>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>

        <?php endif; ?>
    </div>
</div>

<!-- Lightbox (para sa Gallery) -->
<div id="lightbox" onclick="closeLightbox()"
    class="fixed inset-0 z-50 bg-black/85 items-center justify-center hidden p-4 md:p-8" style="display:none">
    <button type="button" onclick="closeLightbox()" aria-label="Close"
        class="absolute top-4 right-4 w-10 h-10 rounded-full bg-white/15 hover:bg-white/25 text-white flex items-center justify-center transition z-10">
        <i class="fa-solid fa-xmark"></i>
    </button>
    <img id="lightbox-img" src="" alt="" onclick="event.stopPropagation()"
        class="max-w-full max-h-[90vh] object-contain rounded-md">
</div>

<script>
    // ─── Tabs (Specs / Gallery / Reviews) + Lightbox ───────────────────
    const NH_TAB_ACTIVE = ['text-gray-900', 'border-gray-900'];
    const NH_TAB_IDLE = ['text-gray-500', 'border-transparent', 'hover:text-gray-900'];

    function switchTab(tab) {
        ['specs', 'gallery', 'reviews'].forEach(p => {
            const panel = document.getElementById('panel-' + p);
            const btn = document.getElementById('tab-' + p);
            if (!panel || !btn) return;
            const on = p === tab;
            panel.classList.toggle('hidden', !on);
            NH_TAB_ACTIVE.forEach(c => btn.classList.toggle(c, on));
            NH_TAB_IDLE.forEach(c => btn.classList.toggle(c, !on));
            btn.setAttribute('aria-selected', on ? 'true' : 'false');
        });
    }

    function openLightbox(src) {
        const box = document.getElementById('lightbox');
        document.getElementById('lightbox-img').src = src;
        box.classList.remove('hidden');
        box.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function closeLightbox() {
        const box = document.getElementById('lightbox');
        box.classList.add('hidden');
        box.style.display = 'none';
        document.body.style.overflow = '';
    }

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') closeLightbox();
    });
</script>