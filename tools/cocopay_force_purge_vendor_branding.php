<?php

$root = 'C:\\Users\\user\\Downloads\\UniServerZ\\www\\cocopay\\core';

function write_file_checked(string $path, string $content): void
{
    if (!is_file($path)) {
        throw new RuntimeException("Missing file: {$path}");
    }
    file_put_contents($path, $content);
    echo "UPDATED={$path}" . PHP_EOL;
}

$controller = $root . '\\app\\Http\\Controllers\\Controller.php';
$content = file_get_contents($controller);
$content = str_replace("use Laramin\\Utility\\Onumoti;\r\n", '', $content);
$content = str_replace("use Laramin\\Utility\\Onumoti;\n", '', $content);
$content = preg_replace('/\s*\$className = get_called_class\(\);\R\s*Onumoti::mySite\(\$this, \$className\);\R/', "\n", $content);
write_file_checked($controller, $content);

$routeService = $root . '\\app\\Providers\\RouteServiceProvider.php';
$content = file_get_contents($routeService);
$content = str_replace("use Laramin\\Utility\\VugiChugi;\r\n", '', $content);
$content = str_replace("use Laramin\\Utility\\VugiChugi;\n", '', $content);
$content = str_replace("Route::namespace(\$this->namespace)->middleware(VugiChugi::mdNm())->group(function () {", "Route::namespace(\$this->namespace)->group(function () {", $content);
write_file_checked($routeService, $content);

foreach ([
    $root . '\\app\\Http\\Controllers\\Admin\\Auth\\LoginController.php',
    $root . '\\app\\Http\\Controllers\\BranchStaff\\Auth\\LoginController.php',
] as $path) {
    $content = file_get_contents($path);
    $content = str_replace("use Laramin\\Utility\\Onumoti;\r\n", '', $content);
    $content = str_replace("use Laramin\\Utility\\Onumoti;\n", '', $content);
    $content = preg_replace("/\\s*Onumoti::getData\\(\\);\\R/", "\n", $content);
    write_file_checked($path, $content);
}

$mail = $root . '\\config\\mail.php';
$content = file_get_contents($mail);
$content = str_replace("'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com')", "'address' => env('MAIL_FROM_ADDRESS', 'info@natcodev.com.ng')", $content);
$content = str_replace("'name' => env('MAIL_FROM_NAME', 'Example')", "'name' => env('MAIL_FROM_NAME', 'NATCODEV Coconut Farmers Cooperative')", $content);
write_file_checked($mail, $content);

$env = $root . '\\.env';
$content = file_get_contents($env);
$pairs = [
    'APP_NAME' => '"NATCODEV Coconut Farmers Cooperative"',
    'MAIL_FROM_ADDRESS' => '"info@natcodev.com.ng"',
    'MAIL_FROM_NAME' => '"NATCODEV Coconut Farmers Cooperative"',
];
foreach ($pairs as $key => $value) {
    if (preg_match('/^' . preg_quote($key, '/') . '=.*/m', $content)) {
        $content = preg_replace('/^' . preg_quote($key, '/') . '=.*/m', $key . '=' . $value, $content);
    } else {
        $content .= PHP_EOL . $key . '=' . $value;
    }
}
write_file_checked($env, $content);

$composer = $root . '\\composer.json';
$json = json_decode(file_get_contents($composer), true, 512, JSON_THROW_ON_ERROR);
unset($json['require']['laramin/utility']);
if (isset($json['extra']['laravel']['dont-discover'])) {
    $json['extra']['laravel']['dont-discover'] = array_values(array_filter(
        $json['extra']['laravel']['dont-discover'],
        static fn ($package) => $package !== 'laramin/utility'
    ));
    if (!$json['extra']['laravel']['dont-discover']) {
        unset($json['extra']['laravel']['dont-discover']);
    }
}
write_file_checked($composer, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);

echo 'FORCE_PURGE_COMPLETE' . PHP_EOL;
