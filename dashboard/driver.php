<?php
require_once __DIR__ . '/../middleware.php';
requireLogin();
$role = loadUserRole($pdo);
require_once __DIR__ . '/../notify.php';

if (!isRole('driver')) {
    header('Location: /Wood-shop/dashboard/');
    exit;
}

$uid = $_SESSION['user']['id'];
$tab = $_GET['tab'] ?? 'dashboard';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['act'] === 'update_delivery_status') {
    $oid    = (int)$_POST['order_id'];
    $status = $_POST['status'];
    $pdo->prepare('UPDATE orders SET status=? WHERE id=? AND driver_id=?')
        ->execute([$status, $oid, $uid]);
    logWorkerAction($pdo, "Хүргэлт #{$oid} статус: {$status}", $oid);
    // Хэрэглэгчид мэдэгдэл явуулах
    notifyCustomerStatusChange($pdo, $oid, $status);
    $_SESSION['toast'] = ['msg'=>'Статус шинэчлэгдлээ.','type'=>'success','icon'=>'✅'];
    header('Location: driver.php?tab=deliveries');
    exit;
}

// Миний хүргэлтүүд
$deliveries = $pdo->prepare('
    SELECT o.*, u.ner as user_ner, u.phone as user_phone, u.email as user_email
    FROM orders o JOIN users u ON o.user_id=u.id
    WHERE o.driver_id=?
    ORDER BY o.created_at DESC
');
$deliveries->execute([$uid]);
$deliveries = $deliveries->fetchAll();

// Миний машины мэдээлэл
$driverProfile = $pdo->prepare('SELECT * FROM driver_profiles WHERE user_id=?');
$driverProfile->execute([$uid]);
$driverProfile = $driverProfile->fetch();

$pageTitle  = 'Жолоочийн хянах самбар';
$activePage = $tab;
include __DIR__ . '/layout.php';
?>

<div class="tabs">
  <button class="tab <?= $tab==='dashboard'?'active':'' ?>" onclick="location='driver.php'">📊 Нүүр</button>
  <button class="tab <?= $tab==='deliveries'?'active':'' ?>" onclick="location='driver.php?tab=deliveries'">🚚 Хүргэлтүүд</button>
</div>

<?php if ($tab === 'dashboard'): ?>
<div class="stat-grid">
  <?php $active = array_filter($deliveries, fn($d) => in_array($d['status'],['confirmed','processing','delivering'])); ?>
  <div class="stat-card"><div class="stat-icon">🚚</div><div class="stat-num"><?= count($active) ?></div><div class="stat-lbl">Идэвхтэй хүргэлт</div></div>
  <div class="stat-card"><div class="stat-icon">📦</div><div class="stat-num"><?= count($deliveries) ?></div><div class="stat-lbl">Нийт хүргэлт</div></div>
</div>

<?php if ($driverProfile): ?>
<div class="card" style="margin-bottom:24px">
  <div class="card-header"><div class="card-title">🚗 Миний машины мэдээлэл</div></div>
  <div class="card-body" style="padding:20px">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px">
      <div><div style="font-size:12px;color:var(--muted);font-weight:600;text-transform:uppercase">Дугаар</div><div style="font-size:18px;font-weight:800;color:var(--primary)"><?= htmlspecialchars($driverProfile['vehicle_plate']) ?></div></div>
      <div><div style="font-size:12px;color:var(--muted);font-weight:600;text-transform:uppercase">Марк</div><div style="font-size:15px;font-weight:600"><?= htmlspecialchars($driverProfile['vehicle_model'] ?? '—') ?></div></div>
      <div><div style="font-size:12px;color:var(--muted);font-weight:600;text-transform:uppercase">Төрөл</div><div><?= htmlspecialchars($driverProfile['vehicle_type'] ?? '—') ?></div></div>
      <div><div style="font-size:12px;color:var(--muted);font-weight:600;text-transform:uppercase">Үнэмлэх дуусах</div><div><?= $driverProfile['license_expiry'] ? date('Y/m/d', strtotime($driverProfile['license_expiry'])) : '—' ?></div></div>
      <div><div style="font-size:12px;color:var(--muted);font-weight:600;text-transform:uppercase">Яаралтай утас</div><div><?= htmlspecialchars($driverProfile['emergency_phone'] ?? '—') ?></div></div>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-header"><div class="card-title">🚚 Идэвхтэй хүргэлтүүд</div></div>
  <div class="card-body">
    <table>
      <thead><tr><th>#</th><th>Хэрэглэгч</th><th>Утас</th><th>Бүтээгдэхүүн</th><th>Статус</th><th>Үйлдэл</th></tr></thead>
      <tbody>
      <?php foreach ($active as $d):
        $c = statusColor($d['status']);
      ?>
        <tr>
          <td>#<?= $d['id'] ?></td>
          <td><?= htmlspecialchars($d['user_ner']) ?></td>
          <td><a href="tel:<?= $d['user_phone'] ?>" style="color:var(--accent);font-weight:600"><?= htmlspecialchars($d['user_phone']) ?></a></td>
          <td><?= htmlspecialchars($d['product']) ?> · <?= $d['shirheg'] ?>ш</td>
          <td><span class="badge" style="background:<?= $c ?>22;color:<?= $c ?>"><?= statusLabel($d['status']) ?></span></td>
          <td><button class="btn btn-primary btn-sm" onclick="openDelModal(<?= $d['id'] ?>,'<?= $d['status'] ?>')">Статус солих</button></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php elseif ($tab === 'deliveries'): ?>
<div class="card">
  <div class="card-header"><div class="card-title">🚚 Бүх хүргэлтүүд (<?= count($deliveries) ?>)</div></div>
  <div class="card-body">
    <table>
      <thead><tr><th>#</th><th>Хэрэглэгч</th><th>Утас</th><th>Бүтээгдэхүүн</th><th>Хэмжээ</th><th>Статус</th><th>Огноо</th><th>Үйлдэл</th></tr></thead>
      <tbody>
      <?php foreach ($deliveries as $d):
        $c = statusColor($d['status']);
      ?>
        <tr>
          <td>#<?= $d['id'] ?></td>
          <td><?= htmlspecialchars($d['user_ner']) ?></td>
          <td><a href="tel:<?= $d['user_phone'] ?>" style="color:var(--accent)"><?= htmlspecialchars($d['user_phone']) ?></a></td>
          <td><?= htmlspecialchars($d['product']) ?></td>
          <td><?= $d['shirheg'] ?>ш · <?= $d['urt_m'] ?>м</td>
          <td><span class="badge" style="background:<?= $c ?>22;color:<?= $c ?>"><?= statusLabel($d['status']) ?></span></td>
          <td><?= date('m/d', strtotime($d['created_at'])) ?></td>
          <td><button class="btn btn-primary btn-sm" onclick="openDelModal(<?= $d['id'] ?>,'<?= $d['status'] ?>')">Солих</button></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- Delivery status modal -->
<div class="modal-overlay" id="del-modal">
  <div class="modal-box" style="max-width:340px">
    <button class="modal-close" onclick="closeModal('del-modal')">&times;</button>
    <div class="modal-title">🚚 Статус солих</div>
    <form method="POST">
      <input type="hidden" name="act" value="update_delivery_status">
      <input type="hidden" name="order_id" id="del-oid">
      <div class="form-group" style="margin-bottom:20px">
        <label>Шинэ статус</label>
        <select name="status" id="del-status">
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
function openDelModal(oid, status) {
  document.getElementById('del-oid').value    = oid;
  document.getElementById('del-status').value = status;
  openModal('del-modal');
}
</script>

<?php include __DIR__ . '/layout_end.php'; ?>