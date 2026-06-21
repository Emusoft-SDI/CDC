<?php
declare(strict_types=1);

require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/common.php';

// Ensure the request is made by an authenticated admin
if (!admin_session_is_authenticated($pdo)) {
    http_response_code(403);
    exit('Forbidden: admin authentication required');
}

// Expect a 'file' GET parameter with the relative filename (e.g., user_46_nin_20260614094123_7c030f64b9_sa.jpg)
$rawFile = $_GET['file'] ?? '';
$rawFile = trim($rawFile);
if ($rawFile === '') {
    http_response_code(400);
    exit('Bad request: missing file parameter');
}

// Disallow directory traversal attempts
if (strpos($rawFile, '..') !== false || strpos($rawFile, '/') !== false || strpos($rawFile, "\\") !== false) {
    http_response_code(400);
    exit('Bad request: invalid file name');
}

$documentsDir = realpath(__DIR__ . '/../documents');
if ($documentsDir === false) {
    http_response_code(500);
    exit('Server error: documents directory not found');
}

$absolutePath = realpath($documentsDir . DIRECTORY_SEPARATOR . $rawFile);
// Verify the resolved path is inside the documents directory and is a file
if ($absolutePath === false || !is_file($absolutePath) || strpos($absolutePath, $documentsDir . DIRECTORY_SEPARATOR) !== 0) {
    http_response_code(404);
    exit('Document not found');
}

// Determine MIME type safely
$mime = function_exists('mime_content_type') ? mime_content_type($absolutePath) : 'application/octet-stream';

// Output the file inline for preview (admin can also download via browser UI)
while (ob_get_level() > 0) {
    ob_end_clean();
}
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($absolutePath));
header('Content-Disposition: inline; filename="' . basename($absolutePath) . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, max-age=0');
readfile($absolutePath);
exit;
?>
