<?php

$root = 'C:\\Users\\user\\Downloads\\UniServerZ\\www\\cocopay\\core';
chdir($root);

require $root . '\\vendor\\autoload.php';
$app = require $root . '\\bootstrap\\app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$contexts = [
    'public' => '/',
    'admin' => '/admin/frontend/templates',
    'user' => '/user/dashboard',
    'staff' => '/staff/dashboard',
];

foreach ($contexts as $label => $uri) {
    app()->instance('request', Illuminate\Http\Request::create($uri, 'GET'));

    foreach ([404, 'session_expired'] as $code) {
        try {
            $html = view("errors.$code")->render();
            $broken = str_contains($html, 'Go to Home')
                || str_contains($html, 'Sorry your session has expired')
                || str_contains($html, 'other error ocurred')
                || str_contains($html, 'Symfony Exception')
                || str_contains($html, 'Internal Server Error');

            echo $label . '_' . $code . '|broken=' . ($broken ? 'yes' : 'no');
            if (preg_match('/<a[^>]*>(.*?)<\\/a>/is', $html, $match)) {
                echo '|cta=' . trim(html_entity_decode(strip_tags($match[1])));
            }
            echo PHP_EOL;
        } catch (Throwable $exception) {
            echo $label . '_' . $code . '|EXCEPTION|' . get_class($exception) . '|' . $exception->getMessage() . PHP_EOL;
        }
    }
}
