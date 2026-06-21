<?php

$root = 'C:/Users/user/Downloads/UniServerZ/www/cocopay/core';
$source = $root . '/resources/views/components/n-form-data.blade.php';
$target = $root . '/resources/views/components/viser-form-data.blade.php';

if (!is_file($source)) {
    throw new RuntimeException("Source anonymous component not found: {$source}");
}

file_put_contents($target, file_get_contents($source));

echo "Restored {$target}\n";
