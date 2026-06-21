<?php

$root = 'C:\\Users\\user\\Downloads\\UniServerZ\\www\\cocopay\\core';

file_put_contents($root . '\\config\\backup.php', <<<'PHP'
<?php

return [
    'local_path' => env('BACKUP_LOCAL_PATH', storage_path('app/backups')),
    'include_assets' => env('BACKUP_INCLUDE_ASSETS', true),
    's3_path' => env('BACKUP_S3_PATH', 'natcodev-backups'),
    'google_drive' => [
        'access_token' => env('GOOGLE_DRIVE_ACCESS_TOKEN'),
        'folder_id' => env('GOOGLE_DRIVE_FOLDER_ID'),
    ],
    'bitbucket' => [
        'workspace' => env('BITBUCKET_WORKSPACE'),
        'repo_slug' => env('BITBUCKET_REPO_SLUG'),
        'username' => env('BITBUCKET_USERNAME'),
        'app_password' => env('BITBUCKET_APP_PASSWORD'),
    ],
];
PHP);

file_put_contents($root . '\\app\\Http\\Controllers\\Admin\\BackupController.php', <<<'PHP'
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class BackupController extends Controller
{
    public function index()
    {
        $pageTitle = 'Platform Backup';
        $backupPath = config('backup.local_path');
        if (!is_dir($backupPath)) {
            @mkdir($backupPath, 0755, true);
        }

        $files = collect(glob($backupPath . DIRECTORY_SEPARATOR . 'natcodev-backup-*.zip') ?: [])
            ->map(fn($file) => [
                'name' => basename($file),
                'path' => $file,
                'size' => filesize($file),
                'created_at' => filemtime($file),
            ])
            ->sortByDesc('created_at')
            ->values();

        $targets = $this->targetStatus();

        return view('admin.system.backup', compact('pageTitle', 'files', 'targets'));
    }

    public function create(Request $request)
    {
        $request->validate([
            'target' => 'required|in:local,s3,google_drive,bitbucket',
        ]);

        try {
            $archive = $this->createArchive();
            $message = 'Backup created locally: ' . basename($archive);

            if ($request->target !== 'local') {
                $this->pushArchive($archive, $request->target);
                $message .= ' and pushed to ' . keyToTitle($request->target);
            }

            $notify[] = ['success', $message];
        } catch (\Throwable $e) {
            $notify[] = ['error', $e->getMessage()];
        }

        return back()->withNotify($notify);
    }

    public function download($file)
    {
        $file = basename($file);
        $path = config('backup.local_path') . DIRECTORY_SEPARATOR . $file;

        if (!is_file($path) || !str_starts_with($file, 'natcodev-backup-')) {
            abort(404);
        }

        return response()->download($path);
    }

    private function createArchive(): string
    {
        if (!class_exists(ZipArchive::class)) {
            throw new \RuntimeException('PHP zip extension is required to create platform backups.');
        }

        $backupPath = config('backup.local_path');
        if (!is_dir($backupPath)) {
            mkdir($backupPath, 0755, true);
        }

        $stamp = now()->format('Ymd-His');
        $workDir = storage_path('app/backup-work-' . $stamp);
        mkdir($workDir, 0755, true);

        $sqlPath = $workDir . DIRECTORY_SEPARATOR . 'database.sql';
        file_put_contents($sqlPath, $this->databaseDump());

        $manifest = [
            'name' => 'NATCODEV Cocopay backup',
            'created_at' => now()->toDateTimeString(),
            'app_url' => config('app.url'),
            'database' => config('database.connections.' . config('database.default') . '.database'),
            'includes' => ['database.sql', 'assets', 'storage_public'],
        ];
        file_put_contents($workDir . DIRECTORY_SEPARATOR . 'manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));

        $archive = $backupPath . DIRECTORY_SEPARATOR . 'natcodev-backup-' . $stamp . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Unable to create backup archive.');
        }

        $zip->addFile($sqlPath, 'database.sql');
        $zip->addFile($workDir . DIRECTORY_SEPARATOR . 'manifest.json', 'manifest.json');

        if (config('backup.include_assets')) {
            $this->addDirectoryToZip($zip, base_path('../assets'), 'assets');
            $this->addDirectoryToZip($zip, storage_path('app/public'), 'storage_public');
        }

        $zip->close();
        $this->removeDirectory($workDir);

        return $archive;
    }

    private function databaseDump(): string
    {
        $database = config('database.connections.' . config('database.default') . '.database');
        $dump = "-- NATCODEV Cocopay database backup\n";
        $dump .= "-- Database: {$database}\n";
        $dump .= "-- Created: " . now()->toDateTimeString() . "\n\n";
        $dump .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        foreach ($this->tables() as $table) {
            $safeTable = str_replace('`', '``', $table);
            $create = DB::select("SHOW CREATE TABLE `{$safeTable}`")[0];
            $createSql = $create->{'Create Table'};
            $dump .= "DROP TABLE IF EXISTS `{$safeTable}`;\n";
            $dump .= $createSql . ";\n\n";

            DB::table($table)->orderByRaw('1')->chunk(500, function ($rows) use (&$dump, $safeTable) {
                foreach ($rows as $row) {
                    $values = [];
                    foreach ((array) $row as $value) {
                        $values[] = $this->sqlValue($value);
                    }
                    $dump .= "INSERT INTO `{$safeTable}` VALUES (" . implode(',', $values) . ");\n";
                }
            });
            $dump .= "\n";
        }

        return $dump . "SET FOREIGN_KEY_CHECKS=1;\n";
    }

    private function tables(): array
    {
        $tables = [];
        foreach (DB::select('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"') as $row) {
            $tables[] = array_values((array) $row)[0];
        }
        return $tables;
    }

    private function sqlValue($value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        return DB::getPdo()->quote((string) $value);
    }

    private function addDirectoryToZip(ZipArchive $zip, string $path, string $prefix): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            if (!$item->isFile()) {
                continue;
            }

            $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($path) + 1));
            $zip->addFile($item->getPathname(), $prefix . '/' . $relative);
        }
    }

    private function pushArchive(string $archive, string $target): void
    {
        match ($target) {
            's3' => $this->pushS3($archive),
            'google_drive' => $this->pushGoogleDrive($archive),
            'bitbucket' => $this->pushBitbucket($archive),
            default => null,
        };
    }

    private function pushS3(string $archive): void
    {
        $remote = trim(config('backup.s3_path'), '/') . '/' . basename($archive);
        $stream = fopen($archive, 'r');
        Storage::disk('s3')->put($remote, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }
    }

    private function pushGoogleDrive(string $archive): void
    {
        $token = config('backup.google_drive.access_token');
        if (!$token) {
            throw new \RuntimeException('GOOGLE_DRIVE_ACCESS_TOKEN is not configured.');
        }

        $metadata = ['name' => basename($archive)];
        if (config('backup.google_drive.folder_id')) {
            $metadata['parents'] = [config('backup.google_drive.folder_id')];
        }

        $boundary = 'natcodev_backup_' . bin2hex(random_bytes(8));
        $body = "--{$boundary}\r\n";
        $body .= "Content-Type: application/json; charset=UTF-8\r\n\r\n";
        $body .= json_encode($metadata) . "\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: application/zip\r\n\r\n";
        $body .= file_get_contents($archive) . "\r\n";
        $body .= "--{$boundary}--";

        $this->curlRequest('POST', 'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart', [
            'Authorization: Bearer ' . $token,
            'Content-Type: multipart/related; boundary=' . $boundary,
        ], $body);
    }

    private function pushBitbucket(string $archive): void
    {
        $workspace = config('backup.bitbucket.workspace');
        $repo = config('backup.bitbucket.repo_slug');
        $username = config('backup.bitbucket.username');
        $password = config('backup.bitbucket.app_password');

        if (!$workspace || !$repo || !$username || !$password) {
            throw new \RuntimeException('Bitbucket backup credentials are not fully configured.');
        }

        $url = "https://api.bitbucket.org/2.0/repositories/{$workspace}/{$repo}/downloads";
        $this->curlRequest('POST', $url, [
            'Authorization: Basic ' . base64_encode($username . ':' . $password),
        ], ['files' => new \CURLFile($archive, 'application/zip', basename($archive))]);
    }

    private function curlRequest(string $method, string $url, array $headers, $payload): void
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error || $code >= 400) {
            throw new \RuntimeException($error ?: 'Remote backup upload failed with HTTP ' . $code . ': ' . substr((string) $response, 0, 300));
        }
    }

    private function targetStatus(): array
    {
        return [
            'local' => true,
            's3' => filled(config('filesystems.disks.s3.key')) && filled(config('filesystems.disks.s3.bucket')),
            'google_drive' => filled(config('backup.google_drive.access_token')),
            'bitbucket' => filled(config('backup.bitbucket.workspace')) && filled(config('backup.bitbucket.repo_slug')) && filled(config('backup.bitbucket.username')) && filled(config('backup.bitbucket.app_password')),
        ];
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }
}
PHP);

