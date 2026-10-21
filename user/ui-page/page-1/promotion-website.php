<?php
// /user/ui-page/page-1/promotion-website.php

$promoSites = [];
$psResult = $conn->query("
    SELECT id, name, website_link, image
    FROM noblepromotionwebsite
    WHERE is_active = 1
    ORDER BY created_at DESC
");
while ($row = $psResult->fetch_assoc()) {
    // http/https links lang ang tinatanggap (iwas javascript: at iba pa)
    $scheme = strtolower((string) parse_url($row['website_link'] ?? '', PHP_URL_SCHEME));
    if (!in_array($scheme, ['http', 'https'], true)) continue;

    $host = (string) parse_url($row['website_link'], PHP_URL_HOST);
    $row['domain'] = preg_replace('/^www\./i', '', $host);
    $promoSites[] = $row;
}
$siteCount = count($promoSites);
?>

<?php if ($siteCount > 0): ?>
    <section class="mb-8 md:mb-10">

        <!-- Header -->
        <div class="flex items-end justify-between gap-4 mb-4">
            <div class="flex items-center gap-2.5">
                <span class="w-1.5 h-5 bg-orange-500 rounded-sm"></span>
                <div>
                    <h2 class="text-base md:text-lg font-extrabold text-gray-900">Promotions</h2>
                    <p class="text-xs md:text-sm text-gray-500">Featured promotions and offers</p>
                </div>
            </div>
        </div>

        <?php if ($siteCount === 1):
            $site = $promoSites[0]; ?>
            <!-- Single promotion: feature card -->
            <a href="<?= htmlspecialchars($site['website_link']) ?>" target="_blank" rel="noopener"
                class="group grid grid-cols-1 md:grid-cols-[1.6fr_1fr] bg-white border border-gray-200 rounded-lg overflow-hidden hover:border-gray-400 transition-colors">

                <div class="aspect-[16/7] md:aspect-auto md:min-h-[220px] bg-gray-100 overflow-hidden">
                    <?php if (!empty($site['image'])): ?>
                        <img src="<?= BASE_URL ?>/uploads/promotionwebsite/<?= htmlspecialchars($site['image']) ?>"
                            alt="<?= htmlspecialchars($site['name']) ?>" loading="lazy"
                            class="w-full h-full object-cover group-hover:scale-[1.03] transition-transform duration-500">
                    <?php else: ?>
                        <div class="w-full h-full flex items-center justify-center">
                            <i class="fa-solid fa-store text-4xl text-gray-300"></i>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="p-5 md:p-8 flex flex-col justify-center">
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Promotion</p>
                    <h3 class="text-xl md:text-2xl font-bold text-gray-900 mt-1.5 leading-tight">
                        <?= htmlspecialchars($site['name']) ?>
                    </h3>
                    <?php if ($site['domain']): ?>
                        <p class="text-sm text-gray-500 mt-1"><?= htmlspecialchars($site['domain']) ?></p>
                    <?php endif; ?>
                    <span class="mt-5 inline-flex items-center justify-center gap-2 w-fit h-11 px-5 rounded-md bg-gray-900 text-white text-sm font-semibold group-hover:bg-orange-600 transition-colors">
                        Visit website <i class="fa-solid fa-arrow-up-right-from-square text-xs"></i>
                    </span>
                </div>
            </a>

        <?php else: ?>
            <!-- Multiple promotions: grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 md:gap-4">
                <?php foreach ($promoSites as $site): ?>
                    <a href="<?= htmlspecialchars($site['website_link']) ?>" target="_blank" rel="noopener"
                        class="group flex flex-col bg-white border border-gray-200 rounded-lg overflow-hidden hover:border-gray-400 transition-colors">

                        <div class="aspect-[16/9] bg-gray-100 overflow-hidden">
                            <?php if (!empty($site['image'])): ?>
                                <img src="<?= BASE_URL ?>/uploads/promotionwebsite/<?= htmlspecialchars($site['image']) ?>"
                                    alt="<?= htmlspecialchars($site['name']) ?>" loading="lazy"
                                    class="w-full h-full object-cover group-hover:scale-[1.03] transition-transform duration-500">
                            <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center">
                                    <i class="fa-solid fa-store text-3xl text-gray-300"></i>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="flex items-center justify-between gap-3 px-4 py-3.5 border-t border-gray-200">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-gray-900 truncate"><?= htmlspecialchars($site['name']) ?></p>
                                <?php if ($site['domain']): ?>
                                    <p class="text-xs text-gray-500 truncate"><?= htmlspecialchars($site['domain']) ?></p>
                                <?php endif; ?>
                            </div>
                            <span class="shrink-0 inline-flex items-center gap-1.5 text-xs font-semibold text-gray-900 group-hover:text-orange-600 transition-colors">
                                Visit <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
                            </span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </section>
<?php endif; ?>