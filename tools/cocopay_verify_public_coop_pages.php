<?php

$root = 'C:/Users/user/Downloads/UniServerZ/www/cocopay/core';
chdir($root);

require $root . '/vendor/autoload.php';
$app = require_once $root . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$paths = [
    'home' => '/',
    'about' => '/about',
    'faq' => '/faq',
    'services' => '/services',
    'contact' => '/contact',
];

foreach ($paths as $label => $path) {
    $request = Illuminate\Http\Request::create($path, 'GET');
    $response = $kernel->handle($request);
    $content = $response->getContent();
    echo $label
        . '|status=' . $response->getStatusCode()
        . '|natcodev=' . (stripos($content, 'NATCODEV') !== false ? 'yes' : 'no')
        . '|cooperative=' . (stripos($content, 'cooperative') !== false ? 'yes' : 'no')
        . '|coconut=' . (stripos($content, 'coconut') !== false ? 'yes' : 'no')
        . '|broken=' . (stripos($content, 'exception') !== false || stripos($content, 'ParseError') !== false ? 'yes' : 'no')
        . PHP_EOL;
    $kernel->terminate($request, $response);
}
