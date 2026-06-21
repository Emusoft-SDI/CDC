<?php

$root = 'C:/Users/user/Downloads/UniServerZ/www/cocopay/core';
chdir($root);

require $root . '/vendor/autoload.php';
$app = require_once $root . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = App\Models\User::first();
if ($user) {
    auth()->login($user);
}

$deposit = new App\Models\Deposit();
$deposit->user_id = $user?->id ?? 1;
$deposit->method_code = 126;
$deposit->method_currency = 'NGN';
$deposit->amount = 1000;
$deposit->charge = 0;
$deposit->rate = 1;
$deposit->final_amount = 1000;
$deposit->trx = 'TESTMONNIFY' . time();
$deposit->status = App\Constants\Status::PAYMENT_INITIATE;

$result = json_decode(App\Http\Controllers\Gateway\Monnify\ProcessController::process($deposit));

echo 'has_error=' . (isset($result->error) ? 'yes' : 'no') . PHP_EOL;
echo 'message=' . ($result->message ?? '') . PHP_EOL;
echo 'has_redirect=' . (isset($result->redirect) ? 'yes' : 'no') . PHP_EOL;
