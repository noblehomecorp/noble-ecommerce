<?php
// new-arrivals.php
// Expects $conn from the including page (page.php)
// Same card style as most-popularproduct.php (walang box, punong larawan)

$uploadUrl = $uploadUrl ?? BASE_URL . '/uploads/';

$newArrivals = [];
$newResult = $conn->query("
    SELECT
        p.id,
        p.name,
        p.imageproduct,
        p.description,
        p.category,
        p.created_at,
        MIN(v.pricesize) AS min_price,
        MAX(v.pricesize) AS max_price,
        AVG(r.rating) AS avg_rating,
        COUNT(DISTINCT r.id) AS review_count
    FROM nobleproduct p
    LEFT JOIN nobleproductcolor c ON c.product_id = p.id
    LEFT JOIN nobleproductvariant v ON v.color_id = c.id
    LEFT JOIN noblereview r ON r.product_id = p.id
    WHERE p.created_at >= (NOW() - INTERVAL 3 DAY)
    GROUP BY p.id
    ORDER BY RAND()
    LIMIT 10
");
while ($row = $newResult->fetch_assoc())
    $newArrivals[] = $row;
?>

<?php if (!empty($newArrivals)): ?>
    <div class="mb-8 md:mb-10">

        <!-- Header -->
        <div class="flex items-center justify-between gap-4 mb-4">
            <div class="flex items-center gap-2.5">
                <span class="w-1.5 h-5 bg-orange-500 rounded-sm"></span>
                <h2 class="text-base md:text-lg font-extrabold text-gray-900">New Arrivals</h2>
                <span class="hidden sm:inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-wider
                             text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-full">
                    <i class="fa-solid fa-bolt"></i> Just in
                </span>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <button type="button" id="newArrivalPrev" onclick="newArrivalSlide(-1)" aria-label="Previous"
                    class="hidden md:flex w-8 h-8 rounded-full border border-gray-200 bg-white items-center justify-center
                           text-gray-600 hover:border-orange-400 hover:text-orange-500 disabled:opacity-30
                           disabled:pointer-events-none transition-colors">
                    <i class="fa-solid fa-chevron-left text-xs"></i>
                </button>
                <button type="button" id="newArrivalNext" onclick="newArrivalSlide(1)" aria-label="Next"
                    class="hidden md:flex w-8 h-8 rounded-full border border-gray-200 bg-white items-center justify-center
                           text-gray-600 hover:border-orange-400 hover:text-orange-500 disabled:opacity-30
                           disabled:pointer-events-none transition-colors">
                    <i class="fa-solid fa-chevron-right text-xs"></i>
                </button>
                <a href="<?= BASE_URL ?>/shop"
                    class="ml-1 text-xs md:text-sm font-semibold text-orange-500 hover:text-orange-600 transition-colors">
                    View all →
                </a>
            </div>
        </div>

        <!-- Track -->
        <div id="newArrivalTrack" class="flex gap-3 overflow-x-auto scroll-smooth snap-x snap-mandatory py-1 -mx-1 px-1
                    [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
            <?php foreach ($newArrivals as $p):
                $min = floatval($p['min_price'] ?? 0);
                $max = floatval($p['max_price'] ?? 0);
                ?>
                <a href="<?= BASE_URL ?>/mainproductview?id=<?= (int) $p['id'] ?>"
                    aria-label="View details for <?= htmlspecialchars($p['name']) ?>"
                    class="group shrink-0 snap-start flex flex-col
                          w-[calc(50%-6px)] sm:w-[calc(33.333%-8px)] lg:w-[calc(20%-9.6px)]">

                    <!-- Image -->
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

                        <span class="absolute top-2 left-2 z-10 bg-emerald-500 text-white text-[10px] md:text-xs font-bold px-2 py-0.5 rounded-md shadow-sm">
                            NEW
                        </span>
                    </div>

                    <!-- Info -->
                    <div class="flex-1 flex flex-col pt-2.5 px-0.5">
                        <h3 class="text-xs md:text-sm font-medium text-gray-800 leading-snug line-clamp-2
                                   group-hover:text-orange-600 transition-colors">
                            <?= htmlspecialchars($p['name']) ?>
                        </h3>

                        <?php if (!empty($p['review_count']) && $p['review_count'] > 0): ?>
                            <div class="flex items-center gap-1.5 mt-1 text-[10px] md:text-xs text-gray-400">
                                <i class="fa-solid fa-star text-amber-400"></i>
                                <span class="font-semibold text-gray-700"><?= number_format($p['avg_rating'], 1) ?></span>
                                <span>(<?= (int) $p['review_count'] ?>)</span>
                            </div>
                        <?php endif; ?>

                        <!-- Price -->
                        <div class="mt-1.5 flex items-baseline gap-1">
                            <?php if ($min > 0 || $max > 0): ?>
                                <?php if ($min !== $max): ?>
                                    <span class="text-[10px] md:text-xs text-gray-400">From</span>
                                <?php endif; ?>
                                <span class="text-sm md:text-base font-bold text-gray-900">₱<?= number_format($min, 2) ?></span>
                            <?php else: ?>
                                <span class="text-[11px] md:text-xs text-gray-400">Price not set</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
        (function () {
            const track = document.getElementById('newArrivalTrack');
            if (!track) return;
            const prevBtn = document.getElementById('newArrivalPrev');
            const nextBtn = document.getElementById('newArrivalNext');
            const GAP = 12;

            window.newArrivalSlide = function (dir) {
                const card = track.querySelector('a');
                if (!card) return;
                const step = card.offsetWidth + GAP;
                const visible = Math.max(1, Math.floor((track.clientWidth + GAP) / step));
                track.scrollBy({ left: dir * visible * step, behavior: 'smooth' });
            };

            function updateArrows() {
                if (!prevBtn || !nextBtn) return;
                const maxScroll = track.scrollWidth - track.clientWidth;
                prevBtn.disabled = track.scrollLeft <= 4;
                nextBtn.disabled = maxScroll <= 4 || track.scrollLeft >= maxScroll - 4;
            }

            track.addEventListener('scroll', updateArrows, { passive: true });
            window.addEventListener('resize', updateArrows);
            window.addEventListener('load', updateArrows);
            updateArrows();
        })();
    </script>
<?php endif; ?>