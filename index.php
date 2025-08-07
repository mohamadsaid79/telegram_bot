<?php
// عرض جميع أخطاء PHP
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$botToken = '8093702414:AAH8rhE2HKCaHtwaPPPDea7JDU5T_mYvgoc';
$apiURL = "https://api.telegram.org/bot$botToken/";

// بيانات الاتصال بقاعدة البيانات Railway أو MySQL
$db = new mysqli('sql100.infinityfree.com', 'if0_39649054', '16zm0oBAe61', 'if0_39649054_mlms');
if ($db->connect_error) {
    file_put_contents(__DIR__ . '/bot_log.txt', 'DB Error: ' . $db->connect_error . "\n", FILE_APPEND);
    exit;
}

$content = file_get_contents("php://input");
$update = json_decode($content, true);
file_put_contents(__DIR__ . '/bot_log.txt', date('Y-m-d H:i:s') . "\n" . $content . "\n-------------------\n", FILE_APPEND);

if (!$update || !isset($update['message'])) {
    exit;
}

$chat_id = $update['message']['chat']['id'];
$text = trim($update['message']['text']);
$first_name = isset($update['message']['from']['first_name']) ? $update['message']['from']['first_name'] : '';

// دالة إرسال رسالة
function sendMessage($chat_id, $text, $apiURL) {
    $url = $apiURL . "sendMessage";
    $data = [
        'chat_id' => $chat_id,
        'text' => $text
    ];
    file_get_contents($url . '?' . http_build_query($data));
}

// دالة إرسال رسالة مع لوحة مفاتيح
function sendMessageWithKeyboard($chat_id, $text, $apiURL, $keyboard) {
    $url = $apiURL . "sendMessage";
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'reply_markup' => json_encode($keyboard, JSON_UNESCAPED_UNICODE)
    ];
    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data),
        ],
    ];
    $context  = stream_context_create($options);
    file_get_contents($url, false, $context);
}

// تفعيل الاشتراك عبر رمز التفعيل
function activate_subscription($code, $db, $telegram_id) {
    $stmt = $db->prepare("SELECT id, course_id, max_uses, current_uses, course_title FROM activation_codes WHERE code = ? LIMIT 1");
    $stmt->bind_param('s', $code);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        if ($row['current_uses'] >= $row['max_uses']) {
            return 'تم استخدام رمز التفعيل بالكامل.';
        }
        $check = $db->prepare("SELECT id FROM code_activations WHERE user_id = ? AND course_id = ?");
        $check->bind_param('ii', $telegram_id, $row['course_id']);
        $check->execute();
        $checkResult = $check->get_result();
        if ($checkResult->fetch_assoc()) {
            return 'أنت مشترك بالفعل في هذا الكورس.';
        }
        $insert = $db->prepare("INSERT INTO code_activations (user_id, course_id, code_id) VALUES (?, ?, ?)");
        $insert->bind_param('iii', $telegram_id, $row['course_id'], $row['id']);
        $insert->execute();
        $update = $db->prepare("UPDATE activation_codes SET current_uses = current_uses + 1 WHERE id = ?");
        $update->bind_param('i', $row['id']);
        $update->execute();
        return 'تم تفعيل اشتراكك في كورس: ' . $row['course_title'] . ' بنجاح.';
    } else {
        return 'رمز التفعيل غير صحيح.';
    }
}

// منطق البوت
if ($text == "/start") {
    $reply = "مرحباً $first_name 👋\n\nيرجى اختيار نوع المستخدم:\n👨‍🎓 طالب\n👨‍🏫 مدرس";
    $keyboard = [
        'keyboard' => [
            [['text' => '👨‍🎓 طالب'], ['text' => '👨‍🏫 مدرس']]
        ],
        'resize_keyboard' => true,
        'one_time_keyboard' => true
    ];
    sendMessageWithKeyboard($chat_id, $reply, $apiURL, $keyboard);
} elseif ($text == '👨‍🎓 طالب') {
    $reply = "أهلاً بك كطالب!\nاختر الخدمة المطلوبة:";
    $keyboard = [
        'keyboard' => [
            [['text' => '✅ الاشتراك في كورس'], ['text' => '✅ معرفة حالة الاشتراك']],
            [['text' => '✅ دخول جروب الكورس'], ['text' => '✅ الوصول للمحتوى']],
            [['text' => '✅ امتحانات وتقييمات'], ['text' => '✅ التواصل مع الدعم الفني']]
        ],
        'resize_keyboard' => true,
        'one_time_keyboard' => false
    ];
    sendMessageWithKeyboard($chat_id, $reply, $apiURL, $keyboard);
} elseif ($text == '✅ الاشتراك في كورس') {
    sendMessage($chat_id, "يرجى إرسال رمز التفعيل الخاص بك.", $apiURL);
} elseif (preg_match('/^[A-Z0-9]{8}$/', $text)) {
    $reply = activate_subscription($text, $db, $chat_id);
    sendMessage($chat_id, $reply, $apiURL);
} elseif ($text == '✅ معرفة حالة الاشتراك') {
    $stmt = $db->prepare("SELECT a.course_id, c.course_title FROM code_activations a JOIN activation_codes c ON a.code_id = c.id WHERE a.user_id = ?");
    $stmt->bind_param('i', $chat_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        sendMessage($chat_id, "أنت مشترك في كورس: " . $row['course_title'], $apiURL);
    } else {
        sendMessage($chat_id, "أنت غير مشترك في أي كورس حالياً.", $apiURL);
    }
} else {
    sendMessage($chat_id, "يرجى اختيار خدمة من القائمة أو إرسال رمز التفعيل.", $apiURL);
}
?>
