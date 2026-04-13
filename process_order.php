<?php
// ============================================================
//  process_order.php — Захиалга хүлээн авах + Worker мэдэгдэл
// ============================================================
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/notify.php';

// Нэвтрээгүй бол login рүү
if (!isset($_SESSION['user'])) {
    header('Location: /Wood-shop/?login=1');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /Wood-shop/');
    exit;
}

// DB холболт
try {
    $pdo = new PDO('mysql:host=localhost;dbname=modni_zah;charset=utf8mb4', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die('DB алдаа: ' . $e->getMessage());
}

$uid     = $_SESSION['user']['id'];
$product = trim($_POST['product'] ?? '');
$shirheg = (int)($_POST['shirheg'] ?? 0);
$urt     = (float)($_POST['urt_m'] ?? 0);
$urgun   = (float)($_POST['urgun_cm'] ?? 0);
$zuzaan  = (float)($_POST['zuzaan_cm'] ?? 0);
$notes   = trim($_POST['notes'] ?? '');

// Validation
if (!$product || !$shirheg || !$urt) {
    $_SESSION['toast'] = ['msg'=>'Бүх талбарыг бөглөнө үү.','type'=>'error','icon'=>'❌'];
    header('Location: /Wood-shop/#order-modal');
    exit;
}

// Захиалга хадгалах
$stmt = $pdo->prepare('
    INSERT INTO orders (user_id, product, shirheg, urt_m, urgun_cm, zuzaan_cm, notes, status)
    VALUES (?, ?, ?, ?, ?, ?, ?, "pending")
');
$stmt->execute([$uid, $product, $shirheg, $urt, $urgun, $zuzaan, $notes]);
$orderId = (int)$pdo->lastInsertId();

// ✅ Бүх Worker-т email мэдэгдэл явуулах
notifyWorkersNewOrder($pdo, $orderId);

$_SESSION['toast'] = [
    'msg'  => "Захиалга #{$orderId} амжилттай илгээгдлээ! Ажилтан тантай удахгүй холбогдоно.",
    'type' => 'success',
    'icon' => '✅'
];
header('Location: /Wood-shop/');
exit;
