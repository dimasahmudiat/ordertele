<?php
/**
 * Constants and Configuration
 */

// Error Reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error_log.txt');
date_default_timezone_set('Asia/Jakarta');

// Load environment variables from Vercel
function getEnvVar($key, $default = '') {
    $value = getenv($key);
    return $value !== false ? $value : $default;
}

// Bot Configuration
define('BOT_TOKEN', getEnvVar('BOT_TOKEN'));

// Database Proxy Configuration
define('DB_PROXY_KEY', getEnvVar('DB_PROXY_KEY'));
define('HOSTING_URL', getEnvVar('HOSTING_URL'));

// API Configuration
define('API_KEY', getEnvVar('API_KEY'));
define('MERCHANT_CODE', getEnvVar('MERCHANT_CODE'));

// Path Configuration
define('WELCOME_IMAGE', getEnvVar('WELCOME_IMAGE'));

// Timeout Configuration (25 minutes)
define('ORDER_TIMEOUT', 1500); // 25 * 60 = 1500 seconds
define('PAYMENT_CHECK_INTERVAL', 20); // Check every 20 seconds

// Price Configuration
$prices = [
    '1' => 15000,
    '2' => 30000,
    '3' => 40000,
    '4' => 50000,
    '5' => 60000,
    '6' => 70000,
    '7' => 80000,
    '8' => 90000,
    '10' => 100000,
    '15' => 150000,
    '20' => 180000,
    '30' => 250000
];

// Admin IDs
$admins = ['123456789']; // Ganti dengan ID admin Telegram Anda

// Log directory
if (!file_exists(__DIR__ . '/../logs')) {
    mkdir(__DIR__ . '/../logs', 0755, true);
}

/**
 * Logging function
 */
function logMessage($message) {
    $logFile = __DIR__ . '/../logs/bot_log.txt';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] " . $message . "\n", FILE_APPEND);
}

/**
 * Get global prices
 */
function getPrices() {
    global $prices;
    return $prices;
}

/**
 * Check if user is admin
 */
function isAdmin($chatId) {
    global $admins;
    return in_array($chatId, $admins);
}
