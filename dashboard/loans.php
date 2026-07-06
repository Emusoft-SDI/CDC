<?php
require_once '_user_auth.php';
require_once '../config.php';
require_once '../lib/dashboard-layout.php';

$pdo = db();
dashboard_page_start('Agricultural Inputs & Loans', ['description' => 'Access farm inputs and cooperative financing.']);
?>

<div class="card" style="text-align: center; padding: 40px 20px; max-width: 600px; margin: 0 auto;">
    <img src="../assets/cfc_logo.png" alt="CFC Logo" style="max-height: 80px; margin-bottom: 20px;">
    <h2>Coconut Farmers Cooperative</h2>
    <p class="muted" style="margin-bottom: 30px; font-size: 16px;">
        All financial services, including farm input loans, grants, and savings, are managed exclusively through our dedicated cooperative platform.
    </p>
    <a href="https://www.cfc.natodev.com.ng" target="_blank" class="button" style="display: inline-block; padding: 12px 24px; font-size: 16px;">
        Go to Cooperative Portal
    </a>
</div>

<?php dashboard_page_end(); ?>
