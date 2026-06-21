<?php

$root = 'C:/Users/user/Downloads/UniServerZ/www/cocopay/core';
chdir($root);

require $root . '/vendor/autoload.php';
$app = require_once $root . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$gateway = App\Models\Gateway::where('alias', 'Paystack')->first();
$currency = App\Models\GatewayCurrency::where('gateway_alias', 'Paystack')->where('currency', 'NGN')->first();

echo 'gateway_parameters=' . ($gateway ? $gateway->gateway_parameters : 'missing') . PHP_EOL;
echo 'currency_parameter=' . ($currency ? $currency->gateway_parameter : 'missing') . PHP_EOL;
