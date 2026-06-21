<?php

$root = 'C:\\Users\\user\\Downloads\\UniServerZ\\www\\cocopay\\core';
chdir($root);

require $root . '\\vendor\\autoload.php';
$app = require $root . '\\bootstrap\\app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = App\Models\User::where('username', 'gracious')->firstOrFail();
$admin = App\Models\Admin::orderBy('id')->firstOrFail();
$originalAddress = (array) $user->address;
$restoreAddress = $originalAddress;
$restoreAddress['membership_certificate_status'] = 'pending';
unset($restoreAddress['membership_certificate_rejection_reason'], $restoreAddress['membership_certificate_reviewed_at'], $restoreAddress['membership_certificate_reviewed_by']);

$user->address = $restoreAddress;
$user->save();

auth('admin')->login($admin);
$controller = app(App\Http\Controllers\Admin\ManageUsersController::class);

try {
    $controller->approveCertificate($user->id);
    $approvedAddress = (array) $user->fresh()->address;

    $request = Illuminate\Http\Request::create('/admin/users/certificate-reject/' . $user->id, 'POST', [
        'reason' => 'Verification test reason',
    ]);

    $controller->rejectCertificate($request, $user->id);
    $rejectedAddress = (array) $user->fresh()->address;

    echo 'approve_status=' . ($approvedAddress['membership_certificate_status'] ?? 'missing') . PHP_EOL;
    echo 'approve_reviewed_by=' . (!empty($approvedAddress['membership_certificate_reviewed_by']) ? 'yes' : 'no') . PHP_EOL;
    echo 'reject_status=' . ($rejectedAddress['membership_certificate_status'] ?? 'missing') . PHP_EOL;
    echo 'reject_reason=' . (($rejectedAddress['membership_certificate_rejection_reason'] ?? null) === 'Verification test reason' ? 'yes' : 'no') . PHP_EOL;
} finally {
    Illuminate\Support\Facades\DB::table('users')->where('id', $user->id)->update([
        'address' => json_encode($restoreAddress),
    ]);
}

echo 'restored_status=' . (((array) $user->fresh()->address)['membership_certificate_status'] ?? 'missing') . PHP_EOL;
