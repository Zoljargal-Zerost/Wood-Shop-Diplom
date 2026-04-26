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
        unset($_SESSION['user_role']);
        $_SESSION['toast'] = ['msg'=>'Role шинэчлэгдлээ.','type'=>'success','icon'=>'✅'];
        header('Location: admin.php?tab=users');
        exit;
    }

    // Хэрэглэгчийн мэдээлэл засах
    if ($act === 'edit_user' && can('edit_users')) {
        $uid   = (int)$_POST['user_id'];
        $ner   = trim($_POST['ner'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        if ($ner && $phone && $email) {
            $pdo->prepare('UPDATE users SET ner=?, phone=?, email=?, notes=? WHERE id=?')
                ->execute([$ner, $phone, $email, $notes, $uid]);
            $_SESSION['toast'] = ['msg'=>'Хэрэглэгчийн мэдээлэл шинэчлэгдлээ.','type'=>'success','icon'=>'✅'];
        }
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
        $lexp   = $_POST['license_expiry'] ?: null;
        $ephone = trim($_POST['emergency_phone'] ?? '');
        $notes  = trim($_POST['notes'] ?? '');

        // User байгаа эсэх шалгах
        $exists = $pdo->prepare('SELECT id FROM users WHERE id=?');
        $exists->execute([$uid]);
        if (!$exists->fetchColumn()) {
            $_SESSION['toast'] = ['msg'=>'Хэрэглэгч олдсонгүй. Role-г эхлээд Driver болгоно уу.','type'=>'error','icon'=>'❌'];
            header('Location: admin.php?tab=drivers');
            exit;
        }

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
  <button class="tab <?= $tab==='reports'?'active':'' ?>"    onclick="location='admin.php?tab=reports'">📈 Тайлан</button>
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

// Variant-уудыг бүх бүтээгдэхүүний хувьд татах
$allVariants = $pdo->query('SELECT * FROM product_variants ORDER BY product_id ASC, sort_order ASC')->fetchAll();
$variantsByProduct = [];
foreach ($allVariants as $v) {
    $variantsByProduct[$v['product_id']][] = $v;
}
?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
  <div></div>
  <button class="btn btn-primary" onclick="openModal('prod-add-modal')">➕ Шинэ бүтээгдэхүүн</button>
</div>

<?php foreach ($allProducts as $p): ?>
<div class="card" style="margin-bottom:20px">
  <!-- Бүтээгдэхүүний header -->
  <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
    <div style="display:flex;align-items:center;gap:12px">
      <span style="font-size:28px"><?= $p['image_path'] && file_exists(__DIR__.'/../../'.$p['image_path'])
        ? '<img src="/Wood-shop/'.htmlspecialchars($p['image_path']).'" style="width:36px;height:36px;object-fit:cover;border-radius:8px">'
        : htmlspecialchars($p['emoji'] ?? '🪵') ?></span>
      <div>
        <div class="card-title" style="margin:0"><?= htmlspecialchars($p['name']) ?></div>
        <small style="color:var(--muted)">Төрөл: <?= htmlspecialchars($p['type']) ?></small>
      </div>
      <span class="badge" style="background:<?= $p['is_active']?'#eaf3de':'#fcebeb' ?>;color:<?= $p['is_active']?'#27500a':'#791f1f' ?>">
        <?= $p['is_active']?'Идэвхтэй':'Нуугдсан' ?>
      </span>
    </div>
    <div style="display:flex;gap:6px">
      <button class="btn btn-outline btn-sm" onclick="openProdEdit(<?= htmlspecialchars(json_encode($p)) ?>)">✏️ Засах</button>
      <form method="POST" action="/Wood-shop/product_action.php" style="display:inline">
        <input type="hidden" name="act" value="toggle">
        <input type="hidden" name="id" value="<?= $p['id'] ?>">
        <input type="hidden" name="redirect" value="/Wood-shop/dashboard/admin.php?tab=products">
        <button class="btn btn-outline btn-sm"><?= $p['is_active']?'🙈 Нуух':'👁 Идэвхжүүлэх' ?></button>
      </form>
      <form method="POST" action="/Wood-shop/product_action.php" style="display:inline" onsubmit="return confirm('Бүх variant-тай хамт устгах уу?')">
        <input type="hidden" name="act" value="delete">
        <input type="hidden" name="id" value="<?= $p['id'] ?>">
        <input type="hidden" name="redirect" value="/Wood-shop/dashboard/admin.php?tab=products">
        <button class="btn btn-danger btn-sm">🗑️</button>
      </form>
    </div>
  </div>

  <!-- Variant жагсаалт -->
  <div class="card-body" style="padding:16px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
      <div style="font-size:12px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.05em">Хэмжээ / Үнэ</div>
      <button class="btn btn-primary btn-sm" onclick="openVariantAdd(<?= $p['id'] ?>, '<?= htmlspecialchars($p['name']) ?>')">+ Variant нэмэх</button>
    </div>

    <?php if (empty($variantsByProduct[$p['id']])): ?>
      <div style="color:var(--muted);font-size:13px;padding:12px 0;text-align:center">Variant байхгүй байна. Нэмнэ үү.</div>
    <?php else: ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:10px">
      <?php foreach ($variantsByProduct[$p['id']] as $v): ?>
      <div style="background:var(--card);border:1.5px solid var(--border);border-radius:10px;padding:14px;display:flex;flex-direction:column;gap:0;box-shadow:0 1px 4px rgba(92,61,30,0.06)">
        <!-- Нэр -->
        <div style="font-size:13px;font-weight:700;color:var(--text);margin-bottom:10px;line-height:1.4;border-bottom:1px solid var(--border);padding-bottom:8px"><?= htmlspecialchars($v['name']) ?></div>
        <!-- Үнэ мөрүүд -->
        <div style="display:flex;flex-direction:column;gap:5px;margin-bottom:10px">
          <?php if ($v['unit_price']): ?>
          <div style="display:flex;justify-content:space-between;font-size:12px;align-items:center">
            <span style="color:var(--muted)">Ширхэг</span>
            <span style="font-weight:700;color:var(--accent)"><?= number_format($v['unit_price']) ?>₮</span>
          </div>
          <?php endif; ?>
          <?php if ($v['cube_price']): ?>
          <div style="display:flex;justify-content:space-between;font-size:12px;align-items:center">
            <span style="color:var(--muted)">Куб<?= $v['per_cube'] ? ' <span style="color:var(--muted);font-size:10px">('.$v['per_cube'].'ш)</span>' : '' ?></span>
            <span style="font-weight:700;color:var(--accent)"><?= number_format($v['cube_price']) ?>₮</span>
          </div>
          <?php endif; ?>
          <?php if ($v['pack_price']): ?>
          <div style="display:flex;justify-content:space-between;font-size:12px;align-items:center">
            <span style="color:var(--muted)">Багц<?= $v['per_pack'] ? ' <span style="color:var(--muted);font-size:10px">('.$v['per_pack'].'ш)</span>' : '' ?></span>
            <span style="font-weight:700;color:var(--accent)"><?= number_format($v['pack_price']) ?>₮</span>
          </div>
          <?php endif; ?>
          <?php if ($v['porter_price']): ?>
          <div style="display:flex;justify-content:space-between;font-size:12px;align-items:center">
            <span style="color:var(--muted)">Портер</span>
            <span style="font-weight:700;color:var(--accent)"><?= number_format($v['porter_price']) ?>₮</span>
          </div>
          <?php endif; ?>
        </div>
        <!-- Зарах хэлбэр + байдал -->
        <div style="display:flex;flex-wrap:wrap;gap:3px;margin-bottom:10px">
          <?= $v['sell_shirheg'] ? '<span style="font-size:10px;background:#e1f5ee;color:#085041;padding:2px 7px;border-radius:10px;font-weight:600">Ширхэг</span>' : '' ?>
          <?= $v['sell_kub']     ? '<span style="font-size:10px;background:#e6f1fb;color:#0c447c;padding:2px 7px;border-radius:10px;font-weight:600">Куб</span>' : '' ?>
          <?= $v['sell_bagts']   ? '<span style="font-size:10px;background:#faeeda;color:#633806;padding:2px 7px;border-radius:10px;font-weight:600">Багц</span>' : '' ?>
          <?= $v['sell_porter']  ? '<span style="font-size:10px;background:#faece7;color:#712b13;padding:2px 7px;border-radius:10px;font-weight:600">Портер</span>' : '' ?>
          <span style="font-size:10px;background:<?= $v['is_active']?'#eaf3de':'#fcebeb' ?>;color:<?= $v['is_active']?'#27500a':'#791f1f' ?>;padding:2px 7px;border-radius:10px;margin-left:auto;font-weight:600">
            <?= $v['is_active']?'✓ Идэвхтэй':'✗ Нуугдсан' ?>
          </span>
        </div>
        <!-- Товчнууд -->
        <div style="display:flex;gap:6px;border-top:1px solid var(--border);padding-top:8px;margin-top:auto">
          <button class="btn btn-outline btn-sm" style="flex:1;justify-content:center"
            onclick="openVariantEdit(<?= htmlspecialchars(json_encode($v)) ?>)">✏️ Засах</button>
          <form method="POST" action="/Wood-shop/dashboard/variant_action.php" style="display:inline" onsubmit="return confirm('Устгах уу?')">
            <input type="hidden" name="act" value="delete">
            <input type="hidden" name="id" value="<?= $v['id'] ?>">
            <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
            <button class="btn btn-danger btn-sm">🗑️</button>
          </form>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; ?>

<!-- Add product modal -->
<!-- ── Variant нэмэх modal ── -->
<div class="modal-overlay" id="variant-add-modal">
  <div class="modal-box" style="max-width:600px">
    <button class="modal-close" onclick="closeModal('variant-add-modal')">&times;</button>
    <div class="modal-title">➕ Variant нэмэх — <span id="va-prod-name"></span></div>
    <form method="POST" action="/Wood-shop/dashboard/variant_action.php">
      <input type="hidden" name="act" value="add">
      <input type="hidden" name="product_id" id="va-pid">
      <div class="form-grid">
        <div class="form-group full"><label>Нэр * (жишээ: 5-ийн банз (5×15×4м))</label><input type="text" name="name" required placeholder="5-ийн банз (5×15×4м)"></div>
        <div class="form-group"><label>Зузаан (см)</label><input type="number" name="zuzaan_cm" step="0.1" min="0"></div>
        <div class="form-group"><label>Өргөн (см)</label><input type="number" name="urgun_cm" step="0.1" min="0"></div>
        <div class="form-group"><label>Урт (м)</label><input type="number" name="urt_m" step="0.1" min="0"></div>

        <div style="grid-column:1/-1;border-top:1px solid var(--border);padding-top:12px;margin-top:4px">
          <div style="font-size:12px;font-weight:600;color:var(--muted);margin-bottom:8px;text-transform:uppercase">Үнэ тохируулах</div>
        </div>

        <div class="form-group">
          <label style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
            <input type="checkbox" name="sell_shirheg" value="1"> Ширхэгээр зарна
          </label>
          <input type="number" name="unit_price" min="0" placeholder="1 ширхэгийн үнэ ₮">
        </div>
        <div class="form-group">
          <label style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
            <input type="checkbox" name="sell_kub" value="1"> Куб метрээр зарна
          </label>
          <input type="number" name="cube_price" min="0" placeholder="1 м³ үнэ ₮">
          <input type="number" name="per_cube" min="0" placeholder="1 куб = ? ширхэг" style="margin-top:4px">
        </div>
        <div class="form-group">
          <label style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
            <input type="checkbox" name="sell_bagts" value="1"> Багцаар зарна
          </label>
          <input type="number" name="pack_price" min="0" placeholder="1 багцын үнэ ₮">
          <input type="number" name="per_pack" min="0" placeholder="1 багц = ? ширхэг" style="margin-top:4px">
        </div>
        <div class="form-group">
          <label style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
            <input type="checkbox" name="sell_porter" value="1"> Портераар зарна
          </label>
          <input type="number" name="porter_price" min="0" placeholder="1 портерийн үнэ ₮">
        </div>
        <div class="form-group" style="align-self:end">
          <label style="display:flex;gap:8px;text-transform:none;font-size:14px;font-weight:400">
            <input type="checkbox" name="is_active" value="1" checked> Идэвхтэй
          </label>
        </div>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;margin-top:16px">✅ Нэмэх</button>
    </form>
  </div>
</div>

<!-- ── Variant засах modal ── -->
<div class="modal-overlay" id="variant-edit-modal">
  <div class="modal-box" style="max-width:600px">
    <button class="modal-close" onclick="closeModal('variant-edit-modal')">&times;</button>
    <div class="modal-title">✏️ Variant засах</div>
    <form method="POST" action="/Wood-shop/dashboard/variant_action.php">
      <input type="hidden" name="act" value="edit">
      <input type="hidden" name="id" id="ve-id">
      <input type="hidden" name="product_id" id="ve-pid">
      <div class="form-grid">
        <div class="form-group full"><label>Нэр *</label><input type="text" name="name" id="ve-name" required></div>
        <div class="form-group"><label>Зузаан (см)</label><input type="number" name="zuzaan_cm" id="ve-zuzaan" step="0.1" min="0"></div>
        <div class="form-group"><label>Өргөн (см)</label><input type="number" name="urgun_cm" id="ve-urgun" step="0.1" min="0"></div>
        <div class="form-group"><label>Урт (м)</label><input type="number" name="urt_m" id="ve-urt" step="0.1" min="0"></div>

        <div style="grid-column:1/-1;border-top:1px solid var(--border);padding-top:12px;margin-top:4px">
          <div style="font-size:12px;font-weight:600;color:var(--muted);margin-bottom:8px;text-transform:uppercase">Үнэ тохируулах</div>
        </div>

        <div class="form-group">
          <label style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
            <input type="checkbox" name="sell_shirheg" value="1" id="ve-ss"> Ширхэгээр
          </label>
          <input type="number" name="unit_price" id="ve-up" min="0" placeholder="1 ширхэгийн үнэ ₮">
        </div>
        <div class="form-group">
          <label style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
            <input type="checkbox" name="sell_kub" value="1" id="ve-sk"> Куб метрээр
          </label>
          <input type="number" name="cube_price" id="ve-cp" min="0" placeholder="1 м³ үнэ ₮">
          <input type="number" name="per_cube" id="ve-pc" min="0" placeholder="1 куб = ? ширхэг" style="margin-top:4px">
        </div>
        <div class="form-group">
          <label style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
            <input type="checkbox" name="sell_bagts" value="1" id="ve-sb"> Багцаар
          </label>
          <input type="number" name="pack_price" id="ve-pp" min="0" placeholder="1 багцын үнэ ₮">
          <input type="number" name="per_pack" id="ve-pb" min="0" placeholder="1 багц = ? ширхэг" style="margin-top:4px">
        </div>
        <div class="form-group">
          <label style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
            <input type="checkbox" name="sell_porter" value="1" id="ve-spo"> Портераар
          </label>
          <input type="number" name="porter_price" id="ve-ppo" min="0" placeholder="1 портерийн үнэ ₮">
        </div>
        <div class="form-group" style="align-self:end">
          <label style="display:flex;gap:8px;text-transform:none;font-size:14px;font-weight:400">
            <input type="checkbox" name="is_active" value="1" id="ve-active"> Идэвхтэй
          </label>
        </div>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;margin-top:16px">💾 Хадгалах</button>
    </form>
  </div>
</div>

<div class="modal-overlay" id="prod-add-modal">
  <div class="modal-box" style="max-width:640px">
    <button class="modal-close" onclick="closeModal('prod-add-modal')">&times;</button>
    <div class="modal-title">➕ Шинэ бүтээгдэхүүн</div>
    <form method="POST" action="/Wood-shop/product_action.php" enctype="multipart/form-data">
      <input type="hidden" name="act" value="add">
      <input type="hidden" name="redirect" value="/Wood-shop/dashboard/admin.php?tab=products">
      <input type="hidden" name="price_label" value="Variant-аас авах">
      <input type="hidden" name="sort_order" value="0">
      <div class="form-grid">
        <div class="form-group"><label>Нэр *</label><input type="text" name="name" required placeholder="Жишээ: Банз"></div>
        <div class="form-group"><label>Төрөл (латин, зайгүй) *</label><input type="text" name="type" required placeholder="Жишээ: banz"></div>
        <div class="form-group"><label>Emoji</label><input type="text" name="emoji" value="🪵" maxlength="4"></div>
        <div class="form-group" style="align-self:end"><label style="display:flex;gap:8px;text-transform:none;font-size:14px;font-weight:400"><input type="checkbox" name="is_active" checked> Идэвхтэй</label></div>
        <div class="form-group full"><label>Тайлбар</label><textarea name="description" rows="3" placeholder="Бүтээгдэхүүний дэлгэрэнгүй мэдээлэл..."></textarea></div>
        <div class="form-group full"><label>Зураг (заавал биш)</label><input type="file" name="image" accept="image/*"></div>
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
      <input type="hidden" name="price_label" value="Variant-аас авах">
      <input type="hidden" name="sort_order" value="0">
      <div class="form-grid">
        <div class="form-group"><label>Нэр *</label><input type="text" name="name" id="pe-name" required></div>
        <div class="form-group"><label>Төрөл (латин, зайгүй)</label><input type="text" name="type" id="pe-type" required></div>
        <div class="form-group"><label>Emoji</label><input type="text" name="emoji" id="pe-emoji" maxlength="4"></div>
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
  document.getElementById('pe-desc').value  = p.description||'';
  document.getElementById('pe-active').checked = p.is_active==1;
  var prev = document.getElementById('pe-img-prev');
  if (prev) prev.innerHTML = p.image_path ? '<img src="/Wood-shop/'+p.image_path+'" style="max-height:70px;border-radius:8px">' : '';
  openModal('prod-edit-modal');
}

function openVariantAdd(pid, pname) {
  document.getElementById('va-pid').value       = pid;
  document.getElementById('va-prod-name').textContent = pname;
  openModal('variant-add-modal');
}

function openVariantEdit(v) {
  document.getElementById('ve-id').value    = v.id;
  document.getElementById('ve-pid').value   = v.product_id;
  document.getElementById('ve-name').value  = v.name || '';
  document.getElementById('ve-zuzaan').value = v.zuzaan_cm || '';
  document.getElementById('ve-urgun').value  = v.urgun_cm  || '';
  document.getElementById('ve-urt').value    = v.urt_m     || '';
  document.getElementById('ve-up').value    = v.unit_price   || '';
  document.getElementById('ve-cp').value    = v.cube_price   || '';
  document.getElementById('ve-pc').value    = v.per_cube     || '';
  document.getElementById('ve-pp').value    = v.pack_price   || '';
  document.getElementById('ve-pb').value    = v.per_pack     || '';
  document.getElementById('ve-ppo').value   = v.porter_price || '';
  document.getElementById('ve-ss').checked  = v.sell_shirheg == 1;
  document.getElementById('ve-sk').checked  = v.sell_kub     == 1;
  document.getElementById('ve-sb').checked  = v.sell_bagts   == 1;
  document.getElementById('ve-spo').checked = v.sell_porter  == 1;
  document.getElementById('ve-active').checked = v.is_active == 1;
  openModal('variant-edit-modal');
}

function openUserEdit(u) {
  document.getElementById('ue-id').value    = u.id;
  document.getElementById('ue-ner').value   = u.ner||'';
  document.getElementById('ue-phone').value = u.phone||'';
  document.getElementById('ue-email').value = u.email||'';
  document.getElementById('ue-notes').value = u.notes||'';
  openModal('user-edit-modal');
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
            <!-- Мэдээлэл засах -->
            <button class="btn btn-outline btn-sm" onclick="openUserEdit(<?= htmlspecialchars(json_encode(['id'=>$u['id'],'ner'=>$u['ner'],'phone'=>$u['phone'],'email'=>$u['email'],'notes'=>$u['notes']])) ?>)">✏️</button>
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

<!-- User edit modal -->
<div class="modal-overlay" id="user-edit-modal">
  <div class="modal-box" style="max-width:480px">
    <button class="modal-close" onclick="closeModal('user-edit-modal')">&times;</button>
    <div class="modal-title">✏️ Хэрэглэгчийн мэдээлэл засах</div>
    <form method="POST">
      <input type="hidden" name="act" value="edit_user">
      <input type="hidden" name="user_id" id="ue-id">
      <div class="form-grid">
        <div class="form-group"><label>Нэр *</label><input type="text" name="ner" id="ue-ner" required></div>
        <div class="form-group"><label>Утас *</label><input type="tel" name="phone" id="ue-phone" required></div>
        <div class="form-group full"><label>Имэйл</label><input type="email" name="email" id="ue-email" required></div>
        <div class="form-group full"><label>Admin тайлбар</label><textarea name="notes" id="ue-notes" rows="2" placeholder="Дотоод тэмдэглэл..."></textarea></div>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;margin-top:12px">💾 Хадгалах</button>
    </form>
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
      <thead><tr><th>Нэр</th><th>Имэйл</th><th>Утас</th><th>Албан тушаал</th><th>Хэлтэс</th><th>Статус</th><th>Үйлдэл</th></tr></thead>
      <tbody>
      <?php foreach ($workers as $w): ?>
        <tr>
          <td>
            <strong><?= htmlspecialchars($w['ner']) ?></strong>
            <?php if ($w['wp_notes']): ?><br><small style="color:var(--muted)"><?= htmlspecialchars(mb_substr($w['wp_notes'],0,30)) ?></small><?php endif; ?>
          </td>
          <td><?= htmlspecialchars($w['email']) ?></td>
          <td><a href="tel:<?= htmlspecialchars($w['phone']) ?>" style="color:var(--accent);font-weight:600"><?= htmlspecialchars($w['phone']) ?></a></td>
          <td><?= htmlspecialchars($w['job_title'] ?? '—') ?></td>
          <td><?= htmlspecialchars($w['department'] ?? '—') ?></td>
          <td><span class="badge" style="background:<?= $w['is_active']?'#eaf3de':'#fcebeb' ?>;color:<?= $w['is_active']?'#27500a':'#791f1f' ?>"><?= $w['is_active']?'Идэвхтэй':'Идэвхгүй' ?></span></td>
          <td style="display:flex;gap:6px">
            <button class="btn btn-outline btn-sm" onclick="openWorkerEdit(<?= htmlspecialchars(json_encode($w)) ?>)">✏️ Засах</button>
            <button class="btn btn-outline btn-sm" onclick="openUserEdit(<?= htmlspecialchars(json_encode(['id'=>$w['id'],'ner'=>$w['ner'],'phone'=>$w['phone'],'email'=>$w['email'],'notes'=>$w['notes']])) ?>)">👤 Мэдээлэл</button>
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
    SELECT u.id as uid, u.ner, u.phone, u.email,
           dp.vehicle_plate, dp.vehicle_model, dp.vehicle_type,
           dp.license_no, dp.license_expiry, dp.emergency_phone,
           dp.notes as dp_notes
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
              onclick="openDriverEdit(<?= htmlspecialchars(json_encode([
                'uid'            => $d['uid'],
                'vehicle_plate'  => $d['vehicle_plate'],
                'vehicle_model'  => $d['vehicle_model'],
                'vehicle_type'   => $d['vehicle_type'],
                'license_no'     => $d['license_no'],
                'license_expiry' => $d['license_expiry'],
                'emergency_phone'=> $d['emergency_phone'],
                'notes'          => $d['dp_notes'],
              ])) ?>)">
              Засах
            </button>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php elseif ($tab === 'reports'): ?>
