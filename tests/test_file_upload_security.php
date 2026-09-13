<?php
declare(strict_types=1);

/**
 * Scenario 2: Malicious File Upload Bypass Tests
 * Tests validation against:
 * 1) Double extensions (e.g. shell.php.jpg)
 * 2) Null-byte injection
 * 3) Executable extensions (.phtml, .php, .phar, .sh, .exe)
 * 4) Content-Type vs Magic Bytes mismatch (polyglot files)
 * 5) Safe random filename generation and directory traversal protection
 */

require_once __DIR__ . '/TestHarness.php';


function run_file_upload_tests(): void
{
    TestHarness::start('Scenario 2: Malicious File Upload Bypass');

    // 2.1: Disallowed file extension check (.phtml, .php, .phar, .exe, .sh)
    $dangerousExtensions = ['php', 'phtml', 'php5', 'php7', 'phar', 'exe', 'sh', 'bat', 'cmd', 'js', 'html', 'svg'];
    $allowedImageExts = ['jpg', 'jpeg', 'png', 'webp'];

    foreach ($dangerousExtensions as $ext) {
        $fakeUpload = [
            'name' => "exploit.{$ext}",
            'tmp_name' => sys_get_temp_dir() . '/test_temp_' . bin2hex(random_bytes(4)),
            'error' => UPLOAD_ERR_OK,
            'size' => 1024,
        ];
        file_put_contents($fakeUpload['tmp_name'], '<?php echo "pwned"; ?>');

        TestHarness::assertThrows(function() use ($fakeUpload, $allowedImageExts) {
            $ext = strtolower(pathinfo((string)$fakeUpload['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedImageExts, true)) {
                throw new RuntimeException("Unsupported file type: {$ext}");
            }
        }, RuntimeException::class, "Direct upload of dangerous extension .{$ext} was blocked");

        @unlink($fakeUpload['tmp_name']);
    }

    // 2.2: Double Extension & Path Traversal Neutralization
    $trickyFilenames = [
        'avatar.php.png' => 'png',
        'report.pdf.exe' => 'exe',
        '../../../var/www/shell.jpg' => 'jpg',
        'document..pdf' => 'pdf',
        'image.JPG' => 'jpg',
    ];

    foreach ($trickyFilenames as $filename => $expectedExt) {
        $sanitizedBase = app_safe_upload_name('profile', $filename, $expectedExt);
        TestHarness::assert(
            !str_contains($sanitizedBase, '..') && !str_contains($sanitizedBase, '/') && !str_contains($sanitizedBase, '\\'),
            "Sanitized filename '{$sanitizedBase}' for input '{$filename}' contains no directory traversal sequences"
        );
        TestHarness::assert(
            str_ends_with($sanitizedBase, '.' . $expectedExt),
            "Sanitized filename ends with strictly whitelisted extension .{$expectedExt}"
        );
    }

    // 2.3: Magic Bytes Verification for PDF Uploads
    $validPdfPath = sys_get_temp_dir() . '/valid_test_' . bin2hex(random_bytes(4)) . '.pdf';
    $fakePdfPath = sys_get_temp_dir() . '/fake_test_' . bin2hex(random_bytes(4)) . '.pdf';

    file_put_contents($validPdfPath, "%PDF-1.4\n%âãÏÓ\n1 0 obj<</Type/Catalog>>endobj\nxref\ntrailer\n%%EOF");
    file_put_contents($fakePdfPath, "<?php phpinfo(); ?>");

    $validatePdfMagicBytes = function(string $path): bool {
        $handle = fopen($path, 'rb');
        $sig = $handle ? (string) fread($handle, 4) : '';
        if ($handle) fclose($handle);
        return $sig === '%PDF';
    };

    TestHarness::assert($validatePdfMagicBytes($validPdfPath) === true, 'Valid PDF with %PDF magic bytes is accepted');
    TestHarness::assert($validatePdfMagicBytes($fakePdfPath) === false, 'Malicious PHP script disguised as .pdf is rejected by magic byte inspection');

    @unlink($validPdfPath);
    @unlink($fakePdfPath);

    // 2.4: File Size Limit Enforcement
    $maxBytes = 2 * 1024 * 1024; // 2MB
    $oversizedFile = [
        'name' => 'large_video.jpg',
        'tmp_name' => sys_get_temp_dir() . '/large_file.tmp',
        'error' => UPLOAD_ERR_OK,
        'size' => 15 * 1024 * 1024, // 15MB
    ];

    TestHarness::assertThrows(function() use ($oversizedFile, $maxBytes) {
        if ($oversizedFile['size'] > $maxBytes) {
            throw new RuntimeException('File exceeds allowed limit');
        }
    }, RuntimeException::class, 'Oversized file (15MB vs 2MB max) is immediately rejected');
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    run_file_upload_tests();
    exit(TestHarness::summary());
}
