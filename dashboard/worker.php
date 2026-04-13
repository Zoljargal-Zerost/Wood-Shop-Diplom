<?php
require_once __DIR__ . '/../middleware.php';
requireLogin();
$role = loadUserRole($pdo);
require_once __DIR__ . '/../notify.php';

if (!isRole('worker')) {
    header('Location: /Wood-shop/dashboard/');
    exit;
}

$uid = $_SESSION['user']['id'];
$tab = $_GET['tab'] ?? 'dashboard';

// ── POST үйлдлүүд ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';

    // Захиалгын статус солих
    if ($act === 'update_status' && can('update_order_status')) {
        $oid    = (int)$_POST['order_id'];
        $status = $_POST['status'];
        $pdo->prepare('UPDATE orders SET status=? WHERE id=? AND worker_id=?')
            ->execute([$status, $oid, $uid]);
        logWorkerAction($pdo, "Захиалга #{$oid} статус өөрчлөв: {$status}", $oid);
        // Хэрэглэгчид email мэдэгдэл явуулах
        notifyCustomerStatusChange($pdo, $oid, $status);
        $_SESSION['toast'] = ['msg'=>'Статус шинэчлэгдлээ.','type'=>'success','icon'=>'✅'];
        header('Location: worker.php?tab=orders');
        exit;
    }

    // Хэрэглэгч бүртгэх (offline/walk-in)
    if ($act === 'register_customer' && can('register_customer')) {
        $ner     = trim($_POST['ner']);
        $email   = trim($_POST['email']);
        $phone   = trim($_POST['phone']);
        $notes   = trim($_POST['notes'] ?? '');
        $product = trim($_POST['product'] ?? '');
        $shirheg = (int)($_POST['shirheg'] ?? 0);
        $urt     = (float)($_POST['urt_m'] ?? 0);
        $urgun   = (float)($_POST['urgun_cm'] ?? 0);
        $zuzaan  = (float)($_POST['zuzaan_cm'] ?? 0);

        // Phone format
        $phone = preg_replace('/\D/', '', $phone);
        if (strlen($phone) === 8) $phone = '+976'.$phone;
        elseif (substr($phone,0,1) !== '+') $phone = '+'.$phone;

        // Хэрэглэгч байгаа эсэх шалгах
        $exists = $pdo->prepare('SELECT id FROM users WHERE email=?');
        $exists->execute([$email]);
        $existUser = $exists->fetch();

        if ($existUser) {
            $cid = $existUser['id'];
        } else {
            // Шинэ хэрэглэгч — түр нууц үг
            $tmpPwd = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
            $userRoleId = $pdo->query('SELECT id FROM roles WHERE slug="user"')->fetchColumn();
            $pdo->prepare('INSERT INTO users (ner,email,phone,password,role_id,verified) VALUES (?,?,?,?,?,1)')
                ->execute([$ner,$email,$phone,$tmpPwd,$userRoleId]);
            $cid = $pdo->lastInsertId();
        }

        // Захиалга үүсгэх
        if ($product) {
            $pdo->prepare('INSERT INTO orders (user_id,worker_id,product,shirheg,urt_m,urgun_cm,zuzaan_cm,notes,status)
                           VALUES (?,?,?,?,?,?,?,?,?)')
                ->execute([$cid,$uid,$product,$shirheg,$urt,$urgun,$zuzaan,$notes,'confirmed']);
            logWorkerAction($pdo, "Хэрэглэгч '{$ner}' бүртгэж, '{$product}' захиалга үүсгэв", $pdo->lastInsertId());
        } else {
            logWorkerAction($pdo, "Хэрэглэгч '{$ner}' бүртгэв (захиалгагүй)");
        }

        $_SESSION['toast'] = ['msg'=>"'{$ner}' амжилттай бүртгэгдлээ.",'type'=>'success','icon'=>'✅'];
        header('Location: worker.php?tab=register');
        exit;
    }
}

