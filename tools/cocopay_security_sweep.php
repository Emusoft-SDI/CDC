<?php

$root = 'C:\\Users\\user\\Downloads\\UniServerZ\\www\\cocopay\\core';
$paths = [
    $root . '\\app',
    $root . '\\routes',
    $root . '\\resources\\views',
];

$syntaxErrors = [];
$suspicious = [];
$patterns = [
    'eval(',
    'base64_decode',
    'gzinflate',
    'shell_exec',
    'passthru',
    'proc_open',
    'popen',
    'assert(',
    'system(',
    'document.write',
    'atob(',
    'Function(',
];

$iterator = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    function ($file) {
        $path = $file->getPathname();
        return !str_contains($path, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)
            && !str_contains($path, DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR)
            && !str_contains($path, DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR);
    }
));

foreach ($iterator as $file) {
    if (!$file->isFile()) {
        continue;
    }

    $path = $file->getPathname();
    if (!preg_match('/\.(php|blade\.php|js)$/', $path)) {
        continue;
    }

    $content = file_get_contents($path);
    foreach ($patterns as $pattern) {
        if (stripos($content, $pattern) !== false) {
            $suspicious[] = str_replace($root . '\\', '', $path) . ' :: ' . $pattern;
        }
    }

    if (str_ends_with($path, '.php')) {
        exec('"' . PHP_BINARY . '" -l "' . $path . '" 2>&1', $output, $exit);
        if ($exit !== 0) {
            $syntaxErrors[] = str_replace($root . '\\', '', $path) . ' :: ' . implode(' ', $output);
        }
    }
}

echo 'syntax_errors=' . (count($syntaxErrors) ? implode('|', $syntaxErrors) : 'none') . PHP_EOL;
echo 'suspicious_hits=' . (count($suspicious) ? implode('|', array_slice($suspicious, 0, 40)) : 'none') . PHP_EOL;
