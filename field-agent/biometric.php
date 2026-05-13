<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

session_start();
$pdo = db();
$user = require_user_role($pdo, ['field_agent', 'admin']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Biometric Enrollment - NATCODEV</title>
  <style>
    body { font-family: "Segoe UI", Arial, sans-serif; max-width:680px; margin:32px auto; padding:0 16px; color:#172211; }
    .panel { background:#fff; border:1px solid #dfe8d8; border-radius:8px; padding:24px; box-shadow:0 14px 34px rgba(24,43,18,.08); }
    button { background:#14733a; color:#fff; border:0; border-radius:6px; padding:12px 16px; font-weight:800; cursor:pointer; }
    a { color:#14733a; font-weight:800; }
  </style>
</head>
<body>
  <main class="panel">
    <p><a href="index.php">Back to Field Agent</a></p>
    <h1>Biometric Enrollment</h1>

    <?php if ((int) ($user['biometric_enrolled'] ?? 0) === 1): ?>
      <p>Biometric authentication is already enabled for this account.</p>
    <?php else: ?>
      <p>Enroll this device biometric credential for stronger account protection.</p>
      <button type="button" onclick="enrollBiometric()">Enroll Biometric</button>
    <?php endif; ?>
  </main>

  <script>
    async function enrollBiometric() {
      if (!window.PublicKeyCredential || !navigator.credentials) {
        alert('This browser does not support biometric enrollment.');
        return;
      }

      try {
        const credential = await navigator.credentials.create({
          publicKey: {
            challenge: crypto.getRandomValues(new Uint8Array(32)),
            rp: { name: 'NATCODEV' },
            user: {
              id: new TextEncoder().encode('<?= (int) $user['id'] ?>'),
              name: <?= json_encode((string) $user['email']) ?>,
              displayName: <?= json_encode((string) $user['name']) ?>
            },
            pubKeyCredParams: [{ type: 'public-key', alg: -7 }],
            authenticatorSelection: { userVerification: 'required' },
            timeout: 60000
          }
        });

        const response = await fetch('../api/biometric/enroll.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            credential: Array.from(new Uint8Array(credential.response.attestationObject)),
            clientData: Array.from(new Uint8Array(credential.response.clientDataJSON))
          })
        });

        const payload = await response.json();
        if (response.ok && payload.success) {
          alert('Biometric enrollment successful.');
          location.reload();
          return;
        }
        alert(payload.error || 'Biometric enrollment failed.');
      } catch (error) {
        console.error('Biometric enrollment failed:', error);
        alert('Biometric enrollment failed. Please try again.');
      }
    }
  </script>
</body>
</html>
