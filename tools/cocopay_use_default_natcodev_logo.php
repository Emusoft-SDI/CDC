<?php

$base = 'C:/Users/user/Downloads/UniServerZ/www';
$sourceLogo = $base . '/CDC/assets/logo/natcodev-logo.svg';
$targetDir = $base . '/cocopay/assets/images/logoIcon';
$targetLogo = $targetDir . '/logo.svg';
$targetDarkLogo = $targetDir . '/logo_dark.svg';
$targetFavicon = $targetDir . '/favicon.svg';
$helperFile = $base . '/cocopay/core/app/Http/Helpers/helpers.php';

if (!is_file($sourceLogo) || filesize($sourceLogo) === 0) {
    throw new RuntimeException("Default NATCODEV SVG logo was not found or is empty: {$sourceLogo}");
}

copy($sourceLogo, $targetLogo);
copy($sourceLogo, $targetDarkLogo);

$favicon = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120" role="img" aria-label="NATCODEV">
  <rect width="120" height="120" rx="24" fill="#f7fbf3"/>
  <circle cx="60" cy="55" r="34" fill="#2d5016"/>
  <path d="M45 64c22-2 37-15 45-39 7 31-5 52-35 63 9-9 13-17 12-25-6 6-14 10-22 1Z" fill="#c9a227"/>
  <path d="M39 53c15-20 33-28 54-24-20 7-34 19-43 37-1-6-5-10-11-13Z" fill="#fff"/>
  <text x="60" y="103" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="28" font-weight="700" fill="#2d5016">N</text>
</svg>
SVG;
file_put_contents($targetFavicon, $favicon);

$helpers = file_get_contents($helperFile);
$pattern = <<<'PHP'
function siteLogo($type = null)
{
    $name = $type ? "/logo_$type.png" : '/logo.png';
    $path = getFilePath('logoIcon') . $name;
    $version = @filemtime(public_path($path)) ?: time();
    return getImage($path) . '?v=' . $version;
}

function siteFavicon()
{
    $path = getFilePath('logoIcon') . '/favicon.png';
    $version = @filemtime(public_path($path)) ?: time();
    return getImage($path) . '?v=' . $version;
}
PHP;
$replacement = <<<'PHP'
function siteLogo($type = null)
{
    $name = $type ? "/logo_$type.svg" : '/logo.svg';
    $path = getFilePath('logoIcon') . $name;
    $version = @filemtime(public_path($path)) ?: time();
    return asset($path) . '?v=' . $version;
}

function siteFavicon()
{
    $path = getFilePath('logoIcon') . '/favicon.svg';
    $version = @filemtime(public_path($path)) ?: time();
    return asset($path) . '?v=' . $version;
}
PHP;

if (strpos($helpers, $pattern) === false) {
    throw new RuntimeException('Expected siteLogo/siteFavicon helper block was not found.');
}

$helpers = str_replace($pattern, $replacement, $helpers);
file_put_contents($helperFile, $helpers);

echo "Default NATCODEV SVG logo installed for cocopay." . PHP_EOL;

