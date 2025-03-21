<?php
$apiToken = "7893704603:AAFscb9GxWRccAHuVmqwYmA_ZHRXJE6SP2c";
$chatId = "6535071557";

function sendTelegram($message)
{
    global $apiToken, $chatId;

    $telegramUrl = "https://api.telegram.org/bot$apiToken/sendMessage";

    $options = [
        'chat_id' => $chatId,
        'text' => $message,
        'parse_mode' => 'Markdown',
    ];

    $curl = curl_init($telegramUrl);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $options);
    $response = curl_exec($curl);
    curl_close($curl);
    
    // Simpan ke MongoDB jika handler tersedia
    if (isset($GLOBALS['mongoSessionHandler'])) {
        $GLOBALS['mongoSessionHandler']->saveTelegramData($message, [
            'telegram_response' => $response
        ]);
    }

    return $response;
}
