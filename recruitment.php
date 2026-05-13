<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/admin-layout.php';

$pdo = db();
admin_ensure_schema($pdo);

$roles = [
    'field_agent' => 'Field Agent',
    'agronomist' => 'Agronomist',
    'extensionist' => 'Agric Extensionist',
];
$qualifications = [
    'SSCE / O-Level',
    'OND / NCE',
    'HND',
    'Bachelor\'s Degree',
    'Postgraduate Diploma',
    'Master\'s Degree',
    'PhD',
    'Professional Certification',
    'Other',
];
$certificationPrograms = [
    'Field Agent Certification',
    'Agric Extensionist Certification',
    'Coconut Agronomy Certification',
    'Farm Verification & Documentation',
    'Digital Field Data Collection',
    'Disease Risk Assessment',
];
$message = '';
$error = '';
$states = [];
if (app_table_exists($pdo, 'nigeria_states')) {
    try {
        $states = $pdo->query("SELECT id, state_name FROM nigeria_states ORDER BY state_name")->fetchAll();
    } catch (Throwable $e) {
        $states = [];
    }
}
if (!$states) {
    $states = array_map(
        static fn(string $name): array => ['id' => 0, 'state_name' => $name],
        ['Abia', 'Adamawa', 'Akwa Ibom', 'Anambra', 'Bauchi', 'Bayelsa', 'Benue', 'Borno', 'Cross River', 'Delta', 'Ebonyi', 'Edo', 'Ekiti', 'Enugu', 'Federal Capital Territory', 'Gombe', 'Imo', 'Jigawa', 'Kaduna', 'Kano', 'Katsina', 'Kebbi', 'Kogi', 'Kwara', 'Lagos', 'Nasarawa', 'Niger', 'Ogun', 'Ondo', 'Osun', 'Oyo', 'Plateau', 'Rivers', 'Sokoto', 'Taraba', 'Yobe', 'Zamfara']
    );
}

