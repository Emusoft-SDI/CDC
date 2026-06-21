<?php

$root = 'C:/Users/user/Downloads/UniServerZ/www/cocopay/core';
chdir($root);

require $root . '/vendor/autoload.php';
$app = require_once $root . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$currency = App\Models\GatewayCurrency::where('gateway_alias', 'Monnify')->where('currency', 'NGN')->firstOrFail();
$params = json_decode($currency->gateway_parameter);
$baseUrl = rtrim($params->base_url ?? 'https://sandbox.monnify.com', '/');
$sslVerify = !in_array(strtolower(trim($params->ssl_verify ?? 'true')), ['0', 'false', 'no', 'off'], true);
$auth = base64_encode(($params->api_key ?? '') . ':' . ($params->secret_key ?? ''));

$ch = curl_init($baseUrl . '/api/v1/auth/login');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Basic ' . $auth,
        'Content-Type: application/json',
    ],
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => $sslVerify,
    CURLOPT_SSL_VERIFYHOST => $sslVerify ? 2 : 0,
]);
$response = curl_exec($ch);
$error = curl_error($ch);
$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$json = json_decode((string) $response);
$token = $json->responseBody->accessToken ?? '';

echo 'http_status=' . $status . PHP_EOL;
echo 'curl_error=' . ($error ? 'yes' : 'no') . PHP_EOL;
echo 'authenticated=' . ($token ? 'yes' : 'no') . PHP_EOL;
echo 'message=' . ($json->responseMessage ?? ($error ? 'curl error' : '')) . PHP_EOL;
