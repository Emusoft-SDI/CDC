<?php

$cacheDir = 'C:\\Users\\user\\Downloads\\UniServerZ\\www\\cocopay\\core\\bootstrap\\cache';
$files = ['packages.php', 'services.php', 'config.php', 'routes-v7.php'];

foreach ($files as $file) {
    $path = $cacheDir . DIRECTORY_SEPARATOR . $file;
    if (is_file($path)) {
        unlink($path);
        echo "REMOVED={$file}" . PHP_EOL;
    }
}

echo 'STALE_LARAVEL_CACHE_CLEARED' . PHP_EOL;
