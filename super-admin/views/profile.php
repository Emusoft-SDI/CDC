<?php defined('NATCODEV_SUPER_ADMIN') || exit; ?>
<section class="panel">
  <div class="section-head">
    <div>
      <h2>My Super Admin Profile</h2>
      <p>Manage your own Super Admin profile, password, and secure exit. This page is only editable for user-backed super admin accounts.</p>
    </div>
  </div>
  <?php if ($selfProfile): ?>
  <div class="console-grid">
    <form method="post">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="super_profile">
      <h3>Profile Details</h3>
      <label>Name<input name="name" value="<?= e((string) ($selfProfile['name'] ?? '')) ?>" required></label>
      <label>Email<input value="<?= e((string) ($selfProfile['email'] ?? '')) ?>" disabled></label>
      <label>Phone<input name="phone" value="<?= e((string) ($selfProfile['phone'] ?? '')) ?>"></label>
      <label>Location<input name="location" value="<?= e((string) ($selfProfile['location'] ?? '')) ?>"></label>
      <button type="submit" data-busy-text="Saving profile...">Save Profile</button>
    </form>
    <form method="post">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="super_password">
      <h3>Change Password</h3>
      <label>Current Password<input type="password" name="current_password" autocomplete="current-password" required></label>
      <label>New Password<input type="password" name="new_password" autocomplete="new-password" minlength="8" required></label>
      <label>Confirm Password<input type="password" name="confirm_password" autocomplete="new-password" minlength="8" required></label>
      <button type="submit" data-busy-text="Updating password...">Change Password</button>
    </form>
  </div>
  <form method="post" class="mini-form">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="logout">
    <button class="secondary" type="submit">Logout Securely</button>
  </form>
  <?php else: ?>
    <div class="notice error">This session is using environment password mode. Sign in through a user-backed super admin account before editing profile or password here.</div>
  <?php endif; ?>
</section>
