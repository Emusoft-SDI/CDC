<?php
declare(strict_types=1);
require_once __DIR__ . '/_provider.php';
provider_simple_page('coverage', 'Coverage Areas', 'Set the states and LGAs where your provider operations can serve buyers and growers.', function(PDO $pdo, array $user, array $provider, array $counts): void {
    $states = $pdo->query("SELECT id, state_name FROM nigeria_states ORDER BY state_name")->fetchAll();
    $selectedStates = array_filter(array_map('trim', explode(',', (string) $provider['state_ids'])));
    // Compute real LGA count from selected states
$coverageLgas = 0;
if ($selectedStates) {
    $stateIdList = implode(',', array_map('intval', $selectedStates));
    $lgaStmt = $pdo->prepare("SELECT COUNT(*) FROM nigeria_lgas WHERE state_id IN ({$stateIdList})");
    $lgaStmt->execute();
    $coverageLgas = (int) $lgaStmt->fetchColumn();
}
    $message = '';
    $error = '';
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['_csrf'] ?? null)) {
        $nationwide = isset($_POST['nationwide']) ? 1 : 0;
        $stateIds = $nationwide ? [] : ($_POST['state_ids'] ?? []);
        
        if (!is_array($stateIds)) {
            $stateIds = [];
        }
        
        // If nationwide, collect all state IDs
        if ($nationwide) {
            $stateIds = array_map(static fn(array $state): string => (string) $state['id'], $states);
        }
        
        $stateIdsString = implode(',', $stateIds);
        $statesServed = $nationwide ? 'Nationwide (All States)' : (count($stateIds) > 0 ? count($stateIds) . ' states selected' : 'No coverage set');
        
        try {
            $pdo->prepare("UPDATE provider_registry SET state_ids = ?, states_served = ?, nationwide = ? WHERE id = ?")
                ->execute([$stateIdsString, $statesServed, $nationwide, (int) $provider['id']]);
            $message = 'Coverage areas updated successfully.';
            // Refresh provider record and counts so the summary updates immediately
            $stmt = $pdo->prepare("SELECT * FROM provider_registry WHERE id = ? LIMIT 1");
            $stmt->execute([(int) $provider['id']]);
            $provider = $stmt->fetch() ?: $provider;
            $counts = provider_counts($pdo, $provider, $user);
            $selectedStates = $stateIds;
            // Recompute LGA coverage count based on selected states
            $coverageLgas = 0;
            if ($selectedStates) {
                $stateIdList = implode(',', array_map('intval', $selectedStates));
                $lgaStmt = $pdo->prepare("SELECT COUNT(*) FROM nigeria_lgas WHERE state_id IN ({$stateIdList})");
                $lgaStmt->execute();
                $coverageLgas = (int) $lgaStmt->fetchColumn();
            }
        } catch (Throwable $e) {
            $error = 'Failed to update coverage areas.';
        }
    }
    
    $nationwide = (int) ($provider['nationwide'] ?? 0) === 1;
    
    echo '<div class="grid">';
    
    // Coverage Summary Card
    echo '<section class="card span-6"><div class="card-head"><h2>Coverage Summary</h2><a class="view" href="reports.php">Report</a></div>';
    echo '<p><strong>Coverage Type:</strong> ' . ($nationwide ? 'Nationwide (All States)' : 'Selected States') . '</p>';
    echo '<p><strong>States Covered:</strong> ' . (int) $counts['coverageStates'] . '</p>';
    echo '<p><strong>LGAs Covered:</strong> ' . $coverageLgas . '</p>';
    echo '<p><strong>States Served:</strong> ' . e((string) $provider['states_served']) . '</p>';
    echo '</section>';
    
    // Update Coverage Form Card
    echo '<section class="card span-6">';
    echo '<div class="card-head"><h2>Update Coverage Areas</h2></div>';
    
    if ($message): echo '<div class="alert ok">' . e($message) . '</div>'; endif;
    if ($error): echo '<div class="alert err">' . e($error) . '</div>'; endif;
    
    echo '<form method="post" style="display:grid;gap:14px">';
    echo '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
    
    // Nationwide checkbox
    echo '<label style="display:flex;align-items:center;gap:10px;padding:12px;background:#e8f6ec;border:2px solid #06451f;border-radius:8px;cursor:pointer;font-weight:900">';
    echo '<input type="checkbox" name="nationwide" value="1" ' . ($nationwide ? 'checked' : '') . ' id="nationwide" onchange="toggleAllStates(this.checked)">';
    echo '<span>🌍 Nationwide Coverage (All States)</span>';
    echo '</label>';
    
    echo '<div style="border-top:1px solid var(--line);padding-top:14px">';
    echo '<p style="margin:0 0 10px;color:var(--muted);font-size:.88rem">Or select specific states:</p>';
    echo '<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;max-height:420px;overflow-y:auto;padding:10px;border:1px solid var(--line);border-radius:8px;background:#f9fafb">';
    
    foreach ($states as $state) {
        $stateId = (string) $state['id'];
        $checked = in_array($stateId, $selectedStates, true) ? 'checked' : '';
        echo '<label style="display:flex;align-items:center;gap:8px;padding:8px;background:#fff;border:1px solid var(--line);border-radius:6px;cursor:pointer">';
        echo '<input type="checkbox" name="state_ids[]" value="' . (int) $state['id'] . '" ' . $checked . ' class="state-checkbox">';
        echo '<span>' . e((string) $state['state_name']) . '</span>';
        echo '</label>';
    }
    
    echo '</div>';
    echo '</div>';
    
    echo '<div style="display:flex;gap:10px;align-items:center">';
    echo '<button type="submit" class="button">Save Coverage Areas</button>';
    echo '<a href="coverage.php" class="button secondary">Reset</a>';
    echo '</div>';
    echo '</form>';
    echo '</section>';
    
    echo '</div>';
    
    // JavaScript for nationwide toggle
    echo '<script>';
    echo 'function toggleAllStates(checked) {';
    echo '  document.querySelectorAll(".state-checkbox").forEach(function(cb) { cb.checked = checked; });';
    echo '}';
    echo 'document.getElementById("nationwide").addEventListener("change", function() {';
    echo '  var checkboxes = document.querySelectorAll(".state-checkbox");';
    echo '  checkboxes.forEach(function(cb) { cb.disabled = this.checked; }.bind(this));';
    echo '});';
    echo 'document.querySelectorAll(".state-checkbox").forEach(function(cb) {';
    echo '  cb.addEventListener("change", function() {';
    echo '    var allChecked = Array.from(document.querySelectorAll(".state-checkbox")).every(function(c) { return c.checked; });';
    echo '    document.getElementById("nationwide").checked = allChecked;';
    echo '  });';
    echo '});';
    echo '</script>';
});

