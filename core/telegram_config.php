<?php
// Telegram API credentials
$telegram_api_id = 25260996;
$telegram_api_hash = "a29a1f51eb3578b32a651c0fd7f07ccb";

// Konfigurasi server Flask - GANTI DENGAN URL BACKEND ANDA
$flask_server_url = "https://your-backend-domain.com";

// Make the URL accessible as a constant
define('FLASK_SERVER_URL', $flask_server_url);
