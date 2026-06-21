<?php

$root = 'C:/Users/user/Downloads/UniServerZ/www/cocopay/core';
chdir($root);

require $root . '/vendor/autoload.php';
$app = require_once $root . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$enabled = App\Models\Gateway::where('status', App\Constants\Status::ENABLE)->orderBy('code')->get();
echo 'enabled_gateways=' . $enabled->map(fn($g) => $g->alias . ':' . $g->currencies()->count())->implode(',') . PHP_EOL;

$user = App\Models\User::first();
if ($user) {
    auth()->login($user);
}

$admin = App\Models\Admin::first();

$paths = [
    'user_deposit' => '/user/deposit',
];

foreach ($paths as $label => $path) {
    $request = Illuminate\Http\Request::create($path, 'GET');
    $response = $kernel->handle($request);
    $content = $response->getContent();
    echo $label . '|status=' . $response->getStatusCode()
        . '|paystack=' . (stripos($content, 'Paystack') !== false ? 'yes' : 'no')
        . '|monnify=' . (stripos($content, 'Monnify') !== false ? 'yes' : 'no')
        . '|flutterwave=' . (stripos($content, 'Flutterwave') !== false ? 'yes' : 'no')
        . '|paypal=' . (stripos($content, 'Paypal') !== false ? 'yes' : 'no')
        . PHP_EOL;
    $kernel->terminate($request, $response);
}

if ($admin) {
    auth('admin')->login($admin);
}

foreach ([
    'admin_auto' => '/admin/gateway/automatic',
    'admin_manual' => '/admin/gateway/manual',
] as $label => $path) {
    $request = Illuminate\Http\Request::create($path, 'GET');
    $response = $kernel->handle($request);
    $content = $response->getContent();
    echo $label . '|status=' . $response->getStatusCode()
        . '|paystack=' . (stripos($content, 'Paystack') !== false ? 'yes' : 'no')
        . '|monnify=' . (stripos($content, 'Monnify') !== false ? 'yes' : 'no')
        . '|flutterwave=' . (stripos($content, 'Flutterwave') !== false ? 'yes' : 'no')
        . '|paypal=' . (stripos($content, 'Paypal') !== false ? 'yes' : 'no')
        . PHP_EOL;
    $kernel->terminate($request, $response);
}
