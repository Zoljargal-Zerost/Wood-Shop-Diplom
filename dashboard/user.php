<?php
require_once __DIR__ . '/../middleware.php';
requireLogin();
loadUserRole($pdo);

if (!isRole('user')) {
    header('Location: /Wood-shop/dashboard/');
    exit;
}

$uid = $_SESSION['user']['id'];
$tab = $_GET['tab'] ?? 'orders';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['act'] === 'update_profile') {
    $ner   = trim($_POST['ner']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone'] ?? '');
    $pdo->prepare('UPDATE users SET ner=?,email=?,phone=? WHERE id=?')->execute([$ner,$email,$phone,$uid]);
    $_SESSION['user']['ner']   = $ner;
    $_SESSION['user']['email'] = $email;
    $_SESSION['user']['phone'] = $phone;
    $_SESSION['toast'] = ['msg'=>'Мэдээлэл хадгалагдлаа.','type'=>'success','icon'=>'✅'];
    header('Location: user.php?tab=profile');
    exit;
}

$orders = $pdo->prepare('SELECT * FROM orders WHERE user_id=? ORDER BY created_at DESC');
$orders->execute([$uid]);
$orders = $orders->fetchAll();

$pageTitle  = 'Миний хянах самбар';
$activePage = $tab === 'orders' ? 'orders' : 'profile';
include __DIR__ . '/layout.php';
?>

<div class="tabs">
  <button class="tab <?= $tab==='orders'?'active':'' ?>" onclick="location='user.php'">📦 Миний захиалгууд</button>
  <button class="tab <?= $tab==='profile'?'active':'' ?>" onclick="location='user.php?tab=profile'">👤 Профайл</button>
</div>

<?php if ($tab === 'orders'): ?>
<?php if (empty($orders)): ?>
<div style="text-align:center;padding:60px 20px;color:var(--muted)">
  <div style="font-size:48px;margin-bottom:12px">📭</div>
  <p style="margin-bottom:16px">Одоогоор захиалга байхгүй байна.</p>
  <a href="/Wood-shop/#shop" class="btn btn-primary">Бүтээгдэхүүн үзэх →</a>
</div>
<?php else: ?>
<div class="card">
  <div class="card-header"><div class="card-title">📦 Миний захиалгууд (<?= count($orders) ?>)</div></div>
  <div class="card-body">
    <table>
      <thead><tr><th>#</th><th>Бүтээгдэхүүн</th><th>Хэмжээ</th><th>Статус</th><th>Огноо</th><th>Тайлбар</th></tr></thead>
      <tbody>
      <?php foreach ($orders as $o):
        $c = statusColor($o['status']);
      ?>
        <tr>
          <td>#<?= $o['id'] ?></td>
          <td><strong><?= htmlspecialchars($o['product']) ?></strong></td>
          <td><?= $o['shirheg'] ?>ш · <?= $o['urt_m'] ?>м · <?= $o['urgun_cm'] ?>×<?= $o['zuzaan_cm'] ?>см</td>
          <td><span class="badge" style="background:<?= $c ?>22;color:<?= $c ?>"><?= statusLabel($o['status']) ?></span></td>
          <td><?= date('Y/m/d', strtotime($o['created_at'])) ?></td>
          <td style="font-size:13px;color:var(--muted)"><?= htmlspecialchars($o['notes'] ?? '—') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php elseif ($tab === 'profile'): ?>
<div class="card" style="max-width:560px">
  <div class="card-header"><div class="card-title">👤 Профайл засах</div></div>
  <div class="card-body" style="padding:24px">
    <form method="POST">
      <input type="hidden" name="act" value="update_profile">
      <div class="form-grid">
        <div class="form-group">
          <label>Нэр</label>
          <input type="text" name="ner" value="<?= htmlspecialchars($_SESSION['user']['ner']) ?>" required>
        </div>
        <div class="form-group">
          <label>Имэйл</label>
          <input type="email" name="email" value="<?= htmlspecialchars($_SESSION['user']['email']) ?>" required>
        </div>
        <div class="form-group full">
          <label>Утасны дугаар</label>
          <input type="tel" name="phone" value="<?= htmlspecialchars($_SESSION['user']['phone'] ?? '') ?>">
        </div>
      </div>
      <button type="submit" class="btn btn-primary" style="margin-top:16px">💾 Хадгалах</button>
    </form>
  </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/layout_end.php'; ?>
