<?php

$core = 'C:\\Users\\user\\Downloads\\UniServerZ\\www\\cocopay\\core';

function saveJson(string $path, array $data): void
{
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    echo "UPDATED_JSON={$path}" . PHP_EOL;
}

$composer = $core . '\\composer.json';
$json = json_decode(file_get_contents($composer), true, 512, JSON_THROW_ON_ERROR);
if (isset($json['extra']['laravel']['dont-discover'])) {
    $json['extra']['laravel']['dont-discover'] = array_values(array_filter(
        $json['extra']['laravel']['dont-discover'],
        static fn ($package) => $package !== 'laramin/utility'
    ));
    if (!$json['extra']['laravel']['dont-discover']) {
        unset($json['extra']['laravel']['dont-discover']);
    }
}
saveJson($composer, $json);

$installedJson = $core . '\\vendor\\composer\\installed.json';
if (is_file($installedJson)) {
    $data = json_decode(file_get_contents($installedJson), true, 512, JSON_THROW_ON_ERROR);
    if (isset($data['packages'])) {
        $data['packages'] = array_values(array_filter($data['packages'], static fn ($pkg) => ($pkg['name'] ?? '') !== 'laramin/utility'));
    } else {
        $data = array_values(array_filter($data, static fn ($pkg) => ($pkg['name'] ?? '') !== 'laramin/utility'));
    }
    saveJson($installedJson, $data);
}

$installedPhp = $core . '\\vendor\\composer\\installed.php';
if (is_file($installedPhp)) {
    $data = require $installedPhp;
    unset($data['versions']['laramin/utility']);
    $export = var_export($data, true);
    file_put_contents($installedPhp, "<?php return {$export};\n");
    echo "UPDATED_PHP={$installedPhp}" . PHP_EOL;
}

foreach ([
    $core . '\\vendor\\composer\\autoload_psr4.php',
    $core . '\\vendor\\composer\\autoload_static.php',
    $core . '\\vendor\\composer\\autoload_classmap.php',
] as $path) {
    if (!is_file($path)) {
        continue;
    }
    $content = file_get_contents($path);
    $lines = preg_split('/\R/', $content);
    $lines = array_values(array_filter($lines, static function ($line) {
        return !str_contains($line, 'Laramin\\Utility') && !str_contains($line, '/laramin/utility') && !str_contains($line, '\\laramin\\utility');
    }));
    file_put_contents($path, implode(PHP_EOL, $lines));
    echo "UPDATED_AUTOLOAD={$path}" . PHP_EOL;
}

$versions = $core . '\\vendor\\composer\\package-versions-deprecated\\src\\PackageVersions\\Versions.php';
if (is_file($versions)) {
    $content = file_get_contents($versions);
    $content = preg_replace("/\\s*'laramin\\/utility'\\s*=>\\s*'[^']*',\\R/", '', $content);
    file_put_contents($versions, $content);
    echo "UPDATED_VERSIONS={$versions}" . PHP_EOL;
}

echo 'LARAMIN_METADATA_PURGED' . PHP_EOL;