function recruitment_upload(string $field, string $prefix): ?string
{
    if (empty($_FILES[$field]['name'])) {
        return null;
    }

    $allowed = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
    $original = (string) $_FILES[$field]['name'];
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) {
        throw new RuntimeException('Only PDF, DOC, DOCX, JPG, and PNG files are supported.');
    }

    $dir = __DIR__ . '/recruitment_uploads';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $name = $prefix . '_' . time() . '_' . preg_replace('/[^a-z0-9._-]/i', '_', basename($original));
    if (!move_uploaded_file((string) $_FILES[$field]['tmp_name'], $dir . '/' . $name)) {
        throw new RuntimeException('Unable to upload one of the attached files.');
    }

    return 'recruitment_uploads/' . $name;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = (string) ($_POST['role_applied'] ?? '');
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $state = trim((string) ($_POST['state'] ?? ''));
    $lga = trim((string) ($_POST['lga'] ?? ''));
    $qualification = trim((string) ($_POST['qualification'] ?? ''));
    $license = trim((string) ($_POST['license_number'] ?? ''));
    $experience = max(0, (float) ($_POST['experience_years'] ?? 0));
    $availability = trim((string) ($_POST['availability'] ?? ''));
    $cover = trim((string) ($_POST['cover_note'] ?? ''));
    $certificationInterest = isset($_POST['certification_interest']) ? 1 : 0;
    $certificationProgram = trim((string) ($_POST['certification_program'] ?? ''));

    if (!isset($roles[$role]) || $name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $phone === '' || $state === '') {
        $error = 'Please complete the required fields.';
    } elseif ($qualification !== '' && !in_array($qualification, $qualifications, true)) {
        $error = 'Please select a valid qualification.';
    } elseif ($certificationProgram !== '' && !in_array($certificationProgram, $certificationPrograms, true)) {
        $error = 'Please select a valid certification program.';
    } else {
        try {
            $ref = 'REC-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
            $cvPath = recruitment_upload('cv_file', strtolower($role) . '_cv');
            $idPath = recruitment_upload('id_file', strtolower($role) . '_id');

            $stmt = $pdo->prepare("
                INSERT INTO recruitment_applications
                    (app_ref, role_applied, name, email, phone, state, lga, qualification, license_number, experience_years, availability, cover_note, certification_interest, certification_program, cv_path, id_path)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$ref, $role, $name, $email, $phone, $state, $lga, $qualification, $license, $experience, $availability, $cover, $certificationInterest, $certificationProgram !== '' ? $certificationProgram : null, $cvPath, $idPath]);

            app_send_mail(
                (string) app_env('ADMIN_NOTIFY_EMAIL', 'info@natcodev.com.ng'),
                'New NATCODEV Recruitment Application',
                "A new {$roles[$role]} application has been submitted.\n\nReference: {$ref}\nName: {$name}\nEmail: {$email}\nPhone: {$phone}"
            );
            app_send_mail(
                $email,
                'NATCODEV Recruitment Application Received',
                "Dear {$name},\n\nYour {$roles[$role]} application has been received.\nReference: {$ref}\n\nThe NATCODEV team will review your submission and contact you with next steps."
            );

            $message = "Application submitted successfully. Your reference is {$ref}.";
            $_POST = [];
        } catch (Throwable $e) {
            error_log('Recruitment submission error: ' . $e->getMessage());
            $error = $e instanceof RuntimeException ? $e->getMessage() : 'Unable to submit application. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>NATCODEV Recruitment</title>
  <style>
    :root { --primary:#1a5276; --green:#1f8a55; --green-dark:#166b41; --ink:#1f2937; --muted:#667085; --line:#d8e2dc; --bg:#f5f8f6; }
    * { box-sizing:border-box; }
    body { margin:0; background:linear-gradient(135deg, rgba(26,82,118,.08), rgba(31,138,85,.10)), var(--bg); color:var(--ink); font-family:"Segoe UI", Tahoma, Geneva, Verdana, sans-serif; padding:28px 16px; }
    .shell { max-width:980px; margin:0 auto; }
    .topbar { display:flex; justify-content:space-between; align-items:center; gap:16px; margin-bottom:22px; }
    .brand { display:flex; align-items:center; gap:12px; color:var(--primary); font-weight:900; }
    .brand img { width:52px; height:52px; object-fit:contain; border-radius:50%; border:1px solid var(--line); background:#fff; }
    a { color:var(--green-dark); font-weight:800; text-decoration:none; }
    .panel { background:#fff; border:1px solid rgba(16,24,40,.08); border-radius:8px; box-shadow:0 18px 44px rgba(16,24,40,.12); padding:30px; }
    h1 { margin:0 0 8px; color:var(--primary); font-size:clamp(2rem,5vw,3.2rem); line-height:1.05; }
    .lead, .muted { color:var(--muted); }
    .grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:16px; }
    label { display:block; font-weight:800; margin:14px 0 6px; }
    input, select, textarea { width:100%; padding:12px; border:1px solid var(--line); border-radius:6px; font:inherit; }
    textarea { min-height:120px; }
    input:focus, select:focus, textarea:focus { border-color:var(--green); box-shadow:0 0 0 3px rgba(31,138,85,.14); outline:none; }
    button { margin-top:18px; width:100%; background:var(--green); color:#fff; border:0; border-radius:6px; padding:13px 16px; font-weight:900; cursor:pointer; }
    button:hover { background:var(--green-dark); }
    .notice { padding:13px 15px; border-radius:8px; margin:16px 0; }
    .ok { background:#eaf8f0; color:#0f6b3c; border:1px solid #bfe8cf; }
    .error { background:#fff3f3; color:#a32020; border:1px solid #ffd2d2; }
    @media (max-width:720px) { .grid { grid-template-columns:1fr; } .topbar { align-items:flex-start; flex-direction:column; } .panel { padding:22px 16px; } }
  </style>
</head>
<body>
<main class="shell">
  <div class="topbar">
    <a class="brand" href="index.php"><img src="<?= e(app_primary_logo_url()) ?>" alt="NATCODEV"><span>NATCODEV Recruitment</span></a>
    <a href="index.php">Back to Home</a>
  </div>
  <section class="panel">
    <h1>Join the Field Network</h1>
    <p class="lead">Apply as a Field Agent, Agronomist, or Agric Extensionist. Approved applicants receive staff dashboard access after NATCODEV review.</p>
    <?php if ($message): ?><div class="notice ok"><?= e($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data">
      <div class="grid">
        <div>
          <label>Role Applying For *</label>
          <select name="role_applied" required>
            <option value="">Select role</option>
            <?php foreach ($roles as $value => $label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div><label>Full Name *</label><input type="text" name="name" required></div>
        <div><label>Email *</label><input type="email" name="email" required></div>
        <div><label>Phone *</label><input type="tel" name="phone" required></div>
        <div>
          <label>State *</label>
          <select name="state" id="stateSelect" required>
            <option value="">Select state</option>
            <?php foreach ($states as $stateRow): ?>
              <option value="<?= e($stateRow['state_name']) ?>" data-state-id="<?= (int) $stateRow['id'] ?>"><?= e($stateRow['state_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>LGA</label>
          <select name="lga" id="lgaSelect">
            <option value="">Select state first</option>
          </select>
        </div>
        <div>
          <label>Highest Qualification</label>
          <select name="qualification">
            <option value="">Select qualification</option>
            <?php foreach ($qualifications as $qualificationOption): ?>
              <option value="<?= e($qualificationOption) ?>"><?= e($qualificationOption) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div><label>License / Certification Number</label><input type="text" name="license_number"></div>
        <div><label>Years of Experience</label><input type="number" name="experience_years" min="0" step="0.5" value="0"></div>
        <div><label>Availability</label><input type="text" name="availability" placeholder="Full-time, part-time, state coverage"></div>
        <div><label>CV / Resume</label><input type="file" name="cv_file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"></div>
        <div><label>ID / License File</label><input type="file" name="id_file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"></div>
      </div>
      <label>
        <input type="checkbox" name="certification_interest" style="width:auto;">
        I am interested in NATCODEV paid training and certification.
      </label>
      <label>Preferred Certification Program</label>
      <select name="certification_program">
        <option value="">Select program if interested</option>
        <?php foreach ($certificationPrograms as $program): ?>
          <option value="<?= e($program) ?>"><?= e($program) ?></option>
        <?php endforeach; ?>
      </select>
      <label>Cover Note</label>
      <textarea name="cover_note" placeholder="Tell us about your field experience, coconut/agriculture exposure, and coverage area."></textarea>
      <button type="submit">Submit Recruitment Application</button>
      <p class="muted">Applications are reviewed before system access is created.</p>
    </form>
  </section>
</main>
<script>
const stateSelect = document.getElementById('stateSelect');
const lgaSelect = document.getElementById('lgaSelect');

async function loadLgas() {
  const option = stateSelect.options[stateSelect.selectedIndex];
  const stateId = option ? option.dataset.stateId : '';
  const stateName = stateSelect.value;
  lgaSelect.innerHTML = '<option value="">Loading LGAs...</option>';
  if (!stateName) {
    lgaSelect.innerHTML = '<option value="">Select state first</option>';
    return;
  }
  try {
    const url = Number(stateId) > 0
      ? `api/get-lgas.php?state_id=${encodeURIComponent(stateId)}`
      : `api/get-lgas-by-state.php?state=${encodeURIComponent(stateName)}`;
    const response = await fetch(url);
    const payload = await response.json();
    const items = Array.isArray(payload) ? payload : (payload.items || []);
    lgaSelect.innerHTML = '<option value="">Select LGA</option>' + items.map(item => `<option value="${escapeHtml(item.lga_name)}">${escapeHtml(item.lga_name)}</option>`).join('');
    if (!items.length) {
      lgaSelect.innerHTML = '<option value="">No LGAs found</option>';
    }
  } catch (error) {
    lgaSelect.innerHTML = '<option value="">Unable to load LGAs</option>';
  }
}

function escapeHtml(value) {
  return String(value || '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
}

stateSelect.addEventListener('change', loadLgas);
</script>
</body>
</html>
