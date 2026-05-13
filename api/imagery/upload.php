<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config.php';

session_start();
$pdo = db();
$user = require_user_role($pdo, ['grower', 'field_agent', 'admin']);

// Validate file
if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    json_response(['success' => false, 'error' => 'No image uploaded'], 400);
}

$farmId = filter_var($_POST['farm_id'] ?? null, FILTER_VALIDATE_INT);
$imageryType = $_POST['imagery_type'] ?? 'satellite';
$captureDate = $_POST['capture_date'] ?? date('Y-m-d');
if (!$farmId) {
    json_response(['success' => false, 'error' => 'Farm ID required'], 422);
}

$mime = mime_content_type($_FILES['image']['tmp_name']);
if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
    json_response(['success' => false, 'error' => 'Only JPEG, PNG, or WebP imagery is supported'], 422);
}

if (($user['role'] ?? '') === 'grower') {
    $stmt = $pdo->prepare("
        SELECT a.id
        FROM applications a
        JOIN users u ON u.application_id = a.id
        WHERE a.id = ? AND u.id = ?
        LIMIT 1
    ");
    $stmt->execute([$farmId, (int) $user['id']]);
    if (!$stmt->fetch()) {
        json_response(['success' => false, 'error' => 'Unauthorized'], 403);
    }
}

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS farm_imagery (
            id INT AUTO_INCREMENT PRIMARY KEY,
            farm_id INT NOT NULL,
            imagery_type VARCHAR(40) NOT NULL DEFAULT 'satellite',
            image_url VARCHAR(255) NOT NULL,
            thumbnail_url VARCHAR(255) NULL,
            capture_date DATE NOT NULL,
            provider VARCHAR(50) NOT NULL DEFAULT 'manual',
            ndvi_avg DECIMAL(5,4) NULL,
            ndvi_variance DECIMAL(5,4) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_farm_imagery_farm_date (farm_id, capture_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} catch (Throwable $e) {
    error_log('Imagery schema error: ' . $e->getMessage());
    json_response(['success' => false, 'error' => 'Imagery storage unavailable'], 500);
}

$uploadDir = dirname(__DIR__, 2) . '/imagery/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0775, true);
}

$extension = match ($mime) {
    'image/png' => 'png',
    'image/webp' => 'webp',
    default => 'jpg',
};
$fileName = 'farm_' . $farmId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
$filePath = $uploadDir . $fileName;

if (move_uploaded_file($_FILES['image']['tmp_name'], $filePath)) {
    $thumbnailPath = generateThumbnail($filePath, $uploadDir . 'thumb_' . $fileName);

    $pdo->prepare("
        INSERT INTO farm_imagery
        (farm_id, imagery_type, image_url, thumbnail_url, capture_date, provider)
        VALUES (?, ?, ?, ?, ?, 'manual')
    ")->execute([
        $farmId,
        $imageryType,
        '/imagery/' . $fileName,
        $thumbnailPath ? '/imagery/' . basename($thumbnailPath) : null,
        $captureDate
    ]);

    json_response(['success' => true, 'message' => 'Imagery uploaded successfully']);
} else {
    json_response(['success' => false, 'error' => 'Upload failed'], 500);
}

function generateThumbnail($source, $destination, $width = 300) {
    if (!function_exists('imagecreatetruecolor')) {
        return false;
    }

    [$origWidth, $origHeight, $type] = getimagesize($source);
    $height = ($origHeight / $origWidth) * $width;

    $thumb = imagecreatetruecolor($width, $height);
    $sourceImage = match ($type) {
        IMAGETYPE_PNG => imagecreatefrompng($source),
        IMAGETYPE_WEBP => imagecreatefromwebp($source),
        default => imagecreatefromjpeg($source),
    };
    imagecopyresampled($thumb, $sourceImage, 0, 0, 0, 0, $width, $height, $origWidth, $origHeight);
    return imagejpeg($thumb, $destination) ? $destination : false;
}
