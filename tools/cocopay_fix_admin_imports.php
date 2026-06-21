<?php

$file = 'C:/Users/user/Downloads/UniServerZ/www/cocopay/core/app/Http/Controllers/Admin/AdminController.php';
$contents = file_get_contents($file);

if (strpos($contents, 'use App\Models\SupportMessage;') === false) {
    $contents = str_replace("use App\Models\Loan;\n", "use App\Models\Loan;\nuse App\Models\SupportMessage;\n", $contents);
}

if (strpos($contents, 'use App\Models\SupportTicket;') === false) {
    $contents = str_replace("use App\Models\SupportMessage;\n", "use App\Models\SupportMessage;\nuse App\Models\SupportTicket;\n", $contents);
}

file_put_contents($file, $contents);
echo "Admin imports fixed.\n";

