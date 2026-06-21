<?php

$files = [
    'C:/Users/user/Downloads/UniServerZ/www/cocopay/core/app/Http/Controllers/Admin/AutomaticGatewayController.php' => [
        <<<'PHP'
        $pageTitle = 'Automatic Gateways';
        $gateways = Gateway::automatic()->with('currencies')->get();
PHP,
        <<<'PHP'
        $pageTitle = 'Paystack Gateway';
        $gateways = Gateway::automatic()->whereIn('alias', ['Paystack'])->with('currencies')->get();
PHP
    ],
    'C:/Users/user/Downloads/UniServerZ/www/cocopay/core/app/Http/Controllers/Admin/ManualGatewayController.php' => [
        <<<'PHP'
        $pageTitle = 'Manual Gateways';
        $gateways = Gateway::manual()->orderBy('id', 'desc')->get();
PHP,
        <<<'PHP'
        $pageTitle = 'Monnify Gateway';
        $gateways = Gateway::manual()->whereIn('alias', ['monnify'])->orderBy('id', 'desc')->get();
PHP
    ],
];

foreach ($files as $file => [$search, $replace]) {
    $contents = file_get_contents($file);
    if (!str_contains($contents, $replace)) {
        $contents = str_replace($search, $replace, $contents);
        file_put_contents($file, $contents);
    }
    echo basename($file) . " updated\n";
}
