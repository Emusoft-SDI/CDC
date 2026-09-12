<?php
declare(strict_types=1);

function market_upload_seller_logo(string $field): ?string
{
    if (empty($_FILES[$field]) || (int) ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $upload = app_uploaded_file_info((array) $_FILES[$field], ['jpg', 'jpeg', 'png', 'webp'], 3 * 1024 * 1024, 'Store logo');
    $dir = dirname(__DIR__) . '/uploads/marketplace/logos';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $fileName = app_safe_upload_name('seller_logo', $upload['name'], $upload['extension']);
    $target = $dir . '/' . $fileName;
    if (!move_uploaded_file($upload['tmp_name'], $target)) {
        throw new RuntimeException('Unable to upload store logo. Check upload folder permissions.');
    }
    return 'uploads/marketplace/logos/' . $fileName;
}

function market_seller_logo_url(?array $seller): ?string
{
    $path = trim((string) ($seller['logo_path'] ?? ''));
    return $path !== '' ? '../' . ltrim($path, '/') : null;
}

function market_seller_avatar_html(array $seller, string $class = 'mk-store-avatar'): string
{
    $logo = market_seller_logo_url($seller);
    $name = (string) ($seller['store_name'] ?? 'Seller');
    if ($logo !== null) {
        return '<div class="' . e($class) . '"><img src="' . e($logo) . '" alt="' . e($name) . ' logo"></div>';
    }
    return '<div class="' . e($class) . '">' . e(market_initials($name)) . '</div>';
}

function market_upload_listing_image(string $field): ?string
{
    if (empty($_FILES[$field]) || (int) ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $upload = app_uploaded_file_info((array) $_FILES[$field], ['jpg', 'jpeg', 'png', 'webp'], 5 * 1024 * 1024, 'Marketplace product image');
    $dir = dirname(__DIR__) . '/uploads/marketplace';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $fileName = app_safe_upload_name('market_listing', $upload['name'], $upload['extension']);
    $target = $dir . '/' . $fileName;
    if (!move_uploaded_file($upload['tmp_name'], $target)) {
        throw new RuntimeException('Unable to upload marketplace image. Check upload folder permissions.');
    }
    return 'uploads/marketplace/' . $fileName;
}

function market_upload_listing_images(string $field, int $limit = 4): array
{
    if (empty($_FILES[$field]) || empty($_FILES[$field]['name']) || !is_array($_FILES[$field]['name'])) {
        return [];
    }
    $paths = [];
    $count = min(count($_FILES[$field]['name']), $limit);
    for ($i = 0; $i < $count; $i++) {
        if ((int) ($_FILES[$field]['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $file = [
            'name' => $_FILES[$field]['name'][$i] ?? '',
            'type' => $_FILES[$field]['type'][$i] ?? '',
            'tmp_name' => $_FILES[$field]['tmp_name'][$i] ?? '',
            'error' => $_FILES[$field]['error'][$i] ?? UPLOAD_ERR_NO_FILE,
            'size' => $_FILES[$field]['size'][$i] ?? 0,
        ];
        $upload = app_uploaded_file_info($file, ['jpg', 'jpeg', 'png', 'webp'], 5 * 1024 * 1024, 'Marketplace product image');
        $dir = dirname(__DIR__) . '/uploads/marketplace';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $fileName = app_safe_upload_name('market_listing', $upload['name'], $upload['extension']);
        $target = $dir . '/' . $fileName;
        if (!move_uploaded_file($upload['tmp_name'], $target)) {
            throw new RuntimeException('Unable to upload marketplace gallery image. Check upload folder permissions.');
        }
        $paths[] = 'uploads/marketplace/' . $fileName;
    }
    return $paths;
}

function market_listing_gallery_paths(array $item): array
{
    $paths = [];
    $main = trim((string) ($item['image_path'] ?? ''));
    if ($main !== '') {
        $paths[] = $main;
    }
    $gallery = trim((string) ($item['image_gallery'] ?? ''));
    if ($gallery !== '') {
        $decoded = json_decode($gallery, true);
        if (is_array($decoded)) {
            foreach ($decoded as $path) {
                $path = trim((string) $path);
                if ($path !== '') {
                    $paths[] = $path;
                }
            }
        }
    }
    return array_values(array_unique(array_slice($paths, 0, 4)));
}

function market_listing_gallery_urls(array $item): array
{
    $urls = [];
    foreach (market_listing_gallery_paths($item) as $path) {
        $urls[] = '../' . ltrim($path, '/');
    }
    if (!$urls) {
        $urls[] = market_listing_image_url($item);
    }
    return array_values(array_unique(array_slice($urls, 0, 4)));
}

function market_listing_image_url(array $item): string
{
    $path = trim((string) ($item['image_path'] ?? ''));
    if ($path !== '') {
        return '../' . ltrim($path, '/');
    }
    $text = strtolower((string) (($item['title'] ?? '') . ' ' . ($item['category_name'] ?? '') . ' ' . ($item['listing_type'] ?? '') . ' ' . ($item['summary'] ?? '')));
    if (str_contains($text, 'compost') || str_contains($text, 'fertilizer') || str_contains($text, 'mulch') || str_contains($text, 'soil')) {
        return '../assets/market/organic-compost.png';
    }
    if (str_contains($text, 'pruning') || str_contains($text, 'shear') || str_contains($text, 'tool') || str_contains($text, 'equipment') || str_contains($text, 'brush cutter')) {
        return '../assets/market/farm-tools-pruning.png';
    }
    if (str_contains($text, 'crew') || str_contains($text, 'labor') || str_contains($text, 'farm hand') || str_contains($text, 'planting')) {
        return '../assets/market/planting-crew-service.png';
    }
    if (str_contains($text, 'seedling') || str_contains($text, 'nursery') || str_contains($text, 'coconut')) {
        return '../assets/market/dwarf-coconut-seedlings.png';
    }
    return 'image.php?id=' . (int) ($item['id'] ?? 0);
}
