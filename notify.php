<?php
// ============================================================
//  notify.php — Захиалга ирэхэд бүх Worker-т email явуулах
//  process_order.php дотор нэг мөр нэмж дуудна:
//  require_once __DIR__ . '/notify.php';
//  notifyWorkersNewOrder($pdo, $orderId);
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/mailer.php'; // sendEmailOTP() тай ижил PHPMailer setup

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Шинэ захиалга ирэхэд бүх идэвхтэй Worker-т email явуулна
 */
function notifyWorkersNewOrder(PDO $pdo, int $orderId): void
{
    // Захиалгын дэлгэрэнгүй мэдээлэл татах
    $stmt = $pdo->prepare('
        SELECT o.*, u.ner as user_ner, u.phone as user_phone, u.email as user_email
        FROM orders o
        JOIN users u ON o.user_id = u.id
        WHERE o.id = ?
    ');
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) return;

    // Бүх идэвхтэй Worker-ийн email жагсаалт
    $workers = $pdo->query('
        SELECT u.ner, u.email
        FROM users u
        JOIN roles r ON u.role_id = r.id
        WHERE r.slug = "worker"
          AND u.is_active = 1
          AND u.verified = 1
          AND u.email IS NOT NULL
    ')->fetchAll(PDO::FETCH_ASSOC);

    if (empty($workers)) return;

    // Email агуулга
    $subject = "🌲 Шинэ захиалга #{$orderId} — " . MAIL_NAME;
    $body    = workerOrderEmailTemplate($order);
    $alt     = workerOrderPlainText($order);

    // Бүх worker-т явуулах
    foreach ($workers as $worker) {
        sendWorkerEmail($worker['email'], $worker['ner'], $subject, $body, $alt);
    }
}

/**
 * Worker-т захиалгын статус өөрчлөгдсөн үед хэрэглэгчид мэдэгдэл явуулна
 */
function notifyCustomerStatusChange(PDO $pdo, int $orderId, string $newStatus): void
{
    $stmt = $pdo->prepare('
        SELECT o.*, u.ner as user_ner, u.email as user_email
        FROM orders o
        JOIN users u ON o.user_id = u.id
        WHERE o.id = ?
    ');
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$order || !$order['user_email']) return;

    $statusLabels = [
        'confirmed'  => '✅ Баталгаажсан',
        'processing' => '🔨 Бэлтгэж байна',
        'delivering' => '🚚 Хүргэлтэнд гарсан',
        'delivered'  => '📦 Хүргэгдсэн',
        'cancelled'  => '❌ Цуцлагдсан',
    ];
    if (!isset($statusLabels[$newStatus])) return;

    $subject = "📦 Таны захиалга #{$orderId} — {$statusLabels[$newStatus]}";
    $body    = customerStatusEmailTemplate($order, $newStatus, $statusLabels[$newStatus]);
    $alt     = "Таны '{$order['product']}' захиалгын статус өөрчлөгдлөө: {$statusLabels[$newStatus]}";

    sendWorkerEmail($order['user_email'], $order['user_ner'], $subject, $body, $alt);
}

// ── Email явуулах core function ────────────────────────────
function sendWorkerEmail(string $toEmail, string $toName, string $subject, string $body, string $alt): void
{
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(MAIL_FROM, MAIL_NAME);
        $mail->addAddress($toEmail, $toName);
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body    = $body;
        $mail->AltBody = $alt;
        $mail->send();
    } catch (Exception $e) {
        error_log("Worker notify email error to {$toEmail}: " . $e->getMessage());
    }
}