/*<?php
declare(strict_types=1);
require_once __DIR__ . '/_provider.php';
provider_simple_page('coverage', 'Coverage Areas', 'Set the states and LGAs where your provider operations can serve buyers and growers.', function(PDO $pdo, array $user, array $provider, array $counts): void {
    $states = $pdo->query("SELECT id, state_name FROM nigeria_states ORDER BY state_name")->fetchAll();
    $selectedStates = array_filter(array_map('trim', explode(',', (string) $provider['state_ids'])));
    
    $message = '';
    $error = '';
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf($_POST['_csrf'] ?? null)) {
        $selectedStateIds = $_POST['state_ids'] ?? [];
        if (!is_array($selectedStateIds)) {
            $selectedStateIds = [];
        }
        $stateIdsString = implode(',', array_map('intval', $selectedStateIds));
        
        try {
            $pdo->prepare("UPDATE provider_registry SET state_ids = ? WHERE id = ?")->execute([$stateIdsString, (int) $provider['id']]);
            $message = 'Coverage areas updated successfully.';
            $selectedStates = $selectedStateIds;
        } catch (Throwable $e) {
            $error = 'Failed to update coverage areas.';
        }
    }
    
    if ($message): ?><div class="alert ok"><?= e($message) ?></div><?php endif;
    if ($error): ?><div class="alert err"><?= e($error) ?></div><?php endif;
    
    echo '<div class="grid"><section class="card span-6"><div class="card-head"><h2>Coverage Summary</h2><a class="view" href="reports.php">Report</a></div><p><strong>States Covered:</strong> ' . (int) $counts['coverageStates'] . '</p><p><strong>LGAs Covered:</strong> ' . (int) $counts['coverageLgas'] . '</p><p>' . e((string) $provider['states_served']) . '</p></section>';
    
    echo '<section class="card span-6"><div class="card-head"><h2>Update Coverage Areas</h2></div>';
    echo '<form method="post" style="display:grid;gap:12px">';
    echo '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
    echo '<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;max-height:400px;overflow-y:auto;padding:10px;border:1px solid var(--line);border-radius:8px;background:#f9fafb">';
    
    foreach ($states as $state) {
        $stateId = (string) $state['id'];
        $checked = in_array($stateId, $selectedStates, true) ? 'checked' : '';
        echo '<label style="display:flex;align-items:center;gap:8px;padding:8px;background:#fff;border:1px solid var(--line);border-radius:6px;cursor:pointer">';
        echo '<input type="checkbox" name="state_ids[]" value="' . (int) $state['id'] . '" ' . $checked . '>';
        echo '<span>' . e((string) $state['state_name']) . '</span>';
        echo '</label>';
    }
    
    echo '</div>';
    echo '<div style="display:flex;gap:10px;align-items:center">';
    echo '<button type="submit" class="button">Save Coverage Areas</button>';
    echo '<a href="coverage.php" class="button secondary">Reset</a>';
    echo '</div>';
    echo '</form>';
    echo '</section></div>';
});

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
*/