<?php
// Layout Component: profile-tabs.php
?>
      <nav class="profile-tabs" aria-label="Profile sections">
        <a href="profile.php" class="profile-tab" <?= basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'aria-selected="true"' : '' ?> style="text-decoration:none; display:inline-flex; align-items:center; justify-content:center;">Account Settings</a>
        <a href="account-settings.php#security" class="profile-tab" <?= basename($_SERVER['PHP_SELF']) == 'account-settings.php' ? 'data-profile-tab="security"' : '' ?> style="text-decoration:none; display:inline-flex; align-items:center; justify-content:center;">Security</a>
        <a href="account-settings.php#password" class="profile-tab" <?= basename($_SERVER['PHP_SELF']) == 'account-settings.php' ? 'data-profile-tab="password"' : '' ?> style="text-decoration:none; display:inline-flex; align-items:center; justify-content:center;">Password</a>
        <?php if (($user['role'] ?? 'grower') === 'grower'): ?>
          <a href="farm-profile.php#farm" class="profile-tab" <?= basename($_SERVER['PHP_SELF']) == 'farm-profile.php' ? 'data-profile-tab="farm"' : '' ?> style="text-decoration:none; display:inline-flex; align-items:center; justify-content:center;">Primary Farm</a>
          <a href="farm-profile.php#activity" class="profile-tab" <?= basename($_SERVER['PHP_SELF']) == 'farm-profile.php' ? 'data-profile-tab="activity"' : '' ?> style="text-decoration:none; display:inline-flex; align-items:center; justify-content:center;">Farm Activity</a>
          <a href="farm-profile.php#locations" class="profile-tab" <?= basename($_SERVER['PHP_SELF']) == 'farm-profile.php' ? 'data-profile-tab="locations"' : '' ?> style="text-decoration:none; display:inline-flex; align-items:center; justify-content:center;">Farm Locations</a>
        <?php endif; ?>
        <a href="account-settings.php#notifications" class="profile-tab" <?= basename($_SERVER['PHP_SELF']) == 'account-settings.php' ? 'data-profile-tab="notifications"' : '' ?> style="text-decoration:none; display:inline-flex; align-items:center; justify-content:center;">Notification Preferences</a>
      </nav>
