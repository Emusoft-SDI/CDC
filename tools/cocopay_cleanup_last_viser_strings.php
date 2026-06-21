<?php

$files = [
    'C:/Users/user/Downloads/UniServerZ/www/cocopay/core/resources/views/templates/crystal_sky/sections/faq.blade.php' => [
        'viserBank-faq' => 'natcodev-faq',
    ],
    'C:/Users/user/Downloads/UniServerZ/www/cocopay/assets/admin/css/pdf.css' => [
        '/* ---------viserbank-user-panel-pdf */' => '/* ---------natcodev-user-panel-pdf */',
    ],
];

foreach ($files as $file => $map) {
    $contents = file_get_contents($file);
    $contents = str_replace(array_keys($map), array_values($map), $contents);
    file_put_contents($file, $contents);
}

echo "LAST_VISER_STRINGS_CLEANED\n";

