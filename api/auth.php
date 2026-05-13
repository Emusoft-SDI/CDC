<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

class Auth
{
    private string $key;

    public function __construct()
    {
        $this->key = (string) app_env('JWT_SECRET', '');
        if ($this->key === '') {
            $this->key = hash('sha256', (string) app_env('APP_KEY', 'change-me-in-env'));
        }
    }

    public function generateToken(int $userId, string $role): string
    {
        $payload = [
            'iss' => 'natcodev',
            'aud' => 'natcodev-mobile',
            'iat' => time(),
            'exp' => time() + (7 * 24 * 60 * 60),
            'data' => ['user_id' => $userId, 'role' => $role],
        ];

        return $this->encode($payload);
    }

    public function validateToken(string $token): array|false
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }

        [$header64, $payload64, $signature64] = $parts;
        $expected = $this->base64UrlEncode(hash_hmac('sha256', $header64 . '.' . $payload64, $this->key, true));
        if (!hash_equals($expected, $signature64)) {
            return false;
        }

        $payload = json_decode($this->base64UrlDecode($payload64), true);
        if (!is_array($payload) || (($payload['exp'] ?? 0) < time())) {
            return false;
        }

        return is_array($payload['data'] ?? null) ? $payload['data'] : false;
    }

    private function encode(array $payload): string
    {
        $header = ['typ' => 'JWT', 'alg' => 'HS256'];
        $header64 = $this->base64UrlEncode((string) json_encode($header, JSON_UNESCAPED_SLASHES));
        $payload64 = $this->base64UrlEncode((string) json_encode($payload, JSON_UNESCAPED_SLASHES));
        $signature64 = $this->base64UrlEncode(hash_hmac('sha256', $header64 . '.' . $payload64, $this->key, true));

        return $header64 . '.' . $payload64 . '.' . $signature64;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        return base64_decode(strtr($value, '-_', '+/')) ?: '';
    }
}

function bearer_token(): ?string
{
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $authorization = $headers['Authorization'] ?? $headers['authorization'] ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    if (preg_match('/Bearer\s+(.+)/i', (string) $authorization, $matches)) {
        return trim($matches[1]);
    }
    return null;
}

function require_api_user(): array
{
    $token = bearer_token();
    if (!$token) {
        json_response(['success' => false, 'error' => 'Unauthorized'], 401);
    }

    $user = (new Auth())->validateToken($token);
    if (!$user) {
        json_response(['success' => false, 'error' => 'Invalid token'], 401);
    }

    return $user;
}
