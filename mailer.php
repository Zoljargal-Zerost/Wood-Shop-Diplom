<?php
// ============================================================
//  sendEmailOTP() — sends a real OTP email via PHPMailer
//
//  INSTALL FIRST (run this in your project folder):
//  composer require phpmailer/phpmailer
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/2-Factor-Verify/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendEmailOTP(string $toEmail, string $otp): array
{
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;

        // Recipients
        $mail->setFrom(MAIL_FROM, MAIL_NAME);
        $mail->addAddress($toEmail);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Your verification code';
        $mail->Body    = emailTemplate($otp);
        $mail->AltBody = "Your verification code is: $otp\nIt expires in 5 minutes. Do not share it.";

        $mail->send();

        return ['success' => true, 'message' => 'Email sent successfully.'];

    } catch (Exception $e) {
        // Log error server-side but don't expose details to user
        error_log('PHPMailer Error: ' . $mail->ErrorInfo);
        return ['success' => false, 'message' => 'Failed to send email. Check server logs.'];
    }
}

// Clean HTML email template
function emailTemplate(string $otp): string
{
    $siteName = MAIL_NAME;
    return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#0a0a0f;font-family:'Helvetica Neue',Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0">
    <tr><td align="center" style="padding:48px 20px;">
      <table width="480" cellpadding="0" cellspacing="0" style="background:#13131a;border-radius:20px;border:1px solid #2a2a3a;overflow:hidden;">
        
        <!-- Header -->
        <tr><td style="background:linear-gradient(135deg,#7c6aff,#ff6a9e);padding:32px;text-align:center;">
          <p style="margin:0;color:#fff;font-size:13px;letter-spacing:0.2em;text-transform:uppercase;font-weight:700;">🔐 {$siteName}</p>
        </td></tr>

        <!-- Body -->
        <tr><td style="padding:40px 40px 20px;">
          <h1 style="margin:0 0 12px;color:#e8e8f0;font-size:26px;font-weight:800;">Verification Code</h1>
          <p style="margin:0 0 32px;color:#6b6b80;font-size:15px;line-height:1.6;">
            Use the code below to complete your sign-in. It expires in <strong style="color:#e8e8f0;">5 minutes</strong>.
          </p>

          <!-- OTP Box -->
          <div style="background:#0a0a0f;border:2px solid #7c6aff;border-radius:16px;padding:28px;text-align:center;margin-bottom:32px;">
            <p style="margin:0 0 8px;color:#6b6b80;font-size:12px;text-transform:uppercase;letter-spacing:0.1em;">Your code</p>
            <p style="margin:0;color:#7c6aff;font-size:48px;font-weight:900;letter-spacing:0.2em;">{$otp}</p>
          </div>

          <p style="margin:0 0 8px;color:#6b6b80;font-size:13px;line-height:1.6;">
            ⚠️ Never share this code with anyone. {$siteName} will never ask for it by phone or email.
          </p>
          <p style="margin:0;color:#6b6b80;font-size:13px;line-height:1.6;">
            If you didn't request this, you can safely ignore this email.
          </p>
        </td></tr>

        <!-- Footer -->
        <tr><td style="padding:20px 40px 36px;border-top:1px solid #2a2a3a;">
          <p style="margin:0;color:#3a3a4a;font-size:12px;text-align:center;">{$siteName} · Sent automatically, do not reply</p>
        </td></tr>

      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
}
