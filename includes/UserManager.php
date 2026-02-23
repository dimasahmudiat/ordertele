<?php
/**
 * User Manager
 */

class UserManager {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Save user state
     */
    public function saveUserState($chatId, $state, $data = []) {
        return $this->db->saveUserState($chatId, $state, $data);
    }
    
    /**
     * Get user state
     */
    public function getUserState($chatId) {
        return $this->db->getUserState($chatId);
    }
    
    /**
     * Clear user state
     */
    public function clearUserState($chatId) {
        return $this->db->clearUserState($chatId);
    }
    
    /**
     * Update user error count
     */
    public function updateUserErrorCount($chatId, $errorCount) {
        // Implement if needed
    }
    
    /**
     * Reset user error count
     */
    public function resetUserErrorCount($chatId) {
        // Implement if needed
    }
}
