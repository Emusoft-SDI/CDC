<!-- field-agent/biometric.php -->
<!DOCTYPE html>
<html>
<head>
  <title>Biometric Enrollment - NATCODEV</title>
  <script src="https://cdn.jsdelivr.net/npm/@fingerprintjs/fingerprintjs@3.4.0/dist/fp.min.js"></script>
</head>
<body>
  <h2>Biometric Enrollment</h2>
  
  <?php if ($user['biometric_enrolled']): ?>
    <p>✅ Biometric authentication is already enabled.</p>
    <button onclick="disableBiometric()">Disable Biometric Login</button>
  <?php else: ?>
    <p>Enroll your fingerprint for secure login.</p>
    <button onclick="enrollBiometric()">Enroll Fingerprint</button>
  <?php endif; ?>

  <div id="fingerprintScanner" style="display:none;">
    <p>Place your finger on the scanner...</p>
    <canvas id="fingerprintCanvas" width="200" height="200"></canvas>
  </div>

  <script>
    // WebAuthn API for biometric authentication
    async function enrollBiometric() {
      try {
        const credential = await navigator.credentials.create({
          publicKey: {
            challenge: new Uint8Array(32),
            rp: { name: "NATCODEV", id: "apply.coconutventurehub.ng" },
            user: {
              id: new TextEncoder().encode("<?= $_SESSION['user_id'] ?>"),
              name: "<?= $user['email'] ?>",
              displayName: "<?= $user['name'] ?>"
            },
            pubKeyCredParams: [{ type: "public-key", alg: -7 }],
            authenticatorSelection: {
              authenticatorAttachment: "platform", // Use built-in biometrics
              userVerification: "required"
            },
            timeout: 60000
          }
        });
        
        // Send to server
        const response = await fetch('/api/biometric/enroll.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            credential: Array.from(new Uint8Array(credential.response.attestationObject)),
            clientData: Array.from(new Uint8Array(credential.response.clientDataJSON))
          })
        });
        
        if (response.ok) {
          alert('Biometric enrollment successful!');
          location.reload();
        }
      } catch (error) {
        console.error('Biometric enrollment failed:', error);
        alert('Biometric enrollment failed. Please try again.');
      }
    }
    
    async function verifyBiometric() {
      try {
        const assertion = await navigator.credentials.get({
          publicKey: {
            challenge: new Uint8Array(32),
            timeout: 60000,
            userVerification: "required"
          }
        });
        
        // Verify on server
        const response = await fetch('/api/biometric/verify.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            credentialId: Array.from(new Uint8Array(assertion.rawId)),
            authenticatorData: Array.from(new Uint8Array(assertion.response.authenticatorData)),
            signature: Array.from(new Uint8Array(assertion.response.signature)),
            clientData: Array.from(new Uint8Array(assertion.response.clientDataJSON))
          })
        });
        
        if (response.ok) {
          // Login successful
          window.location.href = '/field-agent/';
        }
      } catch (error) {
        console.error('Biometric verification failed:', error);
        alert('Biometric verification failed.');
      }
    }
  </script>
</body>
</html>