<?php

$root = 'C:\\Users\\user\\Downloads\\UniServerZ\\www\\cocopay\\core';
$scanRoots = [
    $root . '\\app',
    $root . '\\config',
    $root . '\\routes',
    $root . '\\resources',
    $root . '\\database',
    $root . '\\vendor\\laramin',
    $root . '\\composer.json',
    $root . '\\.env',
];

$patterns = [
    'code_execution' => '/\b(eval|assert|shell_exec|system|passthru|proc_open|popen|pcntl_exec|create_function)\s*\(/i',
    'obfuscation' => '/\b(base64_decode|gzinflate|gzuncompress|str_rot13|hex2bin)\s*\(/i',
    'remote_file_read' => '/\b(file_get_contents|fopen|readfile)\s*\(\s*[\'"]https?:\/\//i',
    'curl' => '/\bcurl_exec\s*\(/i',
    'dangerous_include' => '/\b(include|require|include_once|require_once)\s*\(?\s*\$/i',
    'unserialize' => '/\bunserialize\s*\(/i',
    'vendor_brand_hook' => '/\b(Viserlab|ViserLab|VISER|ViserBank|viserbank|viserlab|Onumoti|VugiChugi|Laramin|laramin|purchase|license|activation)\b/i',
    'remote_control' => '/\b(maintenance_mode|updateFile|downloadFile|system-update|update-upload)\b/i',
];

function collectFiles(array $roots): array
{
    $files = [];
    foreach ($roots as $root) {
        if (is_file($root)) {
            $files[] = $root;
            continue;
        }
        if (!is_dir($root)) {
            continue;
        }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $ext = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
            if (in_array($ext, ['php', 'blade.php', 'json', 'env', 'js', 'css'], true) || str_ends_with($file->getFilename(), '.env')) {
                $files[] = $file->getPathname();
            }
        }
    }
    return $files;
}

$findings = [];
foreach (collectFiles($scanRoots) as $file) {
    $lines = @file($file, FILE_IGNORE_NEW_LINES);
    if (!$lines) {
        continue;
    }
    foreach ($lines as $index => $line) {
        foreach ($patterns as $name => $regex) {
            if (preg_match($regex, $line)) {
                $findings[] = [
                    'type' => $name,
                    'file' => $file,
                    'line' => $index + 1,
                    'text' => trim($line),
                ];
            }
        }
    }
}

foreach ($findings as $finding) {
    echo "{$finding['type']}|{$finding['file']}:{$finding['line']}|{$finding['text']}" . PHP_EOL;
}

echo 'TOTAL_FINDINGS=' . count($findings) . PHP_EOL;
