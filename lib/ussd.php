<?php
// lib/ussd.php - Enhanced with SMS fallback
class USSDPayment {
    private $pdo;
    private $termiiApiKey;
    private $twilioSid;
    private $twilioToken;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->termiiApiKey = $pdo->query("SELECT value FROM settings WHERE key_name = 'termii_api_key'")->fetchColumn();
        $this->twilioSid = $pdo->query("SELECT value FROM settings WHERE key_name = 'twilio_sid'")->fetchColumn();
        $this->twilioToken = $pdo->query("SELECT value FROM settings WHERE key_name = 'twilio_token'")->fetchColumn();
    }
    
    // Initiate USSD payment with SMS fallback
    public function initiatePayment($userId, $amount, $phoneNumber) {
        $reference = 'USSD_' . time() . '_' . rand(1000, 9999);
        
        // Save pending payment
        $this->pdo->prepare("
            INSERT INTO wallet_transactions (wallet_id, amount, type, description, reference, status)
            SELECT w.id, ?, 'credit', 'USSD payment', ?, 'pending'
            FROM wallets w WHERE w.user_id = ?
        ")->execute([$amount, $reference, $userId]);
        
        // Try USSD first
        $ussdSuccess = $this->sendUSSDPush($phoneNumber, $amount, $reference);
        
        if (!$ussdSuccess) {
            // Fallback to SMS
            $this->sendSMSFallback($phoneNumber, $amount, $reference);
        }
        
        return $reference;
    }
    
    private function sendUSSDPush($phoneNumber, $amount, $reference) {
        try {
            $url = "https://api.ng.termii.com/api/ussd/send";
            $data = json_encode([
                'api_key' => $this->termiiApiKey,
                'phone_number' => $phoneNumber,
                'message_type' => 'NUMERIC',
                'message_title' => 'NATCODEV Payment',
                'message_body' => "Pay ₦" . number_format($amount, 2) . " for NATCODEV wallet funding?",
                'options' => ['Yes', 'No'],
                'callback_url' => 'https://apply.coconutventurehub.ng/ussd-callback.php'
            ]);
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            return $httpCode === 200;
        } catch (Exception $e) {
            error_log("USSD error: " . $e->getMessage());
            return false;
        }
    }
    
    private function sendSMSFallback($phoneNumber, $amount, $reference) {
        $message = "NATCODEV Payment Alert\n\n" .
                  "We couldn't reach you via USSD.\n\n" .
                  "To fund your wallet with ₦" . number_format($amount, 2) . ":\n" .
                  "1. Dial *384*2024# on your phone\n" .
                  "2. Enter reference: {$reference}\n" .
                  "3. Follow the prompts\n\n" .
                  "Support: 0703-COCONUT";
        
        // Use Twilio for SMS
        try {
            $client = new Twilio\Rest\Client($this->twilioSid, $this->twilioToken);
            $client->messages->create(
                "whatsapp:+234" . ltrim($phoneNumber, '0'),
                [
                    'from' => 'whatsapp:+14155238886', // Your Twilio WhatsApp number
                    'body' => $message
                ]
            );
            
            // Also send regular SMS as backup
            $client->messages->create(
                "+234" . ltrim($phoneNumber, '0'),
                [
                    'from' => '+1234567890', // Your Twilio SMS number
                    'body' => $message
                ]
            );
            
        } catch (Exception $e) {
            error_log("SMS fallback error: " . $e->getMessage());
        }
    }
    
    // Handle USSD callback
    public function handleCallback($reference, $status, $amount) {
        if ($status === 'success') {
            $stmt = $this->pdo->prepare("
                SELECT w.user_id 
                FROM wallet_transactions t 
                JOIN wallets w ON t.wallet_id = w.id 
                WHERE t.reference = ?
            ");
            $stmt->execute([$reference]);
            $userId = $stmt->fetchColumn();
            
            if ($userId) {
                $this->pdo->prepare("UPDATE wallet_transactions SET status = 'completed' WHERE reference = ?")->execute([$reference]);
                $this->pdo->prepare("UPDATE wallets SET balance = balance + ? WHERE user_id = ?")->execute([$amount, $userId]);
                return true;
            }
        }
        return false;
    }
}
?>