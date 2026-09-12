<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

app_require_cli('fetch-satellite');

$pdo = db();
$sentinelInstanceId = (string) app_env('SENTINEL_HUB_INSTANCE_ID', '');
if ($sentinelInstanceId === '') {
    error_log('Satellite imagery fetch skipped: SENTINEL_HUB_INSTANCE_ID is not configured.');
    return;
}

// Get farms with coordinates (you'll need to add lat/lng to applications table)
$stmt = $pdo->prepare("
    SELECT id, farm_lat, farm_lng 
    FROM applications 
    WHERE farm_lat IS NOT NULL AND farm_lng IS NOT NULL
");
$stmt->execute();
$farms = $stmt->fetchAll();

foreach ($farms as $farm) {
    // Fetch Sentinel-2 data (example using free API)
    $url = "https://services.sentinel-hub.com/ogc/wms/{$sentinelInstanceId}?" . http_build_query([
        'REQUEST' => 'GetMap',
        'BBOX' => ($farm['farm_lng'] - 0.01) . ',' . ($farm['farm_lat'] - 0.01) . ',' . ($farm['farm_lng'] + 0.01) . ',' . ($farm['farm_lat'] + 0.01),
        'WIDTH' => 512,
        'HEIGHT' => 512,
        'FORMAT' => 'image/jpeg',
        'LAYERS' => 'NDVI',
        'TIME' => date('Y-m-d', strtotime('-7 days')) . '/' . date('Y-m-d')
    ]);
    
    // Download and save imagery
    $imageData = file_get_contents($url);
    if ($imageData) {
        $fileName = 'sentinel2_farm_' . $farm['id'] . '_' . date('Ymd') . '.jpg';
        file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/imagery/' . $fileName, $imageData);
        
        // Insert record
        $pdo->prepare("
            INSERT INTO farm_imagery 
            (farm_id, imagery_type, image_url, capture_date, provider, resolution_meters)
            VALUES (?, 'ndvi', ?, ?, 'sentinel2', 10.00)
        ")->execute([$farm['id'], '/imagery/' . $fileName, date('Y-m-d')]);
    }
}
?>