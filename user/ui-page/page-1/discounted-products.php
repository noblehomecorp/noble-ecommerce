<?php
// discounted-products.php
// Expects $conn from the including page (page.php)
// Same card style as most-popularproduct.php (walang box, punong larawan)

$uploadUrl = $uploadUrl ?? BASE_URL . '/uploads/';

if (!function_exists('formatSoldCount')) {
    function formatSoldCount($n)
    {
        $n = intval($n);
        if ($n >= 1000) {
            return rtrim(rtrim(number_format($n / 1000, 1), '0'), '.') . 'K';
        }
        return number_format($n);
    }
}

$discountedProducts = [];
$discResult = $conn->query("
    SELECT
        p.id,
        p.name,
        p.imageproduct,
        p.description,
        p.category,
        MIN(v.pricesize) AS min_price,
        MAX(v.pricesize) AS max_price,
        MAX(v.discountvariant) AS max_discount,
        MIN(v.pricesize - (v.pricesize * v.discountvariant / 100)) AS min_discounted_price,
        MAX(v.pricesize - (v.pricesize * v.discountvariant / 100)) AS max_discounted_price,
        rv.avg_rating,
        rv.review_count,
        (
            SELECT COALESCE(SUM(v2.sold), 0)
            FROM nobleproductvariant v2
            JOIN nobleproductcolor c2 ON c2.id = v2.color_id
            WHERE c2.product_id = p.id
        ) AS total_sold
    FROM nobleproduct p
    INNER JOIN nobleproductcolor c ON c.product_id = p.id
    INNER JOIN nobleproductvariant v ON v.color_id = c.id
    LEFT JOIN (
        SELECT product_id, AVG(rating) AS avg_rating, COUNT(*) AS review_count
        FROM noblereview
        GROUP BY product_id
    ) rv ON rv.product_id = p.id
    WHERE v.discountvariant > 0
    GROUP BY p.id
    ORDER BY max_discount DESC
");
while ($row = $discResult->fetch_assoc())
    $discountedProducts[] = $row;

$discSavedIds = [];
if (isset($_SESSION['user_id'])) {
    $uid = (int) $_SESSION['user_id'];
    $discSavedResult = $conn->query("SELECT product_id FROM noblesavedproduct WHERE user_id = $uid");
    if ($discSavedResult) {
        while ($row = $discSavedResult->fetch_assoc()) {
            $discSavedIds[] = (int) $row['product_id'];
        }
    }
}
?>

<?php if (!empty($discountedProducts)): ?>
    <div class="mb-8 md:mb-10">

        <!-- Header -->
        <div class="flex items-center justify-between gap-4 mb-4">
            <div class="flex items-center gap-2.5">
                <span class="w-1.5 h-5 bg-orange-500 rounded-sm"></span>
                <h2 class="text-base md:text-lg font-extrabold text-gray-900">Today's Deals</h2>
                <span class="hidden sm:inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-wider
                             text-red-600 bg-red-50 px-2 py-0.5 rounded-full">
                    <i class="fa-solid fa-tag"></i> Sale
                </span>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <button type="button" id="discountPrev" onclick="discountSlide(-1)" aria-label="Previous"
                    class="hidden md:flex w-8 h-8 rounded-full border border-gray-200 bg-white items-center justify-center
                           text-gray-600 hover:border-orange-400 hover:text-orange-500 disabled:opacity-30
                           disabled:pointer-events-none transition-colors">
                    <i class="fa-solid fa-chevron-left text-xs"></i>
                </button>
                <button type="button" id="discountNext" onclick="discountSlide(1)" aria-label="Next"
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
        <div id="discountTrack" class="flex gap-3 overflow-x-auto scroll-smooth snap-x snap-mandatory py-1 -mx-1 px-1
                    [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
            <?php foreach ($discountedProducts as $p):
                $dMin = floatval($p['min_discounted_price'] ?? 0);
                $dMax = floatval($p['max_discounted_price'] ?? 0);
                $oMin = floatval($p['min_price'] ?? 0);
                $pct = rtrim(rtrim(number_format($p['max_discount'], 2), '0'), '.');
                $isSaved = in_array((int) $p['id'], $discSavedIds, true);
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

                        <!-- Discount badge -->
                        <span class="absolute top-2 left-2 z-10 bg-red-500 text-white text-[10px] md:text-xs font-bold px-2 py-0.5 rounded-md shadow-sm">
                            -<?= $pct ?>%
                        </span>

                        <!-- Actions -->
                        <div class="absolute top-2 right-2 z-10 flex flex-col gap-1.5">
                            <button type="button" class="save-btn w-8 h-8 rounded-full bg-white shadow-sm
                                       flex items-center justify-center transition-all
                                       <?= $isSaved ? '' : 'md:opacity-0 md:group-hover:opacity-100' ?>"
                                data-product-id="<?= (int) $p['id'] ?>" aria-label="Save to favorites">
                                <i class="<?= $isSaved ? 'fa-solid text-red-500' : 'fa-regular text-gray-500' ?> fa-heart text-sm"></i>
                            </button>                        </div>
                    </div>

                    <!-- Info -->
                    <div class="flex-1 flex flex-col pt-2.5 px-0.5">
                        <h3 class="text-xs md:text-sm font-medium text-gray-800 leading-snug line-clamp-2
                                   group-hover:text-orange-600 transition-colors">
                            <?= htmlspecialchars($p['name']) ?>
                        </h3>

                        <?php if ((!empty($p['review_count']) && $p['review_count'] > 0) || (!empty($p['total_sold']) && $p['total_sold'] > 0)): ?>
                            <div class="flex items-center gap-1.5 mt-1 text-[10px] md:text-xs text-gray-400">
                                <?php if (!empty($p['review_count']) && $p['review_count'] > 0): ?>
                                    <i class="fa-solid fa-star text-amber-400"></i>
                                    <span class="font-semibold text-gray-700"><?= number_format($p['avg_rating'], 1) ?></span>
                                    <span>(<?= (int) $p['review_count'] ?>)</span>
                                <?php endif; ?>
                                <?php if (!empty($p['review_count']) && $p['review_count'] > 0 && !empty($p['total_sold']) && $p['total_sold'] > 0): ?>
                                    <span class="text-gray-300">|</span>
                                <?php endif; ?>
                                <?php if (!empty($p['total_sold']) && $p['total_sold'] > 0): ?>
                                    <span><?= formatSoldCount($p['total_sold']) ?> sold</span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Price -->
                        <div class="mt-1.5 flex items-baseline gap-1.5 flex-wrap">
                            <?php if ($dMin !== $dMax): ?>
                                <span class="text-[10px] md:text-xs text-gray-400">From</span>
                            <?php endif; ?>
                            <span class="text-sm md:text-base font-bold text-red-600">₱<?= number_format($dMin, 2) ?></span>
                            <span class="text-[10px] md:text-xs text-gray-400 line-through">₱<?= number_format($oMin, 2) ?></span>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
        (function () {
            const track = document.getElementById('discountTrack');
            if (!track) return;
            const prevBtn = document.getElementById('discountPrev');
            const nextBtn = document.getElementById('discountNext');
            const GAP = 12;

            window.discountSlide = function (dir) {
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

        // Save / Unsave — scoped sa #discountTrack
        document.querySelectorAll('#discountTrack .save-btn').forEach(btn => {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                const icon = this.querySelector('i');

                fetch('<?= BASE_URL ?>/savedproduct', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'product_id=' + encodeURIComponent(this.dataset.productId)
                })
                    .then(res => res.json())
                    .then(data => {
                        if (!data.success) {
                            alert(data.message || 'May error, subukan ulit.');
                            return;
                        }
                        icon.classList.toggle('fa-solid', data.saved);
                        icon.classList.toggle('text-red-500', data.saved);
                        icon.classList.toggle('fa-regular', !data.saved);
                        icon.classList.toggle('text-gray-500', !data.saved);
                        this.classList.toggle('md:opacity-0', !data.saved);
                        this.classList.toggle('md:group-hover:opacity-100', !data.saved);
                    })
                    .catch(() => alert('May error, subukan ulit.'));
            });
        });

    </script>
<?php endif; ?>