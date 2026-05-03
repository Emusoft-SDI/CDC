
<!-- In Resources section -->
<li><a href="profile.php">👤 Edit Profile</a></li>
<li><a href="inbox.php">✉️ Messages</a></li>

<!-- In applications table -->
<td>
  <?php if ($row['state_id']): ?>
    <?= htmlspecialchars(getStateName($row['state_id'])) ?>, 
    <?= htmlspecialchars(getLGAName($row['lga_id'])) ?>
    <?= $row['street_address'] ? ', ' . htmlspecialchars($row['street_address']) : '' ?>
  <?php elseif ($row['latitude']): ?>
    Coordinates: <?= round($row['latitude'], 4) ?>, <?= round($row['longitude'], 4) ?>
  <?php endif; ?>
</td>

<?php
function getStateName($stateId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT state_name FROM nigeria_states WHERE id = ?");
    $stmt->execute([$stateId]);
    return $stmt->fetchColumn();
}

function getLGAName($lgaId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT lga_name FROM nigeria_lgas WHERE id = ?");
    $stmt->execute([$lgaId]);
    return $stmt->fetchColumn();
}
?>