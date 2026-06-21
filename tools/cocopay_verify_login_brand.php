<?php

$root = 'C:/Users/user/Downloads/UniServerZ/www/cocopay/core';
chdir($root);

require $root . '/vendor/autoload.php';
$app = require_once $root . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$request = Illuminate\Http\Request::create('/user/login', 'GET');
$response = $kernel->handle($request);
$content = $response->getContent();

echo 'status=' . $response->getStatusCode() . PHP_EOL;
echo 'has_natcodev=' . (stripos($content, 'NATCODEV') !== false ? 'yes' : 'no') . PHP_EOL;
echo 'has_cooperative=' . (stripos($content, 'cooperative') !== false ? 'yes' : 'no') . PHP_EOL;
echo 'has_coconut=' . (stripos($content, 'coconut') !== false ? 'yes' : 'no') . PHP_EOL;
echo 'has_certificate=' . (stripos($content, 'certificate') !== false ? 'yes' : 'no') . PHP_EOL;
echo 'has_monnify=' . (stripos($content, 'Monnify') !== false ? 'yes' : 'no') . PHP_EOL;
echo 'has_old_generic=' . (stripos($content, "Don't Have An Account") !== false ? 'yes' : 'no') . PHP_EOL;
echo 'broken=' . (stripos($content, 'exception') !== false || stripos($content, 'ParseError') !== false ? 'yes' : 'no') . PHP_EOL;

$kernel->terminate($request, $response);
