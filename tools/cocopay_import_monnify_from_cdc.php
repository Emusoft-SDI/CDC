<?php

$cdcEnv = 'C:/Users/user/Downloads/UniServerZ/www/CDC/.env';
$root = 'C:/Users/user/Downloads/UniServerZ/www/cocopay/core';

function read_env_file_value(string $path, string $key, string $default = ''): string {
    if (!is_file($path)) {
        return $default;
    }

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$envKey, $value] = array_map('trim', explode('=', $line, 2));
        if ($envKey === $key) {
            return trim($value, "\"'");
        }
    }

    return $default;
}

$config = [
    'api_key' => read_env_file_value($cdcEnv, 'MONNIFY_API_KEY'),
    'secret_key' => read_env_file_value($cdcEnv, 'MONNIFY_SECRET_KEY'),
    'contract_code' => read_env_file_value($cdcEnv, 'MONNIFY_CONTRACT_CODE'),
    'base_url' => rtrim(read_env_file_value($cdcEnv, 'MONNIFY_BASE_URL', 'https://api.monnify.com'), '/'),
    'payment_methods' => read_env_file_value($cdcEnv, 'MONNIFY_PAYMENT_METHODS', 'CARD,ACCOUNT_TRANSFER,USSD'),
    'ssl_verify' => read_env_file_value($cdcEnv, 'MONNIFY_SSL_VERIFY', 'true'),
];

chdir($root);
require $root . '/vendor/autoload.php';
$app = require_once $root . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$gateway = App\Models\Gateway::where('alias', 'Monnify')->firstOrFail();
$gateway->gateway_parameters = json_encode([
    'api_key' => [
        'title' => 'API Key',
        'global' => true,
        'value' => $config['api_key'],
    ],
    'secret_key' => [
        'title' => 'Secret Key',
        'global' => true,
        'value' => $config['secret_key'],
    ],
    'contract_code' => [
        'title' => 'Contract Code',
        'global' => true,
        'value' => $config['contract_code'],
    ],
    'base_url' => [
        'title' => 'Base URL',
        'global' => true,
        'value' => $config['base_url'],
    ],
    'payment_methods' => [
        'title' => 'Payment Methods',
        'global' => true,
        'value' => $config['payment_methods'],
    ],
    'ssl_verify' => [
        'title' => 'Verify SSL',
        'global' => true,
        'value' => $config['ssl_verify'],
    ],
]);
$gateway->save();

$currency = App\Models\GatewayCurrency::where('method_code', $gateway->code)->where('currency', 'NGN')->firstOrFail();
$currency->gateway_alias = 'Monnify';
$currency->gateway_parameter = json_encode($config);
$currency->save();

echo 'monnify_configured=' . (($config['api_key'] && $config['secret_key'] && $config['contract_code']) ? 'yes' : 'no') . PHP_EOL;
echo 'base_url=' . $config['base_url'] . PHP_EOL;
echo 'payment_methods=' . $config['payment_methods'] . PHP_EOL;
