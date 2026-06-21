<?php

$root = 'C:/Users/user/Downloads/UniServerZ/www/cocopay/core';
require $root . '/vendor/autoload.php';
$app = require $root . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$admin = App\Models\Admin::orderBy('id')->first();
auth('admin')->login($admin);

$request = Illuminate\Http\Request::create('/admin/request-report', 'GET');
$response = $kernel->handle($request);

echo "status={$response->getStatusCode()}\n";
echo 'redirect=' . ($response->isRedirection() ? 'yes' : 'no') . "\n";
echo 'target=' . ($response->headers->get('Location') ?? '') . "\n";

$kernel->terminate($request, $response);

