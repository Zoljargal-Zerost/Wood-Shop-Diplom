<?php
// ============================================================
//  sendSmsOTP() — sends OTP via Vonage (works with Mongolia!)
//
//  INSTALL FIRST (run in your project folder):
//  composer require vonage/client
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/2-Factor-Verify/vendor/autoload.php';

function sendSmsOTP(string $phoneNumber, string $otp): array
{
    $apiKey    = VONAGE_API_KEY;
    $apiSecret = VONAGE_API_SECRET;
    $from      = VONAGE_FROM;

    // Phone number must be in E.164 format without the +
    // Vonage uses numbers WITHOUT the + sign
    $to = ltrim($phoneNumber, '+');

    $url  = 'https://rest.nexmo.com/sms/json';
    $data = [
        'api_key'    => $apiKey,
        'api_secret' => $apiSecret,
        'to'         => $to,
        'from'       => $from,
        'text'       => "Your verification code is: $otp\nExpires in 5 minutes. Do not share this code."
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        error_log('Vonage cURL Error: ' . $curlError);
        return ['success' => false, 'message' => 'Network error sending SMS.'];
    }

    $result = json_decode($response, true);
    $msg    = $result['messages'][0] ?? [];

    if (($msg['status'] ?? '99') === '0') {
        return ['success' => true, 'message' => 'SMS sent successfully.'];
    } else {
        $errorText = $msg['error-text'] ?? 'Unknown error';
        error_log('Vonage Error: ' . $errorText);
        // Show real error temporarily so you can debug
        return ['success' => false, 'message' => 'Vonage error: ' . $errorText];
    }
}