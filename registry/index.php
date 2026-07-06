<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

$logo = app_primary_logo_url();
$year = date('Y');
$roles = [
    ['title' => 'Coconut Grower', 'text' => 'Join the NATCODEV coconut registry as a farmer or grower.', 'href' => '../apply.php?type=farmer', 'icon' => 'fa-seedling', 'primary' => true],
    ['title' => 'Commercial Outgrower', 'text' => 'Register larger coconut production or outgrower operations.', 'href' => '../apply.php?type=outgrower', 'icon' => 'fa-tractor', 'primary' => false],
    ['title' => 'Cooperative', 'text' => 'Register a coconut farmers cooperative or group.', 'href' => '../apply.php?type=cooperative', 'icon' => 'fa-people-group', 'primary' => false],
    ['title' => 'Input or Service Provider', 'text' => 'Start provider onboarding and accreditation.', 'href' => '../provider/index.php', 'icon' => 'fa-handshake', 'primary' => false],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Join the Coconut Registry - NATCODEV</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    :root{--green:#063f20;--green2:#08753a;--soft:#f4faf2;--ink:#111827;--muted:#667085;--line:#dfe8d8;--shadow:0 18px 42px rgba(16,24,40,.1)}*{box-sizing:border-box}body{margin:0;font-family:"Segoe UI",Arial,sans-serif;color:var(--ink);background:#f7faf5}a{text-decoration:none;color:inherit}.top{background:#fff;border-bottom:1px solid var(--line);position:sticky;top:0;z-index:10}.top-inner{max-width:1240px;margin:0 auto;min-height:82px;padding:12px 22px;display:flex;align-items:center;justify-content:space-between;gap:18px}.brand{display:flex;align-items:center;gap:12px}.brand img{width:58px;height:58px;border-radius:50%;object-fit:contain}.brand strong{display:block;color:var(--green);font-size:1.65rem;line-height:1}.brand span span{font-size:.72rem;font-weight:900;color:#1f2937}.nav{display:flex;gap:10px;flex-wrap:wrap}.btn{display:inline-flex;align-items:center;justify-content:center;gap:9px;border:1px solid var(--green2);border-radius:8px;background:var(--green2);color:#fff;font-weight:900;padding:12px 17px}.btn.light{background:#fff;color:var(--green)}.hero{background:linear-gradient(90deg,rgba(6,63,32,.9),rgba(6,63,32,.56)),url('../assets/public/natcodev-home-hero.png') center/cover;color:#fff}.hero-inner{max-width:1240px;margin:0 auto;padding:62px 22px 76px}.hero h1{font-size:clamp(2.4rem,5vw,4.8rem);line-height:1;margin:0 0 14px}.hero p{max-width:780px;font-size:1.18rem;line-height:1.55;font-weight:700;margin:0 0 24px}.wrap{max-width:1240px;margin:-44px auto 42px;padding:0 22px;position:relative}.panel{background:#fff;border:1px solid var(--line);border-radius:14px;box-shadow:var(--shadow);padding:22px}.notice{border:1px solid #b9e3c3;background:#effaf1;border-radius:10px;padding:14px 16px;color:#06451f;font-weight:800;margin-bottom:18px}.grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}.card{border:1px solid var(--line);border-radius:10px;padding:18px;display:flex;flex-direction:column;gap:10px;min-height:220px;background:#fff}.card.primary{border-color:#86d39b;background:linear-gradient(180deg,#f4fff5,#fff)}.icon{width:54px;height:54px;border-radius:50%;display:grid;place-items:center;background:#e8f6ec;color:var(--green2);font-size:1.45rem}.card h2{margin:0;color:var(--green);font-size:1.25rem}.card p{margin:0;color:#475467;line-height:1.45;font-weight:650}.card .btn{margin-top:auto}.steps{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-top:18px}.step{border:1px solid var(--line);border-radius:10px;padding:14px;background:#fbfdf9}.step strong{display:block;color:var(--green);margin-bottom:5px}.footer{text-align:center;color:#475467;padding:24px}@media(max-width:900px){.top-inner{align-items:flex-start;flex-direction:column}.grid,.steps{grid-template-columns:1fr}.hero-inner{padding-top:42px}.wrap{margin-top:-30px}}
  </style>
</head>
<body>
<header class="top"><div class="top-inner"><a class="brand" href="../index.php"><img src="<?= e($logo) ?>" alt="NATCODEV"><span><strong>NATCODEV</strong><span>National Coconut Registry</span></span></a><nav class="nav"><a class="btn light" href="../index.php">Home</a><a class="btn light" href="../login.php">Login</a><a class="btn" href="../apply.php?type=farmer"><i class="fas fa-user-plus"></i> Join Registry</a></nav></div></header>
<section class="hero"><div class="hero-inner"><h1>Join the Coconut Registry</h1><p>If you landed here by mistake, you are still in the right public place. Choose your registry path below and NATCODEV will take you to the correct registration form.</p><a class="btn" href="../apply.php?type=farmer"><i class="fas fa-seedling"></i> Start Grower Registration</a></div></section>
<main class="wrap"><section class="panel"><div class="notice"><i class="fas fa-circle-info"></i> Public users join the coconut registry here. Admin registry workspaces are only for authorized NATCODEV staff after login.</div><div class="grid"><?php foreach ($roles as $role): ?><a class="card <?= $role['primary'] ? 'primary' : '' ?>" href="<?= e($role['href']) ?>"><span class="icon"><i class="fas <?= e($role['icon']) ?>"></i></span><h2><?= e($role['title']) ?></h2><p><?= e($role['text']) ?></p><span class="btn <?= $role['primary'] ? '' : 'light' ?>"><?= $role['primary'] ? 'Start Now' : 'Continue' ?></span></a><?php endforeach; ?></div><div class="steps"><div class="step"><strong>1. Register</strong>Submit your basic profile and contact details.</div><div class="step"><strong>2. Confirm</strong>Confirm your email and continue your dashboard setup.</div><div class="step"><strong>3. Verify</strong>Upload documents and farm evidence for review.</div><div class="step"><strong>4. Activate</strong>Access certificates, academy, support, wallet, and marketplace.</div></div></section></main>
<footer class="footer">&copy; <?= e($year) ?> NATCODEV. National Coconut Development & Propagation Initiative.</footer>
</body>
</html>