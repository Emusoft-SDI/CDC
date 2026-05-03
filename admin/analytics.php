<!-- admin/analytics.php -->
<?php
// ... admin auth check ...
$pdo = new PDO("mysql:host=localhost;dbname=coconutventure_growers;charset=utf8mb4", 
               "coconutventure_growers", "1^v1V&Ak{DIPL~Y.");

// Get visit trends (same as weekly report)
// ... [reuse visitTrends logic] ...

// Weather forecast
require_once '../lib/weather.php';
$weather = getWeatherForecast();
?>
<!DOCTYPE html>
<html>
<head>
  <title>Predictive Analytics - NATCODEV</title>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    .chart-container { width: 100%; height: 400px; margin: 20px 0; }
    .weather-card { background: #e8f5e9; padding: 15px; border-radius: 8px; margin: 20px 0; }
  </style>
</head>
<body>
  <h1>Predictive Analytics Dashboard</h1>
  
  <div class="weather-card">
    <h2>🌦️ Weather Forecast</h2>
    <div id="weatherChart" class="chart-container"></div>
  </div>
  
  <div class="chart-container">
    <canvas id="visitTrendChart"></canvas>
  </div>
  
  <script>
    // Visit Trend Chart
    const ctx = document.getElementById('visitTrendChart').getContext('2d');
    new Chart(ctx, {
      type: 'bar',
       {
        labels: <?= json_encode(array_column($visitTrends, 'name')) ?>,
        datasets: [{
          label: 'This Week',
           <?= json_encode(array_column($visitTrends, 'this_week')) ?>,
          backgroundColor: '#2d5016'
        }, {
          label: 'Last Week',
           <?= json_encode(array_column($visitTrends, 'last_week')) ?>,
          backgroundColor: '#8fc27d'
        }]
      }
    });
    
    // Weather Chart (simplified)
    const weatherCtx = document.getElementById('weatherChart').getContext('2d');
    // ... [add weather chart logic] ...
  </script>
</body>
</html>