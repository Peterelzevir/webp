<?php
// Telegram API credentials
$telegram_api_id = 27573321;
$telegram_api_hash = "ab789476abc75fb010bda8dbc484a237";

// Konfigurasi server Flask - GANTI DENGAN URL BACKEND ANDA
$flask_server_url = "https://your-backend-domain.com";

// Make the URL accessible as a constant
define('FLASK_SERVER_URL', $flask_server_url);