<!-- ══ REPORTS TAB ══ -->
<?php
// Огноо шүүлтүүр
$dateFrom = $_GET['from'] ?? date('Y-m-01'); // Сарын эхний өдөр
$dateTo   = $_GET['to']   ?? date('Y-m-d');  // Өнөөдөр

// Нийт захиалгууд (огноогоор)
$reportOrders = $pdo->prepare('
    SELECT o.*, u.ner as user_ner, u.phone as user_phone, u.email as user_email
    FROM orders o
    JOIN users u ON o.user_id = u.id
    WHERE DATE(o.created_at) BETWEEN ? AND ?
    ORDER BY o.created_at DESC
');
$reportOrders->execute([$dateFrom, $dateTo]);
$reportOrders = $reportOrders->fetchAll();

// Захиалгын дэлгэрэнгүй (order_items)
$orderIds = array_column($reportOrders, 'id');
$reportItems = [];
if ($orderIds) {
    $in = implode(',', array_fill(0, count($orderIds), '?'));
    $itemStmt = $pdo->prepare("
        SELECT oi.*, o.created_at as order_date, o.status as order_status,
               u.ner as user_ner
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        JOIN users u ON o.user_id = u.id
        WHERE oi.order_id IN ($in)
        ORDER BY o.created_at DESC
    ");
    $itemStmt->execute($orderIds);
    $reportItems = $itemStmt->fetchAll();
}

// Статистик тооцоо
$totalRevenue  = 0;
$totalOrders   = count($reportOrders);
$statusCounts  = [];
$productSales  = [];
foreach ($reportOrders as $o) {
    $totalRevenue += (int)($o['total_price'] ?? 0);
    $s = $o['status'];
    $statusCounts[$s] = ($statusCounts[$s] ?? 0) + 1;
}
foreach ($reportItems as $item) {
    $pname = $item['product_name'];
    if (!isset($productSales[$pname])) {
        $productSales[$pname] = ['qty' => 0, 'revenue' => 0, 'count' => 0];
    }
    $productSales[$pname]['qty']     += $item['qty'];
    $productSales[$pname]['revenue'] += $item['subtotal'];
    $productSales[$pname]['count']++;
}
arsort($productSales);
?>

<!-- Огноо шүүлтүүр -->
<div class="card" style="margin-bottom:20px">
  <div class="card-body" style="padding:16px">
    <form method="GET" style="display:flex;align-items:flex-end;gap:12px;flex-wrap:wrap">
      <input type="hidden" name="tab" value="reports">
      <div class="form-group" style="gap:4px">
        <label>Эхлэх огноо</label>
        <input type="date" name="from" value="<?= htmlspecialchars($dateFrom) ?>">
      </div>
      <div class="form-group" style="gap:4px">
        <label>Дуусах огноо</label>
        <input type="date" name="to" value="<?= htmlspecialchars($dateTo) ?>">
      </div>
      <button type="submit" class="btn btn-primary">🔍 Шүүх</button>
      <a href="admin.php?tab=reports&from=<?= date('Y-m-01') ?>&to=<?= date('Y-m-d') ?>" class="btn btn-outline">Энэ сар</a>
      <a href="admin.php?tab=reports&from=<?= date('Y-m-d', strtotime('-7 days')) ?>&to=<?= date('Y-m-d') ?>" class="btn btn-outline">7 хоног</a>
      <a href="admin.php?tab=reports&from=<?= date('Y-01-01') ?>&to=<?= date('Y-m-d') ?>" class="btn btn-outline">Энэ жил</a>
    </form>
  </div>
</div>

<!-- Товч статистик -->
<div class="stat-grid">
  <div class="stat-card">
    <div class="stat-icon">📦</div>
    <div class="stat-num"><?= $totalOrders ?></div>
    <div class="stat-lbl">Нийт захиалга</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">💰</div>
    <div class="stat-num"><?= number_format($totalRevenue) ?>₮</div>
    <div class="stat-lbl">Нийт орлого</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">✅</div>
    <div class="stat-num"><?= ($statusCounts['delivered'] ?? 0) ?></div>
    <div class="stat-lbl">Хүргэгдсэн</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">⏳</div>
    <div class="stat-num"><?= ($statusCounts['pending'] ?? 0) ?></div>
    <div class="stat-lbl">Хүлээгдэж буй</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">❌</div>
    <div class="stat-num"><?= ($statusCounts['cancelled'] ?? 0) ?></div>
    <div class="stat-lbl">Цуцлагдсан</div>
  </div>
</div>

<!-- Бүтээгдэхүүн борлуулалт -->
<?php if ($productSales): ?>
<div class="card" style="margin-bottom:20px">
  <div class="card-header"><div class="card-title">🌲 Бүтээгдэхүүнээр</div></div>
  <div class="card-body">
    <table>
      <thead><tr><th>Бүтээгдэхүүн</th><th>Захиалга тоо</th><th>Нийт тоо ширхэг</th><th>Нийт орлого</th></tr></thead>
      <tbody>
      <?php foreach ($productSales as $pname => $ps): ?>
        <tr>
          <td><strong><?= htmlspecialchars($pname) ?></strong></td>
          <td><?= $ps['count'] ?></td>
          <td><?= $ps['qty'] ?></td>
          <td style="font-weight:700;color:var(--accent)"><?= number_format($ps['revenue']) ?>₮</td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- Захиалгын дэлгэрэнгүй жагсаалт -->
<div class="card" id="report-table">
  <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
    <div class="card-title">📋 Захиалгын дэлгэрэнгүй (<?= $dateFrom ?> → <?= $dateTo ?>)</div>
    <div style="display:flex;gap:8px">
      <button class="btn btn-outline btn-sm" onclick="openPrintReport()">🖨️ Хэвлэх</button>
      <button class="btn btn-primary btn-sm" onclick="downloadCSV()">📥 CSV татах</button>
    </div>
  </div>
  <div class="card-body">
    <?php if (empty($reportOrders)): ?>
      <div style="text-align:center;padding:40px;color:var(--muted)">Энэ хугацаанд захиалга байхгүй байна.</div>
    <?php else: ?>
    <table id="report-data-table">
      <thead>
        <tr>
          <th>#</th>
          <th>Огноо</th>
          <th>Хэрэглэгч</th>
          <th>Утас</th>
          <th>Бүтээгдэхүүн</th>
          <th>Тоо/Хэмжээ</th>
          <th>Нийт дүн</th>
          <th>Статус</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($reportOrders as $o):
        $c = statusColor($o['status']);
      ?>
        <tr>
          <td>#<?= $o['id'] ?></td>
          <td><?= date('Y/m/d H:i', strtotime($o['created_at'])) ?></td>
          <td><strong><?= htmlspecialchars($o['user_ner']) ?></strong></td>
          <td><?= htmlspecialchars($o['user_phone']) ?></td>
          <td><?= htmlspecialchars($o['product']) ?></td>
          <td><?= $o['shirheg'] ?> · <?= htmlspecialchars($o['urt_m']) ?></td>
          <td style="font-weight:700;color:var(--accent)"><?= number_format($o['total_price'] ?? 0) ?>₮</td>
          <td><span class="badge" style="background:<?= $c ?>22;color:<?= $c ?>"><?= statusLabel($o['status']) ?></span></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr style="background:var(--bg-alt);font-weight:700">
          <td colspan="6" style="text-align:right">Нийт дүн:</td>
          <td style="color:var(--accent);font-size:16px"><?= number_format($totalRevenue) ?>₮</td>
          <td><?= $totalOrders ?> захиалга</td>
        </tr>
      </tfoot>
    </table>
    <?php endif; ?>
  </div>
</div>

<!-- Print стиль -->
<style>
@media print {
  .sidebar, .topbar, .tabs, form, .btn, .modal-overlay,
  .chat-widget, button { display: none !important; }
  .main-wrap { margin-left: 0 !important; }
  .content { padding: 0 !important; }
  .card { box-shadow: none !important; border: 1px solid #ddd !important; break-inside: avoid; }
  .stat-grid { grid-template-columns: repeat(5, 1fr) !important; }
  body { background: #fff !important; font-size: 12px !important; }
  table { font-size: 11px !important; }
  #report-table .card-header { justify-content: flex-start !important; }
  #report-table .card-header div:last-child { display: none !important; }
  @page { margin: 1cm; }
}
</style>

<script>
function openPrintReport() {
  var from = '<?= htmlspecialchars($dateFrom) ?>';
  var to   = '<?= htmlspecialchars($dateTo) ?>';
  window.open('report_print.php?from=' + from + '&to=' + to, '_blank');
}

function downloadCSV() {
  var table = document.getElementById('report-data-table');
  if (!table) { alert('Хүснэгт хоосон байна.'); return; }

  var rows = table.querySelectorAll('thead tr, tbody tr');
  var csv = '\uFEFF'; // BOM for Excel UTF-8

  rows.forEach(function(row) {
    var cols = row.querySelectorAll('th, td');
    var line = [];
    cols.forEach(function(col) {
      var text = col.textContent.trim().replace(/"/g, '""');
      line.push('"' + text + '"');
    });
    csv += line.join(',') + '\n';
  });

  var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
  var link = document.createElement('a');
  link.href = URL.createObjectURL(blob);
  link.download = 'tailan_<?= $dateFrom ?>_<?= $dateTo ?>.csv';
  link.click();
}
</script>

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
  document.getElementById('dp-uid').value    = d.uid;
  document.getElementById('dp-plate').value  = d.vehicle_plate  || '';
  document.getElementById('dp-model').value  = d.vehicle_model  || '';
  document.getElementById('dp-type').value   = d.vehicle_type   || '';
  document.getElementById('dp-lic').value    = d.license_no     || '';
  document.getElementById('dp-lexp').value   = d.license_expiry || '';
  document.getElementById('dp-ephone').value = d.emergency_phone|| '';
  document.getElementById('dp-notes').value  = d.notes          || '';
  openModal('driver-edit-modal');
}  document.getElementById('dp-plate').value  = d.vehicle_plate || '';
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