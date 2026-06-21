<?php

$base = 'C:/Users/user/Downloads/UniServerZ/www/cocopay';
$core = $base . '/core';

$vendorConfig = $core . '/vendor/barryvdh/laravel-dompdf/config/dompdf.php';
$appConfig = $core . '/config/dompdf.php';

if (!is_file($vendorConfig)) {
    throw new RuntimeException("Missing vendor DomPDF config: {$vendorConfig}");
}

$config = file_get_contents($vendorConfig);
$config = str_replace(
    "'public_path' => null,",
    "'public_path' => 'C:/Users/user/Downloads/UniServerZ/www/cocopay',",
    $config
);
file_put_contents($appConfig, $config);

$helpersFile = $core . '/app/Http/Helpers/helpers.php';
$helpers = file_get_contents($helpersFile);
$old = <<<'PHP'
function siteLogo($type = null)
{
    $name = $type ? "/logo_$type.svg" : '/logo.svg';
    $path = getFilePath('logoIcon') . $name;
    $version = @filemtime(public_path($path)) ?: time();
    return asset($path) . '?v=' . $version;
}

function siteFavicon()
{
    $path = getFilePath('logoIcon') . '/favicon.svg';
    $version = @filemtime(public_path($path)) ?: time();
    return asset($path) . '?v=' . $version;
}
PHP;
$new = <<<'PHP'
function siteLogo($type = null)
{
    $name = $type ? "/logo_$type.svg" : '/logo.svg';
    $path = getFilePath('logoIcon') . $name;
    $file = base_path('../' . $path);
    $version = @filemtime($file) ?: time();
    return asset($path) . '?v=' . $version;
}

function siteFavicon()
{
    $path = getFilePath('logoIcon') . '/favicon.svg';
    $file = base_path('../' . $path);
    $version = @filemtime($file) ?: time();
    return asset($path) . '?v=' . $version;
}
PHP;

if (strpos($helpers, $old) !== false) {
    $helpers = str_replace($old, $new, $helpers);
    file_put_contents($helpersFile, $helpers);
}

echo "DomPDF public path and logo helper fixed." . PHP_EOL;

