<?php

$root = 'C:/Users/user/Downloads/UniServerZ/www/cocopay/core';
chdir($root);

require $root . '/vendor/autoload.php';

$app = require_once $root . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$console = $app->make(Illuminate\Contracts\Console\Kernel::class);
$console->bootstrap();

if (class_exists(App\Models\Admin::class)) {
    $admin = App\Models\Admin::first();
    if ($admin) {
        auth('admin')->login($admin);
    }
}

$bank = App\Models\OtherBank::find(1);
echo 'bank_exists=' . ($bank ? 'yes' : 'no') . PHP_EOL;
echo 'bank_form_id=' . ($bank && $bank->form_id ? $bank->form_id : 'none') . PHP_EOL;
echo 'bank_form_relation=' . ($bank && $bank->form ? 'yes' : 'no') . PHP_EOL;

$request = Illuminate\Http\Request::create('/admin/other-banks/edit/1', 'GET');
$response = $kernel->handle($request);
$content = $response->getContent();

echo 'status=' . $response->getStatusCode() . PHP_EOL;
echo 'has_error=' . (stripos($content, 'Call to a member function') !== false || stripos($content, 'exception') !== false ? 'yes' : 'no') . PHP_EOL;
echo 'has_form=' . (stripos($content, '<form') !== false ? 'yes' : 'no') . PHP_EOL;
echo 'has_account_name=' . (stripos($content, 'Account Name') !== false ? 'yes' : 'no') . PHP_EOL;

$kernel->terminate($request, $response);
