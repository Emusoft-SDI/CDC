<?php

$root = 'C:/Users/user/Downloads/UniServerZ/www/cocopay/core';
chdir($root);

require $root . '/vendor/autoload.php';
$app = require_once $root . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Constants\Status;
use App\Models\Gateway;
use App\Models\GatewayCurrency;
use Illuminate\Support\Facades\DB;

DB::transaction(function () {
    Gateway::whereNotIn('alias', ['Paystack', 'Monnify', 'monnify'])->update(['status' => Status::DISABLE]);

    $monnify = Gateway::where('alias', 'Monnify')->orWhere('alias', 'monnify')->orWhere('name', 'Monnify')->first() ?? new Gateway();
    $oldCode = $monnify->exists ? $monnify->code : null;
    $newCode = 126;

    if ($oldCode && $oldCode != $newCode) {
        GatewayCurrency::where('method_code', $oldCode)->update(['method_code' => $newCode]);
    }

    $monnify->code = $newCode;
    $monnify->name = 'Monnify';
    $monnify->alias = 'Monnify';
    $monnify->status = Status::ENABLE;
    $monnify->gateway_parameters = json_encode([
        'api_key' => [
            'title' => 'API Key',
            'global' => true,
            'value' => '',
        ],
        'secret_key' => [
            'title' => 'Secret Key',
            'global' => true,
            'value' => '',
        ],
        'contract_code' => [
            'title' => 'Contract Code',
            'global' => true,
            'value' => '',
        ],
        'base_url' => [
            'title' => 'Base URL',
            'global' => true,
            'value' => 'https://sandbox.monnify.com',
        ],
    ]);
    $monnify->supported_currencies = ['NGN'];
    $monnify->crypto = Status::DISABLE;
    $monnify->description = 'Monnify hosted checkout for Nigerian Naira cooperative deposits.';
    $monnify->form_id = 0;
    $monnify->save();

    $currency = GatewayCurrency::where('method_code', $newCode)->where('currency', 'NGN')->first() ?? new GatewayCurrency();
    $currency->method_code = $newCode;
    $currency->gateway_alias = 'Monnify';
    $currency->name = 'Monnify NGN';
    $currency->currency = 'NGN';
    $currency->symbol = '₦';
    $currency->min_amount = $currency->min_amount ?: 100;
    $currency->max_amount = $currency->max_amount ?: 10000000;
    $currency->fixed_charge = $currency->fixed_charge ?? 0;
    $currency->percent_charge = $currency->percent_charge ?? 0;
    $currency->rate = 1;
    $currency->gateway_parameter = json_encode([
        'api_key' => '',
        'secret_key' => '',
        'contract_code' => '',
        'base_url' => 'https://sandbox.monnify.com',
    ]);
    $currency->save();

    Gateway::where('alias', 'Paystack')->update(['status' => Status::ENABLE]);
});

foreach (Gateway::whereIn('alias', ['Paystack', 'Monnify'])->orderBy('code')->get() as $gateway) {
    echo $gateway->code . '|' . $gateway->alias . '|' . $gateway->name . '|' . ($gateway->status ? 'enabled' : 'disabled') . '|' . $gateway->currencies()->count() . PHP_EOL;
}
