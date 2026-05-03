<?php
// lib/identity-validation.php
class IdentityValidator {
    private $pdo;
    private $bvnApiUrl;
    private $bvnApiKey;
    private $ninApiUrl;
    private $ninApiKey;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->bvnApiUrl = $pdo->query("SELECT value FROM settings WHERE key_name = 'bvn_api_url'")->fetchColumn();
        $this->bvnApiKey = $pdo->query("SELECT value FROM settings WHERE key_name = 'bvn_api_key'")->fetchColumn();
        $this->ninApiUrl = $pdo->query("SELECT value FROM settings WHERE key_name = 'nin_api_url'")->fetchColumn();
        $this->ninApiKey = $pdo->query("SELECT value FROM settings WHERE key_name = 'nin_api_key'")->fetchColumn();
    }
    
    // Validate BVN
    public function validateBVN($bvn, $firstName, $lastName, $dob) {
        if (!$this->bvnApiUrl || !$this->bvnApiKey) {
            return ['status' => 'error', 'message' => 'BVN API not configured'];
        }
        
        $data = json_encode([
            'bvn' => $bvn,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'date_of_birth' => date('d-m-Y', strtotime($dob))
        ]);
        
        return $this->makeApiRequest($this->bvnApiUrl, $this->bvnApiKey, $data);
    }
    
    // Validate NIN
    public function validateNIN($nin, $firstName, $lastName, $dob) {
        if (!$this->ninApiUrl || !$this->ninApiKey) {
            return ['status' => 'error', 'message' => 'NIN API not configured'];
        }
        
        $data = json_encode([
            'nin' => $nin,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'date_of_birth' => date('d-m-Y', strtotime($dob))
        ]);
        
        return $this->makeApiRequest($this->ninApiUrl, $this->ninApiKey, $data);
    }
    
    private function makeApiRequest($url, $apiKey, $data) {
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer {$apiKey}",
                "Content-Type: application/json"
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200) {
                $result = json_decode($response, true);
                // VerifyMe response format
                if (isset($result['status']) && $result['status'] === 'success') {
                    return ['status' => 'valid', 'response' => $result];
                } else {
                    return ['status' => 'invalid', 'response' => $result];
                }
            } else {
                return ['status' => 'error', 'message' => "HTTP {$httpCode}: " . substr($response, 0, 200)];
            }
        } catch (Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
    
    // Validate document automatically
    public function autoValidateDocument($docId, $docType, $documentNumber, $userId) {
        // Get user details for validation
        $stmt = $pdo->prepare("
            SELECT u.name, u.dob, dr.document_type 
            FROM users u 
            JOIN document_requirements dr ON u.id = dr.user_id 
            WHERE dr.id = ?
        ");
        $stmt->execute([$docId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return false;
        }
        
        // Parse name (assuming format "First Last")
        $nameParts = explode(' ', $user['name'], 2);
        $firstName = $nameParts[0] ?? '';
        $lastName = $nameParts[1] ?? $firstName;
        
        // Validate based on document type
        if ($docType === 'bvn') {
            $result = $this->validateBVN($documentNumber, $firstName, $lastName, $user['dob']);
        } elseif ($docType === 'nin') {
            $result = $this->validateNIN($documentNumber, $firstName, $lastName, $user['dob']);
        } else {
            return false; // Only validate BVN/NIN automatically
        }
        
        // Update validation status
        $this->pdo->prepare("
            UPDATE document_requirements 
            SET api_validation_status = ?, 
                api_validation_response = ?, 
                api_validation_timestamp = NOW()
            WHERE id = ?
        ")->execute([$result['status'], json_encode($result), $docId]);
        
        return $result['status'] === 'valid';
    }
}
?>