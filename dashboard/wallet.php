<!-- Paystack -->
<button onclick="payWithPaystack(5000)">Fund Wallet with Paystack</button>

<!-- Flutterwave -->
<button onclick="payWithFlutterwave(5000)">Fund Wallet with Flutterwave</button>

<script src="https://js.paystack.co/v1/inline.js"></script>
<script src="https://checkout.flutterwave.com/v3.js"></script>

<script>
// Paystack
function payWithPaystack(amount) {
    const handler = PaystackPop.setup({
        key: 'pk_test_xxx', // Your public key
        email: '<?= $userEmail ?>',
        amount: amount * 100, // in kobo
        metadata: { user_id: <?= $userId ?> },
        callback: function(response) {
            alert('Payment successful! Reference: ' + response.reference);
            location.reload();
        },
        onClose: function() {
            alert('Payment cancelled');
        }
    });
    handler.openIframe();
}

// Flutterwave
function payWithFlutterwave(amount) {
    FlutterwaveCheckout({
        public_key: "FLWPUBK_TEST-xxx",
        tx_ref: "NATCODEV-" + Date.now(),
        amount: amount,
        currency: "NGN",
        payment_options: "card, banktransfer, ussd",
        meta: { user_id: <?= $userId ?> },
        customer: {
            email: "<?= $userEmail ?>",
            name: "<?= $userName ?>"
        },
        callback: function(data) {
            alert('Payment successful! TX: ' + data.transaction_id);
            location.reload();
        },
        onclose: function() {
            alert('Payment cancelled');
        }
    });
}
</script>

<!-- USSD Payment Option -->
<div class="payment-option">
  <h3>📱 USSD Payment (Feature Phones)</h3>
  <p>No smartphone? Pay via USSD!</p>
  <form id="ussdForm">
    <input type="number" name="amount" placeholder="Amount (NGN)" min="100" required>
    <input type="tel" name="phone" placeholder="Phone Number" required>
    <button type="submit">Initiate USSD Payment</button>
  </form>
</div>

<script>
document.getElementById('ussdForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const formData = new FormData(e.target);
  
  const response = await fetch('/api/ussd-payment.php', {
    method: 'POST',
    body: formData
  });
  
  const result = await response.json();
  if (result.success) {
    alert(`USSD initiated! Check your phone for prompt.\nReference: ${result.reference}`);
  } else {
    alert('Failed to initiate USSD payment.');
  }
});
</script>