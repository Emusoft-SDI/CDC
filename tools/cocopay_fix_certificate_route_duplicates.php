<?php

$path = 'C:\\Users\\user\\Downloads\\UniServerZ\\www\\cocopay\\core\\routes\\admin.php';
$contents = file_get_contents($path);
if ($contents === false) {
    fwrite(STDERR, "Unable to read admin routes\n");
    exit(1);
}

$lines = preg_split("/\r\n|\n|\r/", $contents);
$filtered = [];
foreach ($lines as $line) {
    if (str_contains($line, "certificate-view/{id}") || str_contains($line, "certificate-approve/{id}") || str_contains($line, "certificate-reject/{id}")) {
        continue;
    }
    $filtered[] = $line;
}

$contents = implode(PHP_EOL, $filtered);
$needle = "        Route::post('status/{id}', 'status')->name('status');";
$insert = $needle . PHP_EOL
    . "        Route::get('certificate-view/{id}', 'viewCertificate')->name('certificate.view');" . PHP_EOL
    . "        Route::post('certificate-approve/{id}', 'approveCertificate')->name('certificate.approve');" . PHP_EOL
    . "        Route::post('certificate-reject/{id}', 'rejectCertificate')->name('certificate.reject');";

$pos = strpos($contents, $needle);
if ($pos === false) {
    fwrite(STDERR, "User status route not found\n");
    exit(1);
}

$contents = substr_replace($contents, $insert, $pos, strlen($needle));
file_put_contents($path, $contents);

echo "FIXED_CERTIFICATE_ROUTE_DUPLICATES\n";
