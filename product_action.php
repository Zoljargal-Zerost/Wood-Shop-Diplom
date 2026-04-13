<?php
// ============================================================
//  product_action.php — Бүтээгдэхүүний CRUD үйлдлүүд
//  Admin/Manager л хандаж чадна
// ============================================================
session_start();
require_once __DIR__ . '/middleware.php';
requireLogin();
loadUserRole($pdo);

if (!isRole('admin','manager')) {
    http_response_code(403);
    exit('Эрхгүй');
}

$act      = $_POST['act'] ?? $_GET['act'] ?? '';
$redirect = $_POST['redirect'] ?? '/Wood-shop/dashboard/admin.php?tab=products';

// ── Зураг хадгалах helper ──────────────────────────────────
function saveProductImage(): ?string {
    if (empty($_FILES['image']['name'])) return null;

    $uploadDir = __DIR__ . '/uploads/products/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $ext      = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
    $allowed  = ['jpg','jpeg','png','webp','gif'];
    if (!in_array($ext, $allowed)) return null;
    if ($_FILES['image']['size'] > 3 * 1024 * 1024) return null; // 3MB max

    $filename = 'product_' . time() . '_' . rand(100,999) . '.' . $ext;
    move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $filename);
    return 'uploads/products/' . $filename;
}

switch ($act) {

// ── Шинэ бүтээгдэхүүн нэмэх ───────────────────────────────
case 'add':
    $name    = trim($_POST['name']);
    $type    = trim($_POST['type']);
    $emoji   = trim($_POST['emoji'] ?? '🪵');
    $desc    = trim($_POST['description'] ?? '');
    $price_l = trim($_POST['price_label'] ?? 'Үнийн санал авах');
    $price_v = $_POST['price_value'] !== '' ? (float)$_POST['price_value'] : null;
    $stock   = $_POST['stock'] !== '' ? (int)$_POST['stock'] : null;
    $sort    = (int)($_POST['sort_order'] ?? 0);
    $active  = isset($_POST['is_active']) ? 1 : 0;
    $imgPath = saveProductImage();

    $pdo->prepare('INSERT INTO products
        (name,type,emoji,description,price_label,price_value,image_path,stock,is_active,sort_order,created_by)
        VALUES (?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([$name,$type,$emoji,$desc,$price_l,$price_v,$imgPath,$stock,$active,$sort,$_SESSION['user']['id']]);

    $_SESSION['toast'] = ['msg'=>"'{$name}' нэмэгдлээ.",'type'=>'success','icon'=>'✅'];
    header('Location: ' . $redirect);
    exit;

// ── Засах ─────────────────────────────────────────────────
case 'edit':
    $id      = (int)$_POST['id'];
    $name    = trim($_POST['name']);
    $type    = trim($_POST['type']);
    $emoji   = trim($_POST['emoji'] ?? '🪵');
    $desc    = trim($_POST['description'] ?? '');
    $price_l = trim($_POST['price_label'] ?? 'Үнийн санал авах');
    $price_v = $_POST['price_value'] !== '' ? (float)$_POST['price_value'] : null;
    $stock   = $_POST['stock'] !== '' ? (int)$_POST['stock'] : null;
    $sort    = (int)($_POST['sort_order'] ?? 0);
    $active  = isset($_POST['is_active']) ? 1 : 0;
    $imgPath = saveProductImage();

    if ($imgPath) {
        // Хуучин зургийг устгах
        $old = $pdo->prepare('SELECT image_path FROM products WHERE id=?');
        $old->execute([$id]);
        $oldPath = $old->fetchColumn();
        if ($oldPath && file_exists(__DIR__ . '/' . $oldPath)) {
            unlink(__DIR__ . '/' . $oldPath);
        }
        $pdo->prepare('UPDATE products SET name=?,type=?,emoji=?,description=?,price_label=?,price_value=?,image_path=?,stock=?,is_active=?,sort_order=? WHERE id=?')
            ->execute([$name,$type,$emoji,$desc,$price_l,$price_v,$imgPath,$stock,$active,$sort,$id]);
    } else {
        $pdo->prepare('UPDATE products SET name=?,type=?,emoji=?,description=?,price_label=?,price_value=?,stock=?,is_active=?,sort_order=? WHERE id=?')
            ->execute([$name,$type,$emoji,$desc,$price_l,$price_v,$stock,$active,$sort,$id]);
    }

    $_SESSION['toast'] = ['msg'=>"'{$name}' шинэчлэгдлээ.",'type'=>'success','icon'=>'✅'];
    header('Location: ' . $redirect);
    exit;

// ── Идэвхтэй/Нуух toggle ──────────────────────────────────
case 'toggle':
    $id = (int)($_POST['id'] ?? $_GET['id']);
    $pdo->prepare('UPDATE products SET is_active = NOT is_active WHERE id=?')->execute([$id]);
    $_SESSION['toast'] = ['msg'=>'Бүтээгдэхүүний байдал өөрчлөгдлөө.','type'=>'success','icon'=>'✅'];
    header('Location: ' . $redirect);
    exit;

// ── Устгах ────────────────────────────────────────────────
case 'delete':
    $id = (int)$_POST['id'];
    // Зургийг устгах
    $old = $pdo->prepare('SELECT image_path FROM products WHERE id=?');
    $old->execute([$id]);
    $oldPath = $old->fetchColumn();
    if ($oldPath && file_exists(__DIR__ . '/' . $oldPath)) {
        unlink(__DIR__ . '/' . $oldPath);
    }
    $pdo->prepare('DELETE FROM products WHERE id=?')->execute([$id]);
    $_SESSION['toast'] = ['msg'=>'Бүтээгдэхүүн устгагдлаа.','type'=>'success','icon'=>'✅'];
    header('Location: ' . $redirect);
    exit;

// ── Эрэмбэ өөрчлөх (drag-drop-гүй, дээш/доош товч) ──────
case 'reorder':
    $id        = (int)$_POST['id'];
    $direction = $_POST['direction']; // 'up' or 'down'
    $current   = $pdo->prepare('SELECT sort_order FROM products WHERE id=?');
    $current->execute([$id]);
    $curSort   = (int)$current->fetchColumn();

    if ($direction === 'up') {
        // Дээрх бүтээгдэхүүнийг олж сол
        $swap = $pdo->prepare('SELECT id, sort_order FROM products WHERE sort_order < ? ORDER BY sort_order DESC LIMIT 1');
        $swap->execute([$curSort]);
    } else {
        $swap = $pdo->prepare('SELECT id, sort_order FROM products WHERE sort_order > ? ORDER BY sort_order ASC LIMIT 1');
        $swap->execute([$curSort]);
    }
    $swapRow = $swap->fetch();
    if ($swapRow) {
        $pdo->prepare('UPDATE products SET sort_order=? WHERE id=?')->execute([$swapRow['sort_order'], $id]);
        $pdo->prepare('UPDATE products SET sort_order=? WHERE id=?')->execute([$curSort, $swapRow['id']]);
    }
    header('Location: ' . $redirect);
    exit;

default:
    header('Location: ' . $redirect);
    exit;
}
