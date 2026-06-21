<?php

$root = 'C:\\Users\\user\\Downloads\\UniServerZ\\www\\cocopay\\core';
chdir($root);

require $root . '\\vendor\\autoload.php';
$app = require $root . '\\bootstrap\\app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = App\Models\User::where('username', 'gracious')->firstOrFail();
$address = (array) $user->address;
$address['membership_certificate_status'] = 'pending';
unset($address['membership_certificate_rejection_reason'], $address['membership_certificate_reviewed_at'], $address['membership_certificate_reviewed_by']);

Illuminate\Support\Facades\DB::table('users')->where('id', $user->id)->update([
    'address' => json_encode($address),
]);

$fresh = App\Models\User::find($user->id);
echo 'restored_status=' . (((array) $fresh->address)['membership_certificate_status'] ?? 'missing') . PHP_EOL;
