<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

json_response([
    'success' => false,
    'message' => 'Legacy duplicate submission endpoint is disabled. Use send_email.php.',
], 410);
