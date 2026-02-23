<?php
/**
 * Main Webhook Handler for Vercel
 * Bot Order 24 Jam - DIMZMODS
 */

// Load environment variables
require_once __DIR__ . '/../config/constants.php';

// Log incoming request
$input = file_get_contents('php://input');
logMessage("[" . date('Y-m-d H:i:s') . "] Webhook received: " . substr($input, 0, 500));

$update = json_decode($input, true);

if (!$update) {
    logMessage("No update data received");
    http_response_code(200);
    echo "OK";
    exit;
}

// Load bot handler
require_once __DIR__ . '/../includes/BotHandler.php';

$bot = new BotHandler();
$bot->handleUpdate($update);

// Always return 200 OK
http_response_code(200);
echo "OK";
