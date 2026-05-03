<?php
// api/imagery/upload.php - Secure imagery upload
session_start();
// Admin or authorized drone operator only
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit;
}

// Validate file
if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    exit(json_encode(['error' => 'No image uploaded']));
}

$farmId = intval($_POST['farm_id'] ?? 0);
$imageryType = $_POST['imagery_type'] ?? 'satellite';
$captureDate = $_POST['capture_date'] ?? date('Y-m-d');

// Validate farm ownership (admins can upload to any farm)
if ($_SESSION['role'] !== 'admin') {
    $stmt = $pdo->prepare("SELECT id FROM applications WHERE id = ? AND user_id = ?");
    $stmt->execute([$farmId, $_SESSION['user_id']]);
    if (!$stmt->fetch()) {
        http_response_code(403);
        exit(json_encode(['error' => 'Unauthorized']));
    }
}

// Save to cloud storage (example: local storage)
$uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/imagery/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

$fileName = 'farm_' . $farmId . '_' . time() . '_' . basename($_FILES['image']['name']);
$filePath = $uploadDir . $fileName;

if (move_uploaded_file($_FILES['image']['tmp_name'], $filePath)) {
    // Generate thumbnail
    $thumbnailPath = generateThumbnail($filePath, $uploadDir . 'thumb_' . $fileName);
    
    // Insert record
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
    
    echo json_encode(['success' => true, 'message' => 'Imagery uploaded successfully']);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Upload failed']);
}

function generateThumbnail($source, $destination, $width = 300) {
    // Basic thumbnail generation
    list($origWidth, $origHeight) = getimagesize($source);
    $height = ($origHeight / $origWidth) * $width;
    
    $thumb = imagecreatetruecolor($width, $height);
    $sourceImage = imagecreatefromjpeg($source);
    imagecopyresampled($thumb, $sourceImage, 0, 0, 0, 0, $width, $height, $origWidth, $origHeight);
    return imagejpeg($thumb, $destination) ? $destination : false;
}
?>