<?php
// shop-by-department.php
// Expects $conn from the including page (page.php)
// Light icon row inside a card (Shopee/Lazada-style category row).

// Image ang ginagamit; icon lang kapag walang naka-upload na image ang department.
$NH_DEPT_USE_IMAGES = true;

$departments = [];
$deptResult = $conn->query("SELECT id, name, image FROM noblecategory ORDER BY name ASC");
while ($row = $deptResult->fetch_assoc())
    $departments[] = $row;

if (!function_exists('nhDeptName')) {
    // "DOOR AND WINDOW" -> "Door and Window", "BATHROOMFIXTURES" -> "Bathroom Fixtures"
    function nhDeptName(string $name): string
    {
        $name = mb_strtolower(trim($name));
        // Display patch lang para sa mga pangalang walang space sa DB
        $name = preg_replace('/(?<=[a-z])(fixtures?|materials?|supplies|accessories)$/u', ' $1', $name);

        $small = ['and', 'or', 'of', 'the', 'for', 'in', 'on', '&'];
        $words = explode(' ', $name);
        foreach ($words as $i => $w) {
            if ($i === 0 || !in_array($w, $small, true)) {
                $words[$i] = mb_strtoupper(mb_substr($w, 0, 1)) . mb_substr($w, 1);
            }
        }
        return implode(' ', $words);
    }
}

if (!function_exists('nhDeptIcon')) {
    function nhDeptIcon(string $name): string
    {
        $n = mb_strtolower($name);
        $map = [
            'aac' => 'fa-cubes', 'block' => 'fa-cubes', 'cement' => 'fa-trowel-bricks', 'brick' => 'fa-trowel-bricks',
            'aircon' => 'fa-snowflake', 'cool' => 'fa-snowflake',
            'bath' => 'fa-bath', 'toilet' => 'fa-toilet',
            'door' => 'fa-door-open', 'window' => 'fa-door-open',
            'furniture' => 'fa-couch', 'kitchen' => 'fa-sink',
            'light' => 'fa-lightbulb', 'electric' => 'fa-plug',
            'tile' => 'fa-border-all', 'floor' => 'fa-border-all',
            'paint' => 'fa-paint-roller', 'tool' => 'fa-screwdriver-wrench', 'hardware' => 'fa-screwdriver-wrench',
            'plumb' => 'fa-faucet', 'roof' => 'fa-house-chimney', 'wood' => 'fa-tree', 'garden' => 'fa-seedling',
        ];
        foreach ($map as $kw => $icon) {
            if (str_contains($n, $kw)) return $icon;
        }
        return 'fa-layer-group';
    }
}
?>

<?php if (!empty($departments)): ?>
    <div class="mb-6 md:mb-8 bg-white border border-gray-200 rounded-xl p-4 md:p-5">

        <!-- Header -->
        <div class="flex items-center justify-between gap-4 mb-4">
            <div class="flex items-center gap-2.5">
                <span class="w-1.5 h-5 bg-orange-500 rounded-sm"></span>
                <h2 class="text-base md:text-lg font-extrabold text-gray-900">Shop by Department</h2>
            </div>
            <a href="<?= BASE_URL ?>/shop"
                class="shrink-0 text-xs md:text-sm font-semibold text-orange-500 hover:text-orange-600 transition-colors">
                View all →
            </a>
        </div>

        <!-- Icon row: swipe sa mobile, pantay-pantay sa desktop -->
        <div class="grid grid-flow-col auto-cols-[84px] md:auto-cols-fr gap-2 overflow-x-auto pb-1
                    [scrollbar-width:none] [&::-webkit-scrollbar]:hidden snap-x">
            <?php foreach ($departments as $dept):
                $name = nhDeptName($dept['name']);
                $showImg = $NH_DEPT_USE_IMAGES && !empty($dept['image']);
                ?>
                <a href="<?= BASE_URL ?>/productcategory?id=<?= (int) $dept['id'] ?>" title="<?= htmlspecialchars($name) ?>"
                    class="group snap-start flex flex-col items-center gap-2 py-2 px-1 rounded-lg hover:bg-orange-50/60 transition-colors">

                    <span class="w-16 h-16 md:w-20 md:h-20 rounded-full flex items-center justify-center overflow-hidden
                                 ring-2 ring-offset-2 group-hover:-translate-y-0.5 transition-all duration-200
                                 <?= $showImg
                                     ? 'bg-gray-100 ring-gray-100 group-hover:ring-orange-500'
                                     : 'bg-orange-50 ring-orange-100 group-hover:bg-orange-500 group-hover:ring-orange-500' ?>">
                        <?php if ($showImg): ?>
                            <img src="<?= BASE_URL . '/' . htmlspecialchars($dept['image']) ?>"
                                alt="<?= htmlspecialchars($name) ?>" loading="lazy"
                                class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300"
                                onerror="this.replaceWith(Object.assign(document.createElement('i'),{className:'fa-solid <?= nhDeptIcon($dept['name']) ?> text-xl text-orange-500'}))">
                        <?php else: ?>
                            <i class="fa-solid <?= nhDeptIcon($dept['name']) ?> text-lg md:text-xl text-orange-500 group-hover:text-white transition-colors"></i>
                        <?php endif; ?>
                    </span>

                    <span class="text-[11px] md:text-xs font-medium text-gray-700 text-center leading-tight line-clamp-2
                                 group-hover:text-orange-600 transition-colors">
                        <?= htmlspecialchars($name) ?>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>