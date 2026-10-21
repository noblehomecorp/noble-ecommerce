<?php
// page.php

include ROOT_PATH . '/network/connect.php';

// Fetch active promotions (within date range)
$promotions = [];
$promoResult = $conn->query("
    SELECT id, title, description, banner_image, category_id
    FROM noblepromotions
    WHERE is_active = 1
      AND start_date <= CURDATE()
      AND end_date   >= CURDATE()
    ORDER BY created_at DESC
");
while ($row = $promoResult->fetch_assoc())
    $promotions[] = $row;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NobleHome</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/user/navigation/top.php'; ?>
</head>

<body class="bg-gray-50">

    <div class="max-w-screen-xl mx-auto px-3 md:px-4 py-4 md:py-6 pb-20 md:pb-6">

        <!-- Hero: promo slider + side cards + trust strip -->
        <section>
            <?php include ROOT_PATH . '/user/ui-page/page-1/hero-section.php'; ?>
        </section>

        <!-- Department icon row -->
        <section>
            <?php include ROOT_PATH . '/user/ui-page/page-1/shop-by-department.php'; ?>
        </section>

        <section>
            <?php include ROOT_PATH . '/user/ui-page/page-1/most-popularproduct.php'; ?>
        </section>

        <section>
            <?php include ROOT_PATH . '/user/ui-page/page-1/promotion-website.php'; ?>
        </section>

        <!-- Target ng "Today's Deals" card sa hero (scroll-mt = clearance sa sticky header) -->
        <section id="deals" class="scroll-mt-40">
            <?php include ROOT_PATH . '/user/ui-page/page-1/discounted-products.php'; ?>
        </section>

        <section>
            <?php include ROOT_PATH . '/user/ui-page/page-1/new-arrivals.php'; ?>
        </section>
    </div>

    <?php include ROOT_PATH . '/user/navigation/bottom.php'; ?>

</body>

</html>