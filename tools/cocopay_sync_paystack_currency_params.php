<?php

$root = 'C:/Users/user/Downloads/UniServerZ/www/cocopay/core';
chdir($root);

require $root . '/vendor/autoload.php';
$app = require_once $root . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$gateway = App\Models\Gateway::where('alias', 'Paystack')->firstOrFail();
$currency = App\Models\GatewayCurrency::where('gateway_alias', 'Paystack')->where('currency', 'NGN')->firstOrFail();
$params = json_decode($gateway->gateway_parameters);

$currency->gateway_parameter = json_encode([
    'public_key' => $params->public_key->value ?? '',
    'secret_key' => $params->secret_key->value ?? '',
]);
$currency->save();

echo "Paystack NGN parameters synced\n";
