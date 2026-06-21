<?php

$files = [
    'C:/Users/user/Downloads/UniServerZ/www/cocopay/core/vendor/composer/autoload_classmap.php',
    'C:/Users/user/Downloads/UniServerZ/www/cocopay/core/vendor/composer/autoload_static.php',
];

foreach ($files as $file) {
    if (!file_exists($file)) {
        continue;
    }

    $contents = file_get_contents($file);
    $contents = preg_replace("/^\\s*'App\\\\\\\\View\\\\\\\\Components\\\\\\\\ViserForm'\\s*=>.*\\R/m", '', $contents);
    file_put_contents($file, $contents);
}

echo "VISER_AUTOLOAD_ENTRIES_REMOVED\n";

