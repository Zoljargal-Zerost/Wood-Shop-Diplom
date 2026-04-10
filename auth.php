<?php
// ============================================================
//  auth.php — Нэвтрэх / Бүртгүүлэх / OTP баталгаажуулах
//  Таны өмнөх auth.php-д эдгээр хэсгийг нэмсэн.
//  Хэрэв танд өөр auth.php байвал доорх case-үүдийг нэмнэ үү.
// ============================================================

session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/mailer.php';   // sendEmailOTP()
require_once __DIR__ . '/sms.php';      // sendSmsOTP()

// DB холболт — өөрийнхөөрөө солино уу
// require_once __DIR__ . '/db.php';
// Тест DB: PDO жишээ
try {
    $pdo = new PDO('mysql:host=localhost;dbname=modni_zah;charset=utf8', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    die('DB холболтын алдаа: ' . $e->getMessage());
}

$action   = $_GET['action'] ?? $_POST['action'] ?? '';
$redirect = $_POST['redirect'] ?? '/';

// ============================================================
switch ($action) {

// ── НЭВТРЭХ ──────────────────────────────────────────────
case 'login':
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? AND verified = 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        // Toast: амжилттай нэвтэрсэн
        $_SESSION['toast'] = ['msg' => 'Тавтай морил, ' . $user['ner'] . '! 👋', 'type' => 'success', 'icon' => '✅'];
        $_SESSION['user'] = [
            'id'    => $user['id'],
            'ner'   => $user['ner'],
            'email' => $user['email'],
            'phone' => $user['phone'],
        ];
        header('Location: ' . $redirect);
    } else {
        $_SESSION['toast'] = ['msg' => 'Имэйл эсвэл нууц үг буруу байна.', 'type' => 'error', 'icon' => '❌'];
    $_SESSION['auth_error'] = 'Имэйл эсвэл нууц үг буруу байна.';
        header('Location: ' . $redirect . '#login-modal');
    }
    exit;

// ── БҮРТГҮҮЛЭХ (OTP илгээх) ───────────────────────────────
case 'register':
    $ner      = trim($_POST['ner'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $method   = $_POST['otp_method'] ?? 'email'; // 'email' эсвэл 'sms'

    // Validation
    if (!$ner || !$email || !$phone || !$password) {
        $_SESSION['toast'] = ['msg' => 'Бүх талбарыг бөглөнө үү.', 'type' => 'error', 'icon' => '❌'];
    $_SESSION['auth_error'] = 'Бүх талбарыг бөглөнө үү.';
        header('Location: ' . $redirect . '#register-modal');
        exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['toast'] = ['msg' => 'Имэйл хаяг буруу байна.', 'type' => 'error', 'icon' => '❌'];
    $_SESSION['auth_error'] = 'Имэйл хаяг буруу байна.';
        header('Location: ' . $redirect . '#register-modal');
        exit;
    }
    
$password        = $_POST['password']         ?? '';
$password_confirm = $_POST['password_confirm'] ?? '';

// Урт шалгах
if (strlen($password) < 8) {
    $_SESSION['toast'] = ['msg' => 'Нууц үг 8-аас дээш тэмдэгт байх ёстой.', 'type' => 'error', 'icon' => '❌'];
    $_SESSION['auth_error'] = 'Нууц үг 8-аас дээш тэмдэгт байх ёстой.';
    header('Location: ' . $redirect . '#register-modal');
    exit;
}

// Том үсэг шалгах
if (!preg_match('/[A-Z]/', $password)) {
    $_SESSION['toast'] = ['msg' => 'Нууц үг том үсэг агуулсан байх ёстой.', 'type' => 'error', 'icon' => '❌'];
    $_SESSION['auth_error'] = 'Нууц үг том үсэг агуулсан байх ёстой.';
    header('Location: ' . $redirect . '#register-modal');
    exit;
}

// Тоо шалгах
if (!preg_match('/[0-9]/', $password)) {
    $_SESSION['toast'] = ['msg' => 'Нууц үг тоо агуулсан байх ёстой.', 'type' => 'error', 'icon' => '❌'];
    $_SESSION['auth_error'] = 'Нууц үг тоо агуулсан байх ёстой.';
    header('Location: ' . $redirect . '#register-modal');
    exit;
}

// Давтсантай таарч байгаа эсэх
if ($password !== $password_confirm) {
    $_SESSION['toast'] = ['msg' => 'Нууц үг таарахгүй байна.', 'type' => 'error', 'icon' => '❌'];
    $_SESSION['auth_error'] = 'Нууц үг таарахгүй байна.';
    header('Location: ' . $redirect . '#register-modal');
    exit;
}

    // Имэйл давхардсан эсэх шалгах
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $_SESSION['toast'] = ['msg' => 'Энэ имэйл аль хэдийн бүртгэлтэй байна.', 'type' => 'error', 'icon' => '❌'];
    $_SESSION['auth_error'] = 'Энэ имэйл аль хэдийн бүртгэлтэй байна.';
        header('Location: ' . $redirect . '#register-modal');
        exit;
    }

    // Утасны дугаарыг E.164 формат руу хөрвүүлэх
    // Жишээ: 99112233 → +97699112233
    $phone = preg_replace('/\D/', '', $phone); // зөвхөн тоо
    if (strlen($phone) === 8) {
        $phone = '+976' . $phone; // Монгол дугаар
    } elseif (substr($phone, 0, 1) !== '+') {
        $phone = '+' . $phone;
    }

    // OTP үүсгэх
    $otp    = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expiry = time() + OTP_EXPIRY_SECONDS;

    // Session-д хадгалах (DB-д хадгалах ч болно)
    $_SESSION['otp']              = $otp;
    $_SESSION['otp_expiry']       = $expiry;
    $_SESSION['otp_method']       = $method;
    $_SESSION['otp_target']       = ($method === 'sms') ? $phone : $email;
    $_SESSION['otp_attempts']     = 0;
    $_SESSION['otp_pending']      = true;
    $_SESSION['pending_register'] = [
        'ner'      => $ner,
        'email'    => $email,
        'phone'    => $phone,
        'password' => password_hash($password, PASSWORD_DEFAULT),
    ];

    // Код илгээх
    if ($method === 'sms') {
        $result = sendSmsOTP($phone, $otp);
    } else {
        $result = sendEmailOTP($email, $otp);
    }

    if (!$result['success']) {
        unset($_SESSION['otp'], $_SESSION['otp_expiry'], $_SESSION['otp_pending'], $_SESSION['pending_register']);
        $_SESSION['toast'] = ['msg' => 'Код илгээхэд алдаа гарлаа. Дахин оролдоно уу.', 'type' => 'error', 'icon' => '❌'];
    $_SESSION['auth_error'] = 'Код илгээхэд алдаа гарлаа. Дахин оролдоно уу.';
        header('Location: ' . $redirect . '#register-modal');
        exit;
    }

    // OTP modal нээхийн тулд буцаах
    header('Location: ' . $redirect . '#otp-modal');
    exit;

// ── OTP БАТАЛГААЖУУЛАХ ────────────────────────────────────
case 'verify_otp':
    $entered = trim($_POST['otp'] ?? '');

    if (!isset($_SESSION['otp'], $_SESSION['otp_expiry'], $_SESSION['pending_register'])) {
        $_SESSION['toast'] = ['msg' => 'Цаг дууссан. Дахин бүртгүүлнэ үү.', 'type' => 'error', 'icon' => '❌'];
    $_SESSION['otp_error'] = 'Цаг дууссан. Дахин бүртгүүлнэ үү.';
        header('Location: /Wood-shop/');
        exit;
    }

    if (time() > $_SESSION['otp_expiry']) {
        unset($_SESSION['otp'], $_SESSION['otp_expiry'], $_SESSION['otp_pending']);
        $_SESSION['toast'] = ['msg' => 'Кодны хугацаа дууссан. Дахин илгээнэ үү.', 'type' => 'error', 'icon' => '❌'];
    $_SESSION['otp_error'] = 'Кодны хугацаа дууссан. Дахин илгээнэ үү.';
        header('Location: /Wood-shop/');
        exit;
    }

    $_SESSION['otp_attempts'] = ($_SESSION['otp_attempts'] ?? 0) + 1;
    if ($_SESSION['otp_attempts'] > OTP_MAX_ATTEMPTS) {
        unset($_SESSION['otp'], $_SESSION['otp_expiry'], $_SESSION['otp_pending'], $_SESSION['pending_register']);
        $_SESSION['toast'] = ['msg' => 'Хэт олон удаа буруу оруулсан. Дахин бүртгүүлнэ үү.', 'type' => 'error', 'icon' => '❌'];
    $_SESSION['auth_error'] = 'Хэт олон удаа буруу оруулсан. Дахин бүртгүүлнэ үү.';
        header('Location: /Wood-shop/');
        exit;
    }

    if (!hash_equals($_SESSION['otp'], $entered)) {
        $left = OTP_MAX_ATTEMPTS - $_SESSION['otp_attempts'];
        $_SESSION['otp_error'] = "Код буруу байна. $left оролдлого үлдлээ.";
        header('Location: /Wood-shop/#otp-modal');
        exit;
    }

    // ✅ Код зөв — хэрэглэгч үүсгэх
    $reg = $_SESSION['pending_register'];
    $stmt = $pdo->prepare(
        'INSERT INTO users (ner, email, phone, password, verified, created_at)
         VALUES (?, ?, ?, ?, 1, NOW())'
    );
    $stmt->execute([$reg['ner'], $reg['email'], $reg['phone'], $reg['password']]);
    $newId = $pdo->lastInsertId();

    // Toast: бүртгэл амжилттай
    $_SESSION['toast'] = ['msg' => 'Тавтай морил, ' . $reg['ner'] . '! Бүртгэл амжилттай. 🎉', 'type' => 'success', 'icon' => '🎉'];

    // Session цэвэрлэх
    unset($_SESSION['otp'], $_SESSION['otp_expiry'], $_SESSION['otp_attempts'],
          $_SESSION['otp_pending'], $_SESSION['pending_register'],
          $_SESSION['otp_method'], $_SESSION['otp_target']);

    // Нэвтэрсэн болгох
        $_SESSION['user'] = [
        'id'    => $newId,
        'ner'   => $reg['ner'],
        'email' => $reg['email'],
        'phone' => $reg['phone'],
    ];

    header('Location: /Wood-shop/');
    exit;

// ── OTP ДАХИН ИЛГЭЭХ ─────────────────────────────────────
case 'resend_otp':
    if (!isset($_SESSION['pending_register'])) {
        header('Location: /Wood-shop/');
        exit;
    }

    $otp    = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expiry = time() + OTP_EXPIRY_SECONDS;

    $_SESSION['otp']          = $otp;
    $_SESSION['otp_expiry']   = $expiry;
    $_SESSION['otp_attempts'] = 0;

    $method = $_SESSION['otp_method'] ?? 'email';
    $target = $_SESSION['otp_target'] ?? '';

    if ($method === 'sms') {
        sendSmsOTP($target, $otp);
    } else {
        sendEmailOTP($target, $otp);
    }

    header('Location: /Wood-shop/#otp-modal');
    exit;

// ── ГАРАХ ────────────────────────────────────────────────
case 'logout':
    session_destroy();
    session_start();
    $_SESSION['toast'] = ['msg' => 'Та амжилттай гарлаа. Баяртай! 👋', 'type' => 'info', 'icon' => '👋'];
    header('Location: /Wood-shop/');
    exit;

default:
    header('Location: /Wood-shop/');
    exit;
}