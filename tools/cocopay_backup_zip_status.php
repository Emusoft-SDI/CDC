<?php

$view = 'C:\\Users\\user\\Downloads\\UniServerZ\\www\\cocopay\\core\\resources\\views\\admin\\system\\backup.blade.php';
$code = file_get_contents($view);

if (!str_contains($code, 'PHP ZIP extension is not enabled')) {
    $code = str_replace(
        "                    <p class=\"text-muted\">@lang('Creates a ZIP containing the database dump, manifest, public assets, and public storage files. Cloud upload runs only when credentials are configured.')</p>",
        "                    <p class=\"text-muted\">@lang('Creates a ZIP containing the database dump, manifest, public assets, and public storage files. Cloud upload runs only when credentials are configured.')</p>\n                    @if (!class_exists(ZipArchive::class))\n                        <div class=\"alert alert-warning p-3\">@lang('PHP ZIP extension is not enabled. Enable php_zip before running backups.')</div>\n                    @endif",
        $code
    );
    $code = str_replace(
        "<button type=\"submit\" class=\"btn btn--primary w-100 h-45\">",
        "<button type=\"submit\" class=\"btn btn--primary w-100 h-45\" @disabled(!class_exists(ZipArchive::class))>",
        $code
    );
    file_put_contents($view, $code);
}

echo "BACKUP_ZIP_STATUS_ADDED\n";
