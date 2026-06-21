<?php

$root = 'C:/Users/user/Downloads/UniServerZ/www/cocopay/core';
require $root . '/vendor/autoload.php';
$app = require $root . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Admin;
use Illuminate\Support\Facades\Hash;

$admin = Admin::orderBy('id')->first();

if (!$admin) {
    echo "NO_ADMIN_FOUND\n";
    exit(1);
}

$admin->password = Hash::make('admin123');
$admin->status = 1;
$admin->save();

echo "ADMIN_RESET_OK\n";
echo "id={$admin->id}\n";
echo "username={$admin->username}\n";
echo "email={$admin->email}\n";
echo "password=admin123\n";

