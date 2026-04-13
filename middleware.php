<?php
// ============================================================
//  middleware.php — Role & Permission хамгаалалт
//  Бүх dashboard файлын ЭХЭНД нэмнэ:
//  require_once __DIR__ . '/../middleware.php';
// ============================================================

if (session_status() === PHP_SESSION_NONE) session_start();

// DB холболт (config.php-с авна)
if (!isset($pdo)) {
    require_once __DIR__ . '/config.php';
    try {
        $pdo = new PDO(
            'mysql:host=localhost;dbname=modni_zah;charset=utf8mb4',
            'root', '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
             PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    } catch (PDOException $e) {
        die('DB холболтын алдаа: ' . $e->getMessage());
    }
}

// ── Нэвтэрсэн эсэх шалгах ──────────────────────────────────
function requireLogin(): void {
    if (!isset($_SESSION['user'])) {
        header('Location: /Wood-shop/?login=1');
        exit;
    }
}

// ── Role object session-д байхгүй бол DB-с татах ──────────
function loadUserRole(PDO $pdo): array {
    requireLogin();
    $uid = $_SESSION['user']['id'];

    if (!isset($_SESSION['user_role'])) {
        $stmt = $pdo->prepare('
            SELECT r.*, u.is_active
            FROM users u
            JOIN roles r ON u.role_id = r.id
            WHERE u.id = ?
        ');
        $stmt->execute([$uid]);
        $role = $stmt->fetch();

        if (!$role || !$role['is_active']) {
            session_destroy();
            header('Location: /Wood-shop/?err=account_disabled');
            exit;
        }

        $role['permissions'] = json_decode($role['permissions'], true) ?? [];
        $_SESSION['user_role'] = $role;
    }

    return $_SESSION['user_role'];
}

// ── Эрх шалгах ─────────────────────────────────────────────
function can(string $permission): bool {
    $perms = $_SESSION['user_role']['permissions'] ?? [];
    return in_array($permission, $perms, true);
}

// ── Role slug шалгах ───────────────────────────────────────
function isRole(string ...$slugs): bool {
    $current = $_SESSION['user_role']['slug'] ?? '';
    return in_array($current, $slugs, true);
}

// ── Эрхгүй бол зогсоох ────────────────────────────────────
function requirePermission(string $permission): void {
    if (!can($permission)) {
        http_response_code(403);
        include __DIR__ . '/dashboard/403.php';
        exit;
    }
}

// ── Dashboard router — role-оор чиглүүлэх ─────────────────
function redirectToDashboard(): void {
    $slug = $_SESSION['user_role']['slug'] ?? 'user';
    $map  = [
        'admin'    => '/Wood-shop/dashboard/admin.php',
        'director' => '/Wood-shop/dashboard/director.php', // захирал = зөвхөн харах
        'manager'  => '/Wood-shop/dashboard/admin.php',
        'worker'   => '/Wood-shop/dashboard/worker.php',
        'driver'   => '/Wood-shop/dashboard/driver.php',
        'user'     => '/Wood-shop/dashboard/user.php',
    ];
    header('Location: ' . ($map[$slug] ?? '/Wood-shop/dashboard/user.php'));
    exit;
}

// ── Worker log бичих ───────────────────────────────────────
function logWorkerAction(PDO $pdo, string $action, ?int $orderId = null): void {
    if (!isset($_SESSION['user'])) return;
    $stmt = $pdo->prepare('
        INSERT INTO worker_logs (worker_id, order_id, action, ip_address)
        VALUES (?, ?, ?, ?)
    ');
    $stmt->execute([
        $_SESSION['user']['id'],
        $orderId,
        $action,
        $_SERVER['REMOTE_ADDR'] ?? null
    ]);
}

// ── Status Монгол нэр ──────────────────────────────────────
function statusLabel(string $status): string {
    return [
        'pending'    => '⏳ Хүлээгдэж байна',
        'confirmed'  => '✅ Баталгаажсан',
        'processing' => '🔨 Бэлтгэж байна',
        'delivering' => '🚚 Хүргэлтэнд гарсан',
        'delivered'  => '📦 Хүргэгдсэн',
        'cancelled'  => '❌ Цуцлагдсан',
    ][$status] ?? $status;
}

function statusColor(string $status): string {
    return [
        'pending'    => '#B7770D',
        'confirmed'  => '#27500A',
        'processing' => '#1565C0',
        'delivering' => '#6A1B9A',
        'delivered'  => '#2E7D32',
        'cancelled'  => '#791F1F',
    ][$status] ?? '#555';
}