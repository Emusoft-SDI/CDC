<?php

$source = 'C:/Users/user/Downloads/17814191509d2a.png';
$root = 'C:/Users/user/Downloads/UniServerZ/www/cocopay';
$logoDir = $root . '/assets/images/logoIcon';
$helpersPath = $root . '/core/app/Http/Helpers/helpers.php';

if (!is_file($source)) {
    throw new RuntimeException("Source logo not found: {$source}");
}
if (!is_dir($logoDir)) {
    throw new RuntimeException("Logo directory not found: {$logoDir}");
}
if (!is_file($helpersPath)) {
    throw new RuntimeException("Helpers file not found: {$helpersPath}");
}

copy($source, $logoDir . '/logo.png');
copy($source, $logoDir . '/logo_dark.png');
copy($source, $logoDir . '/favicon.png');

$helpers = file_get_contents($helpersPath);
$helpers = str_replace(
    '$name = $type ? "/logo_$type.svg" : \'/logo.svg\';',
    '$name = $type ? "/logo_$type.png" : \'/logo.png\';',
    $helpers
);
$helpers = str_replace(
    '$path = getFilePath(\'logoIcon\') . \'/favicon.svg\';',
    '$path = getFilePath(\'logoIcon\') . \'/favicon.png\';',
    $helpers
);
file_put_contents($helpersPath, $helpers);

echo "Uploaded logo set as default PNG logo.\n";
