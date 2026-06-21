<?php
declare(strict_types=1);
require_once __DIR__ . '/_provider.php';

provider_simple_page('academy', 'NATCODEV Academy', 'Provider learning, compliance courses, and approved provider training programs.', function(PDO $pdo, array $user, array $provider, array $counts): void {
    echo '<div class="grid"><section class="card span-4"><h2>My Learning</h2><p style="font-size:2rem;font-weight:950;color:#06451f">' . (int) $counts['academy'] . '</p><a class="btn" href="../academy/index.php?screen=my-learning">Open My Learning</a></section><section class="card span-4"><h2>Course Catalog</h2><p>Provider accreditation, marketplace seller conduct, service quality, and compliance programs.</p><a class="btn light" href="../academy/index.php?screen=catalog">Browse Courses</a></section><section class="card span-4"><h2>Sell Training</h2><p>Approved providers can add training offerings from Products & Services as training programs for NATCODEV review.</p><a class="btn light" href="products.php">Add Training Program</a></section></div>';
});
