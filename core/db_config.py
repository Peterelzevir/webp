<?php
// Konfigurasi MongoDB Atlas
$mongo_connection_string = "ganti dengan url mongodb mu";

// Nama database
$mongo_db = "mongodb nama database nya";

// Nama koleksi (collections)
$mongo_sessions_collection = "sessions";
$mongo_telegram_collection = "telegram_data";

// Pengaturan TTL (Time To Live) untuk session
// 0 berarti data disimpan permanen (tidak ada TTL/auto-delete)
$mongo_session_ttl = 0;
