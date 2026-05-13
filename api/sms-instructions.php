<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

json_response([
    'success' => true,
    'instructions' => [
        'Use your registered phone number for wallet and verification messages.',
        'Contact NATCODEV support if you do not receive an OTP within a few minutes.',
    ],
]);
