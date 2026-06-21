<?php

chdir('C:/Users/user/Downloads/UniServerZ/www/cocopay/core');

require 'vendor/autoload.php';

$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$plan = App\Models\LoanPlan::with('form')->first();

echo 'plan=' . $plan->name . PHP_EOL;
echo 'form_id=' . $plan->form_id . PHP_EOL;
echo 'form_loaded=' . ($plan->form ? 'yes' : 'no') . PHP_EOL;
echo 'fields=' . count((array) $plan->form->form_data) . PHP_EOL;

