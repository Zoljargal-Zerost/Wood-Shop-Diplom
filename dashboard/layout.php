<?php
// dashboard/layout.php — Бүх dashboard-д хуваалцах layout
// Ашиглах: $pageTitle, $activePage тохируулаад include хийнэ

$role     = $_SESSION['user_role'];
$userName = $_SESSION['user']['ner'];
$userEmail= $_SESSION['user']['email'];
$roleSlug = $role['slug'];

// Sidebar цэс — role-оор харуулах
$menuItems = [];

if (isRole('admin','manager','director')) {
    if (isRole('director')) {
        // Захирал — зөвхөн харах, ямар ч засах цэсгүй
        $menuItems = [
            ['icon'=>'📊','label'=>'Нүүр тайлан',    'href'=>'director.php',               'page'=>'dashboard'],
            ['icon'=>'📦','label'=>'Захиалгууд',      'href'=>'director.php?tab=orders',    'page'=>'orders'],
            ['icon'=>'👷','label'=>'Ажилтнууд',       'href'=>'director.php?tab=workers',   'page'=>'workers'],
            ['icon'=>'📋','label'=>'Үйл ажиллагаа',  'href'=>'director.php?tab=logs',      'page'=>'logs'],
            ['icon'=>'📅','label'=>'Өдрийн тайлан',  'href'=>'director.php?tab=daily',     'page'=>'daily'],
        ];
    } else {
        // Admin / Manager
        $menuItems = [
            ['icon'=>'📊','label'=>'Хянах самбар','href'=>'admin.php','page'=>'dashboard'],
            ['icon'=>'📦','label'=>'Бүх захиалга','href'=>'admin.php?tab=orders','page'=>'orders'],
            ['icon'=>'👥','label'=>'Хэрэглэгчид','href'=>'admin.php?tab=users','page'=>'users'],
            ['icon'=>'👷','label'=>'Ажилтнууд','href'=>'admin.php?tab=workers','page'=>'workers'],
            ['icon'=>'🚚','label'=>'Жолооч','href'=>'admin.php?tab=drivers','page'=>'drivers'],
            ['icon'=>'📋','label'=>'Бүртгэл','href'=>'admin.php?tab=logs','page'=>'logs'],
        ];
        if (isRole('admin','director')) {
            $menuItems[] = ['icon'=>'🔐','label'=>'Role удирдлага','href'=>'admin.php?tab=roles','page'=>'roles'];
        }
    }
} elseif (isRole('worker')) {
    $menuItems = [
        ['icon'=>'📊','label'=>'Хянах самбар','href'=>'worker.php','page'=>'dashboard'],
        ['icon'=>'📦','label'=>'Захиалгууд','href'=>'worker.php?tab=orders','page'=>'orders'],
        ['icon'=>'➕','label'=>'Хэрэглэгч бүртгэх','href'=>'worker.php?tab=register','page'=>'register'],
        ['icon'=>'📋','label'=>'Миний үйлдлүүд','href'=>'worker.php?tab=logs','page'=>'logs'],
    ];
} elseif (isRole('driver')) {
    $menuItems = [
        ['icon'=>'📊','label'=>'Хянах самбар','href'=>'driver.php','page'=>'dashboard'],
        ['icon'=>'🚚','label'=>'Хүргэлтүүд','href'=>'driver.php?tab=deliveries','page'=>'deliveries'],
    ];
} else {
    $menuItems = [
        ['icon'=>'📦','label'=>'Захиалгууд','href'=>'user.php','page'=>'orders'],
        ['icon'=>'👤','label'=>'Профайл','href'=>'user.php?tab=profile','page'=>'profile'],
    ];
}
?>
<!DOCTYPE html>
<html lang="mn">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?> — Модны Зах</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Exo+2:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --primary:   #5C3D1E;
      --accent:    #C8833A;
      --accent-lt: #F0D5B0;
      --bg:        #F9F5EE;
      --bg-alt:    #F2EDE3;
      --card:      #FFFFFF;
      --border:    #DDD0BC;
      --text:      #2A1A0A;
      --muted:     #7A6248;
      --radius:    12px;
      --sidebar-w: 240px;
      --header-h:  60px;
      --shadow:    0 2px 16px rgba(92,61,30,0.10);
    }
    body { font-family: 'Exo 2', sans-serif; background: var(--bg); color: var(--text); display: flex; min-height: 100vh; }

    /* ── Sidebar ── */
    .sidebar {
      width: var(--sidebar-w);
      background: var(--primary);
      color: #fff;
      display: flex;
      flex-direction: column;
      position: fixed;
      top: 0; left: 0; bottom: 0;
      z-index: 100;
      transition: transform 0.3s;
    }
    .sidebar-logo {
      padding: 20px 20px 16px;
      font-size: 17px;
      font-weight: 800;
      display: flex;
      align-items: center;
      gap: 10px;
      border-bottom: 1px solid rgba(255,255,255,0.1);
    }
    .sidebar-logo svg { flex-shrink: 0; }

    .sidebar-user {
      padding: 16px 20px;
      border-bottom: 1px solid rgba(255,255,255,0.1);
    }
    .sidebar-avatar {
      width: 36px; height: 36px;
      background: var(--accent);
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-weight: 700; font-size: 15px;
      margin-bottom: 8px;
    }
    .sidebar-uname { font-weight: 600; font-size: 14px; }
    .sidebar-role  {
      display: inline-block;
      font-size: 11px;
      padding: 2px 8px;
      border-radius: 20px;
      background: <?= htmlspecialchars($role['color']) ?>;
      color: #fff;
      margin-top: 4px;
      font-weight: 600;
    }

    .sidebar-nav { flex: 1; padding: 12px 0; overflow-y: auto; }
    .sidebar-nav a {
      display: flex; align-items: center; gap: 10px;
      padding: 11px 20px;
      color: rgba(255,255,255,0.75);
      text-decoration: none;
      font-size: 14px;
      font-weight: 500;
      transition: background 0.15s, color 0.15s;
      border-left: 3px solid transparent;
    }
    .sidebar-nav a:hover { background: rgba(255,255,255,0.08); color: #fff; }
    .sidebar-nav a.active { background: rgba(255,255,255,0.12); color: #fff; border-left-color: var(--accent); font-weight: 600; }

    .sidebar-bottom {
      padding: 16px 20px;
      border-top: 1px solid rgba(255,255,255,0.1);
      display: flex; flex-direction: column; gap: 8px;
    }
    .sidebar-bottom a {
      color: rgba(255,255,255,0.7);
      font-size: 13px;
      text-decoration: none;
      display: flex; align-items: center; gap: 8px;
      transition: color 0.15s;
    }
    .sidebar-bottom a:hover { color: #fff; }

    /* ── Main ── */
    .main-wrap {
      margin-left: var(--sidebar-w);
      flex: 1;
      display: flex;
      flex-direction: column;
      min-height: 100vh;
    }
    .topbar {
      height: var(--header-h);
      background: var(--card);
      border-bottom: 1px solid var(--border);
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 28px;
      position: sticky; top: 0; z-index: 50;
      box-shadow: var(--shadow);
    }
    .topbar-title { font-size: 18px; font-weight: 700; color: var(--primary); }
    .topbar-right { display: flex; align-items: center; gap: 14px; }
    .topbar-right a { color: var(--muted); font-size: 13px; text-decoration: none; }
    .topbar-right a:hover { color: var(--primary); }

    .content { padding: 28px; flex: 1; }

    /* ── Cards / Stats ── */
    .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px,1fr)); gap: 16px; margin-bottom: 28px; }
    .stat-card {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 20px;
      box-shadow: var(--shadow);
    }
    .stat-icon { font-size: 28px; margin-bottom: 10px; }
    .stat-num  { font-size: 28px; font-weight: 800; color: var(--primary); }
    .stat-lbl  { font-size: 13px; color: var(--muted); margin-top: 4px; }

    /* ── Tables ── */
    .card {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      margin-bottom: 24px;
      overflow: hidden;
    }
    .card-header {
      padding: 16px 20px;
      border-bottom: 1px solid var(--border);
      display: flex;
      align-items: center;
      justify-content: space-between;
      background: var(--bg-alt);
    }
    .card-title { font-size: 15px; font-weight: 700; color: var(--primary); }
    .card-body  { padding: 0; }

    table { width: 100%; border-collapse: collapse; font-size: 13px; }
    th { padding: 12px 16px; text-align: left; font-weight: 600; color: var(--muted); background: var(--bg-alt); border-bottom: 1px solid var(--border); white-space: nowrap; }
    td { padding: 12px 16px; border-bottom: 1px solid var(--border); vertical-align: middle; }
    tr:last-child td { border-bottom: none; }
    tr:hover td { background: var(--bg); }

    /* ── Badges ── */
    .badge {
      display: inline-block;
      padding: 3px 10px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 600;
      white-space: nowrap;
    }

    /* ── Buttons ── */
    .btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; border: none; text-decoration: none; transition: opacity 0.2s, transform 0.1s; font-family: inherit; }
    .btn:hover { opacity: 0.88; transform: translateY(-1px); }
    .btn-primary { background: var(--accent); color: #fff; }
    .btn-outline { background: transparent; border: 1.5px solid var(--border); color: var(--text); }
    .btn-danger  { background: #e74c3c; color: #fff; }
    .btn-sm      { padding: 5px 12px; font-size: 12px; }
    .btn-success { background: #27ae60; color: #fff; }

    /* ── Form ── */
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    .form-group { display: flex; flex-direction: column; gap: 6px; }
    .form-group.full { grid-column: 1 / -1; }
    label { font-size: 12px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: .05em; }
    input[type=text], input[type=email], input[type=tel], input[type=password],
    input[type=date], select, textarea {
      padding: 10px 12px;
      border: 1.5px solid var(--border);
      border-radius: 8px;
      font-size: 14px;
      font-family: inherit;
      background: var(--bg);
      color: var(--text);
      outline: none;
      transition: border-color .2s;
      width: 100%;
    }
    input:focus, select:focus, textarea:focus { border-color: var(--accent); background: #fff; }
    textarea { resize: vertical; min-height: 80px; }

    /* ── Modal ── */
    .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.45); z-index: 500; align-items: center; justify-content: center; }
    .modal-overlay.open { display: flex; }
    .modal-box { background: #fff; border-radius: 16px; padding: 28px; width: 100%; max-width: 560px; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.25); position: relative; animation: popIn .25s ease; }
    @keyframes popIn { from { opacity:0; transform:scale(.95) translateY(10px); } to { opacity:1; transform:scale(1) translateY(0); } }
    .modal-title { font-size: 18px; font-weight: 700; margin-bottom: 20px; color: var(--primary); }
    .modal-close { position: absolute; top: 16px; right: 16px; background: none; border: none; font-size: 22px; cursor: pointer; color: var(--muted); }

    /* ── Alerts ── */
    .alert { padding: 12px 16px; border-radius: 8px; font-size: 14px; margin-bottom: 16px; }
    .alert-success { background: #eaf3de; color: #27500a; border: 1px solid #c0dd97; }
    .alert-error   { background: #fcebeb; color: #791f1f; border: 1px solid #f7c1c1; }
    .alert-info    { background: var(--accent-lt); color: var(--primary); border: 1px solid var(--border); }

    /* ── Tabs ── */
    .tabs { display: flex; gap: 4px; margin-bottom: 24px; border-bottom: 2px solid var(--border); }
    .tab { padding: 10px 18px; font-size: 14px; font-weight: 600; color: var(--muted); cursor: pointer; border: none; background: none; border-bottom: 2px solid transparent; margin-bottom: -2px; font-family: inherit; transition: color .2s; }
    .tab.active { color: var(--primary); border-bottom-color: var(--accent); }

    /* ── Responsive ── */
    @media (max-width: 768px) {
      .sidebar { transform: translateX(-100%); }
      .sidebar.open { transform: translateX(0); }
      .main-wrap { margin-left: 0; }
      .form-grid { grid-template-columns: 1fr; }
      .stat-grid { grid-template-columns: 1fr 1fr; }
    }

    /* ── Permission denied ── */
    .no-perm { text-align: center; padding: 60px 20px; color: var(--muted); }
    .no-perm-icon { font-size: 48px; margin-bottom: 12px; }
  </style>
</head>
<body>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2L6 10h3l-4 7h5v3h4v-3h5l-4-7h3L12 2z"/></svg>
    Модны Зах
  </div>

  <div class="sidebar-user">
    <div class="sidebar-avatar"><?= mb_substr($userName, 0, 1) ?></div>
    <div class="sidebar-uname"><?= htmlspecialchars($userName) ?></div>
    <span class="sidebar-role"><?= htmlspecialchars($role['name']) ?></span>
  </div>

  <nav class="sidebar-nav">
    <?php foreach ($menuItems as $item): ?>
      <a href="<?= $item['href'] ?>"
         class="<?= ($activePage ?? '') === $item['page'] ? 'active' : '' ?>">
        <?= $item['icon'] ?> <?= htmlspecialchars($item['label']) ?>
      </a>
    <?php endforeach; ?>
  </nav>

  <div class="sidebar-bottom">
    <a href="/Wood-shop/">🏠 Нүүр хуудас руу</a>
    <a href="/Wood-shop/auth.php?action=logout">🚪 Гарах</a>
  </div>
</aside>

<!-- Main -->
<div class="main-wrap">
  <div class="topbar">
    <div class="topbar-title"><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></div>
    <div class="topbar-right">
      <span style="color:var(--muted);font-size:13px"><?= htmlspecialchars($userEmail) ?></span>
      <a href="/Wood-shop/auth.php?action=logout">Гарах →</a>
    </div>
  </div>
  <div class="content">
<?php
// Toast харуулах
if (!empty($_SESSION['toast'])):
  $t = $_SESSION['toast']; unset($_SESSION['toast']);
?>
<div class="alert alert-<?= $t['type'] === 'success' ? 'success' : ($t['type'] === 'error' ? 'error' : 'info') ?>" style="margin-bottom:20px">
  <?= $t['icon'] ?? '' ?> <?= htmlspecialchars($t['msg']) ?>
</div>
<?php endif; ?>