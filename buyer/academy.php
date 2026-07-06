<?php
declare(strict_types=1);
require_once __DIR__ . '/_buyer.php';
buyer_simple_page('academy', 'Buyer Academy', 'Access buyer onboarding, product quality training, and coconut value-chain learning.', function(): void {
    echo '<div class="grid"><section class="card span-6"><h2>Course Catalog</h2><p>Browse free and paid NATCODEV Academy courses.</p><a class="btn" href="../academy/index.php?screen=catalog">Browse Courses</a></section><section class="card span-6"><h2>My Learning</h2><p>Open your active registrations, course materials, and certificates.</p><a class="btn light" href="../academy/index.php?screen=my-learning">Open My Learning</a></section></div>';
});
?>
