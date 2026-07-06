<?php
declare(strict_types=1);
require_once __DIR__ . '/_provider.php';

provider_simple_page('settings', 'Settings', 'Provider account settings, password, notifications, and workspace preferences.', function(): void {
    echo '<div class="grid"><a class="card span-4" href="profile.php"><h2>Profile Settings</h2><p>Edit business details and settlement info.</p></a><a class="card span-4" href="profile.php#account"><h2>Account Security</h2><p>Manage contact details and password inside Provider Console.</p></a><a class="card span-4" href="logout.php"><h2>Logout</h2><p>Sign out from this provider workspace.</p></a></div>';
});
