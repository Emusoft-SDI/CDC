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
    public function processPayment($userId, $amount, $reference, $description) {
        try {
            // Get wallet
            $stmt = $this->pdo->prepare("SELECT id FROM wallets WHERE user_id = ?");
            $stmt->execute([$userId]);
            $walletId = $stmt->fetchColumn();
            
            if (!$walletId) {
                throw new Exception("Wallet not found");
            }
            
            // Add transaction
            $this->pdo->prepare("
                INSERT INTO wallet_transactions (wallet_id, amount, type, description, reference, status)
                VALUES (?, ?, 'credit', ?, ?, 'completed')
            ")->execute([$walletId, $amount, $description, $reference]);
            
            // Update balance
            $this->pdo->prepare("UPDATE wallets SET balance = balance + ? WHERE user_id = ?")
                     ->execute([$amount, $userId]);
            
            return true;
        } catch (Exception $e) {
            error_log("Payment processing error: " . $e->getMessage());
            return false;
        }
    }
}
?>