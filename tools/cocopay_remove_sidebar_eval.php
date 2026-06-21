<?php

$files = [
    'C:\\Users\\user\\Downloads\\UniServerZ\\www\\cocopay\\core\\resources\\views\\admin\\partials\\sidenav.blade.php',
    'C:\\Users\\user\\Downloads\\UniServerZ\\www\\cocopay\\core\\resources\\views\\branch_staff\\partials\\sidenav.blade.php',
];

foreach ($files as $file) {
    $code = file_get_contents($file);
    $code = str_replace('scrollTop: eval($(".active").offset().top - 320)', 'scrollTop: $(".active").offset().top - 320', $code);
    file_put_contents($file, $code);
}

echo "SIDEBAR_EVAL_REMOVED\n";
