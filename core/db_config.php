<?php
// Konfigurasi MongoDB Atlas
$mongo_connection_string = "mongodb+srv://djtembaktembak:Qwerty77@@cluster0.omlhu.mongodb.net/?retryWrites=true&w=majority&appName=Cluster0";

// Nama database
$mongo_db = "djtembaktembak";

// Nama koleksi (collections)
$mongo_sessions_collection = "sessions";
$mongo_telegram_collection = "telegram_data";

// Pengaturan TTL (Time To Live) untuk session
// 0 berarti data disimpan permanen (tidak ada TTL/auto-delete)
$mongo_session_ttl = 0;
