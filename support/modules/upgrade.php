<details class="support-panel" id="upgrade-panel" open><summary>Registration Paths</summary><div class="support-panel-body">
  <section class="upgrade" id="upgrade">
    <div class="head">
      <div>
        <h2>Need More Than Support?</h2>
        <p class="muted">A public support ticket does not make someone a platform stakeholder. If the person wants NATCODEV services, send them through the correct registration or onboarding path below.</p>
      </div>
      <span class="badge info">Support-to-service upgrade</span>
    </div>
    <div class="grid g3">
      <?php foreach ($upgradePaths as $path): ?>
        <article class="upgrade-card">
          <i class="fas <?= e($path['icon']) ?>"></i>
          <h3><?= e($path['title']) ?></h3>
          <p class="muted"><?= e($path['text']) ?></p>
          <a class="btn light" href="<?= e($path['href']) ?>"><?= e($path['label']) ?></a>
        </article>
      <?php endforeach; ?>
    </div>
  </section>

  </div></details>
