<?php
// عرض جميع أخطاء PHP
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// تسجيل كل طلب وارد في ملف نصي
file_put_contents(__DIR__ . '/bot_log.txt', date('Y-m-d H:i:s') . "\n" . file_get_contents("php://input") . "\n-------------------\n", FILE_APPEND);

// Telegram Bot Token
$botToken = '8093702414:AAH8rhE2HKCaHtwaPPPDea7JDU5T_mYvgoc';
$apiURL = "https://api.telegram.org/bot$botToken/";

// Connect to database (بيانات الاستضافة المجانية)
$db = new mysqli('sql100.infinityfree.com', 'if0_39649054', '16zm0oBAe61', 'if0_39649054_mlms');
if ($db->connect_error) {
    die('Database connection error: ' . $db->connect_error);
}

// Get incoming update from Telegram
$content = file_get_contents("php://input");
$update = json_decode($content, true);

// تسجيل كل رسالة واردة في ملف نصي (مع محتوى الرسالة الخام)
$logFile = __DIR__ . '/bot_log.txt';
$logData = date('Y-m-d H:i:s') . "\nCONTENT:\n" . $content . "\nUPDATE:\n" . print_r($update, true) . "\n-------------------\n";
file_put_contents($logFile, $logData, FILE_APPEND);

if (!$update || !isset($update['message'])) {
    exit;
}

$chat_id = $update['message']['chat']['id'];
$text = trim($update['message']['text']);
$first_name = isset($update['message']['from']['first_name']) ? $update['message']['from']['first_name'] : '';

// تحقق من رمز الاشتراك
function activate_subscription($code, $db, $chat_id) {
    // مثال: تحقق من وجود الرمز في جدول الاشتراكات
    $stmt = $db->prepare("SELECT id, status FROM subscriptions WHERE code = ? LIMIT 1");
    $stmt->bind_param('s', $code);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        if ($row['status'] == 'active') {
            return 'الاشتراك مفعل مسبقاً.';
        } else {
            // فعل الاشتراك
            $updateStmt = $db->prepare("UPDATE subscriptions SET status = 'active', user_id = ? WHERE id = ?");
            $updateStmt->bind_param('ii', $chat_id, $row['id']);
            $updateStmt->execute();
            return 'تم تفعيل الاشتراك بنجاح.';
        }
    } else {
        return 'رمز الاشتراك غير صحيح.';
    }
}

// الرد على المستخدم
function sendMessage($chat_id, $text, $apiURL) {
    $url = $apiURL . "sendMessage";
    $data = [
        'chat_id' => $chat_id,
        'text' => $text
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

// معالجة الرسالة
// تخزين نوع المستخدم مؤقتًا (يفضل لاحقًا تخزينه في قاعدة بيانات)
session_start();
if (!isset($_SESSION['user_type_' . $chat_id])) {
    $_SESSION['user_type_' . $chat_id] = null;
}

if ($text) {
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
        $_SESSION['user_type_' . $chat_id] = 'student';
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
    } elseif ($text == '👨‍🏫 مدرس') {
        $_SESSION['user_type_' . $chat_id] = 'teacher';
        $reply = "مرحباً بك كمدرس!\nاختر الخدمة المطلوبة:";
        $keyboard = [
            'keyboard' => [
                [['text' => '✅ استعراض الطلاب المشتركين'], ['text' => '✅ توليد تقارير']],
                [['text' => '✅ إرسال إشعارات'], ['text' => '✅ رفع محتوى جديد']],
                [['text' => '✅ إضافة/إزالة طالب من الجروب']]
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => false
        ];
        sendMessageWithKeyboard($chat_id, $reply, $apiURL, $keyboard);
    } else {
        // منطق الوظائف حسب نوع المستخدم
        if ($_SESSION['user_type_' . $chat_id] == 'student') {
            // وظائف الطالب
            if ($text == '✅ الاشتراك في كورس') {
                sendMessage($chat_id, "يرجى اختيار الكورس المناسب أو إرسال اسمه.", $apiURL);
            } elseif ($text == '✅ معرفة حالة الاشتراك') {
                sendMessage($chat_id, "سيتم عرض حالة اشتراكك هنا.", $apiURL);
            } elseif ($text == '✅ دخول جروب الكورس') {
                sendMessage($chat_id, "سيتم إرسال رابط الجروب بعد الدفع.", $apiURL);
            } elseif ($text == '✅ الوصول للمحتوى') {
                sendMessage($chat_id, "روابط الفيديوهات أو الملفات ستظهر هنا.", $apiURL);
            } elseif ($text == '✅ امتحانات وتقييمات') {
                sendMessage($chat_id, "امتحانات قصيرة ستظهر هنا أو رابط خارجي.", $apiURL);
            } elseif ($text == '✅ التواصل مع الدعم الفني') {
                sendMessage($chat_id, "للتواصل مع الدعم الفني: 0123456789 أو اضغط هنا.", $apiURL);
            } else {
                sendMessage($chat_id, "يرجى اختيار خدمة من القائمة.", $apiURL);
            }
        } elseif ($_SESSION['user_type_' . $chat_id] == 'teacher') {
            // وظائف المدرس
            if ($text == '✅ استعراض الطلاب المشتركين') {
                sendMessage($chat_id, "سيتم عرض قائمة الطلاب المشتركين هنا.", $apiURL);
            } elseif ($text == '✅ توليد تقارير') {
                sendMessage($chat_id, "سيتم توليد تقرير يومي أو أسبوعي هنا.", $apiURL);
            } elseif ($text == '✅ إرسال إشعارات') {
                sendMessage($chat_id, "يمكنك إرسال إشعار لكل الطلاب هنا.", $apiURL);
            } elseif ($text == '✅ رفع محتوى جديد') {
                sendMessage($chat_id, "يمكنك رفع لينك أو ملف وسيصل للطلاب تلقائياً.", $apiURL);
            } elseif ($text == '✅ إضافة/إزالة طالب من الجروب') {
                sendMessage($chat_id, "يمكنك إضافة أو إزالة طالب من الجروب هنا.", $apiURL);
            } else {
                sendMessage($chat_id, "يرجى اختيار خدمة من القائمة.", $apiURL);
            }
        } else {
            sendMessage($chat_id, "يرجى اختيار نوع المستخدم أولاً عبر /start.", $apiURL);
        }
    }
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
?>
