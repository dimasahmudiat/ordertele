<?php
/**
 * Database Proxy Client
 * Handles communication with hosting database via API
 */

class DatabaseProxy {
    private $apiUrl;
    private $apiKey;
    
    public function __construct() {
        $this->apiUrl = rtrim(HOSTING_URL, '/') . '/api/db-proxy.php';
        $this->apiKey = DB_PROXY_KEY;
    }
    
    /**
     * Make API request to hosting
     */
    private function request($action, $data = []) {
        $data['action'] = $action;
        $data['api_key'] = $this->apiKey;
        
        $ch = curl_init($this->apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            logMessage("DatabaseProxy Error: " . $error);
            return ['success' => false, 'error' => $error];
        }
        
        $result = json_decode($response, true);
        logMessage("DatabaseProxy Response - Action: $action, Result: " . json_encode($result));
        
        return $result ?: ['success' => false, 'error' => 'Invalid response'];
    }
    
    /**
     * Check if username exists
     */
    public function isUsernameExists($username, $table = null) {
        $result = $this->request('isUsernameExists', [
            'username' => $username,
            'table' => $table
        ]);
        
        return $result['success'] ? $result['exists'] : false;
    }
    
    /**
     * Get user by username and password
     */
    public function getUserByUsernameAndPassword($username, $password, $gameType = null) {
        $result = $this->request('getUserByUsernameAndPassword', [
            'username' => $username,
            'password' => $password,
            'game_type' => $gameType
        ]);
        
        return $result['success'] ? $result['user'] : false;
    }
    
    /**
     * Extend user license
     */
    public function extendUserLicense($username, $password, $duration, $gameType) {
        $result = $this->request('extendUserLicense', [
            'username' => $username,
            'password' => $password,
            'duration' => $duration,
            'game_type' => $gameType
        ]);
        
        return $result['success'] ? $result['affected'] > 0 : false;
    }
    
    /**
     * Save license to database
     */
    public function saveLicenseToDatabase($table, $username, $password, $duration, $reference) {
        $result = $this->request('saveLicenseToDatabase', [
            'table' => $table,
            'username' => $username,
            'password' => $password,
            'duration' => $duration,
            'reference' => $reference
        ]);
        
        return $result['success'] ? $result['saved'] : false;
    }
    
    /**
     * Save user state
     */
    public function saveUserState($chatId, $state, $data = []) {
        $result = $this->request('saveUserState', [
            'chat_id' => $chatId,
            'state' => $state,
            'data' => json_encode($data)
        ]);
        
        return $result['success'] ?? false;
    }
    
    /**
     * Get user state
     */
    public function getUserState($chatId) {
        $result = $this->request('getUserState', [
            'chat_id' => $chatId
        ]);
        
        return $result['success'] ? $result['state'] : null;
    }
    
    /**
     * Clear user state
     */
    public function clearUserState($chatId) {
        $result = $this->request('clearUserState', [
            'chat_id' => $chatId
        ]);
        
        return $result['success'] ?? false;
    }
    
    /**
     * Save pending order
     */
    public function savePendingOrder($orderId, $chatId, $gameType, $duration, $amount, $depositCode, $keyType, $manualUsername = '', $manualPassword = '') {
        $result = $this->request('savePendingOrder', [
            'order_id' => $orderId,
            'chat_id' => $chatId,
            'game_type' => $gameType,
            'duration' => $duration,
            'amount' => $amount,
            'deposit_code' => $depositCode,
            'key_type' => $keyType,
            'manual_username' => $manualUsername,
            'manual_password' => $manualPassword
        ]);
        
        return $result['success'] ?? false;
    }
    
    /**
     * Get pending order
     */
    public function getPendingOrder($chatId) {
        $result = $this->request('getPendingOrder', [
            'chat_id' => $chatId
        ]);
        
        return $result['success'] ? $result['order'] : false;
    }
    
    /**
     * Update order status
     */
    public function updateOrderStatus($depositCode, $status) {
        $result = $this->request('updateOrderStatus', [
            'deposit_code' => $depositCode,
            'status' => $status
        ]);
        
        return $result['success'] ?? false;
    }
    
