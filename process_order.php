<?php
// ============================================================
//  process_order.php — Cart захиалга хадгалах
// ============================================================
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/notify.php';

if (!isset($_SESSION['user'])) {
    header('Location: /Wood-shop/?login=1');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /Wood-shop/');
    exit;
}

try {
    $pdo = new PDO('mysql:host=localhost;dbname=modni_zah;charset=utf8mb4', 'root', '', [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die('DB алдаа: ' . $e->getMessage());
}

$uid   = $_SESSION['user']['id'];
$notes = trim($_POST['notes'] ?? '');

// Cart JSON-г unserialize хийх
$cartJson = $_POST['cart_json'] ?? '[]';
$cart     = json_decode($cartJson, true);

if (empty($cart)) {
    $_SESSION['toast'] = ['msg'=>'Сагс хоосон байна.','type'=>'error','icon'=>'❌'];
    header('Location: /Wood-shop/#order-modal');
    exit;
}

// Variant-уудыг DB-с баталгаажуулж үнийг дахин тооцох
$totalPrice = 0;
$validItems = [];

foreach ($cart as $item) {
    $vid   = (int)($item['variant_id'] ?? 0);
    $stype = $item['sell_type'] ?? '';
    $qty   = (float)($item['qty'] ?? 0);

    if (!$vid || !$qty || $qty <= 0) continue;

    // Variant DB-с авах
    $stmt = $pdo->prepare('
        SELECT v.*, p.id as pid, p.name as pname
        FROM product_variants v
        JOIN products p ON v.product_id = p.id
        WHERE v.id = ? AND v.is_active = 1
    ');
    $stmt->execute([$vid]);
    $variant = $stmt->fetch();
    if (!$variant) continue;

    // Зарах хэлбэрийг шалгаж үнэ тооцох
    $unitPrice = 0;
    switch ($stype) {
        case 'shirheg':
            if (!$variant['sell_shirheg'] || !$variant['unit_price']) continue 2;
            $unitPrice = (int)$variant['unit_price'];
            break;
        case 'kub':
            if (!$variant['sell_kub'] || !$variant['cube_price']) continue 2;
            $unitPrice = (int)$variant['cube_price'];
            break;
        case 'bagts':
            if (!$variant['sell_bagts'] || !$variant['pack_price']) continue 2;
            $unitPrice = (int)$variant['pack_price'];
            break;
        case 'porter':
            if (!$variant['sell_porter'] || !$variant['porter_price']) continue 2;
            $unitPrice = (int)$variant['porter_price'];
            break;
        default:
            continue 2;
    }

    $subtotal    = (int)round($unitPrice * $qty);
    $totalPrice += $subtotal;

    $validItems[] = [
        'product_id'   => $variant['pid'],
        'variant_id'   => $vid,
        'product_name' => $variant['pname'],
        'variant_name' => $variant['name'],
        'sell_type'    => $stype,
        'qty'          => $qty,
        'unit_price'   => $unitPrice,
        'subtotal'     => $subtotal,
    ];
}

if (empty($validItems)) {
    $_SESSION['toast'] = ['msg'=>'Захиалгын мэдээлэл буруу байна.','type'=>'error','icon'=>'❌'];
    header('Location: /Wood-shop/#order-modal');
    exit;
}

// Транзакц эхлүүлэх
$pdo->beginTransaction();
try {
    // orders хүснэгтэд нэг мөр
    $firstItem = $validItems[0];
    $stmt = $pdo->prepare('
        INSERT INTO orders (user_id, product, shirheg, urt_m, notes, status, total_price, item_count)
        VALUES (?, ?, ?, ?, ?, "pending", ?, ?)
    ');
    $stmt->execute([
        $uid,
        $firstItem['product_name'],  // үндсэн бүтээгдэхүүн
        $firstItem['qty'],
        $firstItem['variant_name'],
        $notes,
        $totalPrice,
        count($validItems),
    ]);
    $orderId = (int)$pdo->lastInsertId();

    // order_items — мөр бүр
    $iStmt = $pdo->prepare('
        INSERT INTO order_items
            (order_id, product_id, variant_id, product_name, variant_name, sell_type, qty, unit_price, subtotal)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    foreach ($validItems as $item) {
        $iStmt->execute([
            $orderId,
            $item['product_id'],
            $item['variant_id'],
            $item['product_name'],
            $item['variant_name'],
            $item['sell_type'],
            $item['qty'],
            $item['unit_price'],
            $item['subtotal'],
        ]);
    }

    $pdo->commit();

    // Worker-т мэдэгдэл
    notifyWorkersNewOrder($pdo, $orderId);

    $_SESSION['toast'] = [
        'msg'  => "Захиалга #{$orderId} амжилттай илгээгдлээ! Нийт дүн: ₮" . number_format($totalPrice) . ". Ажилтан тантай удахгүй холбогдоно.",
        'type' => 'success',
        'icon' => '✅',
    ];
    header('Location: /Wood-shop/');
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    error_log('Order error: ' . $e->getMessage());
    $_SESSION['toast'] = ['msg'=>'Захиалга хадгалахад алдаа гарлаа. Дахин оролдоно уу.','type'=>'error','icon'=>'❌'];
    header('Location: /Wood-shop/#order-modal');
    exit;
}