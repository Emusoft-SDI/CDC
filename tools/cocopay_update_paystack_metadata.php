<?php

$root = 'C:\\Users\\user\\Downloads\\UniServerZ\\www\\cocopay\\core';

$controller = $root . '\\app\\Http\\Controllers\\Gateway\\Paystack\\ProcessController.php';
$code = file_get_contents($controller);
if (!str_contains($code, "\$send['metadata']")) {
    $code = str_replace(
        "        \$send['ref'] = \$deposit->trx;\r\n        \$send['view'] = 'user.payment.' . \$alias;",
        "        \$send['ref'] = \$deposit->trx;\r\n        \$send['metadata'] = [\r\n            'custom_fields' => [\r\n                [\r\n                    'display_name' => 'Cooperative',\r\n                    'variable_name' => 'cooperative',\r\n                    'value' => 'NATCODEV Coconut Farmers Cooperative Society',\r\n                ],\r\n                [\r\n                    'display_name' => 'Payment Purpose',\r\n                    'variable_name' => 'payment_purpose',\r\n                    'value' => 'Member wallet funding',\r\n                ],\r\n            ],\r\n        ];\r\n        \$send['view'] = 'user.payment.' . \$alias;",
        $code
    );
    file_put_contents($controller, $code);
}

foreach ([
    $root . '\\resources\\views\\templates\\indigo_fusion\\user\\payment\\Paystack.blade.php',
    $root . '\\resources\\views\\templates\\crystal_sky\\user\\payment\\Paystack.blade.php',
] as $view) {
    $html = file_get_contents($view);
    if (!str_contains($html, 'data-metadata=')) {
        $html = str_replace(
            'data-ref="{{ $data->ref }}" data-custom-button="btn-confirm"',
            "data-ref=\"{{ \$data->ref }}\" data-metadata='@json(\$data->metadata)' data-custom-button=\"btn-confirm\"",
            $html
        );
        file_put_contents($view, $html);
    }
}

echo "PAYSTACK_METADATA_UPDATED\n";
