<?php
declare(strict_types=1);
require_once __DIR__ . '/_provider.php';

provider_simple_page('support', 'Support Desk', 'Get help with registration, accreditation, marketplace listings, Academy, wallet, or orders.', function(): void {
    echo '<div class="grid"><a class="card span-6" href="../support/index.php?category=provider"><h2>Open Support Desk</h2><p>Create or reply to tickets through the shared platform support desk.</p></a><a class="card span-6" href="../verify-certificate.php"><h2>Verify Certificate</h2><p>Check accreditation and certificate references online.</p></a></div>';
});
