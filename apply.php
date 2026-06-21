<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/nigeria-locations.php';

$pdo = db();
app_ensure_core_schema($pdo);
$states = app_table_exists($pdo, 'nigeria_states')
    ? $pdo->query("SELECT id, state_name FROM nigeria_states ORDER BY state_name")->fetchAll()
    : [];
$applicationType = strtolower((string) ($_GET['type'] ?? 'farmer'));
$applicationTypeLabel = match ($applicationType) {
    'outgrower' => 'Commercial Coconut Outgrowers Registration',
    'cooperative' => 'Coconut Farmers Cooperative Registration',
    default => 'Coconut Grower Registration',
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register as a Grower - NATCODEV</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    :root{--green:#06451f;--green2:#08753a;--mint:#eef8ef;--gold:#d89b10;--blue:#0e7490;--ink:#101828;--muted:#667085;--line:#dfe8d8;--bg:#fbfcf8;--soft:#f7fbf4;--shadow:0 18px 48px rgba(16,24,40,.08)}*{box-sizing:border-box}body{margin:0;background:var(--bg);font-family:"Segoe UI",Arial,sans-serif;color:var(--ink)}a{text-decoration:none;color:inherit}.top{height:82px;background:#fff;border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;padding:0 36px;position:sticky;top:0;z-index:20}.brand{display:flex;gap:12px;align-items:center}.brand img{width:62px;height:62px;border-radius:50%;object-fit:contain}.brand strong{font-size:1.65rem;color:var(--green)}.brand small{display:block;font-weight:800;color:#344054}.nav{display:flex;gap:28px;font-weight:900}.nav a.active{color:var(--green);border-bottom:3px solid var(--green);padding-bottom:27px}.actions{display:flex;gap:12px;align-items:center}.btn{display:inline-flex;gap:10px;align-items:center;justify-content:center;border:1px solid var(--green);border-radius:8px;background:var(--green);color:#fff;font-weight:950;padding:12px 18px;cursor:pointer}.btn.light{background:#fff;color:var(--green)}.icon-btn{width:48px;height:48px;border:1px solid var(--line);border-radius:9px;display:grid;place-items:center;color:var(--green)}.hero{min-height:255px;background:linear-gradient(90deg,rgba(4,38,16,.86),rgba(4,38,16,.58) 48%,rgba(4,38,16,.12)),url("assets/public/grower-registration-hero.png") center/cover;color:#fff;padding:42px 58px;display:flex;align-items:center}.hero h1{font-size:clamp(2.2rem,4vw,4.25rem);line-height:1;margin:0 0 12px}.hero p{font-size:1.1rem;line-height:1.5;max-width:720px;font-weight:750}.wrap{padding:0 52px 34px}.shell{display:grid;grid-template-columns:minmax(0,1fr) 360px;gap:0;background:#fff;border:1px solid var(--line);border-radius:16px;box-shadow:var(--shadow);margin-top:-42px;position:relative;z-index:2;overflow:hidden}.form{padding:30px 36px}.form h2,.side h2{color:var(--green);margin:0}.form-head{display:flex;gap:18px;align-items:flex-start;margin-bottom:18px}.form-head i{width:54px;height:54px;border-radius:50%;display:grid;place-items:center;background:#e8f6ec;color:var(--green);font-size:1.35rem;flex:0 0 auto}.form-head p{color:var(--muted);font-weight:750;line-height:1.5;margin:7px 0 0;max-width:700px}.progress-strip{display:grid;grid-template-columns:repeat(6,1fr);gap:8px;margin:18px 0 22px}.progress-step{border:1px solid var(--line);border-radius:9px;background:#fff;padding:10px;min-height:68px}.progress-step.active{background:var(--mint);border-color:#b9e3c3}.progress-step span{width:26px;height:26px;border-radius:50%;background:#d0d5dd;color:#344054;display:grid;place-items:center;font-size:.8rem;font-weight:950;margin-bottom:6px}.progress-step.active span{background:var(--green);color:#fff}.progress-step strong{display:block;font-size:.86rem;line-height:1.2}.form-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:16px}label{display:block;font-weight:850;color:#1f2937}input,select,textarea{width:100%;border:1px solid var(--line);border-radius:9px;padding:13px;margin-top:7px;font:inherit;background:#fff}input:focus,select:focus,textarea:focus{outline:3px solid #dff3e5;border-color:#7ccf94}.wide{grid-column:1/-1}.pass{position:relative}.pass button{position:absolute;right:9px;top:36px;border:0;background:#eef8ef;color:var(--green);border-radius:8px;padding:8px 10px;font-weight:900;cursor:pointer}.safe{display:flex;gap:14px;align-items:center;background:#f5fbf4;border:1px solid #cbe8cd;border-radius:10px;padding:14px;margin:16px 0;color:#06451f}.submit-row{display:flex;gap:16px;align-items:center;flex-wrap:wrap}.side{border-left:1px solid var(--line);padding:24px;background:var(--soft)}.guide-card{position:sticky;top:106px}.guide-intro{color:var(--muted);font-weight:750;line-height:1.5;margin:8px 0 16px}.mini-help{display:flex;gap:12px;align-items:center;border:1px solid #cbe8cd;border-radius:10px;background:#fff;padding:14px;margin-bottom:14px}.mini-help i{color:var(--green);font-size:1.35rem}.guide-section{border:1px solid var(--line);border-radius:10px;background:#fff;margin-top:10px;overflow:hidden}.guide-section summary{cursor:pointer;list-style:none;display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 15px;font-weight:950;color:#1f2937}.guide-section summary::-webkit-details-marker{display:none}.guide-section summary:after{content:"+";width:24px;height:24px;border-radius:50%;background:#eef8ef;color:var(--green);display:grid;place-items:center}.guide-section[open] summary:after{content:"-"}.guide-body{border-top:1px solid var(--line);padding:13px 15px;color:#475467;font-weight:750;line-height:1.45}.guide-list{display:grid;gap:10px;margin:0;padding:0;list-style:none}.guide-list li{display:flex;gap:10px;align-items:flex-start}.guide-list i{color:var(--green);margin-top:3px}.doc-row{display:flex;justify-content:space-between;gap:10px;margin:10px 0}.badge{border-radius:999px;background:#fff3d6;color:#9a6500;padding:4px 8px;font-size:.78rem;font-weight:950;white-space:nowrap}.next-list{counter-reset:next;margin:0;padding:0;list-style:none;display:grid;gap:10px}.next-list li{counter-increment:next;display:flex;gap:10px}.next-list li:before{content:counter(next);width:24px;height:24px;border-radius:50%;background:#e8f6ec;color:var(--green);display:grid;place-items:center;font-weight:950;flex:0 0 auto}.trust{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;background:#fff;border:1px solid var(--line);border-radius:14px;padding:16px;margin-top:18px}.trust-item{display:flex;gap:12px;align-items:center}.trust-item i{font-size:1.55rem;color:var(--green)}.trust-item strong{display:block}.trust-item span{color:#475467;font-weight:750}.footer{background:#052d15;color:#e8f6ec;display:flex;justify-content:space-between;gap:20px;align-items:center;padding:20px 52px;font-weight:900}.footer span{display:flex;gap:10px;align-items:center}.alert{padding:13px;border-radius:10px;margin-bottom:14px;font-weight:850;display:none}.alert.ok{background:#e8f6ec;color:var(--green);border:1px solid #b9e3c3}.alert.err{background:#fff1f2;color:#b42318;border:1px solid #fecdd3}.social{display:flex;gap:10px;flex-wrap:wrap;margin:12px 0 18px}.social a{border:1px solid var(--line);border-radius:8px;padding:10px 12px;color:var(--green);font-weight:900}.social span{color:var(--muted);font-weight:800;padding:10px 0}
    @media(max-width:1180px){.shell{grid-template-columns:1fr}.side{border-left:0;border-top:1px solid var(--line)}.guide-card{position:static}.progress-strip,.trust{grid-template-columns:repeat(3,1fr)}}@media(max-width:760px){.top{height:auto;padding:14px;align-items:flex-start;flex-direction:column}.nav{gap:14px;flex-wrap:wrap}.hero{min-height:230px;padding:32px 18px}.wrap{padding:0 14px 28px}.form{padding:22px}.form-head{align-items:flex-start}.form-grid,.progress-strip,.trust{grid-template-columns:1fr}.footer{align-items:flex-start;flex-direction:column;padding:20px}}
  </style>
</head>
<body>
<header class="top">
  <a class="brand" href="index.php"><img src="<?= e(app_primary_logo_url()) ?>" alt="NATCODEV"><span><strong>NATCODEV</strong><small>National Coconut Development & Propagation Initiative</small></span></a>
  <nav class="nav"><a href="index.php">Home</a><a href="market/index.php">Marketplace</a><a href="academy/index.php?screen=catalog">Academy</a><a class="active" href="apply.php">Registry</a><a href="verify-certificate.php">Certificates</a><a href="support/index.php?category=account">Support</a></nav>
  <div class="actions"><a class="icon-btn" href="market/index.php"><i class="fas fa-search"></i></a><a class="btn light" href="dashboard/login.php">Login</a><a class="btn" href="#grower-form"><i class="fas fa-user-plus"></i> Register</a></div>
</header>
<section class="hero">
  <div>
    <h1>Register as a Grower</h1>
    <p>Start your NATCODEV grower profile with a few basic details. You can complete verification, documents, and farm updates from your dashboard after registration.</p>
  </div>
</section>
<main class="wrap">
  <section class="shell">
    <section class="form">
      <div class="form-head"><i class="fas fa-user"></i><div><h2>Step 1 of 6: Personal Details</h2><p>Start with your basic details. You can complete deeper profile, documents, and verification from your dashboard later.</p></div></div>
      <div class="progress-strip" aria-label="Registration progress">
        <?php foreach (['Personal Details','Farm Location','Coconut Stands','Intercrops','Documents','Verification'] as $i => $step): ?>
          <div class="progress-step <?= $i === 0 ? 'active' : '' ?>"><span><?= $i + 1 ?></span><strong><?= e($step) ?></strong></div>
        <?php endforeach; ?>
      </div>
      <div id="formAlert" class="alert"></div>
      <div class="social"><a href="?social=google"><i class="fab fa-google"></i> Continue with Google</a><a href="?social=facebook"><i class="fab fa-facebook"></i> Continue with Facebook</a><span style="color:#667085;font-weight:800">Email registration is active now. Social login needs OAuth keys.</span></div>
      <form id="grower-form">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" id="application_type" name="application_type" value="<?= e($applicationTypeLabel) ?>">
        <input type="hidden" id="location" name="location" value="">
        <input type="hidden" id="state_id" name="state_id" value="">
        <input type="hidden" id="lga_id" name="lga_id" value="">
        <input type="hidden" id="commitments" name="commitments" value="">
        <div class="form-grid">
          <label>Full Name *<input name="name" id="name" required placeholder="Enter your full name"></label>
          <label>Phone Number *<input name="phone" id="phone" required placeholder="08012345678"></label>
          <label>Email Address *<input type="email" name="email" id="email" required placeholder="Enter your email address"></label>
          <label class="pass">Password *<input id="password" type="password" name="password" minlength="6" required placeholder="Create dashboard password"><button type="button" data-toggle-password="password">Show</button></label>
          <label>State *<select id="stateSelect" required><option value="">Select your state</option><?php foreach ($states as $state): ?><option value="<?= e((string) $state['state_name']) ?>" data-state-id="<?= (int) $state['id'] ?>"><?= e((string) $state['state_name']) ?></option><?php endforeach; ?></select></label>
          <label>LGA *<select id="lgaSelect" required><option value="">Select your LGA</option></select></label>
          <label>Farm Size (in Hectares) *<input type="number" min="0.1" max="1000" step="0.1" name="farm_size" id="farm_size" required placeholder="e.g., 2.5"></label>
          <label>Number of Coconut Seedlings/Stands *<input type="number" min="0" step="1" id="stands" placeholder="e.g., 200"></label>
          <label>Intercrop Activities <input id="intercrops" placeholder="e.g., Cassava, Plantain, Vegetables"></label>
          <label>Livestock Activities <input id="livestock" placeholder="e.g., Poultry, Goats, Fish Farming"></label>
        </div>
        <div class="safe"><i class="fas fa-seedling"></i><span><strong>Your Data is Safe</strong><br>Your information is protected and used only for NATCODEV program purposes.</span></div>
        <div class="submit-row"><button class="btn" type="submit" id="submitBtn">Start Registration <i class="fas fa-arrow-right"></i></button><span>Already have an account? <a style="color:var(--green);font-weight:950" href="dashboard/login.php">Login here</a></span></div>
      </form>
    </section>
    <aside class="side">
      <div class="guide-card">
        <h2>Registration Guide</h2>
        <p class="guide-intro">Useful details are here when you need them, while the form stays clear for completion.</p>
        <div class="mini-help"><i class="fas fa-headset"></i><span><strong>Need help?</strong><br><a style="color:var(--green);font-weight:950" href="support/index.php?category=account">Contact support</a></span></div>
        <details class="guide-section" open>
          <summary>Why register?</summary>
          <div class="guide-body">
            <ul class="guide-list">
              <li><i class="fas fa-award"></i><span>Verified grower certificate after review.</span></li>
              <li><i class="fas fa-chart-line"></i><span>Farm dashboard for records and performance.</span></li>
              <li><i class="fas fa-cart-shopping"></i><span>Marketplace, academy, wallet, and support access.</span></li>
            </ul>
          </div>
        </details>
        <details class="guide-section">
          <summary>Required documents</summary>
          <div class="guide-body">
            <div class="doc-row"><span><i class="fas fa-id-card"></i> Valid ID Card</span><span class="badge">Required</span></div>
            <div class="doc-row"><span><i class="fas fa-camera"></i> Farm Evidence</span><span class="badge">Required</span></div>
            <div class="doc-row"><span><i class="fas fa-users"></i> Cooperative Membership</span><span class="badge">Optional</span></div>
            <p>Uploads can be completed after your dashboard is created.</p>
          </div>
        </details>
        <details class="guide-section">
          <summary>What happens next?</summary>
          <div class="guide-body">
            <ol class="next-list">
              <li>Submit this starter application.</li>
              <li>Login to complete documents and farm profile.</li>
              <li>NATCODEV reviews and verifies your record.</li>
              <li>Your certificate becomes available when approved.</li>
            </ol>
          </div>
        </details>
      </div>
    </aside>
  </section>
  <section class="trust"><div class="trust-item"><i class="fas fa-shield-heart"></i><span><strong>Free registration</strong>No hidden charges.</span></div><div class="trust-item"><i class="fas fa-lock"></i><span><strong>Secure records</strong>Shared only with authorized officials.</span></div><div class="trust-item"><i class="fas fa-seedling"></i><span><strong>Built for growers</strong>Designed for Nigerian farmers.</span></div></section>
</main>
<footer class="footer"><span><i class="fas fa-leaf"></i> Empowering coconut farmers and communities across Nigeria.</span><span><i class="fas fa-building-columns"></i> Trusted by Government</span><span><i class="fas fa-shield-halved"></i> Secure Platform</span><span>&copy; <?= e(date('Y')) ?> NATCODEV.</span></footer>
<script>
document.querySelectorAll('[data-toggle-password]').forEach(function(button){button.addEventListener('click',function(){var input=document.getElementById(button.getAttribute('data-toggle-password'));var visible=input.type==='text';input.type=visible?'password':'text';button.textContent=visible?'Show':'Hide';});});
document.addEventListener('DOMContentLoaded', function(){
  const form=document.getElementById('grower-form');const stateSelect=document.getElementById('stateSelect');const lgaSelect=document.getElementById('lgaSelect');const alertBox=document.getElementById('formAlert');
  function showAlert(type,msg){alertBox.className='alert '+type;alertBox.textContent=msg;alertBox.style.display='block';alertBox.scrollIntoView({behavior:'smooth',block:'center'});}
  function escapeHtml(value){return String(value||'').replace(/[&<>"']/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));}
  stateSelect.addEventListener('change', async function(){const option=stateSelect.options[stateSelect.selectedIndex];const stateId=option?option.dataset.stateId:'';document.getElementById('state_id').value=Number(stateId)>0?stateId:'';document.getElementById('lga_id').value='';lgaSelect.innerHTML='<option>Loading LGAs...</option>';if(!stateSelect.value){lgaSelect.innerHTML='<option value="">Select your LGA</option>';return;}try{const response=await fetch('api/get-lgas.php?state_id='+encodeURIComponent(stateId));const payload=await response.json();const items=payload.items||[];lgaSelect.innerHTML='<option value="">Select your LGA</option>'+items.map(item=>'<option value="'+escapeHtml(item.lga_name)+'" data-lga-id="'+(Number(item.id)||'')+'">'+escapeHtml(item.lga_name)+'</option>').join('');}catch(e){lgaSelect.innerHTML='<option value="">Unable to load LGAs</option>';}}); 
  lgaSelect.addEventListener('change',function(){const option=lgaSelect.options[lgaSelect.selectedIndex];document.getElementById('lga_id').value=option?(option.dataset.lgaId||''):'';document.getElementById('location').value=stateSelect.value&&lgaSelect.value?stateSelect.value+', '+lgaSelect.value:'';});
  form.addEventListener('submit',async function(e){e.preventDefault();if(!stateSelect.value||!lgaSelect.value){showAlert('err','Please select your state and LGA.');return;}document.getElementById('location').value=stateSelect.value+', '+lgaSelect.value;const details=[];details.push('Application Type: '+document.getElementById('application_type').value);details.push('Grower Registry');details.push('Farm Assessment');details.push('Dashboard Activation');const stands=document.getElementById('stands').value;if(stands)details.push('Coconut stands: '+stands);const intercrops=document.getElementById('intercrops').value;if(intercrops)details.push('Intercrops: '+intercrops);const livestock=document.getElementById('livestock').value;if(livestock)details.push('Livestock: '+livestock);document.getElementById('commitments').value=details.join(', ');const submitBtn=document.getElementById('submitBtn');const original=submitBtn.innerHTML;submitBtn.disabled=true;submitBtn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Registering...';try{const response=await fetch('send_email.php',{method:'POST',body:new FormData(form)});const result=await response.json();if(result.success){showAlert('ok','Registration started successfully. Reference: '+result.app_ref+'. You can login with your email and password, and also confirm your email when the message arrives.');setTimeout(()=>{window.location.href='dashboard/login.php';},1800);}else{showAlert('err',result.message||'Registration failed.');}}catch(err){showAlert('err','Network error. Please try again.');}finally{submitBtn.disabled=false;submitBtn.innerHTML=original;}});
});
</script>
</body>
</html>
