<?php
// lib/satellite/service.php
require_once 'planet.php';
require_once 'airbus.php';

class SatelliteService {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function getBestAvailableImagery($farmLat, $farmLng, $farmId, $userId) {
        // Check if user has premium access
        if (!$this->isPremiumUser($userId)) {
            throw new Exception("Premium feature required");
        }
        
        // Check if satellite module is enabled
        if (!$this->isModuleEnabled('satellite_module_enabled')) {
            throw new Exception("Satellite service temporarily unavailable");
        }
        
        // Try providers in order of preference/resolution
        $providers = ['airbus', 'planet', 'sentinel2'];
        
        foreach ($providers as $provider) {
            try {
                switch ($provider) {
                    case 'airbus':
                        $service = new AirbusSatellite($this->pdo);
                        return $service->getHighResImagery($farmLat, $farmLng, $farmId);
                    case 'planet':
                        $service = new PlanetSatellite($this->pdo);
                        return $service->getLatestImagery($farmLat, $farmLng, $farmId);
                    case 'sentinel2':
                        // Free Sentinel-2 data (already implemented in cron job)
                        return $this->getSentinel2Data($farmLat, $farmLng, $farmId);
                }
            } catch (Exception $e) {
                error_log("Satellite provider {$provider} failed: " . $e->getMessage());
                continue;
            }
        }
        
        throw new Exception("No satellite imagery available");
    }
    
    private function isPremiumUser($userId) {
        $stmt = $this->pdo->prepare("SELECT plan FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetchColumn() === 'premium';
    }
    
    private function isModuleEnabled($settingName) {
        $stmt = $this->pdo->prepare("SELECT value FROM settings WHERE key_name = ?");
        $stmt->execute([$settingName]);
        return $stmt->fetchColumn() === '1';
    }
    
    private function getSentinel2Data($farmLat, $farmLng, $farmId) {
        // Implementation from previous cron job
        return [
            'image_url' => "/imagery/sentinel2_farm_{$farmId}_" . date('Ymd') . ".jpg",
            'capture_date' => date('Y-m-d'),
            'resolution' => 10.00
        ];
    }
}
?>