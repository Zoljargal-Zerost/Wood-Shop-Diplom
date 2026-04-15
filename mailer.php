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
<body style="margin:0;padding:0;background:#F2EDE3;font-family:'Helvetica Neue',Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0">
    <tr><td align="center" style="padding:40px 20px;">
      <table width="480" cellpadding="0" cellspacing="0" style="background:#FFFFFF;border-radius:18px;border:1px solid #DDD0BC;overflow:hidden;box-shadow:0 8px 32px rgba(92,61,30,0.12);">

        <!-- Header -->
        <tr><td style="background:linear-gradient(135deg,#5C3D1E,#C8833A);padding:30px 32px;text-align:center;">
          <p style="margin:0 0 6px;color:rgba(255,255,255,0.75);font-size:12px;letter-spacing:0.2em;text-transform:uppercase;">🌲 {$siteName}</p>
          <h1 style="margin:0;color:#ffffff;font-size:22px;font-weight:800;letter-spacing:0.02em;">Баталгаажуулах код</h1>
        </td></tr>

        <!-- Body -->
        <tr><td style="padding:36px 40px 24px;">
          <p style="margin:0 0 28px;color:#7A6248;font-size:15px;line-height:1.7;">
            Бүртгэлийг дуусгахын тулд доорх кодыг оруулна уу.
            Код <strong style="color:#5C3D1E;">5 минутын</strong> дотор дуусна.
          </p>

          <!-- OTP Box -->
          <div style="background:#F9F5EE;border:2px solid #C8833A;border-radius:14px;padding:28px 20px;text-align:center;margin-bottom:28px;">
            <p style="margin:0 0 10px;color:#7A6248;font-size:11px;text-transform:uppercase;letter-spacing:0.15em;font-weight:600;">Таны код</p>
            <p style="margin:0;color:#5C3D1E;font-size:52px;font-weight:900;letter-spacing:0.25em;line-height:1;">{$otp}</p>
          </div>

          <!-- Warning box -->
          <table width="100%" cellpadding="0" cellspacing="0" style="background:#FFF8EE;border:1px solid #F0D5B0;border-radius:10px;margin-bottom:20px;">
            <tr><td style="padding:14px 16px;">
              <p style="margin:0;color:#7A6248;font-size:13px;line-height:1.6;">
                ⚠️ Энэ кодыг хэнд ч хэлж болохгүй. {$siteName} утас болон имэйлээр код асуухгүй.
              </p>
            </td></tr>
          </table>

          <p style="margin:0;color:#7A6248;font-size:13px;line-height:1.6;">
            Хэрэв та бүртгүүлж байгаагүй бол энэ имэйлийг үл тоомсорлоно уу.
          </p>
        </td></tr>

        <!-- Footer -->
        <tr><td style="padding:18px 40px 28px;border-top:1px solid #DDD0BC;text-align:center;">
          <p style="margin:0;color:#B0A090;font-size:12px;">{$siteName} · Автомат мэдэгдэл · Хариулах шаардлагагүй</p>
        </td></tr>

      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
}