<?php

$root = 'C:\\Users\\user\\Downloads\\UniServerZ\\www\\cocopay\\core';
chdir($root);

require $root . '\\vendor\\autoload.php';
$app = require $root . '\\bootstrap\\app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$user = App\Models\User::where('username', 'gracious')->first() ?? App\Models\User::firstOrFail();
auth()->login($user);

function get_user_page($kernel, $uri)
{
    $request = Illuminate\Http\Request::create($uri, 'GET', [], [], [], [
        'HTTP_HOST' => 'localhost',
        'REQUEST_URI' => $uri,
    ]);
    $response = $kernel->handle($request);
    $content = method_exists($response, 'getContent') ? (string) $response->getContent() : '';
    $kernel->terminate($request, $response);

    return [$response->getStatusCode(), $content];
}

[$depositStatus, $depositHtml] = get_user_page($kernel, '/user/deposit');
[$withdrawStatus, $withdrawHtml] = get_user_page($kernel, '/user/withdraw');

echo 'deposit_status=' . $depositStatus . PHP_EOL;
echo 'deposit_has_paystack=' . (stripos($depositHtml, 'Paystack') !== false ? 'yes' : 'no') . PHP_EOL;
echo 'deposit_has_monnify=' . (stripos($depositHtml, 'Monnify') !== false ? 'yes' : 'no') . PHP_EOL;
echo 'deposit_has_old_gateway_names=' . (preg_match('/Paypal|Stripe|Flutterwave|Coinpayments|Razorpay|Mollie/i', $depositHtml) ? 'yes' : 'no') . PHP_EOL;
echo 'withdraw_status=' . $withdrawStatus . PHP_EOL;
echo 'withdraw_has_form=' . (stripos($withdrawHtml, 'Withdraw') !== false && stripos($withdrawHtml, 'Current Balance') !== false ? 'yes' : 'no') . PHP_EOL;
