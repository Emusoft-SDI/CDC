<?php

$path = 'C:/Users/user/Downloads/UniServerZ/www/cocopay/assets/images/logoIcon/logo.png';
$image = imagecreatefrompng($path);
if (!$image) {
    throw new RuntimeException('Unable to read logo.png');
}

$width = imagesx($image);
$height = imagesy($image);
$points = [
    'top_left' => [0, 0],
    'top_right' => [$width - 1, 0],
    'bottom_left' => [0, $height - 1],
    'bottom_right' => [$width - 1, $height - 1],
    'center' => [(int) floor($width / 2), (int) floor($height / 2)],
];

$result = [];
foreach ($points as $name => [$x, $y]) {
    $rgba = imagecolorat($image, $x, $y);
    $colors = imagecolorsforindex($image, $rgba);
    $result[$name] = $colors['alpha'];
}

imagedestroy($image);

echo json_encode([
    'width' => $width,
    'height' => $height,
    'alpha' => $result,
], JSON_PRETTY_PRINT) . PHP_EOL;
