<?php
// lib/analytics/engine.php
class AnalyticsEngine {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function generateYieldPrediction($farmId, $userId) {
        $this->validatePremiumAccess($userId);
        
        // Get farm data
        $farm = $this->getFarmData($farmId);
        $sensorData = $this->getSensorData($farmId);
        $imageryData = $this->getImageryData($farmId);
        
        // Simple yield prediction model (replace with ML in production)
        $baseYield = $farm['farm_size'] * 8; // 8 tons/ha baseline
        
        // Adjust based on NDVI (vegetation health)
        if (!empty($imageryData['ndvi_avg'])) {
            $ndviFactor = min(1.5, max(0.5, $imageryData['ndvi_avg'] / 0.8));
            $baseYield *= $ndviFactor;
        }
        
        // Adjust based on soil moisture
        if (!empty($sensorData['soil_moisture_avg'])) {
            $moistureFactor = min(1.2, max(0.8, $sensorData['soil_moisture_avg'] / 60));
            $baseYield *= $moistureFactor;
        }
        
        // Add confidence score based on data availability
        $confidence = $this->calculateConfidence($sensorData, $imageryData);
        
        // Save prediction
        $this->saveAnalytics($farmId, 'yield_prediction', $confidence, [
            'predicted_yield_tons' => round($baseYield, 2),
            'baseline_tons' => round($farm['farm_size'] * 8, 2),
            'adjustment_factors' => [
                'ndvi' => $ndviFactor ?? 1.0,
                'moisture' => $moistureFactor ?? 1.0
            ]
        ]);
        
        return [
            'predicted_yield' => round($baseYield, 2),
            'confidence' => $confidence,
            'factors' => [
                'ndvi' => $ndviFactor ?? 1.0,
                'moisture' => $moistureFactor ?? 1.0
            ]
        ];
    }
    
    public function detectDiseaseRisk($farmId, $userId) {
        $this->validatePremiumAccess($userId);
        
        // Simple disease detection based on imagery anomalies
        $imagery = $this->getRecentImagery($farmId);
        if (empty($imagery)) {
            return ['risk_level' => 'low', 'confidence' => 0.3];
        }
        
        // In production, use computer vision models
        // For now, simulate based on NDVI variance
        $ndviVariance = $imagery['ndvi_variance'] ?? 0.1;
        $riskLevel = $ndviVariance > 0.3 ? 'high' : ($ndviVariance > 0.2 ? 'medium' : 'low');
        $confidence = min(0.9, 0.5 + ($ndviVariance * 2));
        
        $this->saveAnalytics($farmId, 'disease_risk', $confidence, [
            'risk_level' => $riskLevel,
            'ndvi_variance' => $ndviVariance,
            'recommended_action' => $riskLevel === 'high' ? 'Contact agronomist immediately' : 'Monitor weekly'
        ]);
        
        return ['risk_level' => $riskLevel, 'confidence' => $confidence];
    }
    
    private function validatePremiumAccess($userId) {
        if (!$this->isModuleEnabled('analytics_module_enabled')) {
            throw new Exception("Analytics service unavailable");
        }
        
        $stmt = $this->pdo->prepare("SELECT plan FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        if ($stmt->fetchColumn() !== 'premium') {
            throw new Exception("Premium subscription required for advanced analytics");
        }
    }
    
    private function isModuleEnabled($settingName) {
        $stmt = $this->pdo->prepare("SELECT value FROM settings WHERE key_name = ?");
        $stmt->execute([$settingName]);
        return $stmt->fetchColumn() === '1';
    }
    
    // Helper methods (implement based on your data structure)
    private function getFarmData($farmId) { /* ... */ }
    private function getSensorData($farmId) { /* ... */ }
    private function getImageryData($farmId) { /* ... */ }
    private function calculateConfidence($sensorData, $imageryData) { /* ... */ }
    private function saveAnalytics($farmId, $type, $confidence, $data) { /* ... */ }
    private function getRecentImagery($farmId) { /* ... */ }
}
?>