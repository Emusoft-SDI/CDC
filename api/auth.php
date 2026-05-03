<?php
// api/auth.php
require_once '../vendor/autoload.php'; // Firebase JWT
use \Firebase\JWT\JWT;

class Auth {
    private $key = "your_jwt_secret_key_123!@#"; // Store in config
    
    public function generateToken($userId, $role) {
        $payload = [
            'iss' => 'natcodev',
            'aud' => 'natcodev-mobile',
            'iat' => time(),
            'exp' => time() + (7 * 24 * 60 * 60), // 7 days
            'data' => ['user_id' => $userId, 'role' => $role]
        ];
        return JWT::encode($payload, $this->key, 'HS256');
    }
    
    public function validateToken($token) {
        try {
            $decoded = JWT::decode($token, new \Firebase\JWT\Key($this->key, 'HS256'));
            return $decoded->data;
        } catch (Exception $e) {
            return false;
        }
    }
}
?>