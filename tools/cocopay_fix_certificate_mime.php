<?php

$root = 'C:\\Users\\user\\Downloads\\UniServerZ\\www\\cocopay\\core';

$profile = $root . '\\app\\Http\\Controllers\\User\\ProfileController.php';
$profileCode = file_get_contents($profile);
$profileCode = str_replace(
    "        if (!\$certificate || !is_file(base_path('../' . \$certificate))) {\r\n            abort(404);\r\n        }\r\n\r\n        return response()->file(base_path('../' . \$certificate));",
    "        \$certificateFile = base_path('../' . \$certificate);\r\n\r\n        if (!\$certificate || !is_file(\$certificateFile)) {\r\n            abort(404);\r\n        }\r\n\r\n        \$mime = match (strtolower(pathinfo(\$certificateFile, PATHINFO_EXTENSION))) {\r\n            'pdf' => 'application/pdf',\r\n            'png' => 'image/png',\r\n            'jpg', 'jpeg' => 'image/jpeg',\r\n            default => 'application/octet-stream',\r\n        };\r\n\r\n        return response()->file(\$certificateFile, ['Content-Type' => \$mime]);",
    $profileCode
);
file_put_contents($profile, $profileCode);

$admin = $root . '\\app\\Http\\Controllers\\Admin\\ManageUsersController.php';
$adminCode = file_get_contents($admin);
$adminCode = str_replace(
    "        if (!\$certificate || !is_file(base_path('../' . \$certificate))) {\r\n            \$notify[] = ['error', 'Membership certificate file was not found'];\r\n            return back()->withNotify(\$notify);\r\n        }\r\n\r\n        return response()->file(base_path('../' . \$certificate));",
    "        \$certificateFile = base_path('../' . \$certificate);\r\n\r\n        if (!\$certificate || !is_file(\$certificateFile)) {\r\n            \$notify[] = ['error', 'Membership certificate file was not found'];\r\n            return back()->withNotify(\$notify);\r\n        }\r\n\r\n        \$mime = match (strtolower(pathinfo(\$certificateFile, PATHINFO_EXTENSION))) {\r\n            'pdf' => 'application/pdf',\r\n            'png' => 'image/png',\r\n            'jpg', 'jpeg' => 'image/jpeg',\r\n            default => 'application/octet-stream',\r\n        };\r\n\r\n        return response()->file(\$certificateFile, ['Content-Type' => \$mime]);",
    $adminCode
);
file_put_contents($admin, $adminCode);

echo "CERTIFICATE_MIME_FIXED\n";
