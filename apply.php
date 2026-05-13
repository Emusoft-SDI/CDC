<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/nigeria-locations.php';

$pdo = db();
app_ensure_core_schema($pdo);
$states = [];
if (app_table_exists($pdo, 'nigeria_states')) {
    try {
        $states = $pdo->query("SELECT id, state_name FROM nigeria_states ORDER BY state_name")->fetchAll();
    } catch (Throwable $e) {
        $states = [];
    }
}
if (!$states) {
    $stateNames = ['Abia', 'Adamawa', 'Akwa Ibom', 'Anambra', 'Bauchi', 'Bayelsa', 'Benue', 'Borno', 'Cross River', 'Delta', 'Ebonyi', 'Edo', 'Ekiti', 'Enugu', 'Federal Capital Territory', 'Gombe', 'Imo', 'Jigawa', 'Kaduna', 'Kano', 'Katsina', 'Kebbi', 'Kogi', 'Kwara', 'Lagos', 'Nasarawa', 'Niger', 'Ogun', 'Ondo', 'Osun', 'Oyo', 'Plateau', 'Rivers', 'Sokoto', 'Taraba', 'Yobe', 'Zamfara'];
    $states = array_map(static fn(string $name): array => ['id' => 0, 'state_name' => $name], $stateNames);
}

$applicationTypes = [
  'farmer' => 'Coconut Farmer Registration',
  'outgrower' => 'Commercial Coconut Outgrowers Registration',
  'cooperative' => 'Coconut Farmers Cooperative Registration',
  'service-provider' => 'Agricultural Service Provider Registration',
  'input-provider' => 'Agricultural Input Provider Registration',
];

$applicationType = strtolower((string) ($_GET['type'] ?? 'outgrower'));
$applicationTypeLabel = $applicationTypes[$applicationType] ?? $applicationTypes['outgrower'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>NATCODEV Application</title>
  <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
  <style>
    :root {
      --primary: #1a5276;
      --green: #1f8a55;
      --green-dark: #166b41;
      --ink: #1f2937;
      --muted: #667085;
      --line: #d8e2dc;
      --surface: #ffffff;
      --bg: #f5f8f6;
    }
    * { box-sizing: border-box; }
    body {
      font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
      background:
        linear-gradient(135deg, rgba(26,82,118,.08), rgba(31,138,85,.10)),
        var(--bg);
      color: var(--ink);
      margin: 0;
      min-height: 100vh;
      padding: 32px 18px;
    }
    .page-shell {
      max-width: 980px;
      margin: 0 auto;
    }
    .topbar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 16px;
      margin-bottom: 22px;
    }
    .brand {
      font-weight: 800;
      color: var(--primary);
      letter-spacing: .02em;
    }
    .back-link {
      color: var(--green-dark);
      font-weight: 700;
      text-decoration: none;
    }
    .form-container {
      max-width: 720px;
      margin: 0 auto;
      background: var(--surface);
      padding: 34px;
      border-radius: 8px;
      border: 1px solid rgba(16, 24, 40, .08);
      box-shadow: 0 18px 44px rgba(16, 24, 40, .12);
    }
    h2 {
      color: var(--primary);
      font-size: clamp(1.6rem, 4vw, 2.2rem);
      line-height: 1.15;
      margin: 0 0 10px;
      text-align: left;
    }
    .lead {
      color: var(--muted);
      margin: 0 0 28px;
      max-width: 620px;
    }
    .form-group {
      margin-bottom: 18px;
    }
    label {
      display: block;
      margin-bottom: 7px;
      font-weight: 700;
      color: #263238;
    }
    input, select {
      width: 100%;
      padding: 12px 13px;
      border: 1px solid var(--line);
      border-radius: 5px;
      color: var(--ink);
      font-size: 1rem;
      background: #fff;
    }
    input:focus, select:focus {
      border-color: var(--green);
      box-shadow: 0 0 0 3px rgba(31, 138, 85, .14);
      outline: none;
    }
    .checkbox-group {
      display: grid;
      gap: 10px;
      margin: 20px 0;
    }
    .checkbox-group label {
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 10px;
      cursor: pointer;
      background: #f8faf9;
      border: 1px solid var(--line);
      border-radius: 6px;
      padding: 12px;
    }
    .checkbox-group input {
      width: auto;
      accent-color: var(--green);
    }
    button {
      width: 100%;
      padding: 13px 16px;
      background: var(--green);
      color: white;
      border: none;
      border-radius: 5px;
      font-size: 1rem;
      font-weight: 800;
      cursor: pointer;
      box-shadow: 0 10px 24px rgba(31, 138, 85, .22);
    }
    button:hover {
      background: var(--green-dark);
    }
    button:disabled {
      background: #ccc;
      cursor: not-allowed;
      box-shadow: none;
    }
    .form-note {
      color: var(--muted);
      font-size: .92rem;
      margin-top: 16px;
      text-align: center;
    }
    @media (max-width: 640px) {
      body { padding: 18px 12px; }
      .topbar { align-items: flex-start; flex-direction: column; }
      .form-container { padding: 24px 18px; }
    }
  </style>
