<?php

$root = 'C:/Users/user/Downloads/UniServerZ/www/cocopay/core';
chdir($root);

require $root . '/vendor/autoload.php';
$app = require_once $root . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo 'active=' . activeTemplate() . PHP_EOL;
echo 'active_true=' . activeTemplate(true) . PHP_EOL;

foreach (App\Models\Page::where('slug', '/')->get(['tempname', 'slug', 'secs']) as $page) {
    echo 'page=' . $page->tempname . '|' . $page->slug . '|' . $page->secs . PHP_EOL;
}
