<?php

$root = 'C:\\Users\\user\\Downloads\\UniServerZ\\www\\cocopay\\core';

function replace_in_file($file, array $replacements): void
{
    $code = file_get_contents($file);
    foreach ($replacements as $from => $to) {
        $code = str_replace($from, $to, $code);
    }
    file_put_contents($file, $code);
}

replace_in_file($root . '\\app\\Http\\Controllers\\Controller.php', [
    "use Laramin\\Utility\\Onumoti;\r\n" => '',
    "\r\n        \$className = get_called_class();\r\n        Onumoti::mySite(\$this, \$className);" => '',
]);

foreach ([
    $root . '\\app\\Http\\Controllers\\Admin\\Auth\\LoginController.php',
    $root . '\\app\\Http\\Controllers\\BranchStaff\\Auth\\LoginController.php',
] as $file) {
    replace_in_file($file, [
        "use Laramin\\Utility\\Onumoti;\r\n" => '',
        "\r\n        Onumoti::getData();" => '',
        "\n        Onumoti::getData();" => '',
    ]);
}

replace_in_file($root . '\\app\\Providers\\RouteServiceProvider.php', [
    "use Laramin\\Utility\\VugiChugi;\r\n" => '',
    "            Route::namespace(\$this->namespace)->middleware(VugiChugi::mdNm())->group(function () {\r\n" => "            Route::namespace(\$this->namespace)->group(function () {\r\n",
]);

$composer = $root . '\\composer.json';
$json = json_decode(file_get_contents($composer), true);
$json['extra']['laravel']['dont-discover'] = array_values(array_unique(array_merge(
    $json['extra']['laravel']['dont-discover'] ?? [],
    ['laramin/utility']
)));
file_put_contents($composer, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);

$vendor = $root . '\\vendor\\laramin\\utility\\src';
foreach ([
    'Onumoti.php' => 'NatcodevVendorHookDisabled.php',
    'UtilityServiceProvider.php' => 'NatcodevDisabledServiceProvider.php',
    'routes.php' => 'natcodev_disabled_routes.php',
] as $from => $to) {
    $fromPath = $vendor . '\\' . $from;
    $toPath = $vendor . '\\' . $to;
    if (is_file($fromPath)) {
        if (is_file($toPath)) {
            unlink($toPath);
        }
        rename($fromPath, $toPath);
    }
}

replace_in_file($root . '\\.env', [
    'APP_NAME=CocoPay' => 'APP_NAME="NATCODEV Coconut Farmers Cooperative"',
    'MAIL_FROM_ADDRESS="hello@example.com"' => 'MAIL_FROM_ADDRESS="info@natcodev.com.ng"',
    'MAIL_FROM_NAME="${APP_NAME}"' => 'MAIL_FROM_NAME="NATCODEV Coconut Farmers Cooperative"',
]);

replace_in_file($root . '\\app\\Http\\Controllers\\Admin\\AutomaticGatewayController.php', [
    \"\\$pageTitle = 'Paystack Gateway';\" => \"\\$pageTitle = 'Secure Payment Gateway Integrations';\",
]);

echo \"VENDOR_HOOKS_PURGED_AND_BRANDING_UPDATED\\n\";
