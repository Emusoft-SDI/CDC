<?php

$root = 'C:/Users/user/Downloads/UniServerZ/www/cocopay/core';
chdir($root);

require $root . '/vendor/autoload.php';
$app = require_once $root . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$request = Illuminate\Http\Request::create('/', 'GET');
$response = $kernel->handle($request);
$content = $response->getContent();

echo 'status=' . $response->getStatusCode() . PHP_EOL;
echo 'has_natcodev=' . (stripos($content, 'NATCODEV Coconut Farmers Cooperative Society') !== false ? 'yes' : 'no') . PHP_EOL;
echo 'has_coconut=' . (stripos($content, 'coconut farmers') !== false ? 'yes' : 'no') . PHP_EOL;
echo 'has_dwarf=' . (stripos($content, 'dwarf coconut') !== false ? 'yes' : 'no') . PHP_EOL;
echo 'has_monnify=' . (stripos($content, 'Monnify') !== false ? 'yes' : 'no') . PHP_EOL;
echo 'has_images=' . (substr_count($content, 'assets/images/frontend/natcodev/') >= 6 ? 'yes' : 'no') . PHP_EOL;
echo 'has_exception=' . (stripos($content, 'exception') !== false || stripos($content, 'ParseError') !== false ? 'yes' : 'no') . PHP_EOL;

$kernel->terminate($request, $response);
