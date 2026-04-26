<?php
// ============================================================
//  report_print.php — Тайлан хэвлэх (цэвэрхэн хуудас)
//  Параметрүүд: from, to, worker_id (заавал биш)
// ============================================================
require_once __DIR__ . '/../middleware.php';
requireLogin();
$role = loadUserRole($pdo);

// Зөвхөн ажилтан, админ, менежер, захирал
if (!isRole('admin','manager','director','worker')) {
    header('Location: /Wood-shop/dashboard/');
    exit;
}

// Worker зөвхөн өөрийнхөө тайланг харна
$workerId = null;
if (isRole('worker')) {
    $workerId = $_SESSION['user']['id'];
} elseif (!empty($_GET['worker_id'])) {
    $workerId = (int)$_GET['worker_id'];
}

$dateFrom = $_GET['from'] ?? date('Y-m-01');
$dateTo   = $_GET['to']   ?? date('Y-m-d');

// Захиалгууд татах
$sql = '
    SELECT o.*, u.ner as user_ner, u.phone as user_phone
    FROM orders o
    JOIN users u ON o.user_id = u.id
    WHERE DATE(o.created_at) BETWEEN ? AND ?
';
$params = [$dateFrom, $dateTo];

if ($workerId) {
    $sql .= ' AND o.worker_id = ?';
    $params[] = $workerId;
}

$sql .= ' ORDER BY o.created_at DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Нийт дүн
$totalAmount = 0;
foreach ($orders as $o) {
    $totalAmount += (int)($o['total_price'] ?? 0);
}

// Ажилтны нэр (хэрэв worker_id байвал)
$workerName = '';
if ($workerId) {
    $ws = $pdo->prepare('SELECT ner FROM users WHERE id = ?');
    $ws->execute([$workerId]);
    $workerName = $ws->fetchColumn() ?: '';
}

// Статус нэр
function printStatusLabel(string $s): string {
    return [
        'pending'    => 'Хүлээгдэж байна',
        'confirmed'  => 'Баталгаажсан',
        'processing' => 'Бэлтгэж байна',
        'delivering' => 'Хүргэлтэнд',
        'delivered'  => 'Хүргэгдсэн',
        'cancelled'  => 'Цуцлагдсан',
    ][$s] ?? $s;
}
?>
<!DOCTYPE html>
<html lang="mn">
<head>
  <meta charset="UTF-8">
  <title>Тайлан — Модны Зах</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      font-family: Arial, sans-serif;
      font-size: 13px;
      color: #222;
      padding: 30px;
      background: #fff;
    }
    .header {
      text-align: center;
      margin-bottom: 24px;
      border-bottom: 2px solid #333;
      padding-bottom: 16px;
    }
    .header h1 {
      font-size: 20px;
      margin-bottom: 4px;
    }
    .header p {
      font-size: 13px;
      color: #555;
    }
    .summary {
      display: flex;
      justify-content: space-between;
      margin-bottom: 16px;
      font-size: 13px;
    }
    .summary strong { color: #333; }
    table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 16px;
    }
    th, td {
      border: 1px solid #999;
      padding: 7px 10px;
      text-align: left;
      font-size: 12px;
    }
    th {
      background: #eee;
      font-weight: bold;
    }
    tr:nth-child(even) { background: #f9f9f9; }
    .total-row {
      font-weight: bold;
      font-size: 14px;
      background: #eee !important;
    }
    .footer {
      text-align: center;
      font-size: 11px;
      color: #888;
      margin-top: 20px;
      border-top: 1px solid #ccc;
      padding-top: 10px;
    }
    .no-print { margin-bottom: 20px; text-align: center; }
    .no-print button {
      padding: 10px 24px;
      font-size: 14px;
      cursor: pointer;
      border: 1px solid #555;
      background: #fff;
      border-radius: 4px;
      margin: 0 4px;
    }
    .no-print button:hover { background: #f0f0f0; }
    @media print {
      .no-print { display: none; }
      body { padding: 10px; }
      @page { margin: 1cm; }
    }
  </style>
</head>
<body>

<div class="no-print">
  <button onclick="window.print()">🖨️ Хэвлэх</button>
  <button onclick="window.close()">✕ Хаах</button>
</div>

<div class="header">
  <h1>Модны Зах — Тайлан</h1>
  <p>
    <?= htmlspecialchars($dateFrom) ?> — <?= htmlspecialchars($dateTo) ?>
    <?php if ($workerName): ?>
      &nbsp;|&nbsp; Ажилтан: <?= htmlspecialchars($workerName) ?>
    <?php endif; ?>
  </p>
</div>

<div class="summary">
  <span>Нийт захиалга: <strong><?= count($orders) ?></strong></span>
  <span>Нийт дүн: <strong><?= number_format($totalAmount) ?>₮</strong></span>
</div>

<?php if (empty($orders)): ?>
  <p style="text-align:center;padding:30px;color:#888">Энэ хугацаанд захиалга байхгүй байна.</p>
<?php else: ?>
<table>
  <thead>
    <tr>
      <th>#</th>
      <th>Огноо</th>
      <th>Хэрэглэгч</th>
      <th>Утас</th>
      <th>Бүтээгдэхүүн</th>
      <th>Тоо/Хэмжээ</th>
      <th>Дүн</th>
      <th>Статус</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($orders as $o): ?>
    <tr>
      <td><?= $o['id'] ?></td>
      <td><?= date('Y/m/d H:i', strtotime($o['created_at'])) ?></td>
      <td><?= htmlspecialchars($o['user_ner']) ?></td>
      <td><?= htmlspecialchars($o['user_phone']) ?></td>
      <td><?= htmlspecialchars($o['product']) ?></td>
      <td><?= $o['shirheg'] ?>ш · <?= $o['urt_m'] ?>м · <?= $o['urgun_cm'] ?>×<?= $o['zuzaan_cm'] ?>см</td>
      <td><?= number_format($o['total_price'] ?? 0) ?>₮</td>
      <td><?= printStatusLabel($o['status']) ?></td>
    </tr>
    <?php endforeach; ?>
    <tr class="total-row">
      <td colspan="6" style="text-align:right">Нийт дүн:</td>
      <td><?= number_format($totalAmount) ?>₮</td>
      <td><?= count($orders) ?> захиалга</td>
    </tr>
  </tbody>
</table>
<?php endif; ?>

<div class="footer">
  Модны Зах | Дархан-Уул | Утас: 9446-9149 | Хэвлэсэн: <?= date('Y/m/d H:i') ?>
</div>

</body>
</html>
