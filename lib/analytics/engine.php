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
        if (function_exists('app_table_exists') && app_table_exists($this->pdo, 'settings') && !$this->isModuleEnabled('analytics_module_enabled')) {
            throw new Exception("Analytics service unavailable");
        }

        if (function_exists('app_column_exists') && !app_column_exists($this->pdo, 'users', 'plan')) {
            return;
        }

        $stmt = $this->pdo->prepare("SELECT plan FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $plan = $stmt->fetchColumn();
        if ($plan !== false && $plan !== 'premium') {
            throw new Exception("Premium subscription required for advanced analytics");
        }
    }
    
    private function isModuleEnabled($settingName) {
        if (function_exists('app_table_exists') && !app_table_exists($this->pdo, 'settings')) {
            return true;
        }

        $stmt = $this->pdo->prepare("SELECT value FROM settings WHERE key_name = ?");
        $stmt->execute([$settingName]);
        $value = $stmt->fetchColumn();
        return $value === false || $value === '1';
    }
    
    private function getFarmData($farmId) {
        $stmt = $this->pdo->prepare("SELECT id, farm_size, location FROM applications WHERE id = ? LIMIT 1");
        $stmt->execute([$farmId]);
        $farm = $stmt->fetch();
        if (!$farm) {
            throw new Exception("Farm not found");
        }
        return $farm;
    }

    private function getSensorData($farmId) {
        if (function_exists('app_table_exists') && (!app_table_exists($this->pdo, 'iot_sensors') || !app_table_exists($this->pdo, 'sensor_readings'))) {
            return [];
        }

        $stmt = $this->pdo->prepare("
            SELECT AVG(sr.reading_value) AS soil_moisture_avg
            FROM sensor_readings sr
            JOIN iot_sensors s ON sr.sensor_id = s.id
            WHERE s.farm_id = ?
              AND s.sensor_type IN ('soil_moisture', 'moisture')
              AND sr.reading_timestamp >= DATE_SUB(NOW(), INTERVAL 14 DAY)
        ");
        $stmt->execute([$farmId]);
        return $stmt->fetch() ?: [];
    }

    private function getImageryData($farmId) {
        if (function_exists('app_table_exists') && !app_table_exists($this->pdo, 'farm_imagery')) {
            return [];
        }
        if (function_exists('app_column_exists') && !app_column_exists($this->pdo, 'farm_imagery', 'ndvi_avg')) {
            return [];
        }

        $varianceExpr = function_exists('app_column_exists') && app_column_exists($this->pdo, 'farm_imagery', 'ndvi_variance')
            ? 'AVG(ndvi_variance)'
            : 'NULL';
        $stmt = $this->pdo->prepare("
            SELECT AVG(ndvi_avg) AS ndvi_avg, {$varianceExpr} AS ndvi_variance
            FROM farm_imagery
            WHERE farm_id = ? AND capture_date >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
        ");
        $stmt->execute([$farmId]);
        return $stmt->fetch() ?: [];
    }

    private function calculateConfidence($sensorData, $imageryData) {
        $confidence = 0.35;
        if (!empty($sensorData['soil_moisture_avg'])) {
            $confidence += 0.25;
        }
        if (!empty($imageryData['ndvi_avg'])) {
            $confidence += 0.3;
        }
        return min(0.9, $confidence);
    }

    private function saveAnalytics($farmId, $type, $confidence, $data) {
        if (function_exists('app_table_exists') && !app_table_exists($this->pdo, 'analytics_results')) {
            return;
        }
        if (function_exists('app_column_exists') && (
            !app_column_exists($this->pdo, 'analytics_results', 'farm_id')
            || !app_column_exists($this->pdo, 'analytics_results', 'analysis_type')
            || !app_column_exists($this->pdo, 'analytics_results', 'confidence')
            || !app_column_exists($this->pdo, 'analytics_results', 'result_data')
        )) {
            return;
        }

        $hasCreatedAt = !function_exists('app_column_exists') || app_column_exists($this->pdo, 'analytics_results', 'created_at');
        $sql = $hasCreatedAt
            ? "INSERT INTO analytics_results (farm_id, analysis_type, confidence, result_data, created_at) VALUES (?, ?, ?, ?, NOW())"
            : "INSERT INTO analytics_results (farm_id, analysis_type, confidence, result_data) VALUES (?, ?, ?, ?)";

        $this->pdo->prepare($sql)->execute([$farmId, $type, $confidence, json_encode($data, JSON_UNESCAPED_SLASHES)]);
    }

    private function getRecentImagery($farmId) {
        if (function_exists('app_table_exists') && !app_table_exists($this->pdo, 'farm_imagery')) {
            return [];
        }

        $stmt = $this->pdo->prepare("
            SELECT *
            FROM farm_imagery
            WHERE farm_id = ?
            ORDER BY capture_date DESC
            LIMIT 1
        ");
        $stmt->execute([$farmId]);
        return $stmt->fetch() ?: [];
    }
}
?>
