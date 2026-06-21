<?php

$file = 'C:/Users/user/Downloads/UniServerZ/www/cocopay/core/app/Http/Controllers/User/Auth/RegisterController.php';
$contents = file_get_contents($file);
$contents = str_replace(
    <<<'PHP'
        $pageTitle  = "Register";
        $info       = json_decode(json_encode(getIpInfo()), true);
        $mobileCode = 'NG';
PHP,
    <<<'PHP'
        $pageTitle  = "Register";
        $mobileCode = 'NG';
PHP,
    $contents
);
file_put_contents($file, $contents);
echo "RegisterController unused IP lookup removed\n";
