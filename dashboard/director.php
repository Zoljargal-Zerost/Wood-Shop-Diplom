<?php
// ============================================================
//  dashboard/director.php — Захирлын хянах самбар
//  ✅ Зөвхөн ХАРАХ эрхтэй — ямар ч зүйл өөрчилж чадахгүй
//  ✅ Бүх тайлан, ажилтны бүртгэл, захиалгын статистик
// ============================================================
require_once __DIR__ . '/../middleware.php';
requireLogin();
$role = loadUserRole($pdo);

if (!isRole('director')) {
    header('Location: /Wood-shop/dashboard/');
    exit;
}

$tab = $_GET['tab'] ?? 'dashboard';

// ── Бүх статистик ─────────────────────────────────────────
$stats = [];

// Захиалгын тоо статусаар
$orderStats = $pdo->query("
    SELECT status, COUNT(*) as cnt
    FROM orders
    GROUP BY status
")->fetchAll(PDO::FETCH_KEY_PAIR);

$stats['total_orders']     = array_sum($orderStats);
$stats['pending']          = $orderStats['pending']    ?? 0;
$stats['delivered']        = $orderStats['delivered']  ?? 0;
$stats['cancelled']        = $orderStats['cancelled']  ?? 0;
$stats['processing']       = ($orderStats['confirmed'] ?? 0) + ($orderStats['processing'] ?? 0) + ($orderStats['delivering'] ?? 0);

// Хэрэглэгч тоо
$stats['total_users']      = $pdo->query("SELECT COUNT(*) FROM users u JOIN roles r ON u.role_id=r.id WHERE r.slug='user'")->fetchColumn();
$stats['total_workers']    = $pdo->query("SELECT COUNT(*) FROM users u JOIN roles r ON u.role_id=r.id WHERE r.slug='worker'")->fetchColumn();
$stats['total_drivers']    = $pdo->query("SELECT COUNT(*) FROM users u JOIN roles r ON u.role_id=r.id WHERE r.slug='driver'")->fetchColumn();

// Өнөөдрийн захиалга
$stats['today_orders']     = $pdo->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at)=CURDATE()")->fetchColumn();

// Энэ сарын захиалга
$stats['month_orders']     = $pdo->query("SELECT COUNT(*) FROM orders WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())")->fetchColumn();

// Хамгийн идэвхтэй ажилтан (энэ сард)
$topWorker = $pdo->query("
    SELECT u.ner, COUNT(l.id) as action_count
    FROM worker_logs l
    JOIN users u ON l.worker_id=u.id
    WHERE MONTH(l.created_at)=MONTH(NOW())
    GROUP BY l.worker_id
    ORDER BY action_count DESC
    LIMIT 1
")->fetch();
$stats['top_worker']       = $topWorker['ner'] ?? '—';
$stats['top_worker_count'] = $topWorker['action_count'] ?? 0;

$pageTitle  = 'Захирлын хянах самбар';
$activePage = $tab;
include __DIR__ . '/layout.php';
?>

<!-- ── Tabs ── -->
<div class="tabs">
  <button class="tab <?= $tab==='dashboard'?'active':'' ?>"   onclick="location='director.php'">📊 Нүүр тайлан</button>
  <button class="tab <?= $tab==='orders'?'active':'' ?>"      onclick="location='director.php?tab=orders'">📦 Захиалгууд</button>
  <button class="tab <?= $tab==='workers'?'active':'' ?>"     onclick="location='director.php?tab=workers'">👷 Ажилтнууд</button>
  <button class="tab <?= $tab==='logs'?'active':'' ?>"        onclick="location='director.php?tab=logs'">📋 Үйл ажиллагаа</button>
  <button class="tab <?= $tab==='daily'?'active':'' ?>"       onclick="location='director.php?tab=daily'">📅 Өдрийн тайлан</button>
</div>

<!-- ════════════════════════════════════════════
     DASHBOARD TAB — Ерөнхий тойм
════════════════════════════════════════════ -->
<?php if ($tab === 'dashboard'): ?>

<!-- Stat cards -->
<div class="stat-grid" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr))">
  <div class="stat-card">
    <div class="stat-icon">📦</div>
    <div class="stat-num"><?= $stats['total_orders'] ?></div>
    <div class="stat-lbl">Нийт захиалга</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">📅</div>
    <div class="stat-num"><?= $stats['today_orders'] ?></div>
    <div class="stat-lbl">Өнөөдөр</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">📆</div>
    <div class="stat-num"><?= $stats['month_orders'] ?></div>
    <div class="stat-lbl">Энэ сар</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">⏳</div>
    <div class="stat-num"><?= $stats['pending'] ?></div>
    <div class="stat-lbl">Хүлээгдэж буй</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">✅</div>
    <div class="stat-num"><?= $stats['delivered'] ?></div>
    <div class="stat-lbl">Хүргэгдсэн</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">❌</div>
    <div class="stat-num"><?= $stats['cancelled'] ?></div>
    <div class="stat-lbl">Цуцлагдсан</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">👥</div>
    <div class="stat-num"><?= $stats['total_users'] ?></div>
    <div class="stat-lbl">Хэрэглэгч</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">👷</div>
    <div class="stat-num"><?= $stats['total_workers'] ?></div>
    <div class="stat-lbl">Ажилтан</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">🚚</div>
    <div class="stat-num"><?= $stats['total_drivers'] ?></div>
    <div class="stat-lbl">Жолооч</div>
  </div>
  <div class="stat-card" style="border-color:var(--accent)">
    <div class="stat-icon">🏆</div>
    <div class="stat-num" style="font-size:18px"><?= htmlspecialchars($stats['top_worker']) ?></div>
    <div class="stat-lbl">Идэвхтэй ажилтан (<?= $stats['top_worker_count'] ?> үйлдэл)</div>
  </div>
