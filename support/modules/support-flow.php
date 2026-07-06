<details class="support-panel" open><summary>Resolution Flow</summary><div class="support-panel-body">
  <section class="support-flow" aria-label="Support resolution flow">
    <?php
    $flowSteps = [
        ['fa-comments', 'Open Support', 'index.php?view=lookup', ''],
        ['fa-plus', 'Submit Issue', 'index.php?view=new-ticket', ''],
        ['fa-people-arrows', 'Routed to Team', 'javascript:void(0)', 'alert("Tickets are instantly routed to target departments (Academy, Payments, Registry) based on category. Standard review takes less than 2 hours.");'],
        ['fa-reply', 'Response / Action', 'index.php?view=lookup', ''],
        ['fa-circle-check', 'Resolve', 'index.php?view=lookup', ''],
        ['fa-star', 'Rate & Feedback', 'javascript:void(0)', 'alert("Customer satisfaction and resolution rates are monitored directly inside the coordinator panel to audit representative performance.");'],
        ['fa-chart-simple', 'Report & Improve', 'javascript:void(0)', 'alert("Support metrics and ticket resolution feedback are audited weekly to improve NATCODEV services.");']
    ];
    foreach ($flowSteps as $step):
    ?>
      <a class="flow" href="<?= e($step[2]) ?>" onclick="<?= e($step[3]) ?>">
        <i class="fas <?= e($step[0]) ?>"></i><br>
        <strong><?= e($step[1]) ?></strong>
      </a>
    <?php endforeach; ?>
  </section>
  </div></details>
