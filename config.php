<?php
// ============================================================
//  2FA CONFIG — fill in your credentials here
//  Never share this file or push it to GitHub!
// ============================================================

// ------------------------------------------------------------
//  EMAIL (PHPMailer + Gmail)
//  Setup steps:
//  1. Go to myaccount.google.com → Security → 2-Step Verification (enable it)
//  2. Then go to myaccount.google.com/apppasswords
//  3. Create an App Password for "Mail"
//  4. Paste it below (looks like: abcd efgh ijkl mnop)
// ------------------------------------------------------------
define('MAIL_HOST',     'smtp.gmail.com');
define('MAIL_PORT',     587);
define('MAIL_USERNAME', 'auto.selbeg.da@gmail.com');   // ← your Gmail address
define('MAIL_PASSWORD', 'ldri qrwo ioxc mwmh');    // ← Gmail App Password (NOT your real password)
define('MAIL_FROM',     'auto.selbeg.da@gmail.com');   // ← same Gmail
define('MAIL_NAME',     'Wood-Shop');             // ← sender name shown to user

// ------------------------------------------------------------
// Replace the TWILIO lines with these:
define('VONAGE_API_KEY',    'fe40d5a9');          // ← your Vonage API key
define('VONAGE_API_SECRET', 'fOJ4aZrNC6G8QRcM');  // ← your Vonage API secret
define('VONAGE_FROM',       'Wood-Shop');         // ← sender name (max 11 chars, no spaces)

// ------------------------------------------------------------
//  GOOGLE GEMINI AI (Үнэгүй)
//  API key авах: https://aistudio.google.com/apikey
//  1. Google account-аар нэвтэрнэ
//  2. "Create API Key" дарна
//  3. Доорх хоосон хэсэгт paste хийнэ
// ------------------------------------------------------------
define('GEMINI_API_KEY', 'AIzaSyBWr78Sj8fveMXDKwOhe-yk_5ZyAmeOavE');  // ← Энд API key-гээ тавина

// ------------------------------------------------------------
//  OTP Settings
// ------------------------------------------------------------
define('OTP_EXPIRY_SECONDS', 300);   // 5 minutes
define('OTP_MAX_ATTEMPTS',   5);