// ── Worker-т явах email template ──────────────────────────
function workerOrderEmailTemplate(array $order): string
{
    $site    = MAIL_NAME;
    $oid     = $order['id'];
    $product = htmlspecialchars($order['product']);
    $uname   = htmlspecialchars($order['user_ner']);
    $uphone  = htmlspecialchars($order['user_phone']);
    $uemail  = htmlspecialchars($order['user_email']);
    $shirheg = $order['shirheg'] ?? '—';
    $urt     = $order['urt_m'] ?? '—';
    $urgun   = $order['urgun_cm'] ?? '—';
    $zuzaan  = $order['zuzaan_cm'] ?? '—';
    $notes   = htmlspecialchars($order['notes'] ?? '');
    $date    = date('Y/m/d H:i', strtotime($order['created_at']));
    $dashUrl = 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/Wood-shop/dashboard/worker.php?tab=orders';

    return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#F9F5EE;font-family:'Helvetica Neue',Arial,sans-serif">
<table width="100%" cellpadding="0" cellspacing="0">
<tr><td align="center" style="padding:40px 20px">
<table width="520" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:16px;border:1px solid #DDD0BC;overflow:hidden;box-shadow:0 4px 24px rgba(92,61,30,.10)">

  <!-- Header -->
  <tr><td style="background:linear-gradient(135deg,#5C3D1E,#C8833A);padding:28px 32px">
    <p style="margin:0;color:#fff;font-size:13px;letter-spacing:.15em;text-transform:uppercase;font-weight:700">🌲 {$site}</p>
    <h1 style="margin:10px 0 0;color:#fff;font-size:22px;font-weight:800">Шинэ захиалга ирлээ!</h1>
  </td></tr>

  <!-- Alert badge -->
  <tr><td style="padding:20px 32px 0">
    <div style="background:#FEF9E7;border:1px solid #ffc107;border-radius:10px;padding:14px 18px;display:flex;align-items:center;gap:12px">
      <span style="font-size:24px">📦</span>
      <div>
        <div style="font-weight:700;color:#856404;font-size:15px">Захиалга #${oid}</div>
        <div style="color:#856404;font-size:13px">{$date}-д орсон</div>
      </div>
    </div>
  </td></tr>

  <!-- Order details -->
  <tr><td style="padding:24px 32px 0">
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#F9F5EE;border-radius:10px;overflow:hidden">
      <tr><td colspan="2" style="padding:14px 16px;font-weight:700;color:#5C3D1E;font-size:14px;border-bottom:1px solid #DDD0BC">📋 Захиалгын мэдээлэл</td></tr>
      <tr>
        <td style="padding:10px 16px;font-size:13px;color:#7A6248;border-bottom:1px solid #DDD0BC;width:140px">Бүтээгдэхүүн</td>
        <td style="padding:10px 16px;font-size:14px;font-weight:700;color:#2A1A0A;border-bottom:1px solid #DDD0BC">{$product}</td>
      </tr>
      <tr>
        <td style="padding:10px 16px;font-size:13px;color:#7A6248;border-bottom:1px solid #DDD0BC">Тоо</td>
        <td style="padding:10px 16px;font-size:14px;font-weight:600;color:#2A1A0A;border-bottom:1px solid #DDD0BC">{$shirheg} ш</td>
      </tr>
      <tr>
        <td style="padding:10px 16px;font-size:13px;color:#7A6248;border-bottom:1px solid #DDD0BC">Хэмжээ</td>
        <td style="padding:10px 16px;font-size:14px;color:#2A1A0A;border-bottom:1px solid #DDD0BC">{$urt}м · {$urgun}×{$zuzaan}см</td>
      </tr>
      <tr>
        <td style="padding:10px 16px;font-size:13px;color:#7A6248">Тайлбар</td>
        <td style="padding:10px 16px;font-size:14px;color:#2A1A0A">{$notes}</td>
      </tr>
    </table>
  </td></tr>

  <!-- Customer info -->
  <tr><td style="padding:20px 32px 0">
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#F2EDE3;border-radius:10px;overflow:hidden">
      <tr><td colspan="2" style="padding:14px 16px;font-weight:700;color:#5C3D1E;font-size:14px;border-bottom:1px solid #DDD0BC">👤 Захиалагч</td></tr>
      <tr>
        <td style="padding:10px 16px;font-size:13px;color:#7A6248;border-bottom:1px solid #DDD0BC;width:140px">Нэр</td>
        <td style="padding:10px 16px;font-size:14px;font-weight:700;color:#2A1A0A;border-bottom:1px solid #DDD0BC">{$uname}</td>
      </tr>
      <tr>
        <td style="padding:10px 16px;font-size:13px;color:#7A6248;border-bottom:1px solid #DDD0BC">Утас</td>
        <td style="padding:10px 16px;font-size:15px;font-weight:800;color:#C8833A;border-bottom:1px solid #DDD0BC">{$uphone}</td>
      </tr>
      <tr>
        <td style="padding:10px 16px;font-size:13px;color:#7A6248">Имэйл</td>
        <td style="padding:10px 16px;font-size:14px;color:#2A1A0A">{$uemail}</td>
      </tr>
    </table>
  </td></tr>

  <!-- CTA Button -->
  <tr><td style="padding:28px 32px;text-align:center">
    <a href="{$dashUrl}"
       style="display:inline-block;background:linear-gradient(135deg,#5C3D1E,#C8833A);color:#fff;text-decoration:none;padding:14px 32px;border-radius:10px;font-weight:700;font-size:15px">
      📊 Dashboard харах →
    </a>
    <p style="margin:16px 0 0;font-size:13px;color:#7A6248">Захиалгыг хариуцан авч, статусыг шинэчилнэ үү.</p>
  </td></tr>

  <!-- Footer -->
  <tr><td style="padding:20px 32px;border-top:1px solid #DDD0BC;text-align:center">
    <p style="margin:0;font-size:12px;color:#7A6248">{$site} · Автомат мэдэгдэл · Хариулах шаардлагагүй</p>
  </td></tr>

</table>
</td></tr>
</table>
</body>
</html>
HTML;
}

