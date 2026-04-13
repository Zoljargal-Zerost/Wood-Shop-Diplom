
// ── НУУЦ ҮГ МАРТСАН — OTP илгээх ──────────────────────────
case 'forgot_password':
    $identifier = trim($_POST['identifier'] ?? ''); // имэйл эсвэл утас
    $method     = $_POST['otp_method'] ?? 'email';

    if (!$identifier) {
        $_SESSION['toast'] = ['msg' => 'Имэйл эсвэл утасны дугаараа оруулна уу.', 'type' => 'error', 'icon' => '❌'];
        header('Location: /Wood-shop/#forgot-modal');
        exit;
    }

    // Имэйл эсвэл утасаар хэрэглэгч хайх
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? OR phone = ? AND verified = 1');
    $stmt->execute([$identifier, $identifier]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $_SESSION['toast'] = ['msg' => 'Тийм имэйл эсвэл утастай бүртгэл олдсонгүй.', 'type' => 'error', 'icon' => '❌'];
        header('Location: /Wood-shop/#forgot-modal');
        exit;
    }

    // OTP үүсгэх
    $otp    = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expiry = time() + OTP_EXPIRY_SECONDS;

    $_SESSION['reset_otp']        = $otp;
    $_SESSION['reset_otp_expiry'] = $expiry;
    $_SESSION['reset_otp_attempts'] = 0;
    $_SESSION['reset_user_id']    = $user['id'];
    $_SESSION['reset_method']     = $method;
    $_SESSION['reset_target']     = ($method === 'sms') ? $user['phone'] : $user['email'];

    // Код илгээх
    if ($method === 'sms') {
        $result = sendSmsOTP($user['phone'], $otp);
    } else {
        $result = sendEmailOTP($user['email'], $otp);
    }

    if (!$result['success']) {
        unset($_SESSION['reset_otp'], $_SESSION['reset_otp_expiry'], $_SESSION['reset_user_id']);
        $_SESSION['toast'] = ['msg' => 'Код илгээхэд алдаа гарлаа. Дахин оролдоно уу.', 'type' => 'error', 'icon' => '❌'];
        header('Location: /Wood-shop/#forgot-modal');
        exit;
    }

    $_SESSION['toast'] = ['msg' => 'Баталгаажуулах код илгээлээ. Шалгана уу.', 'type' => 'info', 'icon' => 'ℹ️'];
    header('Location: /Wood-shop/#reset-modal');
    exit;

// ── НУУЦ ҮГ ШИНЭЧЛЭХ — OTP шалгаад хадгалах ─────────────
case 'reset_password':
    $entered  = trim($_POST['otp'] ?? '');
    $new_pass = $_POST['new_password'] ?? '';
    $confirm  = $_POST['new_password_confirm'] ?? '';

    // Session шалгах
    if (!isset($_SESSION['reset_otp'], $_SESSION['reset_otp_expiry'], $_SESSION['reset_user_id'])) {
        $_SESSION['toast'] = ['msg' => 'Цаг дууссан. Дахин оролдоно уу.', 'type' => 'error', 'icon' => '❌'];
        header('Location: /Wood-shop/#forgot-modal');
        exit;
    }

    // Хугацаа шалгах
    if (time() > $_SESSION['reset_otp_expiry']) {
        unset($_SESSION['reset_otp'], $_SESSION['reset_otp_expiry'], $_SESSION['reset_user_id']);
        $_SESSION['toast'] = ['msg' => 'Кодны хугацаа дууссан. Дахин илгээнэ үү.', 'type' => 'error', 'icon' => '❌'];
        header('Location: /Wood-shop/#forgot-modal');
        exit;
    }

    // Оролдлого шалгах
    $_SESSION['reset_otp_attempts'] = ($_SESSION['reset_otp_attempts'] ?? 0) + 1;
    if ($_SESSION['reset_otp_attempts'] > OTP_MAX_ATTEMPTS) {
        unset($_SESSION['reset_otp'], $_SESSION['reset_otp_expiry'], $_SESSION['reset_user_id']);
        $_SESSION['toast'] = ['msg' => 'Хэт олон удаа буруу оруулсан. Дахин оролдоно уу.', 'type' => 'error', 'icon' => '❌'];
        header('Location: /Wood-shop/#forgot-modal');
        exit;
    }

    // OTP шалгах
    if (!hash_equals($_SESSION['reset_otp'], $entered)) {
        $left = OTP_MAX_ATTEMPTS - $_SESSION['reset_otp_attempts'];
        $_SESSION['toast'] = ['msg' => "Код буруу байна. $left оролдлого үлдлээ.", 'type' => 'error', 'icon' => '❌'];
        header('Location: /Wood-shop/#reset-modal');
        exit;
    }

    // Нууц үг validation
    if (strlen($new_pass) < 8) {
        $_SESSION['toast'] = ['msg' => 'Нууц үг 8-аас дээш тэмдэгт байх ёстой.', 'type' => 'error', 'icon' => '❌'];
        header('Location: /Wood-shop/#reset-modal');
        exit;
    }
    if (!preg_match('/[A-Z]/', $new_pass)) {
        $_SESSION['toast'] = ['msg' => 'Нууц үг том үсэг агуулсан байх ёстой.', 'type' => 'error', 'icon' => '❌'];
        header('Location: /Wood-shop/#reset-modal');
        exit;
    }
    if (!preg_match('/[0-9]/', $new_pass)) {
        $_SESSION['toast'] = ['msg' => 'Нууц үг тоо агуулсан байх ёстой.', 'type' => 'error', 'icon' => '❌'];
        header('Location: /Wood-shop/#reset-modal');
        exit;
    }
    if ($new_pass !== $confirm) {
        $_SESSION['toast'] = ['msg' => 'Нууц үг таарахгүй байна.', 'type' => 'error', 'icon' => '❌'];
        header('Location: /Wood-shop/#reset-modal');
        exit;
    }

    // ✅ Нууц үг шинэчлэх
    $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
    $stmt->execute([$hashed, $_SESSION['reset_user_id']]);

    // Session цэвэрлэх
    unset($_SESSION['reset_otp'], $_SESSION['reset_otp_expiry'], $_SESSION['reset_otp_attempts'],
          $_SESSION['reset_user_id'], $_SESSION['reset_method'], $_SESSION['reset_target']);

    $_SESSION['toast'] = ['msg' => 'Нууц үг амжилттай солигдлоо! Нэвтэрч орно уу.', 'type' => 'success', 'icon' => '✅'];
    header('Location: /Wood-shop/#login-modal');
    exit;
