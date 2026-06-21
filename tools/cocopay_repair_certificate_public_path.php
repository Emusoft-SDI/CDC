<?php

$root = 'C:\\Users\\user\\Downloads\\UniServerZ\\www\\cocopay\\core';

$files = [
    $root . '\\app\\Http\\Controllers\\User\\ProfileController.php',
    $root . '\\app\\Http\\Controllers\\Admin\\ManageUsersController.php',
];

foreach ($files as $file) {
    $code = file_get_contents($file);
    $code = str_replace("is_file(base_path('../' . ))", "is_file(base_path('../' . \$certificate))", $code);
    $code = str_replace("response()->file(base_path('../' . ))", "response()->file(base_path('../' . \$certificate))", $code);
    file_put_contents($file, $code);
}

echo "CERTIFICATE_PUBLIC_PATH_REPAIRED\n";
