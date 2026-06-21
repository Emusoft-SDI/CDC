<?php

$root = 'C:\\Users\\user\\Downloads\\UniServerZ\\www\\cocopay\\core';
chdir($root);

require $root . '\\vendor\\autoload.php';
$app = require $root . '\\bootstrap\\app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$gateways = App\Models\Gateway::automatic()->with('currencies')->orderBy('code')->get(['id', 'code', 'name', 'alias', 'status']);
$active = $gateways->where('status', App\Constants\Status::ENABLE)->pluck('alias')->values()->all();
$notAllowedActive = array_values(array_diff($active, ['Paystack', 'Monnify']));

echo 'automatic_gateways=' . $gateways->map(fn($g) => $g->alias . ':' . $g->status . ':currencies=' . $g->currencies->count())->implode('|') . PHP_EOL;
echo 'active_automatic_gateways=' . implode(',', $active) . PHP_EOL;
echo 'not_allowed_active_gateways=' . (count($notAllowedActive) ? implode(',', $notAllowedActive) : 'none') . PHP_EOL;

$ipn = file_get_contents($root . '\\routes\\ipn.php');
echo 'ipn_has_paystack=' . (str_contains($ipn, "paystack") ? 'yes' : 'no') . PHP_EOL;
echo 'ipn_has_monnify=' . (str_contains($ipn, "monnify") ? 'yes' : 'no') . PHP_EOL;
echo 'ipn_has_old_gateways=' . (preg_match('/paypal|stripe|flutterwave|coinpayments|checkout|razorpay/i', $ipn) ? 'yes' : 'no') . PHP_EOL;
