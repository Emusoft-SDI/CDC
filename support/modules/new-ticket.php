<details class="support-panel" open><summary>New Support Request</summary><div class="support-panel-body">
<section class="hero">
    <article class="panel">
      <div class="head"><div><h1>Support Desk, Requests & Resolution Flows</h1><p class="muted">Fast help, clear updates, and resolved issues for public visitors and registered NATCODEV users.</p></div><span class="badge ok">Available 24/7</span></div>
      <div class="grid g4">
        <div class="stat"><span>Open</span><b><?= (int) ($stats['open'] ?? 0) ?></b></div>
        <div class="stat"><span>Waiting on You</span><b><?= (int) ($stats['waiting_on_user'] ?? 0) ?></b></div>
        <div class="stat"><span>In Progress</span><b><?= (int) ($stats['in_progress'] ?? 0) ?></b></div>
        <div class="stat"><span>Resolved</span><b><?= (int) ($stats['resolved'] ?? 0) ?></b></div>
      </div>
      <h2 style="margin-top:18px">Popular Categories</h2>
      <div class="grid g3">
        <?php foreach ($categories as $key => $cat): ?>
          <a class="cat" href="index.php?view=new-ticket" onclick="document.getElementById('category').value='<?= e($key) ?>'"><i class="fas <?= e($cat['icon']) ?>"></i><span><strong><?= e($cat['label']) ?></strong><span><?= e($cat['team']) ?> / <?= e(ucwords(str_replace('_', ' ', $cat['module']))) ?></span></span></a>
        <?php endforeach; ?>
      </div>
    </article>

    <article class="panel" id="new-ticket">
      <div class="head"><h2>New Ticket</h2><span class="badge info"><?= e(support_role_label(support_role_key($user))) ?></span></div>
      <form method="post">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="create">
        <div class="form-grid">
          <div><label>Name</label><input name="name" value="<?= e((string) ($user['name'] ?? '')) ?>" required></div>
          <div><label>Email</label><input type="email" name="email" value="<?= e((string) ($user['email'] ?? '')) ?>" required></div>
          <div><label>Phone</label><input name="phone" value=""></div>
          <div><label>Category</label><select id="category" name="category"><?php foreach ($categories as $key => $cat): ?><option value="<?= e($key) ?>" <?= $prefillCategory === $key ? 'selected' : '' ?>><?= e($cat['label']) ?></option><?php endforeach; ?></select></div>
          <div><label>Priority</label><select name="priority"><?php foreach ($priorities as $key => $label): ?><option value="<?= e($key) ?>" <?= $key === 'medium' ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
          <div><label>Linked Record Type</label><select name="linked_record_type"><option value="">None</option><option value="wallet_transaction">Wallet Transaction</option><option value="course_enrollment">Course Enrollment</option><option value="order">Order</option><option value="certificate">Certificate</option><option value="application">Application</option></select></div>
          <div class="field-full"><label>Issue Title</label><input name="subject" placeholder="Example: Refund not received for course payment" required></div>
          <div class="field-full"><label>Description</label><textarea name="description" placeholder="Tell us what happened, the date, amount/reference if any, and the outcome you need." required></textarea></div>
          <div class="field-full"><label>Linked Record Reference</label><input name="linked_record_ref" placeholder="Transaction ID, certificate ref, order ref, application ref, or course name"></div>
        </div>
        <p><button class="btn" type="submit"><i class="fas fa-paper-plane"></i> Submit Ticket</button></p>
      </form>
    </article>
  </section>

  </div></details>
