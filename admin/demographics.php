<!-- admin/demographics.php -->
<div class="dashboard-section">
  <h2>Intelligent Demographic Analytics</h2>
  
  <!-- Filters -->
  <div class="filters">
    <select id="stateFilter">
      <option value="">All States</option>
      <!-- Populate from states table -->
    </select>
    <select id="lgaFilter">
      <option value="">All LGAs</option>
    </select>
    <select id="genderFilter">
      <option value="">All Genders</option>
      <option value="male">Male</option>
      <option value="female">Female</option>
    </select>
    <input type="number" id="minAge" placeholder="Min Age">
    <input type="number" id="maxAge" placeholder="Max Age">
    <button onclick="loadDemographics()">Apply Filters</button>
  </div>
  
  <!-- Key Metrics -->
  <div class="metrics">
    <div class="metric">
      <h3>Female Participation</h3>
      <div id="femalePercentage">0%</div>
    </div>
    <div class="metric">
      <h3>Youth Engagement</h3>
      <div id="youthPercentage">0%</div>
    </div>
    <div class="metric">
      <h3>Higher Education</h3>
      <div id="educatedPercentage">0%</div>
    </div>
  </div>
  
  <!-- Charts -->
  <div class="charts">
    <canvas id="stateChart"></canvas>
    <canvas id="experienceChart"></canvas>
  </div>
  
  <!-- Export Button -->
  <button onclick="exportDemographics()">📥 Export Report</button>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
let currentData = null;

async function loadDemographics() {
  const params = new URLSearchParams({
    state: document.getElementById('stateFilter').value,
    lga: document.getElementById('lgaFilter').value,
    gender: document.getElementById('genderFilter').value,
    min_age: document.getElementById('minAge').value,
    max_age: document.getElementById('maxAge').value
  });
  
  const response = await fetch(`/api/demographics.php?${params}`);
  const result = await response.json();
  currentData = result;
  
  // Update metrics
  document.getElementById('femalePercentage').textContent = result.insights.female_percentage + '%';
  document.getElementById('youthPercentage').textContent = result.insights.youth_percentage + '%';
  document.getElementById('educatedPercentage').textContent = result.insights.educated_percentage + '%';
  
  // Update charts
  updateCharts(result);
}

function updateCharts(data) {
  // State chart
  const stateCtx = document.getElementById('stateChart').getContext('2d');
  new Chart(stateCtx, {
    type: 'bar',
     {
      labels: Object.keys(data.insights.top_states),
       [{
        label: 'Users by State',
         Object.values(data.insights.top_states),
        backgroundColor: '#2d5016'
      }]
    }
  });
  
  // Experience chart
  const expCtx = document.getElementById('experienceChart').getContext('2d');
  new Chart(expCtx, {
    type: 'pie',
     {
      labels: Object.keys(data.insights.experience_distribution),
       [{
        label: 'Experience Levels',
         Object.values(data.insights.experience_distribution),
        backgroundColor: ['#2d5016', '#8fc27d', '#c8e6c9', '#f1f8e9']
      }]
    }
  });
}

async function exportDemographics() {
  if (!currentData) return;
  
  // Export as CSV
  let csv = 'State,LGA,Role,Age,Education,Experience,Marital Status,Count\n';
  currentData.data.forEach(row => {
    csv += `"${row.state_name || ''}","${row.lga_name || ''}","${row.role}","${row.age || ''}","${row.education_level || ''}","${row.farming_experience_rating || ''}","${row.marital_status || ''}",${row.count}\n`;
  });
  
  const blob = new Blob([csv], { type: 'text/csv' });
  const url = window.URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = 'natcodev_demographics.csv';
  a.click();
}
</script>
<!-- admin/demographics.php -->
<?php
// Get demographic stats
$stats = [
    'education' => $pdo->query("SELECT education_level, COUNT(*) as count FROM users GROUP BY education_level")->fetchAll(),
    'experience' => $pdo->query("SELECT farming_experience_rating, COUNT(*) as count FROM users GROUP BY farming_experience_rating")->fetchAll(),
    'marital' => $pdo->query("SELECT marital_status, COUNT(*) as count FROM users GROUP BY marital_status")->fetchAll(),
    'roles' => $pdo->query("SELECT role, COUNT(*) as count FROM users GROUP BY role")->fetchAll()
];
?>
<h1>Demographic Analytics</h1>

<div style="display: flex; gap: 20px;">
  <div style="flex: 1;">
    <h3>Education Levels</h3>
    <table>
      <?php foreach ($stats['education'] as $row): ?>
        <tr>
          <td><?= htmlspecialchars($row['education_level'] ?: 'Not specified') ?></td>
          <td><?= $row['count'] ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
  
  <div style="flex: 1;">
    <h3>Farming Experience</h3>
    <table>
      <?php foreach ($stats['experience'] as $row): ?>
        <tr>
          <td><?= htmlspecialchars($row['farming_experience_rating'] ?: 'Not specified') ?></td>
          <td><?= $row['count'] ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>