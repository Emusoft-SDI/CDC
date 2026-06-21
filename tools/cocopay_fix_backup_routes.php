<?php

$routes = 'C:\\Users\\user\\Downloads\\UniServerZ\\www\\cocopay\\core\\routes\\admin.php';
$code = file_get_contents($routes);

if (!str_contains($code, "Route::controller('BackupController')->prefix('backups')")) {
    $needle = "    // SEO\r\n";
    $insert = "    Route::controller('BackupController')->prefix('backups')->name('backups.')->group(function () {\r\n        Route::get('/', 'index')->name('index');\r\n        Route::post('create', 'create')->name('create');\r\n        Route::get('download/{file}', 'download')->name('download');\r\n    });\r\n\r\n";
    $code = str_replace($needle, $insert . $needle, $code);
    file_put_contents($routes, $code);
}

echo "BACKUP_ROUTES_FIXED\n";
