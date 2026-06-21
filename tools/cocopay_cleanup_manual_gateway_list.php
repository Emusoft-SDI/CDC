<?php

$file = 'C:/Users/user/Downloads/UniServerZ/www/cocopay/core/app/Http/Controllers/Admin/ManualGatewayController.php';
$contents = file_get_contents($file);
$contents = str_replace(
    <<<'PHP'
        $pageTitle = 'Monnify Gateway';
        $gateways = Gateway::manual()->whereIn('alias', ['monnify'])->orderBy('id', 'desc')->get();
PHP,
    <<<'PHP'
        $pageTitle = 'Manual Gateways';
        $gateways = Gateway::manual()->whereRaw('1 = 0')->orderBy('id', 'desc')->get();
PHP,
    $contents
);
file_put_contents($file, $contents);
echo "Manual gateway list cleaned\n";
