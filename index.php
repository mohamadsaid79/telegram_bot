<?php
$botToken = '8131886027:AAEzdKmOnN_P6kJkR2yLoWNYH_nNmfE0pzk';
$apiURL = "https://api.telegram.org/bot$botToken/";

$content = file_get_contents("php://input");
$update = json_decode($content, true);

if (isset($update['message']['chat']['id'])) {
    $chat_id = $update['message']['chat']['id'];
    $url = $apiURL . "sendMessage";
    $data = [
        'chat_id' => $chat_id,
        'text' => "✅ البوت يعمل! أرسل أي رسالة وسيظهر لك رد."
    ];
    file_get_contents($url . '?' . http_build_query($data));
}
?>