// ── Хэрэглэгчид явах статус мэдэгдлийн template ──────────
function customerStatusEmailTemplate(array $order, string $status, string $statusLabel): string
{
    $site    = MAIL_NAME;
    $oid     = $order['id'];
    $product = htmlspecialchars($order['product']);
    $uname   = htmlspecialchars($order['user_ner']);

    $statusColors = [
        'confirmed'  => '#27AE60',
        'processing' => '#2980B9',
        'delivering' => '#8E44AD',
        'delivered'  => '#27AE60',
        'cancelled'  => '#E74C3C',
    ];
    $color = $statusColors[$status] ?? '#5C3D1E';

    $messages = [
        'confirmed'  => 'Таны захиалга баталгаажлаа. Удахгүй бэлтгэж эхэлнэ.',
        'processing' => 'Таны захиалгыг одоо бэлтгэж байна.',
        'delivering' => 'Таны захиалга хүргэлтэнд гарлаа! Удахгүй ирнэ.',
        'delivered'  => 'Таны захиалга хүргэгдлээ. Баярлалаа!',
        'cancelled'  => 'Таны захиалга цуцлагдлаа. Дэлгэрэнгүйг 9446-9149 дугаараас лавлана уу.',
    ];
    $msg = $messages[$status] ?? '';

    return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#F9F5EE;font-family:'Helvetica Neue',Arial,sans-serif">
<table width="100%" cellpadding="0" cellspacing="0">
<tr><td align="center" style="padding:40px 20px">
<table width="480" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:16px;border:1px solid #DDD0BC;overflow:hidden">
  <tr><td style="background:linear-gradient(135deg,#5C3D1E,#C8833A);padding:28px 32px">
    <p style="margin:0;color:#fff;font-size:13px;letter-spacing:.15em;text-transform:uppercase">🌲 {$site}</p>
    <h1 style="margin:8px 0 0;color:#fff;font-size:20px;font-weight:800">Захиалгын мэдэгдэл</h1>
  </td></tr>

  <tr><td style="padding:28px 32px;text-align:center">
    <div style="font-size:48px;margin-bottom:16px">{$statusLabel}</div>
    <h2 style="color:{$color};font-size:18px;margin:0 0 8px">{$statusLabel}</h2>
    <p style="color:#7A6248;font-size:14px;margin:0">{$msg}</p>
  </td></tr>

  <tr><td style="padding:0 32px 28px">
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#F9F5EE;border-radius:10px">
      <tr>
        <td style="padding:12px 16px;font-size:13px;color:#7A6248;border-bottom:1px solid #DDD0BC">Захиалга #</td>
        <td style="padding:12px 16px;font-weight:700;border-bottom:1px solid #DDD0BC">#{$oid}</td>
      </tr>
      <tr>
        <td style="padding:12px 16px;font-size:13px;color:#7A6248">Бүтээгдэхүүн</td>
        <td style="padding:12px 16px;font-weight:700">{$product}</td>
      </tr>
    </table>
  </td></tr>

  <tr><td style="padding:20px 32px;border-top:1px solid #DDD0BC;text-align:center">
    <p style="margin:0;font-size:12px;color:#7A6248">{$site} · Асуух зүйл байвал: 9446-9149</p>
  </td></tr>
</table>
</td></tr>
</table>
</body>
</html>
HTML;
}

// ── Plain text fallback ────────────────────────────────────
function workerOrderPlainText(array $order): string
{
    return "Шинэ захиалга #{$order['id']}\n\n"
         . "Бүтээгдэхүүн: {$order['product']}\n"
         . "Тоо: {$order['shirheg']} ш\n"
         . "Хэмжээ: {$order['urt_m']}м · {$order['urgun_cm']}×{$order['zuzaan_cm']}см\n\n"
         . "Захиалагч: {$order['user_ner']}\n"
         . "Утас: {$order['user_phone']}\n"
         . "Имэйл: {$order['user_email']}\n\n"
         . "Dashboard: /Wood-shop/dashboard/worker.php";
}
