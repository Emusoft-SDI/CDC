<?php

$root = 'C:/Users/user/Downloads/UniServerZ/www/cocopay/core';
$source = $root . '/app/View/Components/nForm.php';
$target = $root . '/app/View/Components/ViserForm.php';

if (!is_file($source)) {
    throw new RuntimeException("Source component not found: {$source}");
}

$contents = file_get_contents($source);

if (strpos($contents, 'class ViserForm extends Component') === false) {
    throw new RuntimeException('Source component does not contain the expected ViserForm class.');
}

file_put_contents($target, $contents);

echo "Restored {$target}\n";
