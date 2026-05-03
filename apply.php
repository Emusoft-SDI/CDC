<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>NATCODEV Outgrowers Application</title>
  <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
  <style>
    body {
      font-family: Arial, sans-serif;
      background: #f9f9f9;
      padding: 20px;
    }
    .form-container {
      max-width: 600px;
      margin: 0 auto;
      background: white;
      padding: 25px;
      border-radius: 10px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    h2 {
      text-align: center;
      color: #2c7a2c;
    }
    .form-group {
      margin-bottom: 15px;
    }
    label {
      display: block;
      margin-bottom: 5px;
      font-weight: bold;
    }
    input, select {
      width: 100%;
      padding: 10px;
      border: 1px solid #ccc;
      border-radius: 5px;
      box-sizing: border-box;
    }
    .checkbox-group {
      margin: 15px 0;
    }
    .checkbox-group label {
      font-weight: normal;
      display: flex;
      align-items: center;
      gap: 8px;
      cursor: pointer;
    }
    button {
      width: 100%;
      padding: 12px;
      background: #2c7a2c;
      color: white;
      border: none;
      border-radius: 5px;
      font-size: 16px;
      cursor: pointer;
    }
    button:disabled {
      background: #ccc;
      cursor: not-allowed;
    }
  </style>
</head>
<body>
  <div class="form-container">
    <h2>Join the NATCODEV Outgrowers Program</h2>
    <form id="applicationForm">
      <div class="form-group">
        <label for="name">Full Name *</label>
        <input type="text" id="name" name="name" required />
      </div>

      <div class="form-group">
        <label for="location">Location (State, LGA) *</label>
        <input type="text" id="location" name="location" required />
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
    </form>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const form = document.getElementById('applicationForm');
      if (!form) return;

      form.addEventListener('submit', async function (e) {
        e.preventDefault();

        // Get commitments as a string
        const commitments = [];
        if (document.getElementById('option1')?.checked) commitments.push('Founding Growers Circle');
        if (document.getElementById('option2')?.checked) commitments.push('Farm Assessment');
        if (document.getElementById('option3')?.checked) commitments.push('Next Enrollment Session');

        if (commitments.length === 0) {
          alert('Please select at least one commitment option.');
          return;
        }

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