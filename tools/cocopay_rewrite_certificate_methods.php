<?php

$root = 'C:\\Users\\user\\Downloads\\UniServerZ\\www\\cocopay\\core';

$profile = $root . '\\app\\Http\\Controllers\\User\\ProfileController.php';
$profileCode = file_get_contents($profile);
$profileMethod = <<<'PHP'
    public function viewCertificate()
    {
        $user = auth()->user();
        $address = (array) $user->address;
        $certificate = $address['membership_certificate_path'] ?? null;
        $certificateFile = $certificate ? base_path('../' . $certificate) : null;

        if (!$certificateFile || !is_file($certificateFile)) {
            abort(404);
        }

        $mime = match (strtolower(pathinfo($certificateFile, PATHINFO_EXTENSION))) {
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            default => 'application/octet-stream',
        };

        return response()->file($certificateFile, ['Content-Type' => $mime]);
    }
PHP;

$profileCode = preg_replace('/    public function viewCertificate\(\)\s*\{.*?\n    \}\s*\n\n    public function changePassword/s', $profileMethod . "\n\n    public function changePassword", $profileCode, 1);
file_put_contents($profile, $profileCode);

$admin = $root . '\\app\\Http\\Controllers\\Admin\\ManageUsersController.php';
$adminCode = file_get_contents($admin);
$adminMethod = <<<'PHP'
    public function viewCertificate($id)
    {
        $user = User::findOrFail($id);
        $address = (array) $user->address;
        $certificate = $address['membership_certificate_path'] ?? null;
        $certificateFile = $certificate ? base_path('../' . $certificate) : null;

        if (!$certificateFile || !is_file($certificateFile)) {
            $notify[] = ['error', 'Membership certificate file was not found'];
            return back()->withNotify($notify);
        }

        $mime = match (strtolower(pathinfo($certificateFile, PATHINFO_EXTENSION))) {
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            default => 'application/octet-stream',
        };

        return response()->file($certificateFile, ['Content-Type' => $mime]);
    }
PHP;

$adminCode = preg_replace('/    public function viewCertificate\(\$id\)\s*\{.*?\n    \}\s*\n\n    public function approveCertificate/s', $adminMethod . "\n\n    public function approveCertificate", $adminCode, 1);
file_put_contents($admin, $adminCode);

echo "CERTIFICATE_METHODS_REWRITTEN\n";
