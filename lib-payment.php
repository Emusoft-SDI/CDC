<?php
// lib/payments.php
class PaymentGateway {
    private $pdo;
    private $paystackKey;
    private $flutterwaveKey;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->paystackKey = $pdo->query("SELECT value FROM settings WHERE key_name = 'paystack_secret_key'")->fetchColumn();
        $this->flutterwaveKey = $pdo->query("SELECT value FROM settings WHERE key_name = 'flutterwave_secret_key'")->fetchColumn();
    }
    
    // Paystack verification
    public function verifyPaystackPayment($reference) {
        $url = "https://api.paystack.co/transaction/verify/{$reference}";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$this->paystackKey}",
            "Content-Type: application/json"
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($response, true);
    }
    
    // Flutterwave verification
    public function verifyFlutterwavePayment($tx_ref) {
        $url = "https://api.flutterwave.com/v3/transactions/{$tx_ref}/verify";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$this->flutterwaveKey}"
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($response, true);
    }
    
    // Process successful payment
    //
    // Deprecated: this moved money with no provider verification, no
    // idempotency and no transaction wrapper. All wallet credits must go
    // through wallet_credit_once(). Kept only so legacy callers fail
    // loudly instead of double-crediting.
    public function processPayment($userId, $amount, $reference, $description) {
        error_log('PaymentGateway::processPayment is deprecated and refused an unverified credit for user #' . $userId . ' (ref ' . $reference . ').');
        throw new RuntimeException('PaymentGateway::processPayment is deprecated; use wallet_credit_once().');
    }
}
?>