</head>
<body>
  <main class="page-shell">
  <div class="topbar">
    <div class="brand">NATCODEV Registry</div>
    <a class="back-link" href="index.php#registration">Back to options</a>
  </div>
  <div class="form-container">
    <h2><?= htmlspecialchars($applicationTypeLabel, ENT_QUOTES, 'UTF-8') ?></h2>
    <p class="lead">Complete the form below. We will send a confirmation link to your email after submission.</p>
    <form id="applicationForm">
      <input type="hidden" id="application_type" name="application_type" value="<?= htmlspecialchars($applicationTypeLabel, ENT_QUOTES, 'UTF-8') ?>" />
      <input type="hidden" id="location" name="location" value="" />
      <input type="hidden" id="state_id" name="state_id" value="" />
      <input type="hidden" id="lga_id" name="lga_id" value="" />

      <div class="form-group">
        <label for="name">Full Name *</label>
        <input type="text" id="name" name="name" required />
      </div>

      <div class="form-group">
        <label for="stateSelect">State *</label>
        <select id="stateSelect" required>
          <option value="">Select state</option>
          <?php foreach ($states as $state): ?>
            <option value="<?= htmlspecialchars($state['state_name'], ENT_QUOTES, 'UTF-8') ?>" data-state-id="<?= (int) $state['id'] ?>"><?= htmlspecialchars($state['state_name'], ENT_QUOTES, 'UTF-8') ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label for="lgaSelect">Local Government Area *</label>
        <select id="lgaSelect" required>
          <option value="">Select state first</option>
        </select>
      </div>

      <div class="form-group">
        <label for="farm_size">Farm Size (Hectares) *</label>
        <input type="number" id="farm_size" name="farm_size" min="1" step="0.1" required />
      </div>

      <div class="form-group">
        <label for="phone">Phone Number (e.g. 08012345678) *</label>
        <input type="tel" id="phone" name="phone" required />
      </div>

      <div class="form-group">
        <label for="email">Email Address *</label>
        <input type="email" id="email" name="email" required />
      </div>

      <div class="checkbox-group">
        <label>
          <input type="checkbox" id="option1" name="commitments[]" value="Founding Growers Circle" />
          Founding Growers Circle
        </label>
        <label>
          <input type="checkbox" id="option2" name="commitments[]" value="Farm Assessment" />
          Farm Assessment
        </label>
        <label>
          <input type="checkbox" id="option3" name="commitments[]" value="Next Enrollment Session" />
          Next Enrollment Session
        </label>
      </div>

      <button type="submit" id="submitBtn">Submit Application</button>
      <p class="form-note">Use an active email address and phone number so the team can reach you.</p>
    </form>
  </div>
  </main>

  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const form = document.getElementById('applicationForm');
      if (!form) return;
      const stateSelect = document.getElementById('stateSelect');
      const lgaSelect = document.getElementById('lgaSelect');
      const locationInput = document.getElementById('location');
      const stateIdInput = document.getElementById('state_id');
      const lgaIdInput = document.getElementById('lga_id');

      function escapeHtml(value) {
        return String(value || '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
      }

      async function loadLgas() {
        const option = stateSelect.options[stateSelect.selectedIndex];
        const stateId = option ? option.dataset.stateId : '';
        const stateName = stateSelect.value;
        stateIdInput.value = Number(stateId) > 0 ? stateId : '';
        lgaIdInput.value = '';
        locationInput.value = '';
        lgaSelect.innerHTML = '<option value="">Loading LGAs...</option>';
        if (!stateName) {
          lgaSelect.innerHTML = '<option value="">Select state first</option>';
          return;
        }
        try {
          const url = Number(stateId) > 0 ? `api/get-lgas.php?state_id=${encodeURIComponent(stateId)}` : `api/get-lgas-by-state.php?state=${encodeURIComponent(stateName)}`;
          const response = await fetch(url);
          const payload = await response.json();
          const items = Array.isArray(payload) ? payload : (payload.items || []);
          lgaSelect.innerHTML = '<option value="">Select LGA</option>' + items.map(item => `<option value="${escapeHtml(item.lga_name)}" data-lga-id="${Number(item.id) || ''}">${escapeHtml(item.lga_name)}</option>`).join('');
        } catch (err) {
          lgaSelect.innerHTML = '<option value="">Unable to load LGAs</option>';
        }
      }

      stateSelect.addEventListener('change', loadLgas);
      lgaSelect.addEventListener('change', function () {
        const option = lgaSelect.options[lgaSelect.selectedIndex];
        lgaIdInput.value = option ? (option.dataset.lgaId || '') : '';
        locationInput.value = stateSelect.value && lgaSelect.value ? `${stateSelect.value}, ${lgaSelect.value}` : '';
      });

      form.addEventListener('submit', async function (e) {
        e.preventDefault();

        // Get commitments as a string
        const commitments = [];
        const applicationType = document.getElementById('application_type')?.value;
        if (applicationType) commitments.push('Application Type: ' + applicationType);
        if (document.getElementById('option1')?.checked) commitments.push('Founding Growers Circle');
        if (document.getElementById('option2')?.checked) commitments.push('Farm Assessment');
        if (document.getElementById('option3')?.checked) commitments.push('Next Enrollment Session');

        if (commitments.length <= (applicationType ? 1 : 0)) {
          alert('Please select at least one commitment option.');
          return;
        }
        if (!stateSelect.value || !lgaSelect.value) {
          alert('Please select your state and local government area.');
          return;
        }
        locationInput.value = `${stateSelect.value}, ${lgaSelect.value}`;

        const formData = new FormData(form);
        formData.set('commitments', commitments.join(', '));

        const submitBtn = document.getElementById('submitBtn');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';

        try {
          const response = await fetch('send_email.php', {
            method: 'POST',
            body: formData
          });

          const result = await response.json();

          if (result.success) {
            alert(`✅ Application submitted successfully!\nReference: ${result.app_ref}\nCheck your email for confirmation.`);
            form.reset();
          } else {
            alert('❌ Submission failed: ' + (result.message || 'Unknown error'));
          }
        } catch (err) {
          alert('⚠️ Network error. Please check your connection and try again.');
          console.error('Error:', err);
        } finally {
          submitBtn.disabled = false;
          submitBtn.innerHTML = originalText;
        }
      });
    });
  </script>
</body>
</html>