$routes = $root . '\\routes\\admin.php';
$routeCode = file_get_contents($routes);
if (!str_contains($routeCode, "Route::controller('BackupController')")) {
    $routeCode = str_replace(
        "    // SEO\n",
        "    Route::controller('BackupController')->prefix('backups')->name('backups.')->group(function () {\n        Route::get('/', 'index')->name('index');\n        Route::post('create', 'create')->name('create');\n        Route::get('download/{file}', 'download')->name('download');\n    });\n\n    // SEO\n",
        $routeCode
    );
    file_put_contents($routes, $routeCode);
}

$sidenav = $root . '\\resources\\views\\admin\\partials\\sidenav.blade.php';
$sideCode = file_get_contents($sidenav);
if (!str_contains($sideCode, "route('admin.backups.index')")) {
    $insert = <<<'BLADE'

                                @can('admin.backups.index')
                                    <li class="sidebar-menu-item {{ menuActive('admin.backups*') }}">
                                        <a class="nav-link" href="{{ route('admin.backups.index') }}">
                                            <i class="menu-icon las la-dot-circle"></i>
                                            <span class="menu-title">@lang('Platform Backup')</span>
                                        </a>
                                    </li>
                                @endcan
BLADE;
    $sideCode = str_replace("                                @can('admin.system.update')\n", $insert . "\n\n                                @can('admin.system.update')\n", $sideCode);
    file_put_contents($sidenav, $sideCode);
}

