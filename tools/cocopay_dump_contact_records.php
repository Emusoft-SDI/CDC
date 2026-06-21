<?php

$root = 'C:\\Users\\user\\Downloads\\UniServerZ\\www\\cocopay\\core';
chdir($root);

require $root . '\\vendor\\autoload.php';
$app = require $root . '\\bootstrap\\app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

foreach (App\Models\Frontend::where('data_keys', 'like', 'contact_us.%')->get() as $row) {
    echo $row->id . '|' . $row->data_keys . '|' . $row->tempname . '|' . json_encode($row->data_values, JSON_UNESCAPED_SLASHES) . PHP_EOL;
}
