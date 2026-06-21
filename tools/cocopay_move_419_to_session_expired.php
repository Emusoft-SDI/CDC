<?php

$root = 'C:\\Users\\user\\Downloads\\UniServerZ\\www\\cocopay\\core';
$errorDir = $root . '\\resources\\views\\errors';
$legacyPath = $errorDir . '\\419.blade.php';
$sessionPath = $errorDir . '\\session_expired.blade.php';

$contents = file_get_contents($legacyPath);
if ($contents === false) {
    fwrite(STDERR, "Unable to read 419.blade.php\n");
    exit(1);
}

$contents = str_replace(
    "{{ \$general->siteName(\$pageTitle ?? 'Session Expired | NATCODEV Cooperative') }}",
    "{{ \$general->siteName(\$pageTitle ?? 'Session Expired | NATCODEV Cooperative') }}",
    $contents
);

$contents = str_replace(
    'assets/errors/images/error-419.png',
    'assets/errors/images/error-404.png',
    $contents
);

$contents = str_replace(
    "<h2>@lang('Session Expired')</h2>",
    "<h2>@lang('Session Expired')</h2>",
    $contents
);

file_put_contents($sessionPath, $contents);

$legacy = <<<'BLADE'
@include('errors.session_expired')
BLADE;

file_put_contents($legacyPath, $legacy . PHP_EOL);

echo "MOVED_SESSION_EXPIRED_VIEW\n";