file_put_contents($root . '\\resources\\views\\admin\\system\\backup.blade.php', <<<'BLADE'
@extends('admin.layouts.app')

@section('panel')
    <div class="row gy-4">
        <div class="col-lg-5">
            <div class="card b-radius--10">
                <div class="card-header">
                    <h5 class="mb-0">@lang('Create Backup')</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">@lang('Creates a ZIP containing the database dump, manifest, public assets, and public storage files. Cloud upload runs only when credentials are configured.')</p>
                    <form action="{{ route('admin.backups.create') }}" method="POST">
                        @csrf
                        <div class="form-group">
                            <label>@lang('Backup Target')</label>
                            <select name="target" class="form-control">
                                <option value="local">@lang('Local Server')</option>
                                <option value="s3" @disabled(!$targets['s3'])>@lang('S3 Compatible Storage')</option>
                                <option value="google_drive" @disabled(!$targets['google_drive'])>@lang('Google Drive')</option>
                                <option value="bitbucket" @disabled(!$targets['bitbucket'])>@lang('Bitbucket Downloads')</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn--primary w-100 h-45">
                            <i class="las la-save"></i> @lang('Run Backup Now')
                        </button>
                    </form>
                </div>
            </div>

            <div class="card b-radius--10 mt-4">
                <div class="card-header">
                    <h5 class="mb-0">@lang('Remote Target Status')</h5>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        @foreach ([
                            'local' => 'Local Server',
                            's3' => 'S3 Compatible / Open Storage',
                            'google_drive' => 'Google Drive',
                            'bitbucket' => 'Bitbucket Downloads',
                        ] as $key => $label)
                            <li class="list-group-item d-flex justify-content-between">
                                <span>{{ __($label) }}</span>
                                <span class="badge badge--{{ $targets[$key] ? 'success' : 'warning' }}">{{ $targets[$key] ? __('Ready') : __('Needs Config') }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card b-radius--10">
                <div class="card-header">
                    <h5 class="mb-0">@lang('Local Backup Archives')</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table--light style--two table">
                            <thead>
                                <tr>
                                    <th>@lang('File')</th>
                                    <th>@lang('Size')</th>
                                    <th>@lang('Created')</th>
                                    <th>@lang('Action')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($files as $file)
                                    <tr>
                                        <td>{{ $file['name'] }}</td>
                                        <td>{{ showAmount($file['size'] / 1024 / 1024) }} MB</td>
                                        <td>{{ showDateTime($file['created_at']) }}</td>
                                        <td>
                                            <a class="btn btn-sm btn-outline--primary" href="{{ route('admin.backups.download', $file['name']) }}">
                                                <i class="las la-download"></i>@lang('Download')
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="100%" class="text-muted text-center">{{ __($emptyMessage) }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
BLADE);

echo "BACKUP_MODULE_ADDED\n";
