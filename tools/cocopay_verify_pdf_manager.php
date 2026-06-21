<?php

chdir('C:/Users/user/Downloads/UniServerZ/www/cocopay/core');

require 'vendor/autoload.php';

$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$loan = App\Models\Loan::with('plan')->findOrFail(1);
$view = 'templates.indigo_fusion.pdf.loan';
$html = view($view, ['pageTitle' => 'Loan Details', 'loan' => $loan])->render();
$pdf = Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
$output = $pdf->output();

echo 'loan=' . $loan->loan_number . PHP_EOL;
echo 'bytes=' . strlen($output) . PHP_EOL;
echo 'prefix=' . substr($output, 0, 4) . PHP_EOL;

