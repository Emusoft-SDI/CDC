<?php
// lib/twilio.php - Enhanced with SMS fallback
require_once __DIR__ . '/../vendor/autoload.php';

function sendWhatsAppMessage($to, $message) {
    return sendMessage($to, $message, 'whatsapp');
}

function sendSMSMessage($to, $message) {
    return sendMessage($to, $message, 'sms');
}

function sendMessage($to, $message, $channel = 'whatsapp') {
    // Fetch credentials
    $pdo = new PDO("mysql:host=localhost;dbname=natcodevcom_data;charset=utf8mb4", 
                   "natcodevcom_data", "XC^#3)[;*xTcm&V9");
    
    $sid = $pdo->query("SELECT value FROM settings WHERE key_name = 'twilio_sid'")->fetchColumn();
    $token = $pdo->query("SELECT value FROM settings WHERE key_name = 'twilio_token'")->fetchColumn();
    $whatsappFrom = $pdo->query("SELECT value FROM settings WHERE key_name = 'twilio_whatsapp_number'")->fetchColumn();
    $smsFrom = $pdo->query("SELECT value FROM settings WHERE key_name = 'twilio_sms_number'")->fetchColumn();

    if (!$sid || !$token) return false;

    try {
        $client = new Twilio\Rest\Client($sid, $token);
        
        if ($channel === 'whatsapp') {
            $from = $whatsappFrom;
            $to = "whatsapp:+234" . ltrim($to, '0');
        } else {
            $from = $smsFrom;
            $to = "+234" . ltrim($to, '0');
        }

        $client->messages->create($to, [
            'from' => $from,
            'body' => $message
        ]);
        return true;
    } catch (Exception $e) {
        error_log("Twilio {$channel} Error: " . $e->getMessage());
        return false;
    }
}