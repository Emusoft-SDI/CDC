<?php
declare(strict_types=1);
require_once __DIR__ . '/_provider.php';

provider_simple_page('coverage', 'Coverage Areas', 'Set the states and LGAs where your provider operations can serve buyers and growers.', function(PDO $pdo, array $user, array $provider, array $counts): void {
    $states = $pdo->query("SELECT id, state_name FROM nigeria_states ORDER BY state_name")->fetchAll();
    $selectedStates = array_filter(array_map('trim', explode(',', (string) $provider['state_ids'])));
    echo '<div class="grid"><section class="card span-6"><div class="card-head"><h2>Coverage Summary</h2><a class="view" href="reports.php">Report</a></div><p><strong>States Covered:</strong> ' . (int) $counts['coverageStates'] . '</p><p><strong>LGAs Covered:</strong> ' . (int) $counts['coverageLgas'] . '</p><p>' . e((string) $provider['states_served']) . '</p></section><section class="card span-6"><h2>Coverage Map Data</h2><p>Coverage is stored from the selected state/LGA registry and used by marketplace search, reports, and provider verification.</p><div class="list">';
    foreach ($states as $state) {
        $covered = in_array((string) $state['id'], $selectedStates, true);
        echo '<div class="row"><span>' . e((string) $state['state_name']) . '</span><span class="badge ' . ($covered ? '' : 'warn') . '">' . ($covered ? 'Covered' : 'Not Covered') . '</span></div>';
    }
    echo '</div></section></div>';
});
