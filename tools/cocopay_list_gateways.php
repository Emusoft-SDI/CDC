<?php

$root = 'C:/Users/user/Downloads/UniServerZ/www/cocopay/core';
chdir($root);

require $root . '/vendor/autoload.php';
$app = require_once $root . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

foreach (App\Models\Gateway::orderBy('code')->get() as $gateway) {
    echo implode('|', [
        $gateway->id,
        $gateway->code,
        $gateway->alias,
        $gateway->name,
        $gateway->status,
        $gateway->currencies()->count(),
    ]) . PHP_EOL;
}
