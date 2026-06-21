<?php

$root = 'C:\\Users\\user\\Downloads\\UniServerZ\\www\\cocopay\\core';
chdir($root);

require $root . '\\vendor\\autoload.php';
$app = require $root . '\\bootstrap\\app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

foreach (App\Models\Gateway::whereIn('alias', ['Paystack', 'Monnify'])->get() as $gateway) {
    if ($gateway->alias === 'Paystack') {
        $gateway->name = 'NATCODEV Paystack';
    }
    if ($gateway->alias === 'Monnify') {
        $gateway->name = 'NATCODEV Monnify';
    }
    $gateway->save();
}

foreach (App\Models\GatewayCurrency::whereIn('gateway_alias', ['Paystack', 'Monnify'])->get() as $currency) {
    $currency->name = 'NATCODEV ' . $currency->gateway_alias . ' ' . $currency->currency;
    $currency->save();
}

$gs = App\Models\GeneralSetting::first();
if ($gs) {
    $gs->site_name = 'NATCODEV Coconut Farmers Cooperative';
    $gs->cur_text = $gs->cur_text ?: 'NGN';
    $gs->save();
}

echo 'GATEWAY_BRANDING=' . App\Models\Gateway::whereIn('alias', ['Paystack', 'Monnify'])->get(['alias','name','status'])->map(fn($g) => $g->alias . ':' . $g->name . ':' . $g->status)->implode('|') . PHP_EOL;
