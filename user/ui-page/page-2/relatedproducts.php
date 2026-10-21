<?php
// relatedproducts.php
// Expects from mainproductview.php: $conn, $product, $productId, $uploadUrl

$relatedProducts = [];
$usedFallback = false; // true kapag walang match sa category, random products na lang

$relSelect = "
    SELECT p.id, p.name, p.category, p.unit, p.imageproduct,
           MIN(v.pricesize) AS min_price,
           MAX(v.pricesize) AS max_price,
           MAX(v.discountvariant) AS max_discount,
           MIN(v.pricesize - (v.pricesize * v.discountvariant / 100)) AS min_discounted_price,
           AVG(r.rating) AS avg_rating,
           COUNT(DISTINCT r.id) AS review_count,
           (
               SELECT COALESCE(SUM(v2.sold), 0)
               FROM nobleproductvariant v2
               JOIN nobleproductcolor c2 ON c2.id = v2.color_id
               WHERE c2.product_id = p.id
           ) AS total_sold
    FROM nobleproduct p
    LEFT JOIN nobleproductcolor c ON c.product_id = p.id
    LEFT JOIN nobleproductvariant v ON v.color_id = c.id
    LEFT JOIN noblereview r ON r.product_id = p.id
";

if (!empty($product['category'])) {
    $relStmt = $conn->prepare($relSelect . " WHERE p.category = ? AND p.id != ? GROUP BY p.id ORDER BY RAND() LIMIT 10");
    $relStmt->bind_param("si", $product['category'], $productId);
    $relStmt->execute();
    $relResult = $relStmt->get_result();
    while ($rp = $relResult->fetch_assoc()) {
        $relatedProducts[] = $rp;
    }
    $relStmt->close();
}

// Fallback: random products para hindi blangko ang section
if (empty($relatedProducts)) {
    $usedFallback = true;
    $fallbackStmt = $conn->prepare($relSelect . " WHERE p.id != ? GROUP BY p.id ORDER BY RAND() LIMIT 10");
    $fallbackStmt->bind_param("i", $productId);
    $fallbackStmt->execute();
    $fallbackResult = $fallbackStmt->get_result();
    while ($rp = $fallbackResult->fetch_assoc()) {
        $relatedProducts[] = $rp;
    }
    $fallbackStmt->close();
}

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

$relatedSectionTitle = $usedFallback ? 'You May Also Like' : 'Related Products';
?>

<?php if (!empty($relatedProducts)): ?>
    <!-- ════════ Related Products ════════ -->
    <section class="mt-8 md:mt-10">
        <div class="flex items-end justify-between gap-4 mb-4">
            <div>
                <h2 class="text-lg md:text-xl font-bold text-gray-900"><?= htmlspecialchars($relatedSectionTitle) ?></h2>
                <?php if (!$usedFallback && !empty($product['category'])): ?>
                    <p class="text-sm text-gray-500 mt-0.5">More from <?= htmlspecialchars(ucwords(strtolower($product['category']))) ?></p>
                <?php endif; ?>
            </div>
            <a href="<?= BASE_URL ?>/shop" class="shrink-0 text-sm font-semibold text-gray-900 hover:text-orange-600 transition-colors">
                View all <i class="fa-solid fa-arrow-right text-xs ml-1"></i>
            </a>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-x-3 gap-y-6 md:gap-x-4">
            <?php foreach ($relatedProducts as $rp):
                $rpMin = floatval($rp['min_price'] ?? 0);
                $rpMax = floatval($rp['max_price'] ?? 0);
                $rpDisc = floatval($rp['max_discount'] ?? 0);
                $rpDiscMin = floatval($rp['min_discounted_price'] ?? 0);
                $rpAvgRating = round(floatval($rp['avg_rating'] ?? 0), 1);
                $rpReviewCount = intval($rp['review_count'] ?? 0);
                $rpSold = intval($rp['total_sold'] ?? 0);
                ?>
                <a href="<?= BASE_URL ?>/mainproductview?id=<?= (int) $rp['id'] ?>" class="group flex flex-col">

                    <div class="relative aspect-square rounded-lg overflow-hidden bg-gray-100 border border-gray-200">
                        <?php if (!empty($rp['imageproduct'])): ?>
                            <img src="<?= $uploadUrl . htmlspecialchars($rp['imageproduct']) ?>"
                                alt="<?= htmlspecialchars($rp['name']) ?>" loading="lazy"
                                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                        <?php else: ?>
                            <div class="w-full h-full flex items-center justify-center">
                                <i class="fa-regular fa-image text-3xl text-gray-300"></i>
                            </div>
                        <?php endif; ?>
                        <?php if ($rpDisc > 0): ?>
                            <span class="absolute top-2 left-2 bg-red-600 text-white text-[11px] font-semibold px-1.5 py-0.5 rounded-sm">
                                -<?= rtrim(rtrim(number_format($rpDisc, 2), '0'), '.') ?>%
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="pt-2.5 flex-1 flex flex-col">
                        <?php if (!empty($rp['category'])): ?>
                            <span class="text-[11px] font-medium uppercase tracking-wide text-gray-500 truncate">
                                <?= htmlspecialchars($rp['category']) ?>
                            </span>
                        <?php endif; ?>

                        <p class="text-sm font-medium text-gray-900 leading-snug line-clamp-2 mt-0.5 group-hover:underline underline-offset-2">
                            <?= htmlspecialchars($rp['name']) ?>
                        </p>

                        <?php if ($rpReviewCount > 0 || $rpSold > 0): ?>
                            <div class="flex items-center gap-1.5 mt-1 text-xs text-gray-500">
                                <?php if ($rpReviewCount > 0): ?>
                                    <i class="fa-solid fa-star text-[10px] text-amber-400"></i>
                                    <span class="text-gray-700"><?= number_format($rpAvgRating, 1) ?></span>
                                    <span>(<?= $rpReviewCount ?>)</span>
                                <?php endif; ?>
                                <?php if ($rpReviewCount > 0 && $rpSold > 0): ?>
                                    <span class="text-gray-300">|</span>
                                <?php endif; ?>
                                <?php if ($rpSold > 0): ?>
                                    <span><?= formatSoldCount($rpSold) ?> sold</span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <div class="mt-1.5 flex items-baseline gap-1.5 flex-wrap">
                            <?php if ($rpMin > 0 || $rpMax > 0): ?>
                                <?php if ($rpMin !== $rpMax): ?>
                                    <span class="text-xs text-gray-500">From</span>
                                <?php endif; ?>
                                <?php if ($rpDisc > 0 && $rpDiscMin > 0): ?>
                                    <span class="text-base font-bold text-red-600">₱<?= number_format($rpDiscMin, 2) ?></span>
                                    <span class="text-xs text-gray-400 line-through">₱<?= number_format($rpMin, 2) ?></span>
                                <?php else: ?>
                                    <span class="text-base font-bold text-gray-900">₱<?= number_format($rpMin, 2) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($rp['unit'])): ?>
                                    <span class="text-xs text-gray-500">/ <?= htmlspecialchars($rp['unit']) ?></span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-xs text-gray-500">Price not set</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>