    /**
     * Get user points
     */
    public function getUserPoints($chatId) {
        $result = $this->request('getUserPoints', [
            'chat_id' => $chatId
        ]);
        
        return $result['success'] ? $result['points'] : 0;
    }
    
    /**
     * Add user points
     */
    public function addUserPoints($chatId, $points, $description = '') {
        $result = $this->request('addUserPoints', [
            'chat_id' => $chatId,
            'points' => $points,
            'description' => $description
        ]);
        
        return $result['success'] ?? false;
    }
    
    /**
     * Redeem user points
     */
    public function redeemUserPoints($chatId, $points, $description = '') {
        $result = $this->request('redeemUserPoints', [
            'chat_id' => $chatId,
            'points' => $points,
            'description' => $description
        ]);
        
        return $result['success'] ?? false;
    }
    
    /**
     * Save bot user
     */
    public function saveBotUser($chatId, $firstName, $username) {
        $result = $this->request('saveBotUser', [
            'chat_id' => $chatId,
            'first_name' => $firstName,
            'username' => $username
        ]);
        
        return $result['success'] ?? false;
    }
    
    /**
     * Get total bot users
     */
    public function getTotalBotUsers() {
        $result = $this->request('getTotalBotUsers', []);
        return $result['success'] ? $result['total'] : 0;
    }
    
    /**
     * Get all bot users for broadcast
     */
    public function getAllBotUsers() {
        $result = $this->request('getAllBotUsers', []);
        return $result['success'] ? $result['users'] : [];
    }
    
    /**
     * Save admin state
     */
    public function saveAdminState($chatId, $state) {
        $result = $this->request('saveAdminState', [
            'chat_id' => $chatId,
            'state' => $state
        ]);
        
        return $result['success'] ?? false;
    }
    
    /**
     * Get admin state
     */
    public function getAdminState($chatId) {
        $result = $this->request('getAdminState', [
            'chat_id' => $chatId
        ]);
        
        return $result['success'] ? $result['state'] : null;
    }
    
    /**
     * Clear admin state
     */
    public function clearAdminState($chatId) {
        $result = $this->request('clearAdminState', [
            'chat_id' => $chatId
        ]);
        
        return $result['success'] ?? false;
    }
    
    /**
     * Save broadcast history
     */
    public function saveBroadcastHistory($adminId, $type, $messageType, $total, $success, $failed) {
        $result = $this->request('saveBroadcastHistory', [
            'admin_id' => $adminId,
            'type' => $type,
            'message_type' => $messageType,
            'total' => $total,
            'success' => $success,
            'failed' => $failed
        ]);
        
        return $result['success'] ?? false;
    }
    
    /**
     * Schedule auto delete
     */
    public function scheduleAutoDelete($chatId, $messageId, $delaySeconds, $type = 'pending') {
        $result = $this->request('scheduleAutoDelete', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'delay' => $delaySeconds,
            'type' => $type
        ]);
        
        return $result['success'] ?? false;
    }
    
    /**
     * Cancel auto delete
     */
    public function cancelAutoDelete($chatId, $messageId) {
        $result = $this->request('cancelAutoDelete', [
            'chat_id' => $chatId,
            'message_id' => $messageId
        ]);
        
        return $result['success'] ?? false;
    }
    
    /**
     * Process auto delete
     */
    public function processAutoDelete() {
        $result = $this->request('processAutoDelete', []);
        return $result['success'] ?? false;
    }
    
    /**
     * Start real-time payment check
     */
    public function startRealTimePaymentCheck($chatId, $messageId) {
        $result = $this->request('startRealTimePaymentCheck', [
            'chat_id' => $chatId,
            'message_id' => $messageId
        ]);
        
        return $result['success'] ?? false;
    }
    
    /**
     * Process real-time payment checks
     */
    public function processRealTimePaymentChecks() {
        $result = $this->request('processRealTimePaymentChecks', []);
        return $result['success'] ?? false;
    }
}
