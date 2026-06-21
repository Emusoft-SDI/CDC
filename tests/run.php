<?php
declare(strict_types=1);

require_once __DIR__ . '/../api/auth.php';
require_once __DIR__ . '/../lib/certificates.php';

$failures = [];

function assert_true(bool $condition, string $message): void
{
    global $failures;
    if (!$condition) {
        $failures[] = $message;
    }
}

putenv('JWT_SECRET=test-secret-for-cli');

$auth = new Auth();
$token = $auth->generateToken(42, 'field_agent');
$payload = $auth->validateToken($token);
assert_true(is_array($payload), 'JWT should validate.');
assert_true(($payload['user_id'] ?? null) === 42, 'JWT should preserve user id.');
assert_true(($payload['role'] ?? null) === 'field_agent', 'JWT should preserve role.');
assert_true($auth->validateToken($token . 'tampered') === false, 'Tampered JWT should fail.');

$ref = certificate_generate_ref('NAT-260509-ABC123');
assert_true(str_starts_with($ref, 'CERT-NAT-260509-ABC123-'), 'Certificate ref should include app ref.');

$html = certificate_render_html([
    'name' => 'Jane Grower',
    'app_ref' => 'NAT-260509-ABC123',
    'location' => 'Lagos, Epe',
    'farm_size' => '5.00',
], 'CERT-NAT-260509-ABC123-ABCD', '2026-05-09 12:00:00', 'https://natcodev.com.ng/verify');
assert_true(str_contains($html, 'Jane Grower'), 'Certificate HTML should include grower name.');
assert_true(str_contains($html, 'CERT-NAT-260509-ABC123-ABCD'), 'Certificate HTML should include certificate ref.');

$escaped = e('<script>alert(1)</script>');
assert_true($escaped === '&lt;script&gt;alert(1)&lt;/script&gt;', 'Escaping helper should encode HTML.');

if ($failures) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL: {$failure}\n");
    }
    exit(1);
}

echo "All focused CLI tests passed.\n";
