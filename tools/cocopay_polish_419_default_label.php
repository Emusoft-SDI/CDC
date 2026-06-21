<?php

$path = 'C:\\Users\\user\\Downloads\\UniServerZ\\www\\cocopay\\core\\resources\\views\\errors\\419.blade.php';
$contents = file_get_contents($path);
if ($contents === false) {
    fwrite(STDERR, "Unable to read 419.blade.php\n");
    exit(1);
}

$contents = str_replace(
    "\$targetLabel = 'Go to Home';",
    "\$targetLabel = 'Cooperative Home';",
    $contents
);

file_put_contents($path, $contents);

echo "POLISHED_419_DEFAULT_LABEL\n";