</div>

<!-- Захиалгын статус хувиарлал -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px">

  <!-- Статус breakdown -->
  <div class="card">
    <div class="card-header"><div class="card-title">📊 Захиалгын байдал</div></div>
    <div class="card-body" style="padding:20px">
      <?php
      $statusData = [
        'pending'    => ['label'=>'Хүлээгдэж буй',    'color'=>'#B7770D', 'cnt'=>$stats['pending']],
        'processing' => ['label'=>'Боловсруулж байна', 'color'=>'#1565C0', 'cnt'=>$stats['processing']],
        'delivered'  => ['label'=>'Хүргэгдсэн',       'color'=>'#27500A', 'cnt'=>$stats['delivered']],
        'cancelled'  => ['label'=>'Цуцлагдсан',       'color'=>'#791F1F', 'cnt'=>$stats['cancelled']],
      ];
      $total = $stats['total_orders'] ?: 1;
      foreach ($statusData as $s):
        $pct = round($s['cnt'] / $total * 100);
      ?>
        <div style="margin-bottom:14px">
          <div style="display:flex;justify-content:space-between;margin-bottom:5px">
            <span style="font-size:13px;font-weight:600"><?= $s['label'] ?></span>
            <span style="font-size:13px;color:var(--muted)"><?= $s['cnt'] ?> (<?= $pct ?>%)</span>
          </div>
          <div style="height:8px;background:var(--bg-alt);border-radius:4px;overflow:hidden">
            <div style="height:100%;width:<?= $pct ?>%;background:<?= $s['color'] ?>;border-radius:4px;transition:width .6s"></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Сарын захиалга (сүүлийн 6 сар) -->
  <div class="card">
    <div class="card-header"><div class="card-title">📈 Сарын захиалга (6 сар)</div></div>
    <div class="card-body" style="padding:20px">
      <?php
      $monthly = $pdo->query("
          SELECT DATE_FORMAT(created_at,'%Y-%m') as ym,
                 DATE_FORMAT(created_at,'%m-р сар') as label,
                 COUNT(*) as cnt
          FROM orders
          WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
          GROUP BY ym, label
          ORDER BY ym
      ")->fetchAll();
      $maxCnt = max(array_column($monthly, 'cnt') ?: [1]);
      foreach ($monthly as $m):
        $pct = round($m['cnt'] / $maxCnt * 100);
      ?>
        <div style="margin-bottom:12px">
          <div style="display:flex;justify-content:space-between;margin-bottom:4px">
            <span style="font-size:13px"><?= $m['label'] ?></span>
            <span style="font-size:13px;font-weight:700;color:var(--primary)"><?= $m['cnt'] ?></span>
          </div>
          <div style="height:6px;background:var(--bg-alt);border-radius:3px;overflow:hidden">
            <div style="height:100%;width:<?= $pct ?>%;background:var(--accent);border-radius:3px"></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Ажилтны идэвх энэ сард -->
<div class="card">
  <div class="card-header"><div class="card-title">👷 Ажилтны идэвх — энэ сар</div></div>
  <div class="card-body">
    <table>
      <thead>
        <tr><th>Ажилтан</th><th>Роль</th><th>Нийт үйлдэл</th><th>Захиалга</th><th>Идэвхийн хэмжээ</th></tr>
      </thead>
      <tbody>
      <?php
      $workerActivity = $pdo->query("
          SELECT u.ner, r.name as role_name, r.color,
                 COUNT(DISTINCT l.id) as log_count,
                 COUNT(DISTINCT l.order_id) as order_count
          FROM users u
          JOIN roles r ON u.role_id=r.id
          LEFT JOIN worker_logs l ON l.worker_id=u.id
              AND MONTH(l.created_at)=MONTH(NOW())
          WHERE r.slug IN ('worker','driver','manager')
            AND u.is_active=1
          GROUP BY u.id, u.ner, r.name, r.color
          ORDER BY log_count DESC
      ")->fetchAll();
      $maxLogs = max(array_column($workerActivity, 'log_count') ?: [1]);
      foreach ($workerActivity as $w):
        $pct = $maxLogs > 0 ? round($w['log_count'] / $maxLogs * 100) : 0;
      ?>
        <tr>
          <td><strong><?= htmlspecialchars($w['ner']) ?></strong></td>
          <td><span class="badge" style="background:<?= $w['color'] ?>;color:#fff"><?= htmlspecialchars($w['role_name']) ?></span></td>
          <td style="font-weight:700;color:var(--primary)"><?= $w['log_count'] ?></td>
          <td><?= $w['order_count'] ?></td>
          <td style="min-width:120px">
            <div style="height:8px;background:var(--bg-alt);border-radius:4px;overflow:hidden">
              <div style="height:100%;width:<?= $pct ?>%;background:var(--accent);border-radius:4px"></div>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ════════════════════════════════════════════
     ORDERS TAB — Бүх захиалга (зөвхөн харах)
════════════════════════════════════════════ -->
<?php elseif ($tab === 'orders'): ?>

<?php
// Шүүлтүүр
$filterStatus = $_GET['status'] ?? '';
$filterDate   = $_GET['date']   ?? '';
$where = ['1=1'];
$params = [];
if ($filterStatus) { $where[] = 'o.status=?'; $params[] = $filterStatus; }
if ($filterDate)   { $where[] = 'DATE(o.created_at)=?'; $params[] = $filterDate; }

$stmt = $pdo->prepare("
    SELECT o.*, u.ner as user_ner, u.phone as user_phone,
           w.ner as worker_ner, d.ner as driver_ner
    FROM orders o
    JOIN users u ON o.user_id=u.id
    LEFT JOIN users w ON o.worker_id=w.id
    LEFT JOIN users d ON o.driver_id=d.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY o.created_at DESC
");
$stmt->execute($params);
$orders = $stmt->fetchAll();
?>

<!-- Шүүлтүүр -->
<div class="card" style="margin-bottom:20px">
  <div class="card-body" style="padding:16px">
    <form method="GET" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
      <input type="hidden" name="tab" value="orders">
      <div class="form-group" style="margin:0">
        <label>Статус</label>
        <select name="status" style="min-width:160px">
          <option value="">Бүгд</option>
          <option value="pending"    <?= $filterStatus==='pending'?'selected':'' ?>>⏳ Хүлээгдэж байна</option>
          <option value="confirmed"  <?= $filterStatus==='confirmed'?'selected':'' ?>>✅ Баталгаажсан</option>
          <option value="processing" <?= $filterStatus==='processing'?'selected':'' ?>>🔨 Бэлтгэж байна</option>
          <option value="delivering" <?= $filterStatus==='delivering'?'selected':'' ?>>🚚 Хүргэлтэнд</option>
          <option value="delivered"  <?= $filterStatus==='delivered'?'selected':'' ?>>📦 Хүргэгдсэн</option>
          <option value="cancelled"  <?= $filterStatus==='cancelled'?'selected':'' ?>>❌ Цуцлагдсан</option>
        </select>
      </div>
      <div class="form-group" style="margin:0">
        <label>Огноо</label>
        <input type="date" name="date" value="<?= htmlspecialchars($filterDate) ?>">
      </div>
      <button type="submit" class="btn btn-primary">🔍 Шүүх</button>
      <a href="director.php?tab=orders" class="btn btn-outline">Цэвэрлэх</a>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <div class="card-title">📦 Захиалгууд (<?= count($orders) ?>)</div>
    <span style="font-size:13px;color:var(--muted)">👁 Зөвхөн харах горим</span>
  </div>
  <div class="card-body">
    <table>
      <thead>
        <tr><th>#</th><th>Хэрэглэгч</th><th>Утас</th><th>Бүтээгдэхүүн</th><th>Хэмжээ</th><th>Ажилтан</th><th>Жолооч</th><th>Статус</th><th>Огноо</th></tr>
      </thead>
      <tbody>
      <?php foreach ($orders as $o):
        $c = statusColor($o['status']);
      ?>
        <tr>
          <td style="color:var(--muted)">#<?= $o['id'] ?></td>
          <td><strong><?= htmlspecialchars($o['user_ner']) ?></strong></td>
          <td><?= htmlspecialchars($o['user_phone']) ?></td>
          <td><?= htmlspecialchars($o['product']) ?></td>
          <td style="font-size:12px;color:var(--muted)"><?= $o['shirheg'] ?>ш · <?= $o['urt_m'] ?>м · <?= $o['urgun_cm'] ?>×<?= $o['zuzaan_cm'] ?>см</td>
          <td><?= htmlspecialchars($o['worker_ner'] ?? '—') ?></td>
          <td><?= htmlspecialchars($o['driver_ner'] ?? '—') ?></td>
          <td><span class="badge" style="background:<?= $c ?>22;color:<?= $c ?>"><?= statusLabel($o['status']) ?></span></td>
          <td><?= date('Y/m/d H:i', strtotime($o['created_at'])) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ════════════════════════════════════════════
     WORKERS TAB — Бүх ажилтан (зөвхөн харах)
════════════════════════════════════════════ -->
<?php elseif ($tab === 'workers'): ?>
<?php
$workers = $pdo->query("
    SELECT u.id, u.ner, u.email, u.phone, u.is_active, u.created_at,
           r.name as role_name, r.color as role_color, r.slug as role_slug,
           wp.job_title, wp.department,
           dp.vehicle_plate, dp.vehicle_model, dp.license_expiry, dp.emergency_phone,
           COUNT(DISTINCT o.id) as order_count,
           COUNT(DISTINCT l.id) as log_count
    FROM users u
    JOIN roles r ON u.role_id=r.id
    LEFT JOIN worker_profiles wp ON wp.user_id=u.id
    LEFT JOIN driver_profiles dp ON dp.user_id=u.id
    LEFT JOIN orders o ON (o.worker_id=u.id OR o.driver_id=u.id)
    LEFT JOIN worker_logs l ON l.worker_id=u.id
    WHERE r.slug IN ('worker','driver','manager')
    GROUP BY u.id, u.ner, u.email, u.phone, u.is_active, u.created_at,
             r.name, r.color, r.slug, wp.job_title, wp.department,
             dp.vehicle_plate, dp.vehicle_model, dp.license_expiry, dp.emergency_phone
    ORDER BY log_count DESC
")->fetchAll();
?>
<div class="card">
  <div class="card-header">
    <div class="card-title">👷 Бүх ажилтан (<?= count($workers) ?>)</div>
    <span style="font-size:13px;color:var(--muted)">👁 Зөвхөн харах горим</span>
  </div>
  <div class="card-body">
    <table>
      <thead>
        <tr><th>Нэр</th><th>Роль</th><th>Утас</th><th>Имэйл</th><th>Албан тушаал</th><th>Машин</th><th>Захиалга</th><th>Нийт үйлдэл</th><th>Статус</th><th>Бүртгэсэн</th></tr>
      </thead>
      <tbody>
      <?php foreach ($workers as $w): ?>
        <tr>
          <td>
            <strong><?= htmlspecialchars($w['ner']) ?></strong>
          </td>
          <td><span class="badge" style="background:<?= $w['role_color'] ?>;color:#fff"><?= htmlspecialchars($w['role_name']) ?></span></td>
          <td><?= htmlspecialchars($w['phone']) ?></td>
          <td style="font-size:12px"><?= htmlspecialchars($w['email']) ?></td>
          <td>
            <?php if ($w['role_slug'] === 'driver'): ?>
              🚚 Жолооч
            <?php else: ?>
              <?= htmlspecialchars($w['job_title'] ?? '—') ?>
              <?php if ($w['department']): ?><br><small style="color:var(--muted)"><?= htmlspecialchars($w['department']) ?></small><?php endif; ?>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($w['vehicle_plate']): ?>
              <strong><?= htmlspecialchars($w['vehicle_plate']) ?></strong>
              <?php if ($w['vehicle_model']): ?><br><small style="color:var(--muted)"><?= htmlspecialchars($w['vehicle_model']) ?></small><?php endif; ?>
            <?php else: ?>—<?php endif; ?>
          </td>
          <td style="text-align:center;font-weight:700"><?= $w['order_count'] ?></td>
          <td style="text-align:center">
            <span style="font-weight:800;font-size:16px;color:var(--primary)"><?= $w['log_count'] ?></span>
          </td>
          <td>
            <span class="badge" style="background:<?= $w['is_active']?'#eaf3de':'#fcebeb' ?>;color:<?= $w['is_active']?'#27500a':'#791f1f' ?>">
              <?= $w['is_active']?'Идэвхтэй':'Идэвхгүй' ?>
            </span>
          </td>
          <td style="font-size:12px;color:var(--muted)"><?= date('Y/m/d', strtotime($w['created_at'])) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ════════════════════════════════════════════
     LOGS TAB — Цаг бүрийн үйл ажиллагаа
════════════════════════════════════════════ -->
<?php elseif ($tab === 'logs'): ?>

<?php
$filterWorker = (int)($_GET['worker_id'] ?? 0);
$filterLogDate = $_GET['log_date'] ?? '';

$logWhere = ['1=1'];
$logParams = [];
if ($filterWorker)  { $logWhere[] = 'l.worker_id=?'; $logParams[] = $filterWorker; }
if ($filterLogDate) { $logWhere[] = 'DATE(l.created_at)=?'; $logParams[] = $filterLogDate; }

$allWorkersList = $pdo->query("
    SELECT u.id, u.ner FROM users u
    JOIN roles r ON u.role_id=r.id
    WHERE r.slug IN ('worker','driver','manager')
    ORDER BY u.ner
")->fetchAll();

$stmt = $pdo->prepare("
    SELECT l.*, u.ner as worker_name, r.name as role_name, r.color
    FROM worker_logs l
    JOIN users u ON l.worker_id=u.id
    JOIN roles r ON u.role_id=r.id
    WHERE " . implode(' AND ', $logWhere) . "
    ORDER BY l.created_at DESC
    LIMIT 500
");
$stmt->execute($logParams);
$logs = $stmt->fetchAll();
?>

<!-- Шүүлтүүр -->
<div class="card" style="margin-bottom:20px">
  <div class="card-body" style="padding:16px">
    <form method="GET" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
      <input type="hidden" name="tab" value="logs">
      <div class="form-group" style="margin:0">
        <label>Ажилтан</label>
        <select name="worker_id" style="min-width:180px">
          <option value="">Бүгд</option>
          <?php foreach ($allWorkersList as $w): ?>
            <option value="<?= $w['id'] ?>" <?= $filterWorker==$w['id']?'selected':'' ?>>
              <?= htmlspecialchars($w['ner']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" style="margin:0">
        <label>Огноо</label>
        <input type="date" name="log_date" value="<?= htmlspecialchars($filterLogDate) ?>">
      </div>
      <button type="submit" class="btn btn-primary">🔍 Шүүх</button>
      <a href="director.php?tab=logs" class="btn btn-outline">Цэвэрлэх</a>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <div class="card-title">📋 Ажилтны үйл ажиллагааны бүртгэл (<?= count($logs) ?>)</div>
    <span style="font-size:13px;color:var(--muted)">👁 Зөвхөн харах горим</span>
  </div>
  <div class="card-body">
    <table>
      <thead>
        <tr><th>Цаг</th><th>Ажилтан</th><th>Роль</th><th>Үйлдэл</th><th>Захиалга</th><th>IP хаяг</th></tr>
      </thead>
      <tbody>
      <?php foreach ($logs as $l): ?>
        <tr>
          <td style="white-space:nowrap;font-size:12px;color:var(--muted)"><?= date('Y/m/d H:i:s', strtotime($l['created_at'])) ?></td>
          <td><strong><?= htmlspecialchars($l['worker_name']) ?></strong></td>
          <td><span class="badge" style="background:<?= $l['color'] ?>;color:#fff;font-size:11px"><?= htmlspecialchars($l['role_name']) ?></span></td>
          <td><?= htmlspecialchars($l['action']) ?></td>
          <td><?= $l['order_id'] ? '<a href="director.php?tab=orders" style="color:var(--accent)">#'.$l['order_id'].'</a>' : '—' ?></td>
          <td style="font-size:12px;color:var(--muted)"><?= htmlspecialchars($l['ip_address'] ?? '—') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ════════════════════════════════════════════
     DAILY TAB — Өдрийн дэлгэрэнгүй тайлан
════════════════════════════════════════════ -->
<?php elseif ($tab === 'daily'): ?>

<?php
$reportDate = $_GET['report_date'] ?? date('Y-m-d');

// Тэр өдрийн захиалгууд
$dayOrders = $pdo->prepare("
    SELECT o.*, u.ner as user_ner, u.phone as user_phone,
           w.ner as worker_ner, d.ner as driver_ner
    FROM orders o
    JOIN users u ON o.user_id=u.id
    LEFT JOIN users w ON o.worker_id=w.id
    LEFT JOIN users d ON o.driver_id=d.id
    WHERE DATE(o.created_at)=?
    ORDER BY o.created_at
");
$dayOrders->execute([$reportDate]);
$dayOrders = $dayOrders->fetchAll();

// Тэр өдрийн ажилтны бүртгэл
$dayLogs = $pdo->prepare("
    SELECT l.*, u.ner as worker_name, r.name as role_name, r.color
    FROM worker_logs l
    JOIN users u ON l.worker_id=u.id
    JOIN roles r ON u.role_id=r.id
    WHERE DATE(l.created_at)=?
    ORDER BY l.created_at
");
$dayLogs->execute([$reportDate]);
$dayLogs = $dayLogs->fetchAll();

// Тэр өдрийн ажилтан тус бүрийн тоо
$dayWorkerSummary = [];
foreach ($dayLogs as $dl) {
    $wname = $dl['worker_name'];
    if (!isset($dayWorkerSummary[$wname])) {
        $dayWorkerSummary[$wname] = ['role'=>$dl['role_name'],'color'=>$dl['color'],'count'=>0,'actions'=>[]];
    }
    $dayWorkerSummary[$wname]['count']++;
    $dayWorkerSummary[$wname]['actions'][] = $dl['action'];
}
arsort($dayWorkerSummary);
?>

<!-- Огноо сонгох -->
<div class="card" style="margin-bottom:20px">
  <div class="card-body" style="padding:16px">
    <form method="GET" style="display:flex;gap:12px;align-items:flex-end">
      <input type="hidden" name="tab" value="daily">
      <div class="form-group" style="margin:0">
        <label>Тайлангийн огноо</label>
        <input type="date" name="report_date" value="<?= $reportDate ?>">
      </div>
      <button type="submit" class="btn btn-primary">📅 Тайлан харах</button>
    </form>
  </div>
</div>

<div style="display:flex;align-items:center;gap:12px;margin-bottom:20px">
  <h2 style="font-size:20px;font-weight:800;color:var(--primary)">
    📅 <?= date('Y оны m-р сарын d', strtotime($reportDate)) ?>-ний тайлан
  </h2>
  <span class="badge" style="background:var(--accent-lt);color:var(--primary)">
    <?= count($dayOrders) ?> захиалга · <?= count($dayLogs) ?> үйлдэл
  </span>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px">

  <!-- Ажилтны идэвх -->
  <div class="card">
    <div class="card-header"><div class="card-title">👷 Ажилтны идэвх</div></div>
    <div class="card-body" style="padding:20px">
      <?php if (empty($dayWorkerSummary)): ?>
        <p style="color:var(--muted);text-align:center;padding:20px">Тэр өдөр ажилтны бүртгэл байхгүй</p>
      <?php else: ?>
        <?php foreach ($dayWorkerSummary as $wname => $ws): ?>
          <div style="margin-bottom:16px;padding-bottom:16px;border-bottom:1px solid var(--border)">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
              <div>
                <strong><?= htmlspecialchars($wname) ?></strong>
                <span class="badge" style="background:<?= $ws['color'] ?>;color:#fff;font-size:11px;margin-left:6px"><?= $ws['role'] ?></span>
              </div>
              <span style="font-weight:800;font-size:18px;color:var(--primary)"><?= $ws['count'] ?> үйлдэл</span>
            </div>
            <div style="font-size:12px;color:var(--muted)">
              <?php foreach (array_slice($ws['actions'],0,3) as $act): ?>
                <div>• <?= htmlspecialchars($act) ?></div>
              <?php endforeach; ?>
              <?php if (count($ws['actions']) > 3): ?>
                <div style="color:var(--accent)">+ <?= count($ws['actions'])-3 ?> бусад үйлдэл...</div>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- Захиалгын товч тойм -->
  <div class="card">
    <div class="card-header"><div class="card-title">📦 Тэр өдрийн захиалга</div></div>
    <div class="card-body" style="padding:20px">
      <?php if (empty($dayOrders)): ?>
        <p style="color:var(--muted);text-align:center;padding:20px">Тэр өдөр захиалга байхгүй</p>
      <?php else: ?>
        <?php foreach ($dayOrders as $o):
          $c = statusColor($o['status']);
        ?>
          <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--border)">
            <div>
              <strong style="font-size:13px"><?= htmlspecialchars($o['user_ner']) ?></strong>
              <span style="font-size:12px;color:var(--muted)"> · <?= htmlspecialchars($o['product']) ?></span>
            </div>
            <span class="badge" style="background:<?= $c ?>22;color:<?= $c ?>;font-size:11px"><?= statusLabel($o['status']) ?></span>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Өдрийн бүрэн бүртгэл -->
<div class="card">
  <div class="card-header"><div class="card-title">📋 Өдрийн бүрэн бүртгэл — цаг дарааллаар</div></div>
  <div class="card-body">
    <table>
      <thead><tr><th>Цаг</th><th>Ажилтан</th><th>Роль</th><th>Үйлдэл</th><th>Захиалга #</th></tr></thead>
      <tbody>
      <?php foreach ($dayLogs as $dl): ?>
        <tr>
          <td style="white-space:nowrap;font-size:13px;font-weight:600;color:var(--primary)"><?= date('H:i:s', strtotime($dl['created_at'])) ?></td>
          <td><?= htmlspecialchars($dl['worker_name']) ?></td>
          <td><span class="badge" style="background:<?= $dl['color'] ?>;color:#fff;font-size:11px"><?= htmlspecialchars($dl['role_name']) ?></span></td>
          <td><?= htmlspecialchars($dl['action']) ?></td>
          <td><?= $dl['order_id'] ? '#'.$dl['order_id'] : '—' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php endif; ?>

<?php include __DIR__ . '/layout_end.php'; ?>
