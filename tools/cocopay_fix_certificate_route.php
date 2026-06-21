<?php

$routes = 'C:\\Users\\user\\Downloads\\UniServerZ\\www\\cocopay\\core\\routes\\admin.php';
$code = file_get_contents($routes);

if (!str_contains($code, "Route::get('certificates', 'certificateApplications')->name('certificates');")) {
    $code = str_replace(
        "        Route::get('owes/loan/{userId}', 'owesLoan')->name('owes.loan');\r\n\r\n        Route::get('detail/{id}', 'detail')->name('detail');",
        "        Route::get('owes/loan/{userId}', 'owesLoan')->name('owes.loan');\r\n        Route::get('certificates', 'certificateApplications')->name('certificates');\r\n\r\n        Route::get('detail/{id}', 'detail')->name('detail');",
        $code
    );
}

file_put_contents($routes, $code);

echo "CERTIFICATE_ROUTE_FIXED\n";
