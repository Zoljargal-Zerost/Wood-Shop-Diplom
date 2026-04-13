<?php
require_once __DIR__ . '/../middleware.php';
requireLogin();
$role = loadUserRole($pdo);
require_once __DIR__ . '/../notify.php';

if (!isRole('admin','manager','director')) {
    header('Location: /Wood-shop/dashboard/');
    exit;
}

$tab = $_GET['tab'] ?? 'dashboard';

// ── POST үйлдлүүд ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';

    // Захиалгын статус солих
    if ($act === 'update_order_status' && can('update_order_status')) {
        $oid    = (int)$_POST['order_id'];
        $status = $_POST['status'];
        $note   = trim($_POST['admin_notes'] ?? '');
        $stmt   = $pdo->prepare('UPDATE orders SET status=?, admin_notes=? WHERE id=?');
        $stmt->execute([$status, $note, $oid]);
        // Worker assign
        $wid = (int)($_POST['worker_id'] ?? 0);
        $did = (int)($_POST['driver_id'] ?? 0);
        $pdo->prepare('UPDATE orders SET worker_id=?, driver_id=? WHERE id=?')
            ->execute([$wid ?: null, $did ?: null, $oid]);
        logWorkerAction($pdo, "Захиалга #{$oid} статус: {$status}", $oid);
        // Хэрэглэгчид мэдэгдэл явуулах
        notifyCustomerStatusChange($pdo, $oid, $status);
        $_SESSION['toast'] = ['msg'=>'Захиалга шинэчлэгдлээ.','type'=>'success','icon'=>'✅'];
        header('Location: admin.php?tab=orders');
        exit;
    }

    // Хэрэглэгч идэвхгүй болгох/болгох
    if ($act === 'toggle_user' && can('edit_users')) {
        $uid = (int)$_POST['user_id'];
        $pdo->prepare('UPDATE users SET is_active = NOT is_active WHERE id=?')->execute([$uid]);
        $_SESSION['toast'] = ['msg'=>'Хэрэглэгч шинэчлэгдлээ.','type'=>'success','icon'=>'✅'];
        header('Location: admin.php?tab=users');
        exit;
    }

    // Role үүсгэх
    if ($act === 'create_role' && isRole('admin','director')) {
        $name  = trim($_POST['role_name']);
        $slug  = trim($_POST['role_slug']);
        $color = $_POST['role_color'] ?? '#5C3D1E';
        $perms = $_POST['permissions'] ?? [];
        $pdo->prepare('INSERT INTO roles (name, slug, permissions, color, created_by) VALUES (?,?,?,?,?)')
            ->execute([$name, $slug, json_encode($perms), $color, $_SESSION['user']['id']]);
        $_SESSION['toast'] = ['msg'=>"'{$name}' role үүслээ.",'type'=>'success','icon'=>'✅'];
        header('Location: admin.php?tab=roles');
        exit;
    }

    // Role устгах
    if ($act === 'delete_role' && isRole('admin','director')) {
        $rid = (int)$_POST['role_id'];
        $pdo->prepare('DELETE FROM roles WHERE id=? AND is_system=0')->execute([$rid]);
        $_SESSION['toast'] = ['msg'=>'Role устгагдлаа.','type'=>'success','icon'=>'✅'];
        header('Location: admin.php?tab=roles');
        exit;
    }

    // Хэрэглэгчийн role солих
    if ($act === 'change_user_role' && can('edit_users')) {
        $uid = (int)$_POST['user_id'];
        $rid = (int)$_POST['role_id'];
        $pdo->prepare('UPDATE users SET role_id=? WHERE id=?')->execute([$rid, $uid]);
        unset($_SESSION['user_role']); // cache цэвэрлэх
        $_SESSION['toast'] = ['msg'=>'Role шинэчлэгдлээ.','type'=>'success','icon'=>'✅'];
        header('Location: admin.php?tab=users');
        exit;
    }

    // Worker profile нэмэх/засах
    if ($act === 'save_worker_profile' && can('manage_workers')) {
        $uid   = (int)$_POST['user_id'];
        $title = trim($_POST['job_title'] ?? '');
        $dept  = trim($_POST['department'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $pdo->prepare('INSERT INTO worker_profiles (user_id,job_title,department,notes)
                       VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE job_title=?,department=?,notes=?')
            ->execute([$uid,$title,$dept,$notes,$title,$dept,$notes]);
        $_SESSION['toast'] = ['msg'=>'Ажилтны мэдээлэл хадгалагдлаа.','type'=>'success','icon'=>'✅'];
        header('Location: admin.php?tab=workers');
        exit;
    }

    // Driver profile нэмэх/засах
    if ($act === 'save_driver_profile' && can('manage_drivers')) {
        $uid    = (int)$_POST['user_id'];
        $plate  = trim($_POST['vehicle_plate']);
        $model  = trim($_POST['vehicle_model'] ?? '');
        $type   = trim($_POST['vehicle_type'] ?? '');
        $lic    = trim($_POST['license_no'] ?? '');
        $lexp   = $_POST['license_expiry'] ?? null;
        $ephone = trim($_POST['emergency_phone'] ?? '');
        $notes  = trim($_POST['notes'] ?? '');
        $pdo->prepare('INSERT INTO driver_profiles
            (user_id,vehicle_plate,vehicle_model,vehicle_type,license_no,license_expiry,emergency_phone,notes)
            VALUES (?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE
            vehicle_plate=?,vehicle_model=?,vehicle_type=?,license_no=?,license_expiry=?,emergency_phone=?,notes=?')
            ->execute([$uid,$plate,$model,$type,$lic,$lexp,$ephone,$notes,
                       $plate,$model,$type,$lic,$lexp,$ephone,$notes]);
        $_SESSION['toast'] = ['msg'=>'Жолоочийн мэдээлэл хадгалагдлаа.','type'=>'success','icon'=>'✅'];
        header('Location: admin.php?tab=drivers');
        exit;
    }
}

// ── Статистик ──────────────────────────────────────────────
$stats = [];
$stats['users']    = $pdo->query('SELECT COUNT(*) FROM users WHERE role_id=(SELECT id FROM roles WHERE slug="user")')->fetchColumn();
$stats['orders']   = $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();
$stats['pending']  = $pdo->query('SELECT COUNT(*) FROM orders WHERE status="pending"')->fetchColumn();
$stats['workers']  = $pdo->query('SELECT COUNT(*) FROM users u JOIN roles r ON u.role_id=r.id WHERE r.slug IN ("worker","driver")')->fetchColumn();
$stats['products'] = $pdo->query('SELECT COUNT(*) FROM products WHERE is_active=1')->fetchColumn();

// ── Бүх эрхүүд ────────────────────────────────────────────
$allPerms = [
    'view_all_users','edit_users','delete_users','manage_roles',
    'view_all_orders','update_order_status','assign_worker','assign_driver',
    'view_worker_logs','manage_workers','manage_drivers',
    'register_customer','view_statistics','view_driver_info','admin_notes'
];

$pageTitle  = 'Админ хянах самбар';
$activePage = $tab === 'dashboard' ? 'dashboard' : $tab;
include __DIR__ . '/layout.php';
?>

<!-- ── TAB Navigation ── -->
<div class="tabs">
  <button class="tab <?= $tab==='dashboard'?'active':'' ?>" onclick="location='admin.php'">📊 Нүүр</button>
  <button class="tab <?= $tab==='products'?'active':'' ?>"  onclick="location='admin.php?tab=products'">🌲 Бүтээгдэхүүн</button>
  <button class="tab <?= $tab==='orders'?'active':'' ?>"    onclick="location='admin.php?tab=orders'">📦 Захиалга</button>
  <button class="tab <?= $tab==='users'?'active':'' ?>"     onclick="location='admin.php?tab=users'">👥 Хэрэглэгч</button>
  <button class="tab <?= $tab==='workers'?'active':'' ?>"   onclick="location='admin.php?tab=workers'">👷 Ажилтан</button>
  <button class="tab <?= $tab==='drivers'?'active':'' ?>"   onclick="location='admin.php?tab=drivers'">🚚 Жолооч</button>
  <button class="tab <?= $tab==='logs'?'active':'' ?>"      onclick="location='admin.php?tab=logs'">📋 Бүртгэл</button>
  <?php if (isRole('admin','director')): ?>
  <button class="tab <?= $tab==='roles'?'active':'' ?>"     onclick="location='admin.php?tab=roles'">🔐 Role</button>
  <?php endif; ?>
</div>

<?php if ($tab === 'dashboard'): ?>
<!-- ══ DASHBOARD TAB ══ -->
<div class="stat-grid">
  <div class="stat-card"><div class="stat-icon">👥</div><div class="stat-num"><?= $stats['users'] ?></div><div class="stat-lbl">Нийт хэрэглэгч</div></div>
  <div class="stat-card"><div class="stat-icon">📦</div><div class="stat-num"><?= $stats['orders'] ?></div><div class="stat-lbl">Нийт захиалга</div></div>
  <div class="stat-card"><div class="stat-icon">⏳</div><div class="stat-num"><?= $stats['pending'] ?></div><div class="stat-lbl">Хүлээгдэж буй</div></div>
  <div class="stat-card"><div class="stat-icon">👷</div><div class="stat-num"><?= $stats['workers'] ?></div><div class="stat-lbl">Ажилтнууд</div></div>
  <div class="stat-card"><div class="stat-icon">🌲</div><div class="stat-num"><?= $stats['products'] ?></div><div class="stat-lbl">Бүтээгдэхүүн</div></div>
</div>

<!-- Сүүлийн захиалгууд -->
<div class="card">
  <div class="card-header">
    <div class="card-title">📦 Сүүлийн захиалгууд</div>
    <a href="admin.php?tab=orders" class="btn btn-outline btn-sm">Бүгдийг харах →</a>
  </div>
  <div class="card-body">
    <table>
      <thead><tr><th>#</th><th>Хэрэглэгч</th><th>Бүтээгдэхүүн</th><th>Статус</th><th>Огноо</th></tr></thead>
      <tbody>
      <?php
      $rows = $pdo->query('SELECT o.*, u.ner FROM orders o JOIN users u ON o.user_id=u.id ORDER BY o.created_at DESC LIMIT 10')->fetchAll();
      foreach ($rows as $r):
        $color = statusColor($r['status']);
      ?>
        <tr>
          <td>#<?= $r['id'] ?></td>
          <td><?= htmlspecialchars($r['ner']) ?></td>
          <td><?= htmlspecialchars($r['product']) ?></td>
          <td><span class="badge" style="background:<?= $color ?>22;color:<?= $color ?>"><?= statusLabel($r['status']) ?></span></td>
          <td><?= date('m/d H:i', strtotime($r['created_at'])) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php elseif ($tab === 'products'): ?>
<!-- ══ PRODUCTS TAB ══ -->
<?php
$allProducts = $pdo->query('SELECT * FROM products ORDER BY sort_order ASC, id ASC')->fetchAll();
$typeLabels  = ['shilmuust'=>'Шилмүүст','navchit'=>'Навчит','hatu'=>'Хатуу мод','bolvsuruulsan'=>'Боловсруулсан','tulsh'=>'Түлш'];
?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
  <div></div>
  <button class="btn btn-primary" onclick="openModal('prod-add-modal')">➕ Шинэ бүтээгдэхүүн</button>
</div>
<div class="card">
  <div class="card-header"><div class="card-title">🌲 Бүтээгдэхүүнүүд (<?= count($allProducts) ?>)</div></div>
  <div class="card-body">
    <table>
      <thead>
        <tr><th>Эрэмбэ</th><th>Зураг</th><th>Нэр</th><th>Төрөл</th><th>Үнэ</th><th>Үлдэгдэл</th><th>Байдал</th><th>Үйлдэл</th></tr>
      </thead>
      <tbody>
      <?php foreach ($allProducts as $p): ?>
        <tr>
          <td style="text-align:center">
            <form method="POST" action="/Wood-shop/product_action.php" style="display:inline">
              <input type="hidden" name="act" value="reorder">
              <input type="hidden" name="id" value="<?= $p['id'] ?>">
              <input type="hidden" name="direction" value="up">
              <input type="hidden" name="redirect" value="/Wood-shop/dashboard/admin.php?tab=products">
              <button class="btn btn-outline btn-sm" title="Дээш">↑</button>
            </form>
            <form method="POST" action="/Wood-shop/product_action.php" style="display:inline">
              <input type="hidden" name="act" value="reorder">
              <input type="hidden" name="id" value="<?= $p['id'] ?>">
              <input type="hidden" name="direction" value="down">
              <input type="hidden" name="redirect" value="/Wood-shop/dashboard/admin.php?tab=products">
              <button class="btn btn-outline btn-sm" title="Доош">↓</button>
            </form>
          </td>
          <td>
            <?php if ($p['image_path'] && file_exists(__DIR__ . '/../../' . $p['image_path'])): ?>
              <img src="/Wood-shop/<?= htmlspecialchars($p['image_path']) ?>" style="width:48px;height:48px;object-fit:cover;border-radius:8px">
            <?php else: ?>
              <span style="font-size:28px"><?= htmlspecialchars($p['emoji'] ?? '🪵') ?></span>
            <?php endif; ?>
          </td>
          <td><strong><?= htmlspecialchars($p['name']) ?></strong><br><small style="color:var(--muted)"><?= htmlspecialchars(mb_substr($p['description']??'',0,40)) ?>...</small></td>
          <td><?= htmlspecialchars($typeLabels[$p['type']] ?? $p['type']) ?></td>
          <td><?= $p['price_value'] ? number_format($p['price_value'],0,'.',',').'₮' : htmlspecialchars($p['price_label']) ?></td>
          <td><?= $p['stock'] ?? '∞' ?></td>
          <td>
            <span class="badge" style="background:<?= $p['is_active']?'#eaf3de':'#fcebeb' ?>;color:<?= $p['is_active']?'#27500a':'#791f1f' ?>">
              <?= $p['is_active']?'Идэвхтэй':'Нуугдсан' ?>
            </span>
          </td>
          <td style="display:flex;gap:6px;flex-wrap:wrap">
            <button class="btn btn-outline btn-sm" onclick="openProdEdit(<?= htmlspecialchars(json_encode($p)) ?>)">✏️ Засах</button>
            <form method="POST" action="/Wood-shop/product_action.php" style="display:inline">
              <input type="hidden" name="act" value="toggle">
              <input type="hidden" name="id" value="<?= $p['id'] ?>">
              <input type="hidden" name="redirect" value="/Wood-shop/dashboard/admin.php?tab=products">
              <button class="btn btn-outline btn-sm"><?= $p['is_active']?'🙈 Нуух':'👁 Идэвхжүүлэх' ?></button>
            </form>
            <form method="POST" action="/Wood-shop/product_action.php" style="display:inline" onsubmit="return confirm('Устгах уу?')">
              <input type="hidden" name="act" value="delete">
              <input type="hidden" name="id" value="<?= $p['id'] ?>">
              <input type="hidden" name="redirect" value="/Wood-shop/dashboard/admin.php?tab=products">
              <button class="btn btn-danger btn-sm">🗑️</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add product modal -->
<div class="modal-overlay" id="prod-add-modal">
  <div class="modal-box" style="max-width:640px">
    <button class="modal-close" onclick="closeModal('prod-add-modal')">&times;</button>
    <div class="modal-title">➕ Шинэ бүтээгдэхүүн</div>
    <form method="POST" action="/Wood-shop/product_action.php" enctype="multipart/form-data">
      <input type="hidden" name="act" value="add">
      <input type="hidden" name="redirect" value="/Wood-shop/dashboard/admin.php?tab=products">
      <div class="form-grid">
        <div class="form-group"><label>Нэр *</label><input type="text" name="name" required></div>
        <div class="form-group"><label>Төрөл *</label>
          <select name="type">
            <?php foreach ($typeLabels as $v=>$l): ?><option value="<?= $v ?>"><?= $l ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label>Emoji</label><input type="text" name="emoji" value="🪵" maxlength="4"></div>
        <div class="form-group"><label>Үнийн тэмдэглэгээ</label><input type="text" name="price_label" value="Үнийн санал авах"></div>
        <div class="form-group"><label>Үнэ ₮</label><input type="number" name="price_value" min="0"></div>
        <div class="form-group"><label>Үлдэгдэл</label><input type="number" name="stock"></div>
        <div class="form-group"><label>Эрэмбэ</label><input type="number" name="sort_order" value="0"></div>
        <div class="form-group" style="align-self:end"><label style="display:flex;gap:8px;text-transform:none;font-size:14px;font-weight:400"><input type="checkbox" name="is_active" checked> Идэвхтэй</label></div>
        <div class="form-group full"><label>Тайлбар</label><textarea name="description" rows="3"></textarea></div>
        <div class="form-group full"><label>Зураг</label><input type="file" name="image" accept="image/*"></div>
      </div>
      <button type="submit" class="btn btn-primary" style="margin-top:16px;width:100%">✅ Нэмэх</button>
    </form>
  </div>
</div>

<!-- Edit product modal -->
<div class="modal-overlay" id="prod-edit-modal">
  <div class="modal-box" style="max-width:640px">
    <button class="modal-close" onclick="closeModal('prod-edit-modal')">&times;</button>
    <div class="modal-title">✏️ Бүтээгдэхүүн засах</div>
    <form method="POST" action="/Wood-shop/product_action.php" enctype="multipart/form-data">
      <input type="hidden" name="act" value="edit">
      <input type="hidden" name="redirect" value="/Wood-shop/dashboard/admin.php?tab=products">
      <input type="hidden" name="id" id="pe-id">
      <div class="form-grid">
        <div class="form-group"><label>Нэр *</label><input type="text" name="name" id="pe-name" required></div>
        <div class="form-group"><label>Төрөл *</label>
          <select name="type" id="pe-type">
            <?php foreach ($typeLabels as $v=>$l): ?><option value="<?= $v ?>"><?= $l ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label>Emoji</label><input type="text" name="emoji" id="pe-emoji" maxlength="4"></div>
        <div class="form-group"><label>Үнийн тэмдэглэгээ</label><input type="text" name="price_label" id="pe-pl"></div>
        <div class="form-group"><label>Үнэ ₮</label><input type="number" name="price_value" id="pe-pv" min="0"></div>
        <div class="form-group"><label>Үлдэгдэл</label><input type="number" name="stock" id="pe-stock"></div>
        <div class="form-group"><label>Эрэмбэ</label><input type="number" name="sort_order" id="pe-sort"></div>
        <div class="form-group" style="align-self:end"><label style="display:flex;gap:8px;text-transform:none;font-size:14px;font-weight:400"><input type="checkbox" name="is_active" id="pe-active"> Идэвхтэй</label></div>
        <div class="form-group full"><label>Тайлбар</label><textarea name="description" id="pe-desc" rows="3"></textarea></div>
        <div class="form-group full"><label>Шинэ зураг (орхивол хуучин хэвээр)</label><input type="file" name="image" accept="image/*"><div id="pe-img-prev" style="margin-top:6px"></div></div>
      </div>
      <button type="submit" class="btn btn-primary" style="margin-top:16px;width:100%">💾 Хадгалах</button>
    </form>
  </div>
</div>

<script>
function openProdEdit(p) {
  document.getElementById('pe-id').value    = p.id;
  document.getElementById('pe-name').value  = p.name||'';
  document.getElementById('pe-type').value  = p.type||'';
  document.getElementById('pe-emoji').value = p.emoji||'';
  document.getElementById('pe-pl').value    = p.price_label||'';
  document.getElementById('pe-pv').value    = p.price_value||'';
  document.getElementById('pe-stock').value = p.stock||'';
  document.getElementById('pe-sort').value  = p.sort_order||0;
  document.getElementById('pe-desc').value  = p.description||'';
  document.getElementById('pe-active').checked = p.is_active==1;
  var prev = document.getElementById('pe-img-prev');
  if (prev) prev.innerHTML = p.image_path ? '<img src="/Wood-shop/'+p.image_path+'" style="max-height:70px;border-radius:8px">' : '';
  openModal('prod-edit-modal');
}
</script>

<?php elseif ($tab === 'orders'): ?>
<!-- ══ ORDERS TAB ══ -->
<?php
$orders = $pdo->query('
    SELECT o.*, u.ner as user_ner, u.phone as user_phone,
           w.ner as worker_ner, d.ner as driver_ner
    FROM orders o
    JOIN users u ON o.user_id = u.id
    LEFT JOIN users w ON o.worker_id = w.id
    LEFT JOIN users d ON o.driver_id = d.id
    ORDER BY o.created_at DESC
')->fetchAll();

$workers = $pdo->query('SELECT u.id,u.ner FROM users u JOIN roles r ON u.role_id=r.id WHERE r.slug="worker" AND u.is_active=1')->fetchAll();
$drivers = $pdo->query('SELECT u.id,u.ner FROM users u JOIN roles r ON u.role_id=r.id WHERE r.slug="driver" AND u.is_active=1')->fetchAll();
?>
<div class="card">
  <div class="card-header"><div class="card-title">📦 Бүх захиалгууд (<?= count($orders) ?>)</div></div>
  <div class="card-body">
    <table>
      <thead><tr><th>#</th><th>Хэрэглэгч</th><th>Утас</th><th>Бүтээгдэхүүн</th><th>Хэмжээ</th><th>Ажилтан</th><th>Жолооч</th><th>Статус</th><th>Огноо</th><th>Үйлдэл</th></tr></thead>
      <tbody>
      <?php foreach ($orders as $o):
        $c = statusColor($o['status']);
      ?>
        <tr>
          <td>#<?= $o['id'] ?></td>
          <td><strong><?= htmlspecialchars($o['user_ner']) ?></strong></td>
          <td><?= htmlspecialchars($o['user_phone']) ?></td>
          <td><?= htmlspecialchars($o['product']) ?></td>
          <td><?= $o['shirheg'] ?>ш · <?= $o['urt_m'] ?>м</td>
          <td><?= htmlspecialchars($o['worker_ner'] ?? '—') ?></td>
          <td><?= htmlspecialchars($o['driver_ner'] ?? '—') ?></td>
          <td><span class="badge" style="background:<?= $c ?>22;color:<?= $c ?>"><?= statusLabel($o['status']) ?></span></td>
          <td><?= date('m/d', strtotime($o['created_at'])) ?></td>
          <td>
            <button class="btn btn-primary btn-sm"
              onclick="openOrderEdit(<?= htmlspecialchars(json_encode($o)) ?>,
                <?= htmlspecialchars(json_encode($workers)) ?>,
                <?= htmlspecialchars(json_encode($drivers)) ?>)">
              Засах
            </button>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Order edit modal -->
<div class="modal-overlay" id="order-edit-modal">
  <div class="modal-box">
    <button class="modal-close" onclick="closeModal('order-edit-modal')">&times;</button>
    <div class="modal-title">📦 Захиалга засах</div>
    <form method="POST">
      <input type="hidden" name="act" value="update_order_status">
      <input type="hidden" name="order_id" id="edit-order-id">
      <div class="form-grid">
        <div class="form-group">
          <label>Статус</label>
          <select name="status" id="edit-status">
            <option value="pending">⏳ Хүлээгдэж байна</option>
            <option value="confirmed">✅ Баталгаажсан</option>
            <option value="processing">🔨 Бэлтгэж байна</option>
            <option value="delivering">🚚 Хүргэлтэнд гарсан</option>
            <option value="delivered">📦 Хүргэгдсэн</option>
            <option value="cancelled">❌ Цуцлагдсан</option>
          </select>
        </div>
        <div class="form-group">
          <label>Хариуцах ажилтан</label>
          <select name="worker_id" id="edit-worker"></select>
        </div>
        <div class="form-group">
          <label>Хариуцах жолооч</label>
          <select name="driver_id" id="edit-driver"></select>
        </div>
        <div class="form-group full">
          <label>Дотоод тайлбар (хэрэглэгч харахгүй)</label>
          <textarea name="admin_notes" id="edit-notes" rows="3"></textarea>
        </div>
      </div>
      <button type="submit" class="btn btn-primary" style="margin-top:16px;width:100%">💾 Хадгалах</button>
    </form>
  </div>
</div>

<?php elseif ($tab === 'users'): ?>
<!-- ══ USERS TAB ══ -->
<?php
$users = $pdo->query('
    SELECT u.*, r.name as role_name, r.slug as role_slug, r.color as role_color
    FROM users u JOIN roles r ON u.role_id=r.id
    ORDER BY u.created_at DESC
')->fetchAll();
$allRoles = $pdo->query('SELECT * FROM roles ORDER BY id')->fetchAll();
?>
<div class="card">
  <div class="card-header"><div class="card-title">👥 Бүх хэрэглэгчид (<?= count($users) ?>)</div></div>
  <div class="card-body">
    <table>
      <thead><tr><th>#</th><th>Нэр</th><th>Имэйл</th><th>Утас</th><th>Role</th><th>Статус</th><th>Бүртгэсэн</th><th>Үйлдэл</th></tr></thead>
      <tbody>
      <?php foreach ($users as $u): ?>
        <tr>
          <td><?= $u['id'] ?></td>
          <td><strong><?= htmlspecialchars($u['ner']) ?></strong></td>
          <td><?= htmlspecialchars($u['email']) ?></td>
          <td><?= htmlspecialchars($u['phone']) ?></td>
          <td><span class="badge" style="background:<?= $u['role_color'] ?>;color:#fff"><?= htmlspecialchars($u['role_name']) ?></span></td>
          <td><span class="badge" style="background:<?= $u['is_active']?'#eaf3de':'#fcebeb' ?>;color:<?= $u['is_active']?'#27500a':'#791f1f' ?>"><?= $u['is_active']?'Идэвхтэй':'Идэвхгүй' ?></span></td>
          <td><?= date('Y/m/d', strtotime($u['created_at'])) ?></td>
          <td style="display:flex;gap:6px;flex-wrap:wrap">
            <!-- Role солих -->
            <form method="POST" style="display:inline">
              <input type="hidden" name="act" value="change_user_role">
              <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
              <select name="role_id" onchange="this.form.submit()" style="padding:4px 8px;font-size:12px;border-radius:6px;border:1px solid var(--border)">
                <?php foreach ($allRoles as $r): ?>
                  <option value="<?= $r['id'] ?>" <?= $r['id']==$u['role_id']?'selected':'' ?>><?= htmlspecialchars($r['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </form>
            <!-- Идэвхтэй/гүй -->
            <form method="POST" style="display:inline">
              <input type="hidden" name="act" value="toggle_user">
              <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
              <button class="btn <?= $u['is_active']?'btn-danger':'btn-success' ?> btn-sm">
                <?= $u['is_active']?'Блоклох':'Нээх' ?>
              </button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php elseif ($tab === 'workers'): ?>
<!-- ══ WORKERS TAB ══ -->
<?php
$workers = $pdo->query('
    SELECT u.*, wp.job_title, wp.department, wp.notes as wp_notes
    FROM users u
    JOIN roles r ON u.role_id=r.id
    LEFT JOIN worker_profiles wp ON wp.user_id=u.id
    WHERE r.slug="worker"
    ORDER BY u.created_at DESC
')->fetchAll();
?>
<div style="margin-bottom:16px">
  <button class="btn btn-primary" onclick="openModal('add-worker-modal')">➕ Шинэ ажилтан нэмэх</button>
</div>
<div class="card">
  <div class="card-header"><div class="card-title">👷 Ажилтнууд (<?= count($workers) ?>)</div></div>
  <div class="card-body">
    <table>
      <thead><tr><th>Нэр</th><th>Имэйл</th><th>Утас</th><th>Албан тушаал</th><th>Хэлтэс</th><th>Тайлбар</th><th>Үйлдэл</th></tr></thead>
      <tbody>
      <?php foreach ($workers as $w): ?>
        <tr>
          <td><strong><?= htmlspecialchars($w['ner']) ?></strong></td>
          <td><?= htmlspecialchars($w['email']) ?></td>
          <td><?= htmlspecialchars($w['phone']) ?></td>
          <td><?= htmlspecialchars($w['job_title'] ?? '—') ?></td>
          <td><?= htmlspecialchars($w['department'] ?? '—') ?></td>
          <td><?= htmlspecialchars($w['wp_notes'] ?? '—') ?></td>
          <td>
            <button class="btn btn-outline btn-sm"
              onclick="openWorkerEdit(<?= htmlspecialchars(json_encode($w)) ?>)">
              Засах
            </button>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php elseif ($tab === 'drivers'): ?>
<!-- ══ DRIVERS TAB ══ -->
<?php
$drivers = $pdo->query('
    SELECT u.*, dp.*
    FROM users u
    JOIN roles r ON u.role_id=r.id
    LEFT JOIN driver_profiles dp ON dp.user_id=u.id
    WHERE r.slug="driver"
    ORDER BY u.created_at DESC
')->fetchAll();
?>
<div style="margin-bottom:16px">
  <button class="btn btn-primary" onclick="openModal('add-driver-modal')">➕ Шинэ жолооч нэмэх</button>
</div>
<div class="card">
  <div class="card-header"><div class="card-title">🚚 Жолооч нар (<?= count($drivers) ?>)</div></div>
  <div class="card-body">
    <table>
      <thead><tr><th>Нэр</th><th>Утас</th><th>Машины дугаар</th><th>Машины марк</th><th>Төрөл</th><th>Үнэмлэх №</th><th>Дуусах огноо</th><th>Яаралтай утас</th><th>Үйлдэл</th></tr></thead>
      <tbody>
      <?php foreach ($drivers as $d): ?>
        <tr>
          <td><strong><?= htmlspecialchars($d['ner']) ?></strong></td>
          <td><?= htmlspecialchars($d['phone']) ?></td>
          <td><strong><?= htmlspecialchars($d['vehicle_plate'] ?? '—') ?></strong></td>
          <td><?= htmlspecialchars($d['vehicle_model'] ?? '—') ?></td>
          <td><?= htmlspecialchars($d['vehicle_type'] ?? '—') ?></td>
          <td><?= htmlspecialchars($d['license_no'] ?? '—') ?></td>
          <td><?= $d['license_expiry'] ? date('Y/m/d', strtotime($d['license_expiry'])) : '—' ?></td>
          <td><?= htmlspecialchars($d['emergency_phone'] ?? '—') ?></td>
          <td>
            <button class="btn btn-outline btn-sm"
              onclick="openDriverEdit(<?= htmlspecialchars(json_encode($d)) ?>)">
              Засах
            </button>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php elseif ($tab === 'logs'): ?>
<!-- ══ LOGS TAB ══ -->
<?php
$logs = $pdo->query('
    SELECT l.*, u.ner
    FROM worker_logs l
    JOIN users u ON l.worker_id=u.id
    ORDER BY l.created_at DESC LIMIT 200
')->fetchAll();
?>
<div class="card">
  <div class="card-header"><div class="card-title">📋 Ажилтны үйлдлийн бүртгэл</div></div>
  <div class="card-body">
    <table>
      <thead><tr><th>Ажилтан</th><th>Үйлдэл</th><th>Захиалга</th><th>IP</th><th>Огноо</th></tr></thead>
      <tbody>
      <?php foreach ($logs as $l): ?>
        <tr>
          <td><?= htmlspecialchars($l['ner']) ?></td>
          <td><?= htmlspecialchars($l['action']) ?></td>
          <td><?= $l['order_id'] ? '#'.$l['order_id'] : '—' ?></td>
          <td style="font-size:12px;color:var(--muted)"><?= htmlspecialchars($l['ip_address'] ?? '—') ?></td>
          <td><?= date('m/d H:i', strtotime($l['created_at'])) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php elseif ($tab === 'roles' && isRole('admin','director')): ?>
<!-- ══ ROLES TAB ══ -->
<?php $allRoles = $pdo->query('SELECT r.*, (SELECT COUNT(*) FROM users u WHERE u.role_id=r.id) as user_count FROM roles r ORDER BY id')->fetchAll(); ?>
<div style="margin-bottom:16px">
  <button class="btn btn-primary" onclick="openModal('add-role-modal')">➕ Шинэ role үүсгэх</button>
</div>
<div class="card">
  <div class="card-header"><div class="card-title">🔐 Role удирдлага</div></div>
  <div class="card-body">
    <table>
      <thead><tr><th>Нэр</th><th>Slug</th><th>Хэрэглэгч тоо</th><th>Эрхүүд</th><th>Систем</th><th>Үйлдэл</th></tr></thead>
      <tbody>
      <?php foreach ($allRoles as $r):
        $perms = json_decode($r['permissions'], true) ?? [];
      ?>
        <tr>
          <td><span class="badge" style="background:<?= $r['color'] ?>;color:#fff"><?= htmlspecialchars($r['name']) ?></span></td>
          <td><code><?= htmlspecialchars($r['slug']) ?></code></td>
          <td><?= $r['user_count'] ?> хэрэглэгч</td>
          <td style="font-size:12px;color:var(--muted)"><?= implode(', ', $perms) ?></td>
          <td><?= $r['is_system'] ? '🔒 Тогтмол' : '✏️ Захиалгат' ?></td>
          <td>
            <?php if (!$r['is_system']): ?>
            <form method="POST" onsubmit="return confirm('Устгах уу?')">
              <input type="hidden" name="act" value="delete_role">
              <input type="hidden" name="role_id" value="<?= $r['id'] ?>">
              <button class="btn btn-danger btn-sm">Устгах</button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add role modal -->
<div class="modal-overlay" id="add-role-modal">
  <div class="modal-box">
    <button class="modal-close" onclick="closeModal('add-role-modal')">&times;</button>
    <div class="modal-title">➕ Шинэ role үүсгэх</div>
    <form method="POST">
      <input type="hidden" name="act" value="create_role">
      <div class="form-grid">
        <div class="form-group">
          <label>Role нэр (Монгол)</label>
          <input type="text" name="role_name" placeholder="Агуулахын ажилтан" required>
        </div>
        <div class="form-group">
          <label>Slug (латин, доогуур зураастай)</label>
          <input type="text" name="role_slug" placeholder="warehouse_worker" required>
        </div>
        <div class="form-group">
          <label>Badge өнгө</label>
          <input type="color" name="role_color" value="#5C3D1E">
        </div>
      </div>
      <div style="margin:16px 0 8px;font-weight:600;font-size:13px;color:var(--muted)">ЭРХҮҮД (сонгох):</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:20px">
        <?php foreach ($allPerms as $perm): ?>
        <label style="display:flex;align-items:center;gap:8px;font-size:13px;font-weight:400;text-transform:none;letter-spacing:0">
          <input type="checkbox" name="permissions[]" value="<?= $perm ?>">
          <?= $perm ?>
        </label>
        <?php endforeach; ?>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%">✅ Үүсгэх</button>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- ── Worker profile modal ── -->
<div class="modal-overlay" id="worker-edit-modal">
  <div class="modal-box">
    <button class="modal-close" onclick="closeModal('worker-edit-modal')">&times;</button>
    <div class="modal-title">👷 Ажилтны мэдээлэл</div>
    <form method="POST">
      <input type="hidden" name="act" value="save_worker_profile">
      <input type="hidden" name="user_id" id="wp-uid">
      <div class="form-grid">
        <div class="form-group">
          <label>Албан тушаал</label>
          <input type="text" name="job_title" id="wp-title" placeholder="Модны ажилтан">
        </div>
        <div class="form-group">
          <label>Хэлтэс</label>
          <input type="text" name="department" id="wp-dept" placeholder="Борлуулалт">
        </div>
        <div class="form-group full">
          <label>Тайлбар</label>
          <textarea name="notes" id="wp-notes"></textarea>
        </div>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;margin-top:12px">💾 Хадгалах</button>
    </form>
  </div>
</div>

<!-- ── Driver profile modal ── -->
<div class="modal-overlay" id="driver-edit-modal">
  <div class="modal-box">
    <button class="modal-close" onclick="closeModal('driver-edit-modal')">&times;</button>
    <div class="modal-title">🚚 Жолоочийн мэдээлэл</div>
    <form method="POST">
      <input type="hidden" name="act" value="save_driver_profile">
      <input type="hidden" name="user_id" id="dp-uid">
      <div class="form-grid">
        <div class="form-group"><label>Машины дугаар</label><input type="text" name="vehicle_plate" id="dp-plate" placeholder="УНА-1234" required></div>
        <div class="form-group"><label>Машины марк</label><input type="text" name="vehicle_model" id="dp-model" placeholder="Mitsubishi Canter"></div>
        <div class="form-group"><label>Машины төрөл</label><input type="text" name="vehicle_type" id="dp-type" placeholder="Ачааны машин"></div>
        <div class="form-group"><label>Жолооны үнэмлэх №</label><input type="text" name="license_no" id="dp-lic"></div>
        <div class="form-group"><label>Үнэмлэх дуусах огноо</label><input type="date" name="license_expiry" id="dp-lexp"></div>
        <div class="form-group"><label>Яаралтай утас</label><input type="tel" name="emergency_phone" id="dp-ephone"></div>
        <div class="form-group full"><label>Тайлбар</label><textarea name="notes" id="dp-notes"></textarea></div>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;margin-top:12px">💾 Хадгалах</button>
    </form>
  </div>
</div>

<script>
function openOrderEdit(order, workers, drivers) {
  document.getElementById('edit-order-id').value = order.id;
  document.getElementById('edit-status').value   = order.status;
  document.getElementById('edit-notes').value    = order.admin_notes || '';

  var wSel = document.getElementById('edit-worker');
  var dSel = document.getElementById('edit-driver');
  wSel.innerHTML = '<option value="">— Сонгох —</option>';
  dSel.innerHTML = '<option value="">— Сонгох —</option>';

  workers.forEach(function(w) {
    wSel.innerHTML += '<option value="'+w.id+'"'+(order.worker_id==w.id?' selected':'')+'>'+w.ner+'</option>';
  });
  drivers.forEach(function(d) {
    dSel.innerHTML += '<option value="'+d.id+'"'+(order.driver_id==d.id?' selected':'')+'>'+d.ner+'</option>';
  });
  openModal('order-edit-modal');
}

function openWorkerEdit(w) {
  document.getElementById('wp-uid').value   = w.id;
  document.getElementById('wp-title').value = w.job_title || '';
  document.getElementById('wp-dept').value  = w.department || '';
  document.getElementById('wp-notes').value = w.wp_notes || '';
  openModal('worker-edit-modal');
}

function openDriverEdit(d) {
  document.getElementById('dp-uid').value    = d.user_id || d.id;
  document.getElementById('dp-plate').value  = d.vehicle_plate || '';
  document.getElementById('dp-model').value  = d.vehicle_model || '';
  document.getElementById('dp-type').value   = d.vehicle_type || '';
  document.getElementById('dp-lic').value    = d.license_no || '';
  document.getElementById('dp-lexp').value   = d.license_expiry || '';
  document.getElementById('dp-ephone').value = d.emergency_phone || '';
  document.getElementById('dp-notes').value  = d.notes || '';
  openModal('driver-edit-modal');
}
</script>

<?php include __DIR__ . '/layout_end.php'; ?>