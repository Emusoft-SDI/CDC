<?php

$cache = 'C:\\Users\\user\\Downloads\\UniServerZ\\www\\cocopay\\core\\bootstrap\\cache';
$storageViews = 'C:\\Users\\user\\Downloads\\UniServerZ\\www\\cocopay\\core\\storage\\framework\\views';

foreach (glob($cache . DIRECTORY_SEPARATOR . '*.php') ?: [] as $file) {
    unlink($file);
    echo "REMOVED_CACHE=" . basename($file) . PHP_EOL;
}

foreach (glob($storageViews . DIRECTORY_SEPARATOR . '*.php') ?: [] as $file) {
    unlink($file);
}

echo 'EMERGENCY_CACHE_CLEAR_DONE' . PHP_EOL;
