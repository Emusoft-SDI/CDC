<?php

$root = 'C:/Users/user/Downloads/UniServerZ/www/cocopay/core';
chdir($root);

require $root . '/vendor/autoload.php';
$app = require_once $root . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Constants\Status;
use App\Models\Form;
use App\Models\Gateway;
use App\Models\GatewayCurrency;
use Illuminate\Support\Facades\DB;

DB::transaction(function () {
    $allowedAliases = ['Paystack', 'monnify'];

    Gateway::query()->update(['status' => Status::DISABLE]);

    $paystack = Gateway::where('alias', 'Paystack')->first();
    if ($paystack) {
        $paystack->status = Status::ENABLE;
        $paystack->name = 'Paystack';
        $paystack->crypto = Status::DISABLE;
        $paystack->save();

        $paystackCurrency = GatewayCurrency::where('method_code', $paystack->code)->where('currency', 'NGN')->first() ?? new GatewayCurrency();
        $paystackCurrency->method_code = $paystack->code;
        $paystackCurrency->currency = 'NGN';
        $paystackCurrency->name = 'Paystack NGN';
        $paystackCurrency->gateway_alias = $paystack->alias;
        $paystackCurrency->symbol = '₦';
        $paystackCurrency->min_amount = $paystackCurrency->min_amount ?: 100;
        $paystackCurrency->max_amount = $paystackCurrency->max_amount ?: 10000000;
        $paystackCurrency->fixed_charge = $paystackCurrency->fixed_charge ?? 0;
        $paystackCurrency->percent_charge = $paystackCurrency->percent_charge ?? 1.5;
        $paystackCurrency->rate = 1;
        $paystackCurrency->gateway_parameter = $paystackCurrency->gateway_parameter ?: json_encode([
            'public_key' => '',
            'secret_key' => '',
        ]);
        $paystackCurrency->save();
    }

    $form = Form::where('act', 'manual_deposit')->first() ?? new Form();
    if (!$form->exists) {
        $form->act = 'manual_deposit';
        $form->form_data = [];
        $form->save();
    }

    if (!$form->form_data) {
        $form->form_data = [];
        $form->save();
    }

    $monnify = Gateway::where('alias', 'monnify')->orWhere('name', 'Monnify')->first();
    if (!$monnify) {
        $lastManual = Gateway::manual()->orderBy('code', 'desc')->first();
        $monnify = new Gateway();
        $monnify->code = $lastManual ? ((int) $lastManual->code + 1) : 1000;
    }

    $monnify->form_id = $form->id;
    $monnify->name = 'Monnify';
    $monnify->alias = 'monnify';
    $monnify->status = Status::ENABLE;
    $monnify->gateway_parameters = json_encode([]);
    $monnify->supported_currencies = [];
    $monnify->crypto = Status::DISABLE;
    $monnify->description = 'Use Monnify bank transfer or collection account details supplied by NATCODEV. Your payment will be reviewed and approved by the cooperative finance team.';
    $monnify->save();

    $monnifyCurrency = GatewayCurrency::where('method_code', $monnify->code)->where('currency', 'NGN')->first() ?? new GatewayCurrency();
    $monnifyCurrency->method_code = $monnify->code;
    $monnifyCurrency->currency = 'NGN';
    $monnifyCurrency->name = 'Monnify Transfer';
    $monnifyCurrency->gateway_alias = 'monnify';
    $monnifyCurrency->symbol = '₦';
    $monnifyCurrency->min_amount = 100;
    $monnifyCurrency->max_amount = 10000000;
    $monnifyCurrency->fixed_charge = 0;
    $monnifyCurrency->percent_charge = 0;
    $monnifyCurrency->rate = 1;
    $monnifyCurrency->gateway_parameter = $monnifyCurrency->gateway_parameter ?: json_encode([]);
    $monnifyCurrency->save();

    Gateway::whereNotIn('alias', $allowedAliases)->update(['status' => Status::DISABLE]);
});

foreach (Gateway::orderBy('code')->get() as $gateway) {
    echo implode('|', [
        $gateway->code,
        $gateway->alias,
        $gateway->name,
        $gateway->status ? 'enabled' : 'disabled',
        $gateway->currencies()->count(),
    ]) . PHP_EOL;
}
