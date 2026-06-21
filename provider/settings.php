<?php
declare(strict_types=1);
require_once __DIR__ . '/_provider.php';

provider_simple_page('settings', 'Settings', 'Provider account settings, password, notifications, and workspace preferences.', function(): void {
    echo '<div class="grid"><a class="card span-4" href="profile.php"><h2>Profile Settings</h2><p>Edit business details and settlement info.</p></a><a class="card span-4" href="../dashboard/profile.php"><h2>Account Security</h2><p>Manage the linked user account.</p></a><a class="card span-4" href="../dashboard/logout.php"><h2>Logout</h2><p>Sign out from this provider workspace.</p></a></div>';
});
