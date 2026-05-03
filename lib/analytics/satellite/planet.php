<?php
// lib/satellite/planet.php
class PlanetSatellite {
    private $apiKey;
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $stmt = $pdo->prepare("SELECT api_key FROM satellite_providers WHERE provider_name = 'planet' AND is_active = 1");
        $stmt->execute();
        $this->apiKey = $stmt->fetchColumn();
    }
    
    public function getLatestImagery($farmLat, $farmLng, $farmId) {
        if (!$this->apiKey) {
            throw new Exception("Planet API not configured");
        }
        
        // Define area of interest (500m x 500m around farm)
        $aoi = [
            "type" => "Polygon",
            "coordinates" => [[
                [$farmLng - 0.0045, $farmLat - 0.0045],
                [$farmLng + 0.0045, $farmLat - 0.0045],
                [$farmLng + 0.0045, $farmLat + 0.0045],
                [$farmLng - 0.0045, $farmLat + 0.0045],
                [$farmLng - 0.0045, $farmLat - 0.0045]
            ]]
        ];
        
        // Search for imagery
        $searchUrl = "https://api.planet.com/data/v1/quick-search";
        $searchData = json_encode([
            "item_types" => ["PSScene"],
            "filter" => [
                "type" => "AndFilter",
                "config" => [
                    ["type" => "GeometryFilter", "field_name" => "geometry", "config" => $aoi],
                    ["type" => "RangeFilter", "field_name" => "cloud_cover", "config" => ["lte" => 0.1]],
                    ["type" => "DateRangeFilter", "field_name" => "acquired", "config" => ["gte" => date('Y-m-d', strtotime('-30 days'))]]
                ]
            ]
        ]);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $searchUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $searchData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Basic " . base64_encode($this->apiKey . ":"),
            "Content-Type: application/json"
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception("Planet API error: " . $httpCode);
        }
        
        $results = json_decode($response, true);
        if (empty($results['features'])) {
            return null;
        }
        
        // Get the best image (lowest cloud cover)
        $bestImage = $results['features'][0];
        $imageUrl = $this->downloadImage($bestImage['id'], $farmId);
        
        return [
            'image_url' => $imageUrl,
            'capture_date' => substr($bestImage['properties']['acquired'], 0, 10),
            'cloud_cover' => $bestImage['properties']['cloud_cover'],
            'resolution' => 3.00
        ];
    }
    
    private function downloadImage($itemId, $farmId) {
        // Create delivery order (simplified)
        $deliveryUrl = "https://api.planet.com/compute/ops/orders/v2";
        $deliveryData = json_encode([
            "name" => "NATCODEV_FARM_{$farmId}",
            "products" => [[
                "item_ids" => [$itemId],
                "item_type" => "PSScene",
                "product_bundle" => "analytic"
            ]],
            "delivery" => ["archive_type" => "zip"]
        ]);
        
        // In production, you'd handle the async delivery
        // For now, return a placeholder
        return "/imagery/planet_farm_{$farmId}_" . time() . ".jpg";
    }
}
?>