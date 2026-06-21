<?php

$root = 'C:/Users/user/Downloads/UniServerZ/www/cocopay/core';
chdir($root);

require $root . '/vendor/autoload.php';
$app = require_once $root . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$request = Illuminate\Http\Request::create('/user/register', 'GET');
$response = $kernel->handle($request);
$content = $response->getContent();

echo 'status=' . $response->getStatusCode() . PHP_EOL;
echo 'has_natcodev=' . (stripos($content, 'NATCODEV') !== false ? 'yes' : 'no') . PHP_EOL;
echo 'has_cooperative=' . (stripos($content, 'cooperative') !== false ? 'yes' : 'no') . PHP_EOL;
echo 'has_coconut=' . (stripos($content, 'coconut') !== false ? 'yes' : 'no') . PHP_EOL;
echo 'has_certificate=' . (stripos($content, 'grower_certificate') !== false ? 'yes' : 'no') . PHP_EOL;
echo 'certificate_required=' . (preg_match('/name="grower_certificate"[^>]*required/i', $content) ? 'yes' : 'no') . PHP_EOL;
echo 'certificate_optional_copy=' . (stripos($content, 'Optional during registration') !== false || stripos($content, 'upload your NATCODEV growers certificate later') !== false ? 'yes' : 'no') . PHP_EOL;
echo 'has_state=' . (stripos($content, 'name="state_id"') !== false ? 'yes' : 'no') . PHP_EOL;
echo 'has_lga=' . (stripos($content, 'name="lga_id"') !== false ? 'yes' : 'no') . PHP_EOL;
echo 'has_nigeria_default=' . (stripos($content, 'Nigeria') !== false ? 'yes' : 'no') . PHP_EOL;
echo 'broken=' . (stripos($content, 'exception') !== false || stripos($content, 'ParseError') !== false ? 'yes' : 'no') . PHP_EOL;

$kernel->terminate($request, $response);
