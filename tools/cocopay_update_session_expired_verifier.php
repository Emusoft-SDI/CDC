<?php

$path = 'C:\\Users\\user\\Downloads\\UniServerZ\\www\\CDC\\tools\\cocopay_error_page_context_check.php';
$contents = file_get_contents($path);
if ($contents === false) {
    fwrite(STDERR, "Unable to read verifier\n");
    exit(1);
}

$contents = str_replace(
    "foreach ([404, 419] as \$code) {\n        try {\n            \$html = view(\"errors.\$code\")->render();",
    "foreach ([404, 'session_expired'] as \$code) {\n        try {\n            \$html = view(\"errors.\$code\")->render();",
    $contents
);

file_put_contents($path, $contents);

echo "UPDATED_SESSION_EXPIRED_VERIFIER\n";
