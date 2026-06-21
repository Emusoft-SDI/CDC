<?php

$logoDir = 'C:/Users/user/Downloads/UniServerZ/www/cocopay/assets/images/logoIcon';
$files = ['logo.png', 'logo_dark.png', 'favicon.png'];

if (!extension_loaded('gd')) {
    throw new RuntimeException('GD extension is required.');
}

foreach ($files as $file) {
    $path = $logoDir . '/' . $file;
    if (!is_file($path)) {
        throw new RuntimeException("Missing logo file: {$path}");
    }

    $source = imagecreatefrompng($path);
    if (!$source) {
        throw new RuntimeException("Unable to read PNG: {$path}");
    }

    $width = imagesx($source);
    $height = imagesy($source);
    $size = min($width, $height);
    $centerX = $width / 2;
    $centerY = $height / 2;
    $radius = ($size / 2) - 4;
    $softEdge = 3;

    $target = imagecreatetruecolor($width, $height);
    imagealphablending($target, false);
    imagesavealpha($target, true);

    $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
    imagefilledrectangle($target, 0, 0, $width, $height, $transparent);

    for ($y = 0; $y < $height; $y++) {
        for ($x = 0; $x < $width; $x++) {
            $dx = $x + 0.5 - $centerX;
            $dy = $y + 0.5 - $centerY;
            $distance = sqrt(($dx * $dx) + ($dy * $dy));

            if ($distance > $radius + $softEdge) {
                continue;
            }

            $rgba = imagecolorat($source, $x, $y);
            $colors = imagecolorsforindex($source, $rgba);
            $alpha = $colors['alpha'];

            if ($distance > $radius - $softEdge) {
                $edgeFactor = ($radius + $softEdge - $distance) / ($softEdge * 2);
                $edgeFactor = max(0, min(1, $edgeFactor));
                $alpha = (int) round(127 - ((127 - $alpha) * $edgeFactor));
            }

            $color = imagecolorallocatealpha($target, $colors['red'], $colors['green'], $colors['blue'], $alpha);
            imagesetpixel($target, $x, $y, $color);
        }
    }

    imagepng($target, $path, 9);
    imagedestroy($source);
    imagedestroy($target);
}

echo "Logo PNG files converted to transparent circular assets.\n";