// ── Өгөгдөл татах ─────────────────────────────────────────
$myOrders  = $pdo->prepare('
    SELECT o.*, u.ner as user_ner, u.phone as user_phone, u.email as user_email
    FROM orders o JOIN users u ON o.user_id=u.id
    WHERE o.worker_id=?
    ORDER BY o.created_at DESC
');
$myOrders->execute([$uid]);
$myOrders = $myOrders->fetchAll();

$pendingCount   = count(array_filter($myOrders, fn($o) => $o['status']==='pending'));
$deliveredCount = count(array_filter($myOrders, fn($o) => $o['status']==='delivered'));

$pageTitle  = 'Ажилтны хянах самбар';
$activePage = $tab === 'dashboard' ? 'dashboard' : $tab;
include __DIR__ . '/layout.php';
?>

<div class="tabs">
  <button class="tab <?= $tab==='dashboard'?'active':'' ?>" onclick="location='worker.php'">📊 Нүүр</button>
  <button class="tab <?= $tab==='orders'?'active':'' ?>" onclick="location='worker.php?tab=orders'">📦 Захиалгууд</button>
  <button class="tab <?= $tab==='register'?'active':'' ?>" onclick="location='worker.php?tab=register'">➕ Хэрэглэгч бүртгэх</button>
  <button class="tab <?= $tab==='logs'?'active':'' ?>" onclick="location='worker.php?tab=logs'">📋 Миний бүртгэл</button>
</div>

<?php if ($tab === 'dashboard'): ?>
<div class="stat-grid">
  <div class="stat-card"><div class="stat-icon">📦</div><div class="stat-num"><?= count($myOrders) ?></div><div class="stat-lbl">Нийт захиалга</div></div>
  <div class="stat-card"><div class="stat-icon">⏳</div><div class="stat-num"><?= $pendingCount ?></div><div class="stat-lbl">Хүлээгдэж буй</div></div>
  <div class="stat-card"><div class="stat-icon">✅</div><div class="stat-num"><?= $deliveredCount ?></div><div class="stat-lbl">Хүргэгдсэн</div></div>
</div>

<div class="card">
  <div class="card-header"><div class="card-title">📦 Миний захиалгууд (сүүлийн 5)</div></div>
  <div class="card-body">
    <table>
      <thead><tr><th>#</th><th>Хэрэглэгч</th><th>Бүтээгдэхүүн</th><th>Статус</th><th>Огноо</th><th>Үйлдэл</th></tr></thead>
      <tbody>
      <?php foreach (array_slice($myOrders,0,5) as $o):
        $c = statusColor($o['status']);
      ?>
        <tr>
          <td>#<?= $o['id'] ?></td>
          <td><?= htmlspecialchars($o['user_ner']) ?><br><small style="color:var(--muted)"><?= htmlspecialchars($o['user_phone']) ?></small></td>
          <td><?= htmlspecialchars($o['product']) ?></td>
          <td><span class="badge" style="background:<?= $c ?>22;color:<?= $c ?>"><?= statusLabel($o['status']) ?></span></td>
          <td><?= date('m/d', strtotime($o['created_at'])) ?></td>
          <td><button class="btn btn-primary btn-sm" onclick="openStatusModal(<?= $o['id'] ?>,'<?= $o['status'] ?>')">Статус солих</button></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php elseif ($tab === 'orders'): ?>
<div class="card">
  <div class="card-header"><div class="card-title">📦 Миний захиалгууд (<?= count($myOrders) ?>)</div></div>
  <div class="card-body">
    <table>
      <thead><tr><th>#</th><th>Хэрэглэгч</th><th>Утас</th><th>Имэйл</th><th>Бүтээгдэхүүн</th><th>Хэмжээ</th><th>Статус</th><th>Огноо</th><th>Үйлдэл</th></tr></thead>
      <tbody>
      <?php foreach ($myOrders as $o):
        $c = statusColor($o['status']);
      ?>
        <tr>
          <td>#<?= $o['id'] ?></td>
          <td><strong><?= htmlspecialchars($o['user_ner']) ?></strong></td>
          <td><?= htmlspecialchars($o['user_phone']) ?></td>
          <td style="font-size:12px"><?= htmlspecialchars($o['user_email']) ?></td>
          <td><?= htmlspecialchars($o['product']) ?></td>
          <td><?= $o['shirheg'] ?>ш · <?= $o['urt_m'] ?>м · <?= $o['urgun_cm'] ?>×<?= $o['zuzaan_cm'] ?>см</td>
          <td><span class="badge" style="background:<?= $c ?>22;color:<?= $c ?>"><?= statusLabel($o['status']) ?></span></td>
          <td><?= date('m/d H:i', strtotime($o['created_at'])) ?></td>
          <td><button class="btn btn-primary btn-sm" onclick="openStatusModal(<?= $o['id'] ?>,'<?= $o['status'] ?>')">Солих</button></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php elseif ($tab === 'register'): ?>
<div class="card">
  <div class="card-header"><div class="card-title">➕ Хэрэглэгч бүртгэх / Захиалга оруулах</div></div>
  <div class="card-body" style="padding:24px">
    <div class="alert alert-info" style="margin-bottom:20px">
      💡 Биечлэн ирсэн эсвэл утсаар захиалга хийсэн хэрэглэгчийн мэдээллийг энд бүртгэнэ.
    </div>
    <form method="POST">
      <input type="hidden" name="act" value="register_customer">
      <div class="form-grid">
        <div class="form-group"><label>Нэр *</label><input type="text" name="ner" required placeholder="Батбаяр"></div>
        <div class="form-group"><label>Имэйл *</label><input type="email" name="email" required placeholder="bat@email.com"></div>
        <div class="form-group"><label>Утасны дугаар *</label><input type="tel" name="phone" required placeholder="99112233"></div>
        <div class="form-group"><label>Бүтээгдэхүүн</label>
          <select name="product">
            <option value="">— Сонгох (заавал биш) —</option>
            <option>Нарс (Pine)</option><option>Хус (Birch)</option>
            <option>Хар мод</option><option>Хуш (Cedar)</option>
            <option>Модон хавтан</option><option>Түлш мод</option><option>Бусад</option>
          </select>
        </div>
        <div class="form-group"><label>Тоо ширхэг</label><input type="number" name="shirheg" min="1" placeholder="10"></div>
        <div class="form-group"><label>Урт (м)</label><input type="number" name="urt_m" step="0.1" placeholder="3.0"></div>
        <div class="form-group"><label>Өргөн (см)</label><input type="number" name="urgun_cm" placeholder="15"></div>
        <div class="form-group"><label>Зузаан (см)</label><input type="number" name="zuzaan_cm" step="0.1" placeholder="2.5"></div>
        <div class="form-group full"><label>Тайлбар / Нэмэлт мэдээлэл</label><textarea name="notes" placeholder="Онцгой шаардлага..."></textarea></div>
      </div>
      <button type="submit" class="btn btn-primary" style="margin-top:16px;width:100%">✅ Бүртгэх</button>
    </form>
  </div>
</div>

<?php elseif ($tab === 'logs'): ?>
<?php
$logs = $pdo->prepare('SELECT * FROM worker_logs WHERE worker_id=? ORDER BY created_at DESC LIMIT 100');
$logs->execute([$uid]);
$logs = $logs->fetchAll();
?>
<div class="card">
  <div class="card-header"><div class="card-title">📋 Миний үйлдлийн бүртгэл</div></div>
  <div class="card-body">
    <table>
      <thead><tr><th>Үйлдэл</th><th>Захиалга</th><th>Огноо</th></tr></thead>
      <tbody>
      <?php foreach ($logs as $l): ?>
        <tr>
          <td><?= htmlspecialchars($l['action']) ?></td>
          <td><?= $l['order_id'] ? '#'.$l['order_id'] : '—' ?></td>
          <td><?= date('m/d H:i', strtotime($l['created_at'])) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- Status солих modal -->
<div class="modal-overlay" id="status-modal">
  <div class="modal-box" style="max-width:360px">
    <button class="modal-close" onclick="closeModal('status-modal')">&times;</button>
    <div class="modal-title">📦 Статус солих</div>
    <form method="POST">
      <input type="hidden" name="act" value="update_status">
      <input type="hidden" name="order_id" id="sm-order-id">
      <div class="form-group" style="margin-bottom:20px">
        <label>Шинэ статус</label>
        <select name="status" id="sm-status">
          <option value="pending">⏳ Хүлээгдэж байна</option>
          <option value="confirmed">✅ Баталгаажсан</option>
          <option value="processing">🔨 Бэлтгэж байна</option>
          <option value="delivering">🚚 Хүргэлтэнд гарсан</option>
          <option value="delivered">📦 Хүргэгдсэн</option>
          <option value="cancelled">❌ Цуцлагдсан</option>
        </select>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%">Хадгалах</button>
    </form>
  </div>
</div>

<script>
function openStatusModal(orderId, currentStatus) {
  document.getElementById('sm-order-id').value = orderId;
  document.getElementById('sm-status').value   = currentStatus;
  openModal('status-modal');
}
</script>

<?php include __DIR__ . '/layout_end.php'; ?>