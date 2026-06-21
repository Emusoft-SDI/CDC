<?php

chdir('C:/Users/user/Downloads/UniServerZ/www/cocopay/core');

require 'vendor/autoload.php';

$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = App\Models\User::where('username', 'gracious')->firstOrFail();
$user->password = Illuminate\Support\Facades\Hash::make('user123');
$user->status = 1;
$user->kv = 1;
$user->ev = 1;
$user->sv = 1;
$user->save();

$user->refresh();

echo 'id=' . $user->id . PHP_EOL;
echo 'username=' . $user->username . PHP_EOL;
echo 'email=' . $user->email . PHP_EOL;
echo 'status=' . $user->status . ' kv=' . $user->kv . ' ev=' . $user->ev . ' sv=' . $user->sv . PHP_EOL;
echo 'hash_ok=' . (Illuminate\Support\Facades\Hash::check('user123', $user->password) ? 'yes' : 'no') . PHP_EOL;

