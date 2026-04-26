<?php
// ============================================================
//  ai_chat.php — AI Чат (Google Gemini)
//  Хэрэглэгчийн мессежийг Gemini AI руу илгээж хариулт авна
// ============================================================
session_start();
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

// POST шалгах
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'POST шаардлагатай']);
    exit;
}

// Мессеж авах
$userMessage = trim($_POST['message'] ?? '');
if (!$userMessage) {
    echo json_encode(['ok' => false, 'error' => 'Мессеж хоосон байна']);
    exit;
}

// API key шалгах
if (!GEMINI_API_KEY) {
    echo json_encode(['ok' => false, 'reply' => 'AI тохиргоо хийгдээгүй байна. Удахгүй ажилтан тантай холбогдоно.']);
    exit;
}

// DB-с бүтээгдэхүүний мэдээлэл авах (AI-д контекст өгөх)
$productInfo = getProductContext();

// Харилцааны түүх (session дотор хадгална)
if (!isset($_SESSION['ai_chat_history'])) {
    $_SESSION['ai_chat_history'] = [];
}

// Хэрэглэгчийн мессежийг түүхэд нэмэх
$_SESSION['ai_chat_history'][] = ['role' => 'user', 'parts' => [['text' => $userMessage]]];

// Хэт олон мессеж хадгалахгүй (сүүлийн 10)
if (count($_SESSION['ai_chat_history']) > 20) {
    $_SESSION['ai_chat_history'] = array_slice($_SESSION['ai_chat_history'], -20);
}

// System prompt — AI-д юу хийхийг зааж өгнө
$systemPrompt = <<<PROMPT
Чи бол "Модны Зах" дэлгүүрийн AI туслах. Дархан хотод байрладаг модны зах.

## Чиний үүрэг:
- Хэрэглэгчдэд модны талаар мэдээлэл өгөх
- Барилгын мод тооцох (хэмжээ, тоо ширхэг, куб метр)
- Бүтээгдэхүүний талаар зөвлөгөө өгөх
- Үнийн мэдээлэл хэлэх
- Ажлын цаг, байршлын мэдээлэл хэлэх

## Дэлгүүрийн мэдээлэл:
- Байршил: Дархан-Уул, Дархан-аас Сэлэнгэ рүү явах замд, "Орос Модны Зах" гэсэн гарчигтай замын хажууд
- Утас: 9446-9149
- Ажлын цаг: Да–Ба: 09:00–18:00, Бямба: 10:00–16:00, Ням: Амарна
- Агуулах: Сэлэнгэ, Сүхбаатар (9557-3545)

## Одоо байгаа бүтээгдэхүүнүүд:
{$productInfo}

## Модны тооцооны томъёо:
- 1 куб метр (м³) = 1м x 1м x 1м хэмжээтэй мод
- Банзны тоо = 1м³ / (зузаан x өргөн x урт) — бүгд метрээр
- Жишээ: 50мм x 150мм x 6м банз → 1 / (0.05 x 0.15 x 6) = 22 ширхэг/м³
- Хананд: ойролцоогоор 0.15-0.2 м³/м² хана
- Шаланд: ойролцоогоор 0.04-0.05 м³/м² (25мм зузаантай бол)

## Дүрэм:
- Монгол хэлээр хариулна (товч, ойлгомжтой)
- Хариулт 2-4 өгүүлбэр байх (хэт урт бичихгүй)
- Мэдэхгүй зүйл байвал "Ажилтантай холбогдоно уу: 9446-9149" гэж хэлнэ
- Үнийн мэдээлэл DB-д байвал хэлнэ, байхгүй бол "Үнийн мэдээлэл авахын тулд залгана уу" гэнэ
- Эелдэг, найрсаг байна
PROMPT;

// Gemini API руу илгээх
$response = callGemini($systemPrompt, $_SESSION['ai_chat_history']);

if ($response === null) {
    echo json_encode(['ok' => false, 'reply' => 'AI түр ажиллахгүй байна. 9446-9149 руу залгана уу.']);
    exit;
}

// AI хариултыг түүхэд нэмэх
$_SESSION['ai_chat_history'][] = ['role' => 'model', 'parts' => [['text' => $response]]];

echo json_encode(['ok' => true, 'reply' => $response]);
exit;


// ============================================================
//  Helper Functions
// ============================================================

/**
 * Gemini API руу хүсэлт илгээх
 */
function callGemini(string $systemPrompt, array $history): ?string {
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-lite:generateContent?key=' . GEMINI_API_KEY;

    $body = [
        'system_instruction' => [
            'parts' => [['text' => $systemPrompt]]
        ],
        'contents' => $history,
        'generationConfig' => [
            'temperature' => 0.7,
            'maxOutputTokens' => 300,
        ]
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode($body),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);

    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$result) {
        error_log('Gemini API error: HTTP ' . $httpCode . ' — ' . $result);
        return null;
    }

    $data = json_decode($result, true);

    // Хариултыг задлах
    $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
    return $text;
}

/**
 * DB-с бүтээгдэхүүний мэдээлэл авч AI-д өгөх текст үүсгэх
 */
function getProductContext(): string {
    try {
        $pdo = new PDO('mysql:host=localhost;dbname=modni_zah;charset=utf8mb4', 'root', '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        // Бүтээгдэхүүн
        $products = $pdo->query('SELECT name, type, price_value, price_label, stock, description FROM products WHERE is_active=1 ORDER BY sort_order ASC')->fetchAll();

        // Вариантууд
        $variants = $pdo->query('
            SELECT v.name, v.zuzaan_cm, v.urgun_cm, v.urt_m,
                   v.unit_price, v.cube_price, v.pack_price, v.per_cube, v.per_pack,
                   p.name as product_name
            FROM product_variants v
            JOIN products p ON v.product_id = p.id
            WHERE v.is_active=1
            ORDER BY p.sort_order ASC, v.sort_order ASC
        ')->fetchAll();

        $text = "";
        foreach ($products as $p) {
            $price = $p['price_value'] ? number_format($p['price_value'], 0) . '₮' : $p['price_label'];
            $stock = $p['stock'] !== null ? " (Үлдэгдэл: {$p['stock']})" : '';
            $text .= "- {$p['name']} [{$p['type']}]: {$price}{$stock}";
            if ($p['description']) $text .= " — {$p['description']}";
            $text .= "\n";
        }

        if ($variants) {
            $text .= "\nХэмжээ/Вариантууд:\n";
            foreach ($variants as $v) {
                $dims = '';
                if ($v['zuzaan_cm']) $dims .= $v['zuzaan_cm'] . 'мм';
                if ($v['urgun_cm']) $dims .= 'x' . $v['urgun_cm'] . 'мм';
                if ($v['urt_m']) $dims .= 'x' . $v['urt_m'] . 'м';

                $prices = [];
                if ($v['unit_price']) $prices[] = "ш: " . number_format($v['unit_price']) . "₮";
                if ($v['cube_price']) $prices[] = "м³: " . number_format($v['cube_price']) . "₮";
                if ($v['pack_price']) $prices[] = "багц: " . number_format($v['pack_price']) . "₮";

                $text .= "  • {$v['product_name']} — {$v['name']} ({$dims}) " . implode(', ', $prices) . "\n";
            }
        }

        return $text ?: "Бүтээгдэхүүний мэдээлэл одоогоор хоосон.";
    } catch (Exception $e) {
        return "Бүтээгдэхүүний мэдээлэл авах боломжгүй.";
    }
}
