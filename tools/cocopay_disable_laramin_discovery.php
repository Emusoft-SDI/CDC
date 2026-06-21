<?php

$composer = 'C:\\Users\\user\\Downloads\\UniServerZ\\www\\cocopay\\core\\composer.json';
$json = json_decode(file_get_contents($composer), true, 512, JSON_THROW_ON_ERROR);

unset($json['require']['laramin/utility']);

$json['extra'] ??= [];
$json['extra']['laravel'] ??= [];
$json['extra']['laravel']['dont-discover'] ??= [];
if (!in_array('laramin/utility', $json['extra']['laravel']['dont-discover'], true)) {
    $json['extra']['laravel']['dont-discover'][] = 'laramin/utility';
}

file_put_contents($composer, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
echo 'LARAMIN_DISCOVERY_DISABLED' . PHP_EOL;
