<?php
// lib/ussd.php - Enhanced with SMS fallback
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/wallet.php';
require_once __DIR__ . '/layout/misc.php';

$autoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}

class USSDPayment {
    private $pdo;
    private $termiiApiKey;
    private $twilioSid;
    private $twilioToken;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        if (app_table_exists($pdo, 'settings')) {
            $this->termiiApiKey = $pdo->query("SELECT value FROM settings WHERE key_name = 'termii_api_key'")->fetchColumn();
            $this->twilioSid = $pdo->query("SELECT value FROM settings WHERE key_name = 'twilio_sid'")->fetchColumn();
            $this->twilioToken = $pdo->query("SELECT value FROM settings WHERE key_name = 'twilio_token'")->fetchColumn();
        }
    }
    
    // Verify the HMAC-SHA256 signature on a USSD callback payload
    public function verifyCallbackSignature(string $raw, string $signature): bool
    {
        $secret = '';
        if (function_exists('app_secret')) {
            try {
                $secret = (string) app_secret($this->pdo, 'ussd_callback_secret', 'USSD_CALLBACK_SECRET');
            } catch (Throwable $e) {
                $secret = '';
            }
        }
        if ($secret === '') {
            $secret = (string) app_env('USSD_CALLBACK_SECRET', '');
        }
        if ($secret === '') {
            error_log('USSD callback rejected: USSD_CALLBACK_SECRET is not configured.');
            return false;
        }
        return hash_equals(hash_hmac('sha256', $raw, $secret), $signature);
    }

    // Initiate USSD payment with SMS fallback
    public function initiatePayment($userId, $amount, $phoneNumber) {
        $reference = 'USSD_' . time() . '_' . rand(1000, 9999);

        // Save pending payment
        $this->pdo->prepare("
            INSERT INTO wallet_transactions
                (wallet_id, user_id, amount, type, direction, description, reference, provider, provider_reference, status)
            SELECT w.id, ?, ?, 'credit', 'inflow', 'USSD payment', ?, 'ussd', ?, 'pending'
            FROM wallets w WHERE w.user_id = ?
        ")->execute([$userId, $amount, $reference, $reference, $userId]);
        
        // Try USSD first
        $ussdSuccess = $this->sendUSSDPush($phoneNumber, $amount, $reference);
        
        if (!$ussdSuccess) {
            // Fallback to SMS
            $this->sendSMSFallback($phoneNumber, $amount, $reference);
        }
        
        return $reference;
    }
    
    private function sendUSSDPush($phoneNumber, $amount, $reference) {
        if (!$this->termiiApiKey || !function_exists('curl_init')) {
            return false;
        }

        try {
            $url = "https://api.ng.termii.com/api/ussd/send";
            $data = json_encode([
                'api_key' => $this->termiiApiKey,
                'phone_number' => $phoneNumber,
                'message_type' => 'NUMERIC',
                'message_title' => 'NATCODEV Payment',
                'message_body' => "Pay ₦" . number_format($amount, 2) . " for NATCODEV wallet funding?",
                'options' => ['Yes', 'No'],
                'callback_url' => 'https://natcodev.com.ng/ussd-callback.php'
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
        if (!$this->twilioSid || !$this->twilioToken || !class_exists('Twilio\\Rest\\Client')) {
            return false;
        }

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
        if ($status !== 'success') {
            return ['success' => true, 'ignored' => true, 'status' => $status];
        }
        if (!preg_match('/^USSD_\d+_\w{4}$/', (string) $reference)) {
            error_log('USSD callback rejected: malformed reference ' . $reference);
            return ['success' => false, 'error' => 'Malformed reference'];
        }
        $stmt = $this->pdo->prepare("
            SELECT t.amount AS expected_amount, w.user_id
            FROM wallet_transactions t
            JOIN wallets w ON t.wallet_id = w.id
            WHERE t.reference = ? AND t.status = 'pending'
            LIMIT 1
        ");
        $stmt->execute([$reference]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            // Unknown or already-processed reference: idempotent no-op.
            return ['success' => true, 'duplicate' => true];
        }
        $userId = (int) $row['user_id'];
        $expected = (float) $row['expected_amount'];
        $paid = (float) $amount;
        if ($expected <= 0 || $paid + 0.01 < $expected) {
            error_log('USSD callback amount mismatch for ' . $reference . ': paid=' . $paid . ', expected=' . $expected);
            return ['success' => false, 'error' => 'Amount mismatch'];
        }
        try {
            $credit = wallet_credit_once($this->pdo, $userId, $expected, $reference, 'Wallet funding via USSD', 'ussd', $reference, ['source' => 'ussd_callback']);
            return ['success' => true, 'credit' => $credit];
        } catch (Throwable $e) {
            error_log('USSD callback credit failed: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Credit failed'];
        }
    }
}
?>
