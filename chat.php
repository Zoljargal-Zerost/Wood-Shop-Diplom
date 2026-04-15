<?php
// ============================================================
//  chat.php — Чатын API
//  Үйлдлүүд: send, fetch, unread, get_room, ping
// ============================================================
session_start();
require_once __DIR__ . '/config.php';

if (!isset($pdo)) {
    try {
        $pdo = new PDO('mysql:host=localhost;dbname=modni_zah;charset=utf8mb4', 'root', '', [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (PDOException $e) {
        jsonError('DB алдаа'); exit;
    }
}

header('Content-Type: application/json; charset=utf-8');

// Нэвтрээгүй бол
if (!isset($_SESSION['user'])) {
    echo json_encode(['ok' => false, 'error' => 'Нэвтрэх шаардлагатай']);
    exit;
}

$uid = (int)$_SESSION['user']['id'];
$act = $_GET['act'] ?? $_POST['act'] ?? '';

// Онлайн статус шинэчлэх
pingOnline($pdo, $uid);

switch ($act) {

// ── PING — онлайн байгааг мэдэгдэх ─────────────────────────
case 'ping':
    echo json_encode(['ok' => true]);
    exit;

// ── GET_ROOM — өрөө олох эсвэл үүсгэх ──────────────────────
case 'get_room':
    $roomType = $_POST['room_type'] ?? 'user_worker';

    if ($roomType === 'user_worker') {
        // Хэрэглэгч → онлайн ажилтан хайх
        $room = getOrCreateUserWorkerRoom($pdo, $uid);
        if (!$room) {
            echo json_encode(['ok' => false, 'error' => 'Одоогоор онлайн ажилтан байхгүй байна. Түр хүлээнэ үү.']);
            exit;
        }
        // Ажилтны role нэмэх
        $workerRole = $pdo->prepare('SELECT r.name FROM users u JOIN roles r ON u.role_id=r.id WHERE u.id=?');
        $workerRole->execute([$room['worker_id']]);
        $room['worker_role'] = $workerRole->fetchColumn() ?: 'Ажилтан';
        echo json_encode(['ok' => true, 'room' => $room]);

    } elseif ($roomType === 'internal') {
        // Ажилтан → admin/manager-тай чат
        $targetId = (int)($_POST['target_id'] ?? 0);
        if (!$targetId) { jsonError('target_id шаардлагатай'); exit; }
        $room = getOrCreateInternalRoom($pdo, $uid, $targetId);
        echo json_encode(['ok' => true, 'room' => $room]);

    } elseif ($roomType === 'order') {
        $orderId = (int)($_POST['order_id'] ?? 0);
        if (!$orderId) { jsonError('order_id шаардлагатай'); exit; }
        $room = getOrCreateOrderRoom($pdo, $uid, $orderId);
        echo json_encode(['ok' => true, 'room' => $room]);
    }
    exit;

// ── SEND — мессеж илгээх ────────────────────────────────────
case 'send':
    $roomId  = (int)($_POST['room_id'] ?? 0);
    $message = trim($_POST['message'] ?? '');

    if (!$roomId || !$message) {
        jsonError('room_id болон message шаардлагатай'); exit;
    }
    if (strlen($message) > 1000) {
        jsonError('Мессеж хэт урт байна'); exit;
    }

    // Энэ өрөөнд хандах эрх байгаа эсэх шалгах
    if (!canAccessRoom($pdo, $uid, $roomId)) {
        jsonError('Эрхгүй'); exit;
    }

    $stmt = $pdo->prepare('INSERT INTO chat_messages (room_id, sender_id, message) VALUES (?, ?, ?)');
    $stmt->execute([$roomId, $uid, $message]);
    $msgId = $pdo->lastInsertId();

    // Мессежийг буцаах
    $msg = $pdo->prepare('
        SELECT m.*, u.ner as sender_name
        FROM chat_messages m
        JOIN users u ON m.sender_id = u.id
        WHERE m.id = ?
    ');
    $msg->execute([$msgId]);
    $msg = $msg->fetch();

    echo json_encode(['ok' => true, 'message' => formatMessage($msg, $uid)]);
    exit;

// ── FETCH — мессежүүд татах ─────────────────────────────────
case 'fetch':
    $roomId    = (int)($_GET['room_id'] ?? 0);
    $afterId   = (int)($_GET['after_id'] ?? 0);

    if (!$roomId) { jsonError('room_id шаардлагатай'); exit; }
    if (!canAccessRoom($pdo, $uid, $roomId)) { jsonError('Эрхгүй'); exit; }

    if ($afterId) {
        $stmt = $pdo->prepare('
            SELECT m.*, u.ner as sender_name
            FROM chat_messages m
            JOIN users u ON m.sender_id = u.id
            WHERE m.room_id = ? AND m.id > ?
            ORDER BY m.created_at ASC
            LIMIT 50
        ');
        $stmt->execute([$roomId, $afterId]);
    } else {
        $stmt = $pdo->prepare('
            SELECT m.*, u.ner as sender_name
            FROM chat_messages m
            JOIN users u ON m.sender_id = u.id
            WHERE m.room_id = ?
            ORDER BY m.created_at ASC
            LIMIT 50
        ');
        $stmt->execute([$roomId]);
    }
    $messages = $stmt->fetchAll();

    $pdo->prepare('UPDATE chat_messages SET is_read=1 WHERE room_id=? AND sender_id!=? AND is_read=0')
        ->execute([$roomId, $uid]);

    echo json_encode([
        'ok'       => true,
        'messages' => array_map(fn($m) => formatMessage($m, $uid), $messages),
    ]);
    exit;

// ── UNREAD — уншаагүй тоо ────────────────────────────────────
case 'unread':
    $stmt = $pdo->prepare('
        SELECT COUNT(*) as cnt
        FROM chat_messages m
        JOIN chat_rooms r ON m.room_id = r.id
        WHERE m.sender_id != ?
          AND m.is_read = 0
          AND (
            (r.user_id = ? AND r.deleted_by_user = 0)
            OR
            (r.worker_id = ? AND r.archived_by_worker = 0)
          )
    ');
    $stmt->execute([$uid, $uid, $uid]);
    $cnt = (int)$stmt->fetchColumn();
    echo json_encode(['ok' => true, 'unread' => $cnt]);
    exit;

// ── MY_ROOMS — миний бүх чат өрөөнүүд ──────────────────────
case 'my_rooms':
    $rooms = getMyRooms($pdo, $uid);
    echo json_encode(['ok' => true, 'rooms' => $rooms]);
    exit;

// ── DELETE_ROOM — хэрэглэгч бүрмөсөн устгах
//                  ажилтан архивд оруулах ────────────────────
case 'delete_room':
    $roomId = (int)($_POST['room_id'] ?? 0);
    if (!$roomId) { jsonError('room_id шаардлагатай'); exit; }
    if (!canAccessRoom($pdo, $uid, $roomId)) { jsonError('Эрхгүй'); exit; }

    $room = $pdo->prepare('SELECT user_id, worker_id FROM chat_rooms WHERE id=?');
    $room->execute([$roomId]);
    $room = $room->fetch();

    if ($room['user_id'] == $uid) {
        // Хэрэглэгч → мессеж болон өрөө бүрмөсөн устгах
        $pdo->prepare('DELETE FROM chat_messages WHERE room_id=?')->execute([$roomId]);
        $pdo->prepare('DELETE FROM chat_rooms WHERE id=?')->execute([$roomId]);
        echo json_encode(['ok' => true, 'action' => 'deleted']);
    } else {
        // Ажилтан/Admin → архивд оруулах (мессеж хадгалагдана)
        $pdo->prepare('UPDATE chat_rooms SET archived_by_worker=1 WHERE id=?')->execute([$roomId]);
        echo json_encode(['ok' => true, 'action' => 'archived']);
    }
    exit;

// ── MY_ARCHIVED — ажилтны архив ──────────────────────────────
case 'my_archived':
    $stmt = $pdo->prepare('
        SELECT r.*,
            u1.ner as user_name,
            u2.ner as worker_name,
            (SELECT message FROM chat_messages
             WHERE room_id=r.id ORDER BY created_at DESC LIMIT 1) as last_msg,
            (SELECT created_at FROM chat_messages
             WHERE room_id=r.id ORDER BY created_at DESC LIMIT 1) as last_msg_at
        FROM chat_rooms r
        LEFT JOIN users u1 ON r.user_id   = u1.id
        LEFT JOIN users u2 ON r.worker_id = u2.id
        WHERE r.worker_id=? AND r.archived_by_worker=1
        ORDER BY last_msg_at DESC
    ');
    $stmt->execute([$uid]);
    echo json_encode(['ok' => true, 'rooms' => $stmt->fetchAll()]);
    exit;

// ── RESTORE_ROOM — архиваас сэргээх ──────────────────────────
case 'restore_room':
    $roomId = (int)($_POST['room_id'] ?? 0);
    if (!$roomId) { jsonError('room_id шаардлагатай'); exit; }
    $pdo->prepare('UPDATE chat_rooms SET archived_by_worker=0 WHERE id=? AND worker_id=?')
        ->execute([$roomId, $uid]);
    echo json_encode(['ok' => true]);
    exit;

// ── ONLINE_WORKERS — онлайн ажилтнуудын жагсаалт ───────────
case 'online_workers':
    $workers = getOnlineWorkers($pdo);
    echo json_encode(['ok' => true, 'workers' => $workers]);
    exit;

default:
    jsonError('Үл мэдэгдэх үйлдэл');
}


// ============================================================
//  Helper functions
// ============================================================

function pingOnline(PDO $pdo, int $uid): void {
    $pdo->prepare('INSERT INTO user_online (user_id, last_seen) VALUES (?, NOW())
                   ON DUPLICATE KEY UPDATE last_seen = NOW()')
        ->execute([$uid]);
}

function getOnlineWorkers(PDO $pdo): array {
    // Зөвхөн worker role-тай хэрэглэгч — admin/manager биш
    $stmt = $pdo->prepare('
        SELECT u.id, u.ner, u.email
        FROM users u
        JOIN roles r ON u.role_id = r.id
        JOIN user_online o ON o.user_id = u.id
        WHERE r.slug = "worker"
          AND u.is_active = 1
          AND o.last_seen > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        ORDER BY o.last_seen DESC
    ');
    $stmt->execute();
    return $stmt->fetchAll();
}

function getOrCreateUserWorkerRoom(PDO $pdo, int $userId): ?array {
    // Хэрэглэгчийн устгаагүй өрөө байгаа эсэх
    $stmt = $pdo->prepare('
        SELECT r.*, u.ner as worker_name
        FROM chat_rooms r
        JOIN users u ON r.worker_id = u.id
        WHERE r.room_type = "user_worker"
          AND r.user_id = ?
          AND r.deleted_by_user = 0
        ORDER BY r.created_at DESC
        LIMIT 1
    ');
    $stmt->execute([$userId]);
    $existing = $stmt->fetch();

    if ($existing) return $existing;

    // Онлайн ажилтан хайх
    $workers = getOnlineWorkers($pdo);
    if (empty($workers)) return null;

    $workerId = getBusiestWorker($pdo, $workers);

    $pdo->prepare('INSERT INTO chat_rooms (room_type, user_id, worker_id, deleted_by_user, archived_by_worker) VALUES ("user_worker", ?, ?, 0, 0)')
        ->execute([$userId, $workerId]);
    $roomId = $pdo->lastInsertId();

    $stmt = $pdo->prepare('
        SELECT r.*, u.ner as worker_name
        FROM chat_rooms r
        JOIN users u ON r.worker_id = u.id
        WHERE r.id = ?
    ');
    $stmt->execute([$roomId]);
    return $stmt->fetch();
}

function getBusiestWorker(PDO $pdo, array $workers): int {
    // Хамгийн бага идэвхтэй чаттай ажилтан
    $ids  = array_column($workers, 'id');
    $in   = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("
        SELECT worker_id, COUNT(*) as cnt
        FROM chat_rooms
        WHERE worker_id IN ($in) AND room_type='user_worker'
        GROUP BY worker_id
    ");
    $stmt->execute($ids);
    $loads = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $minLoad   = PHP_INT_MAX;
    $chosen    = $workers[0]['id'];
    foreach ($workers as $w) {
        $load = $loads[$w['id']] ?? 0;
        if ($load < $minLoad) { $minLoad = $load; $chosen = $w['id']; }
    }
    return $chosen;
}

function getOrCreateInternalRoom(PDO $pdo, int $uid, int $targetId): array {
    $a = min($uid, $targetId);
    $b = max($uid, $targetId);

    $stmt = $pdo->prepare('
        SELECT * FROM chat_rooms
        WHERE room_type="internal"
          AND ((user_id=? AND worker_id=?) OR (user_id=? AND worker_id=?))
        LIMIT 1
    ');
    $stmt->execute([$a, $b, $b, $a]);
    $room = $stmt->fetch();

    if (!$room) {
        $pdo->prepare('INSERT INTO chat_rooms (room_type, user_id, worker_id) VALUES ("internal", ?, ?)')
            ->execute([$a, $b]);
        $roomId = $pdo->lastInsertId();
        $stmt   = $pdo->prepare('SELECT * FROM chat_rooms WHERE id=?');
        $stmt->execute([$roomId]);
        $room = $stmt->fetch();
    }
    return $room;
}

function getOrCreateOrderRoom(PDO $pdo, int $uid, int $orderId): array {
    $stmt = $pdo->prepare('SELECT * FROM chat_rooms WHERE room_type="order" AND order_id=? LIMIT 1');
    $stmt->execute([$orderId]);
    $room = $stmt->fetch();

    if (!$room) {
        // Захиалгын worker_id авах
        $order = $pdo->prepare('SELECT user_id, worker_id FROM orders WHERE id=?');
        $order->execute([$orderId]);
        $order = $order->fetch();

        $pdo->prepare('INSERT INTO chat_rooms (room_type, user_id, worker_id, order_id) VALUES ("order",?,?,?)')
            ->execute([$order['user_id'], $order['worker_id'], $orderId]);
        $roomId = $pdo->lastInsertId();
        $stmt   = $pdo->prepare('SELECT * FROM chat_rooms WHERE id=?');
        $stmt->execute([$roomId]);
        $room = $stmt->fetch();
    }
    return $room;
}

function canAccessRoom(PDO $pdo, int $uid, int $roomId): bool {
    $stmt = $pdo->prepare('SELECT * FROM chat_rooms WHERE id=?');
    $stmt->execute([$roomId]);
    $room = $stmt->fetch();
    if (!$room) return false;
    return $room['user_id'] == $uid || $room['worker_id'] == $uid;
}

function getMyRooms(PDO $pdo, int $uid): array {
    $stmt = $pdo->prepare('
        SELECT
            r.*,
            u1.ner as user_name,
            u2.ner as worker_name,
            ro1.name as user_role_name,
            ro2.name as worker_role_name,
            (SELECT COUNT(*) FROM chat_messages
             WHERE room_id=r.id AND sender_id!=? AND is_read=0) as unread,
            (SELECT message FROM chat_messages
             WHERE room_id=r.id ORDER BY created_at DESC LIMIT 1) as last_msg,
            (SELECT created_at FROM chat_messages
             WHERE room_id=r.id ORDER BY created_at DESC LIMIT 1) as last_msg_at
        FROM chat_rooms r
        LEFT JOIN users u1  ON r.user_id   = u1.id
        LEFT JOIN users u2  ON r.worker_id = u2.id
        LEFT JOIN roles ro1 ON u1.role_id  = ro1.id
        LEFT JOIN roles ro2 ON u2.role_id  = ro2.id
        WHERE (r.user_id=? AND r.deleted_by_user=0)
           OR (r.worker_id=? AND r.archived_by_worker=0)
        ORDER BY last_msg_at DESC
    ');
    $stmt->execute([$uid, $uid, $uid]);
    return $stmt->fetchAll();
}

function formatMessage(array $msg, int $myUid): array {
    return [
        'id'          => (int)$msg['id'],
        'room_id'     => (int)$msg['room_id'],
        'sender_id'   => (int)$msg['sender_id'],
        'sender_name' => $msg['sender_name'],
        'message'     => $msg['message'],
        'is_read'     => (bool)$msg['is_read'],
        'is_mine'     => (int)$msg['sender_id'] === $myUid,
        'time'        => date('H:i', strtotime($msg['created_at'])),
        'date'        => date('m/d', strtotime($msg['created_at'])),
    ];
}

function jsonError(string $msg): void {
    echo json_encode(['ok' => false, 'error' => $msg]);
}