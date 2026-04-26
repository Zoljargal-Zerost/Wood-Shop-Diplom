<?php
// ============================================================
//  dashboard/variant_action.php — Variant CRUD
// ============================================================
session_start();
require_once __DIR__ . '/../middleware.php';
requireLogin();
loadUserRole($pdo);

if (!isRole('admin','manager')) {
    http_response_code(403); exit('Эрхгүй');
}

$act       = $_POST['act'] ?? '';
$pid       = (int)($_POST['product_id'] ?? 0);
$redirect  = "/Wood-shop/dashboard/admin.php?tab=products";

switch ($act) {

// ── Нэмэх ─────────────────────────────────────────────────
case 'add':
    if (!$pid) { header('Location: '.$redirect); exit; }

    $pdo->prepare('
        INSERT INTO product_variants
            (product_id, name, zuzaan_cm, urgun_cm, urt_m,
             unit_price, cube_price, per_cube,
             pack_price, per_pack, porter_price,
             sell_shirheg, sell_kub, sell_bagts, sell_porter,
             is_active, sort_order)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ')->execute([
        $pid,
        trim($_POST['name']),
        $_POST['zuzaan_cm'] !== '' ? (float)$_POST['zuzaan_cm'] : null,
        $_POST['urgun_cm']  !== '' ? (float)$_POST['urgun_cm']  : null,
        $_POST['urt_m']     !== '' ? (float)$_POST['urt_m']     : null,
        $_POST['unit_price']   !== '' ? (int)$_POST['unit_price']   : null,
        $_POST['cube_price']   !== '' ? (int)$_POST['cube_price']   : null,
        $_POST['per_cube']     !== '' ? (int)$_POST['per_cube']     : null,
        $_POST['pack_price']   !== '' ? (int)$_POST['pack_price']   : null,
        $_POST['per_pack']     !== '' ? (int)$_POST['per_pack']     : null,
        $_POST['porter_price'] !== '' ? (int)$_POST['porter_price'] : null,
        isset($_POST['sell_shirheg']) ? 1 : 0,
        isset($_POST['sell_kub'])     ? 1 : 0,
        isset($_POST['sell_bagts'])   ? 1 : 0,
        isset($_POST['sell_porter'])  ? 1 : 0,
        isset($_POST['is_active'])    ? 1 : 0,
        0,
    ]);
    $_SESSION['toast'] = ['msg'=>'Variant нэмэгдлээ.','type'=>'success','icon'=>'✅'];
    break;

// ── Засах ─────────────────────────────────────────────────
case 'edit':
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) { header('Location: '.$redirect); exit; }

    $pdo->prepare('
        UPDATE product_variants SET
            name=?, zuzaan_cm=?, urgun_cm=?, urt_m=?,
            unit_price=?, cube_price=?, per_cube=?,
            pack_price=?, per_pack=?, porter_price=?,
            sell_shirheg=?, sell_kub=?, sell_bagts=?, sell_porter=?,
            is_active=?
        WHERE id=?
    ')->execute([
        trim($_POST['name']),
        $_POST['zuzaan_cm'] !== '' ? (float)$_POST['zuzaan_cm'] : null,
        $_POST['urgun_cm']  !== '' ? (float)$_POST['urgun_cm']  : null,
        $_POST['urt_m']     !== '' ? (float)$_POST['urt_m']     : null,
        $_POST['unit_price']   !== '' ? (int)$_POST['unit_price']   : null,
        $_POST['cube_price']   !== '' ? (int)$_POST['cube_price']   : null,
        $_POST['per_cube']     !== '' ? (int)$_POST['per_cube']     : null,
        $_POST['pack_price']   !== '' ? (int)$_POST['pack_price']   : null,
        $_POST['per_pack']     !== '' ? (int)$_POST['per_pack']     : null,
        $_POST['porter_price'] !== '' ? (int)$_POST['porter_price'] : null,
        isset($_POST['sell_shirheg']) ? 1 : 0,
        isset($_POST['sell_kub'])     ? 1 : 0,
        isset($_POST['sell_bagts'])   ? 1 : 0,
        isset($_POST['sell_porter'])  ? 1 : 0,
        isset($_POST['is_active'])    ? 1 : 0,
        $id,
    ]);
    $_SESSION['toast'] = ['msg'=>'Variant шинэчлэгдлээ.','type'=>'success','icon'=>'✅'];
    break;

// ── Устгах ────────────────────────────────────────────────
case 'delete':
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
        $pdo->prepare('DELETE FROM product_variants WHERE id=?')->execute([$id]);
        $_SESSION['toast'] = ['msg'=>'Variant устгагдлаа.','type'=>'success','icon'=>'✅'];
    }
    break;
}

header('Location: '.$redirect);
exit;
