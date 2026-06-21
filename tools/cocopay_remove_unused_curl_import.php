<?php

$file = 'C:/Users/user/Downloads/UniServerZ/www/cocopay/core/app/Http/Controllers/Admin/AdminController.php';
$contents = file_get_contents($file);
$contents = str_replace("use App\Lib\CurlRequest;\n", '', $contents);
file_put_contents($file, $contents);

echo "UNUSED_CURL_IMPORT_REMOVED\n";

