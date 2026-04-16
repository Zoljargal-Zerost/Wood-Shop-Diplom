<?php
// ============================================================
//  cart_api.php — Бүтээгдэхүүн болон вариантуудыг JSON-р буцаах
// ============================================================
session_start();
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user'])) {
    echo json_encode(['ok' => false, 'error' => 'Нэвтрэх шаардлагатай']);
    exit;
}

try {
    $pdo = new PDO('mysql:host=localhost;dbname=modni_zah;charset=utf8mb4', 'root', '', [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    echo json_encode(['ok' => false, 'error' => 'DB алдаа']);
    exit;
}

$act = $_GET['act'] ?? '';

switch ($act) {

// ── Идэвхтэй бүтээгдэхүүнүүд ──────────────────────────────
case 'products':
    $stmt = $pdo->query('
        SELECT id, name, type, emoji
        FROM products
        WHERE is_active = 1
        ORDER BY sort_order ASC
    ');
    echo json_encode(['ok' => true, 'products' => $stmt->fetchAll()]);
    break;

// ── Тухайн бүтээгдэхүүний вариантууд ──────────────────────
case 'variants':
    $pid = (int)($_GET['product_id'] ?? 0);
    if (!$pid) {
        echo json_encode(['ok' => false, 'error' => 'product_id шаардлагатай']);
        break;
    }
    $stmt = $pdo->prepare('
        SELECT
            id, name,
            zuzaan_cm, urgun_cm, urt_m,
            unit_price, cube_price, per_cube,
            pack_price, per_pack,
            porter_price,
            sell_shirheg, sell_kub, sell_bagts, sell_porter
        FROM product_variants
        WHERE product_id = ? AND is_active = 1
        ORDER BY sort_order ASC
    ');
    $stmt->execute([$pid]);
    $variants = $stmt->fetchAll();

    // Тоог int болгох
    foreach ($variants as &$v) {
        foreach (['unit_price','cube_price','per_cube','pack_price','per_pack','porter_price'] as $f) {
            $v[$f] = $v[$f] !== null ? (int)$v[$f] : null;
        }
        foreach (['sell_shirheg','sell_kub','sell_bagts','sell_porter'] as $f) {
            $v[$f] = (bool)$v[$f];
        }
    }
    echo json_encode(['ok' => true, 'variants' => $variants]);
    break;

default:
    echo json_encode(['ok' => false, 'error' => 'Үл мэдэгдэх үйлдэл']);
}
