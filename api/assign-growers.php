<!-- admin/assign-growers.php -->
<?php
// Get all field agents
$agents = $pdo->query("SELECT id, name, email FROM users WHERE role = 'field_agent'")->fetchAll();

// Get states for filtering
$states = $pdo->query("SELECT state_name FROM nigeria_states ORDER BY state_name")->fetchAll();
?>
<h2>Intelligent Bulk Grower Assignment</h2>

<form method="POST" id="assignmentForm">
  <div class="form-group">
    <label>Assignment Name</label>
    <input type="text" name="batch_name" required placeholder="e.g., Lagos Epe Q1 2024">
  </div>
  
  <div class="form-group">
    <label>Field Agent</label>
    <select name="agent_id" required>
      <option value="">Select Field Agent</option>
      <?php foreach ($agents as $agent): ?>
        <option value="<?= $agent['id'] ?>"><?= htmlspecialchars($agent['name']) ?> (<?= htmlspecialchars($agent['email']) ?>)</option>
      <?php endforeach; ?>
    </select>
  </div>
  
  <!-- Demographic Criteria -->
  <div class="criteria-section">
    <h3>Assignment Criteria</h3>
    
    <div class="form-group">
      <label>State</label>
      <select name="criteria[state]" onchange="loadLGAs(this.value)">
        <option value="">All States</option>
        <?php foreach ($states as $state): ?>
          <option value="<?= htmlspecialchars($state['state_name']) ?>"><?= htmlspecialchars($state['state_name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    
    <div class="form-group">
      <label>LGA</label>
      <select name="criteria[lga]" id="lgaSelect">
        <option value="">All LGAs</option>
      </select>
    </div>
    
    <div class="form-group">
      <label>Ward</label>
      <input type="text" name="criteria[ward]" placeholder="e.g., Ijede, Odo Epe">
    </div>
    
    <div class="form-group">
      <label>Minimum Farm Size (ha)</label>
      <input type="number" name="criteria[min_farm_size]" min="1" step="0.1" value="1">
    </div>
    
    <div class="form-group">
      <label>Experience Level</label>
      <select name="criteria[experience]">
        <option value="">All Levels</option>
        <option value="beginner">Beginner (0-2 years)</option>
        <option value="intermediate">Intermediate (3-5 years)</option>
        <option value="advanced">Advanced (6-10 years)</option>
        <option value="expert">Expert (10+ years)</option>
      </select>
    </div>
    
    <div class="form-group">
      <label>Education Level</label>
      <select name="criteria[education]">
        <option value="">All Levels</option>
        <option value="primary">Primary</option>
        <option value="secondary">Secondary</option>
        <option value="tertiary">Tertiary</option>
        <option value="post_graduate">Post Graduate</option>
      </select>
    </div>
  </div>
  
  <button type="submit">Preview Assignment</button>
</form>

<div id="previewSection" style="display:none; margin-top: 30px;">
  <h3>Assignment Preview</h3>
  <p id="previewCount"></p>
  <div id="previewList"></div>
  <button onclick="confirmAssignment()">Confirm Assignment</button>
</div>

<script>
function loadLGAs(state) {
  if (!state) {
    document.getElementById('lgaSelect').innerHTML = '<option value="">All LGAs</option>';
    return;
  }
  
  fetch(`/api/get-lgas-by-state.php?state=${encodeURIComponent(state)}`)
    .then(response => response.json())
    .then(data => {
      let options = '<option value="">All LGAs</option>';
      data.forEach(lga => {
        options += `<option value="${lga.lga_name}">${lga.lga_name}</option>`;
      });
      document.getElementById('lgaSelect').innerHTML = options;
    });
}

document.getElementById('assignmentForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  
  const formData = new FormData(this);
  const criteria = {};
  for (let [key, value] of formData.entries()) {
    if (key.startsWith('criteria[')) {
      const field = key.match(/criteria\[(.*?)\]/)[1];
      if (value) criteria[field] = value;
    }
  }
  
  const previewData = {
    agent_id: formData.get('agent_id'),
    criteria: criteria
  };
  
  const response = await fetch('/api/preview-assignment.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(previewData)
  });
  
  const result = await response.json();
  if (result.success) {
    document.getElementById('previewCount').textContent = `Found ${result.count} growers matching criteria`;
    document.getElementById('previewList').innerHTML = result.growers.map(g => 
      `<div>${g.name} - ${g.location} (${g.farm_size} ha)</div>`
    ).join('');
    document.getElementById('previewSection').style.display = 'block';
  }
});

async function confirmAssignment() {
  const formData = new FormData(document.getElementById('assignmentForm'));
  const criteria = {};
  for (let [key, value] of formData.entries()) {
    if (key.startsWith('criteria[')) {
      const field = key.match(/criteria\[(.*?)\]/)[1];
      if (value) criteria[field] = value;
    }
  }
  
  const assignmentData = {
    batch_name: formData.get('batch_name'),
    agent_id: formData.get('agent_id'),
    criteria: criteria
  };
  
  const response = await fetch('/api/assign-growers.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(assignmentData)
  });
  
  const result = await response.json();
  if (result.success) {
    alert(`Successfully assigned ${result.assigned} growers to field agent!`);
    location.reload();
  }
}
</script>