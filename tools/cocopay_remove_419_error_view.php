<?php

$path = 'C:\\Users\\user\\Downloads\\UniServerZ\\www\\cocopay\\core\\resources\\views\\errors\\419.blade.php';

if (is_file($path) && !unlink($path)) {
    fwrite(STDERR, "Unable to remove 419.blade.php\n");
    exit(1);
}

echo "REMOVED_419_ERROR_VIEW\n";
