<?php
// specialist-updateproduct-helpers.php

function loadProductById(mysqli $conn, int $id): ?array
{
    $stmt = $conn->prepare(
        "SELECT p.id, p.name, p.imageproduct, p.description, p.category, p.unit,
                p.specifications, p.gallery, p.created_by, p.updated_by,
                creator.name AS created_by_name,
                editor.name  AS updated_by_name
         FROM nobleproduct p
         LEFT JOIN noblerole creator ON creator.id = p.created_by
         LEFT JOIN noblerole editor  ON editor.id  = p.updated_by
         WHERE p.id = ? LIMIT 1"
    );
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function loadProductColors(mysqli $conn, int $productId): array
{
    $colors = [];

    $stmt = $conn->prepare(
        "SELECT id, colorname, imagecolor, pricecolor
         FROM nobleproductcolor
         WHERE product_id = ?
         ORDER BY id ASC"
    );
    $stmt->bind_param("i", $productId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $row['variants'] = [];
        $colors[$row['id']] = $row;
    }
    $stmt->close();

    if (empty($colors))
        return [];

    // Load all variants for all colors in one query
    $colorIds = implode(',', array_map('intval', array_keys($colors)));
    $variantRows = $conn->query(
        "SELECT id, color_id, sizename, pricesize, discountvariant,
                width, height, leght, dimension_unit, weight, weight_unit, stock
         FROM nobleproductvariant
         WHERE color_id IN ($colorIds)
         ORDER BY color_id ASC, id ASC"
    );
    while ($v = $variantRows->fetch_assoc()) {
        $colors[$v['color_id']]['variants'][] = $v;
    }

    return array_values($colors);
}

/**
 * Converts an uploaded image to WebP — resized to max 1600px so it stays fast.
 * (Converting full-size 4000px phone photos is what made saving slow.)
 * If the insert helpers are included first, their convertToWebp() is used instead.
 */
if (!function_exists('convertToWebp')) {
    function convertToWebp(array $file, string $destDir, int $maxDim = 1600, int $quality = 82): string|false
    {
        $tmp = $file['tmp_name'] ?? '';
        if ($tmp === '' || !is_file($tmp))
            return false;

        $mime = mime_content_type($tmp);
        if (!is_dir($destDir))
            mkdir($destDir, 0755, true);
        $filename = uniqid('product_', true) . '.webp';
        $destPath = rtrim($destDir, '/') . '/' . $filename;

        // Fast path: already a small WebP (e.g. compressed by the browser) → just move it
        $info = @getimagesize($tmp);
        if ($mime === 'image/webp' && $info && max($info[0], $info[1]) <= $maxDim) {
            return (move_uploaded_file($tmp, $destPath) || copy($tmp, $destPath)) ? $filename : false;
        }

        $src = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($tmp),
            'image/png'  => @imagecreatefrompng($tmp),
            'image/gif'  => @imagecreatefromgif($tmp),
            'image/webp' => @imagecreatefromwebp($tmp),
            default      => false,
        };
        if (!$src)
            return false;

        // Palette images (some PNG/GIF) must be truecolor for WebP + keep transparency
        if (!imageistruecolor($src)) {
            imagepalettetotruecolor($src);
        }
        imagealphablending($src, true);
        imagesavealpha($src, true);

        // Resize down if larger than $maxDim
        $w = imagesx($src);
        $h = imagesy($src);
        $scale = min(1, $maxDim / max($w, $h));
        if ($scale < 1) {
            $nw = (int) round($w * $scale);
            $nh = (int) round($h * $scale);
            $dst = imagecreatetruecolor($nw, $nh);
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imagedestroy($src);
            $src = $dst;
        }

        $ok = imagewebp($src, $destPath, $quality);
        imagedestroy($src);
        return $ok ? $filename : false;
    }
}

/**
 * Loads all categories ordered by name.
 */
if (!function_exists('loadCategories')) {
    function loadCategories(mysqli $conn): array
    {
        $categories = [];
        $result = $conn->query("SELECT id, name FROM noblecategory ORDER BY name ASC");
        while ($row = $result->fetch_assoc())
            $categories[] = $row;
        return $categories;
    }
}