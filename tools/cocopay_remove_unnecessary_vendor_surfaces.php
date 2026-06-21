<?php

$core = 'C:\\Users\\user\\Downloads\\UniServerZ\\www\\cocopay\\core';

function mustBeInside(string $path, string $root): string
{
    $rootReal = realpath($root);
    $pathReal = realpath($path) ?: $path;
    if (!$rootReal || stripos($pathReal, $rootReal) !== 0) {
        throw new RuntimeException("Refusing path outside root: {$path}");
    }
    return $pathReal;
}

function removePath(string $path, string $root): void
{
    if (!file_exists($path)) {
        return;
    }
    $path = mustBeInside($path, $root);
    if (is_file($path)) {
        unlink($path);
        echo "REMOVED_FILE={$path}" . PHP_EOL;
        return;
    }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        $itemPath = mustBeInside($item->getPathname(), $root);
        $item->isDir() ? rmdir($itemPath) : unlink($itemPath);
    }
    rmdir($path);
    echo "REMOVED_DIR={$path}" . PHP_EOL;
}

function saveFile(string $path, string $content): void
{
    file_put_contents($path, $content);
    echo "UPDATED={$path}" . PHP_EOL;
}

// Remove public admin access to vendor patch upload/update.
$routes = $core . '\\routes\\admin.php';
$content = file_get_contents($routes);
$content = preg_replace("/\\s*Route::get\\('system-update', 'systemUpdate'\\)->name\\('update'\\);\\R/", '', $content);
$content = preg_replace("/\\s*Route::post\\('update-upload', 'updateUpload'\\)->name\\('update\\.upload'\\);\\R/", '', $content);
saveFile($routes, $content);

$controller = $core . '\\app\\Http\\Controllers\\Admin\\SystemController.php';
$content = file_get_contents($controller);
$content = str_replace("use App\\Models\\UpdateLog;\r\n", '', $content);
$content = str_replace("use App\\Models\\UpdateLog;\n", '', $content);
$content = str_replace("use Illuminate\\Http\\Request;\r\n", '', $content);
$content = str_replace("use Illuminate\\Http\\Request;\n", '', $content);
$content = preg_replace('/\R\s*public function systemUpdate\(\)\s*\{.*?return view\(\'admin\.system\.update\', compact\(\'pageTitle\', \'updates\'\)\);\s*\}\s*/s', "\n", $content);
$content = preg_replace('/\R\s*public function updateUpload\(Request \$request\)\s*\{.*?return back\(\)->withNotify\(\$notify\);\s*\}\s*/s', "\n", $content);
saveFile($controller, $content);

$sidenav = $core . '\\resources\\views\\admin\\partials\\sidenav.blade.php';
$content = file_get_contents($sidenav);
$content = preg_replace('/\s*@can\(\'admin\.system\.update\'\)\s*<li class="sidebar-menu-item \{\{ menuActive\(\'admin\.system\.update\'\) \}\} ">\s*<a href="\{\{ route\(\'admin\.system\.update\'\) \}\}" class="nav-link">\s*<i class="menu-icon las la-dot-circle"><\/i>\s*<span class="menu-title">@lang\(\'Update\'\)<\/span>\s*<\/a>\s*<\/li>\s*@endcan\s*/s', "\n", $content);
saveFile($sidenav, $content);

removePath($core . '\\resources\\views\\admin\\system\\update.blade.php', $core);

// Remove stale Viser/N-form compatibility aliases after migration to form-data.
foreach ([
    $core . '\\resources\\views\\components\\viser-form.blade.php',
    $core . '\\resources\\views\\components\\viser-form-data.blade.php',
    $core . '\\resources\\views\\components\\n-form.blade.php',
    $core . '\\resources\\views\\components\\n-form-data.blade.php',
    $core . '\\app\\View\\Components\\nForm.php',
] as $file) {
    removePath($file, $core);
}

// Remove the disabled Laramin vendor hook package completely from this deployment.
removePath($core . '\\vendor\\laramin', $core);

// Remove dormant gateway controller surfaces. NATCODEV deployment uses Paystack + Monnify only.
$gatewayRoot = $core . '\\app\\Http\\Controllers\\Gateway';
$keep = ['Paystack', 'Monnify'];
foreach (glob($gatewayRoot . '\\*', GLOB_ONLYDIR) as $dir) {
    if (!in_array(basename($dir), $keep, true)) {
        removePath($dir, $core);
    }
}

// Composer should not require the removed Laramin package or SDKs for removed gateway controllers.
$composer = $core . '\\composer.json';
$json = json_decode(file_get_contents($composer), true, 512, JSON_THROW_ON_ERROR);
foreach ([
    'laramin/utility',
    'authorizenet/authorizenet',
    'btcpayserver/btcpayserver-greenfield-php',
    'coingate/coingate-php',
    'mollie/laravel-mollie',
    'razorpay/razorpay',
    'stripe/stripe-php',
] as $package) {
    unset($json['require'][$package]);
}
$json['extra'] ??= [];
$json['extra']['laravel'] ??= [];
$json['extra']['laravel']['dont-discover'] ??= [];
foreach (['laramin/utility', 'mollie/laravel-mollie'] as $package) {
    if (!in_array($package, $json['extra']['laravel']['dont-discover'], true)) {
        $json['extra']['laravel']['dont-discover'][] = $package;
    }
}
saveFile($composer, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);

echo 'UNNECESSARY_VENDOR_SURFACES_REMOVED' . PHP_EOL;
