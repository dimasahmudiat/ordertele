<?php
/**
 * Main Bot Handler
 * Contains all bot logic (converted from original bot.php)
 */

require_once __DIR__ . '/DatabaseProxy.php';
require_once __DIR__ . '/PaymentHandler.php';
require_once __DIR__ . '/UserManager.php';

class BotHandler {
    private $db;
    private $payment;
    private $userManager;
    
    public function __construct() {
        $this->db = new DatabaseProxy();
        $this->payment = new PaymentHandler();
        $this->userManager = new UserManager($this->db);
    }
    
    /**
     * Handle incoming update
     */
    public function handleUpdate($update) {
        // Process auto delete and real-time checks
        $this->db->processAutoDelete();
        $this->db->processRealTimePaymentChecks();
        
        // Extract basic info
        $chatId = $update['message']['chat']['id'] ?? ($update['callback_query']['message']['chat']['id'] ?? '');
        $text = $update['message']['text'] ?? '';
        $firstName = $update['message']['chat']['first_name'] ?? 'User';
        $messageId = $update['message']['message_id'] ?? '';
        
        logMessage("Processing - ChatID: $chatId, Text: $text, Name: $firstName");
        
        // Handle callback queries
        if (isset($update['callback_query'])) {
            $this->handleCallbackQuery($update['callback_query']);
            return;
        }
        
        // Handle text messages
        if (!empty($text)) {
            $this->handleTextMessage($chatId, $text, $firstName, $messageId, $update);
        }
    }
    
    /**
     * Handle text messages
     */
    private function handleTextMessage($chatId, $text, $firstName, $messageId, $update) {
        // Check for admin broadcast first
        $adminState = $this->db->getAdminState($chatId);
        if ($adminState && (strpos($adminState['state'], 'waiting_broadcast') === 0)) {
            $this->handleAdminBroadcast($chatId, $update, $adminState);
            return;
        }
        
        // Start command
        if (strpos($text, '/start') === 0) {
            $this->userManager->clearUserState($chatId);
            
            // Save bot user
            $username = $update['message']['chat']['username'] ?? '';
            $this->db->saveBotUser($chatId, $firstName, $username);
            
            logMessage("User $chatId started bot");
            
            $userPoints = $this->db->getUserPoints($chatId);
            
            $welcomeMessage = "🎮 <b>Selamat Datang, $firstName!</b>\n\n";
            $welcomeMessage .= "✨ <b>BOT PEMBELIAN LISENSI FREE FIRE</b> ✨\n\n";
            $welcomeMessage .= "💰 <b>Point Anda:</b> $userPoints points\n\n";
            $welcomeMessage .= "🛒 <b>Fitur yang tersedia:</b>\n";
            $welcomeMessage .= "• Beli lisensi baru (Random/Manual)\n";
            $welcomeMessage .= "• Extend masa aktif akun\n";
            $welcomeMessage .= "• Tukar point dengan lisensi gratis\n";
            $welcomeMessage .= "• Support Free Fire & Free Fire MAX\n";
            $welcomeMessage .= "• Pembayaran QRIS otomatis\n\n";
            $welcomeMessage .= "💰 <b>Harga mulai dari Rp 15.000</b>\n";
            $welcomeMessage .= "🎁 <b>Dapatkan point untuk setiap pembelian!</b>\n\n";
            $welcomeMessage .= "⏰ <b>Pembayaran otomatis terdeteksi dalam 25 menit!</b>\n\n";
            $welcomeMessage .= "Silakan pilih menu di bawah:";
            
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '🛒 Beli Lisensi Baru', 'callback_data' => 'new_order']
                    ],
                    [
                        ['text' => '⏰ Extend Masa Aktif', 'callback_data' => 'extend_user'],
                        ['text' => '🎁 Tukar Point', 'callback_data' => 'redeem_points']
                    ],
                    [
                        ['text' => 'ℹ️ Bantuan', 'callback_data' => 'help']
                    ]
                ]
            ];
            
            $this->sendMessageWithImage($chatId, $welcomeMessage, json_encode($keyboard));
        }
        // Menu command
        elseif (strpos($text, '/menu') === 0) {
            $this->showMainMenu($chatId, "🏠 <b>Menu Utama</b>\n\nSilakan pilih menu yang diinginkan:");
        }
        // Points command
        elseif (strpos($text, '/points') === 0) {
            $userPoints = $this->db->getUserPoints($chatId);
            $message = "💰 <b>POINT ANDA</b>\n\n";
            $message .= "Total Point: <b>$userPoints points</b>\n\n";
            $message .= "📊 <b>Cara mendapatkan point:</b>\n";
            $message .= "• Beli lisensi 1 hari = 1 point\n";
            $message .= "• Beli lisensi 3 hari = 2 point\n";
            $message .= "• Beli lisensi 5 hari = 4 point\n";
            $message .= "• Beli lisensi 7 hari = 5 point\n";
            $message .= "Dan seterusnya...\n\n";
            $message .= "🎁 <b>Tukar point dengan lisensi gratis!</b>\n";
            $message .= "12 points = 1 hari lisensi gratis";
            
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '🎁 Tukar Point', 'callback_data' => 'redeem_points']
                    ],
                    [
                        ['text' => '🛒 Beli Lisensi', 'callback_data' => 'new_order']
                    ],
                    [
                        ['text' => '🏠 Menu Utama', 'callback_data' => 'main_menu']
                    ]
                ]
            ];
            
            $this->sendMessageWithImage($chatId, $message, json_encode($keyboard));
        }
        // Admin broadcast commands
        elseif (strpos($text, '/pengumuman') === 0) {
            if (!isAdmin($chatId)) {
                $this->sendSimpleMessage($chatId, "❌ <b>Akses Ditolak!</b>\n\nPerintah ini hanya untuk admin.");
                return;
            }
            
            $totalUsers = $this->db->getTotalBotUsers();
            
            $message = "📢 <b>MODE PENGUMUMAN</b>\n\n";
            $message .= "Kirim pesan yang ingin Anda broadcast ke semua pengguna.\n\n";
            $message .= "📊 <b>Total Pengguna Aktif:</b> $totalUsers users\n\n";
            $message .= "✅ <b>Anda dapat mengirim:</b>\n";
            $message .= "• Foto dengan caption\n";
            $message .= "• Video dengan caption\n";
            $message .= "• File/Dokumen dengan caption\n";
            $message .= "• Pesan teks biasa\n\n";
            $message .= "⚠️ <b>Kirim /cancel untuk membatalkan</b>";
            
            $this->db->saveAdminState($chatId, 'waiting_broadcast_pengumuman');
            $this->sendSimpleMessage($chatId, $message);
        }
        elseif (strpos($text, '/adds') === 0) {
            if (!isAdmin($chatId)) {
                $this->sendSimpleMessage($chatId, "❌ <b>Akses Ditolak!</b>\n\nPerintah ini hanya untuk admin.");
                return;
            }
            
            $totalUsers = $this->db->getTotalBotUsers();
            
            $message = "🔔 <b>MODE NOTIFIKASI/IKLAN</b>\n\n";
            $message .= "Kirim pesan yang ingin Anda broadcast ke semua pengguna.\n";
            $message .= "Pesan akan dikirim dengan notifikasi khusus.\n\n";
            $message .= "📊 <b>Total Pengguna Aktif:</b> $totalUsers users\n\n";
            $message .= "✅ <b>Anda dapat mengirim:</b>\n";
            $message .= "• Foto dengan caption\n";
            $message .= "• Video dengan caption\n";
            $message .= "• File/Dokumen dengan caption\n";
            $message .= "• Pesan teks biasa\n\n";
            $message .= "⚠️ <b>Kirim /cancel untuk membatalkan</b>";
            
            $this->db->saveAdminState($chatId, 'waiting_broadcast_adds');
            $this->sendSimpleMessage($chatId, $message);
        }
        elseif (strpos($text, '/cancel') === 0) {
            $adminState = $this->db->getAdminState($chatId);
            if ($adminState && (strpos($adminState['state'], 'waiting_broadcast') === 0)) {
                $this->db->clearAdminState($chatId);
                $this->sendSimpleMessage($chatId, "✅ <b>Broadcast dibatalkan.</b>");
            }
        }
        else {
            // Handle state-based messages (manual input)
            $userState = $this->db->getUserState($chatId);
            
            if ($userState && $userState['state'] == 'waiting_manual_input') {
                $this->handleManualInput($chatId, $text, $userState);
            }
            elseif ($userState && $userState['state'] == 'waiting_extend_credentials') {
                $this->handleExtendCredentials($chatId, $text, $userState);
            }
        }
    }
    
    /**
     * Handle callback queries
     */
    private function handleCallbackQuery($callback) {
        $data = $callback['data'];
        $chatId = $callback['message']['chat']['id'];
        $messageId = $callback['message']['message_id'];
        $callbackId = $callback['id'];
        
        logMessage("Callback received: $data from $chatId");
        
        // Answer callback first
        $this->answerCallbackQuery($callbackId);
        
        try {
            // Main menu
            if ($data == 'main_menu') {
                $this->userManager->clearUserState($chatId);
                $this->showMainMenu($chatId, null, $messageId);
            }
            // New order
            elseif ($data == 'new_order') {
                $message = "👋 <b>Halo!</b>\n\n";
                $message .= "Silakan pilih jenis Free Fire yang ingin Anda beli:";
                
                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => '🎮 FREE FIRE', 'callback_data' => 'type_ff'],
                            ['text' => '⚡ FREE FIRE MAX', 'callback_data' => 'type_ffmax']
                        ],
                        [
                            ['text' => '↩️ Kembali', 'callback_data' => 'main_menu']
                        ]
                    ]
                ];
                
                $this->editMessageSmart($chatId, $messageId, $message, json_encode($keyboard));
            }
            // Extend user
            elseif ($data == 'extend_user') {
                $message = "🎮 <b>EXTEND MASA AKTIF</b>\n\n";
                $message .= "Pilih jenis Free Fire yang ingin di-extend:";
                
                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => '🎮 FREE FIRE', 'callback_data' => 'extend_type_ff'],
                            ['text' => '⚡ FREE FIRE MAX', 'callback_data' => 'extend_type_ffmax']
                        ],
                        [
                            ['text' => '↩️ Kembali', 'callback_data' => 'main_menu']
                        ]
                    ]
                ];
                
                $this->editMessageSmart($chatId, $messageId, $message, json_encode($keyboard));
            }
            // Redeem points
            elseif ($data == 'redeem_points') {
                $this->showRedeemPointsMenu($chatId, $messageId);
            }
            // Help
            elseif ($data == 'help') {
                $userPoints = $this->db->getUserPoints($chatId);
                
                $helpMessage = "ℹ️ <b>BANTUAN</b>\n\n";
                $helpMessage .= "💰 <b>Point Anda:</b> $userPoints points\n\n";
                $helpMessage .= "📝 <b>Cara Penggunaan:</b>\n";
                $helpMessage .= "1. Pilih 'Beli Lisensi Baru' untuk pembelian baru\n";
                $helpMessage .= "2. Pilih 'Extend Masa Aktif' untuk memperpanjang\n";
                $helpMessage .= "3. Pilih 'Tukar Point' untuk lisensi gratis\n";
                $helpMessage .= "4. Ikuti instruksi yang diberikan\n\n";
                $helpMessage .= "🔧 <b>Fitur:</b>\n";
                $helpMessage .= "• Support Free Fire & Free Fire MAX\n";
                $helpMessage .= "• Pembayaran QRIS otomatis\n";
                $helpMessage .= "• Extend masa aktif\n";
                $helpMessage .= "• Key random & manual\n";
                $helpMessage .= "• Sistem point/reward\n\n";
                $helpMessage .= "🎁 <b>Sistem Point:</b>\n";
                $helpMessage .= "• Dapatkan point dari setiap pembelian\n";
                $helpMessage .= "• 12 points = 1 hari lisensi gratis\n";
                $helpMessage .= "• Point tidak memiliki masa kedaluwarsa\n\n";
                $helpMessage .= "⏰ <b>Pembayaran Otomatis:</b>\n";
                $helpMessage .= "• QR berlaku selama 25 menit\n";
                $helpMessage .= "• Cek pembayaran otomatis setiap 20 detik\n";
                $helpMessage .= "• QR terhapus otomatis jika tidak dibayar\n";
                $helpMessage .= "• Pesan sukses tidak akan dihapus\n\n";
                $helpMessage .= "❓ <b>Pertanyaan?</b>\n";
                $helpMessage .= "Hubungi admin jika ada kendala @dimasvip1120";
                
                $this->showMainMenu($chatId, $helpMessage, $messageId);
            }
            // Type selection
            elseif (strpos($data, 'type_') === 0) {
                $type = str_replace('type_', '', $data);
                $this->showDurationSelection($chatId, $messageId, $type);
            }
            // Duration selection
            elseif (strpos($data, 'duration_') === 0) {
                $parts = explode('_', $data);
                $type = $parts[1];
                $duration = $parts[2];
                $this->showKeyTypeSelection($chatId, $messageId, $type, $duration);
            }
            // Key type selection
            elseif (strpos($data, 'keytype_') === 0) {
                $parts = explode('_', $data);
                $type = $parts[1];
                $duration = $parts[2];
                $keyType = $parts[3];
                
                if ($keyType == 'random') {
                    $this->processRandomKeyOrder($chatId, $messageId, $type, $duration);
                } elseif ($keyType == 'manual') {
                    $this->userManager->saveUserState($chatId, 'waiting_manual_input', [
                        'game_type' => $type,
                        'duration' => $duration
                    ]);
                    
                    $instruction = "✏️ <b>MASUKKAN USERNAME & PASSWORD</b>\n\n";
                    $instruction .= "📝 <b>Gunakan format:</b>\n";
                    $instruction .= "<code>/username-password</code>\n\n";
                    $instruction .= "🎯 <b>Contoh:</b>\n";
                    $instruction .= "<code>/kambing-1</code>\n";
                    $instruction .= "<code>/player-123</code>\n";
                    $instruction .= "<code>/gamer-99</code>\n\n";
                    $instruction .= "➡️ <b>Username</b> sebelum tanda minus (-)\n";
                    $instruction .= "➡️ <b>Password</b> setelah tanda minus (-)";
                    
                    $keyboard = [
                        'inline_keyboard' => [
                            [
                                ['text' => '↩️ Kembali', 'callback_data' => 'duration_' . $type . '_' . $duration],
                                ['text' => '🏠 Menu Utama', 'callback_data' => 'main_menu']
                            ]
                        ]
                    ];
                    
                    $this->editMessageSmart($chatId, $messageId, $instruction, json_encode($keyboard));
                }
            }
            // Extend type selection
            elseif (strpos($data, 'extend_type_') === 0) {
                $gameType = str_replace('extend_type_', '', $data);
                $gameName = ($gameType == 'ff') ? 'FREE FIRE' : 'FREE FIRE MAX';
                
                $this->userManager->saveUserState($chatId, 'waiting_extend_credentials', [
                    'game_type' => $gameType
                ]);
                
                $message = "✏️ <b>EXTEND $gameName</b>\n\n";
                $message .= "Masukkan <b>USERNAME dan PASSWORD</b> yang ingin di-extend:\n\n";
                $message .= "📝 <b>Format:</b>\n";
                $message .= "<code>/username-password</code>\n\n";
                $message .= "🎯 <b>Contoh:</b>\n";
                $message .= "<code>/kambing-1</code>\n";
                $message .= "<code>/player-123</code>\n\n";
                $message .= "⚠️ <b>Pastikan username dan password terdaftar di $gameName</b>";
                
                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => '↩️ Kembali', 'callback_data' => 'extend_user'],
                            ['text' => '🏠 Menu Utama', 'callback_data' => 'main_menu']
                        ]
                    ]
                ];
                
                $this->editMessageSmart($chatId, $messageId, $message, json_encode($keyboard));
            }
            // Extend duration selection
            elseif (strpos($data, 'extend_duration_') === 0) {
                $duration = str_replace('extend_duration_', '', $data);
                $userState = $this->db->getUserState($chatId);
                
                if ($userState && $userState['state'] == 'waiting_extend_duration') {
                    $this->processExtendOrder($chatId, $messageId, $duration, $userState);
                }
            }
            // Point redemption
            elseif (strpos($data, 'redeem_') === 0 && is_numeric(str_replace('redeem_', '', $data))) {
                $duration = str_replace('redeem_', '', $data);
                $this->processPointRedemption($chatId, $duration, $messageId);
            }
            elseif ($data == 'redeem_ff' || $data == 'redeem_ffmax') {
                $gameType = ($data == 'redeem_ff') ? 'ff' : 'ffmax';
                $userState = $this->db->getUserState($chatId);
                
                if ($userState && $userState['state'] == 'waiting_redeem_game') {
                    $duration = $userState['data']['duration'];
                    $pointsNeeded = $userState['data']['points_needed'];
                    
                    logMessage("DEBUG: Complete redemption - Game: $gameType, Duration: $duration, Chat: $chatId");
                    
                    $this->completePointRedemption($chatId, $gameType, $duration, $messageId);
                    $this->userManager->clearUserState($chatId);
                } else {
                    $this->editMessageSmart($chatId, $messageId, "❌ <b>Sesi telah berakhir!</b>\n\nSilakan mulai ulang dari menu penukaran point.", $this->getBackButton('redeem_points'));
                }
            }
            // Check payment
            elseif ($data == 'check_payment') {
                $this->checkPaymentStatus($chatId, $messageId);
            }
            // Check extend
            elseif ($data == 'check_extend') {
                $this->checkExtendStatus($chatId, $messageId);
            }
            // Cancel order
            elseif ($data == 'cancel_order') {
                $order = $this->db->getPendingOrder($chatId);
                if ($order) {
                    $this->db->updateOrderStatus($order['deposit_code'], 'cancelled');
                }
                $this->userManager->clearUserState($chatId);
                $this->editMessageSmart($chatId, $messageId, "❌ Pesanan dibatalkan.", $this->getBackButton());
            }
            
        } catch (Exception $e) {
            logMessage("Error processing callback: " . $e->getMessage());
            $this->sendMessageWithImage($chatId, "❌ <b>Terjadi kesalahan!</b>\n\n Silakan coba lagi atau gunakan menu /start", $this->getBackButton());
        }
    }
    
    /**
     * Show main menu
     */
    private function showMainMenu($chatId, $text = null, $messageId = null) {
        $userPoints = $this->db->getUserPoints($chatId);
        
        if ($text) {
            $message = $text;
        } else {
            $message = "🏠 <b>Menu Utama</b>\n\n";
            $message .= "💰 <b>Point Anda:</b> $userPoints points\n\n";
            $message .= "Silakan pilih menu yang diinginkan:";
        }
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🛒 Beli Lisensi Baru', 'callback_data' => 'new_order']
                ],
                [
                    ['text' => '⏰ Extend Masa Aktif', 'callback_data' => 'extend_user']
                ],
                [
                    ['text' => '🎁 Tukar Point', 'callback_data' => 'redeem_points']
                ],
                [
                    ['text' => 'ℹ️ Bantuan', 'callback_data' => 'help']
                ]
            ]
        ];
        
        if ($messageId) {
            $this->editMessageSmart($chatId, $messageId, $message, json_encode($keyboard));
        } else {
            $this->sendMessageWithImage($chatId, $message, json_encode($keyboard));
        }
    }
    
    /**
     * Show duration selection
     */
    private function showDurationSelection($chatId, $messageId, $type) {
        $message = "💰 <b>Pilih Durasi Lisensi " . strtoupper($type) . ":</b>\n\n";
        $message .= "Silakan pilih durasi:";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '1 Hari - 15k', 'callback_data' => "duration_{$type}_1"],
                    ['text' => '2 Hari - 30k', 'callback_data' => "duration_{$type}_2"],
                    ['text' => '3 Hari - 40k', 'callback_data' => "duration_{$type}_3"]
                ],
                [
                    ['text' => '4 Hari - 50k', 'callback_data' => "duration_{$type}_4"],
                    ['text' => '5 Hari - 60k', 'callback_data' => "duration_{$type}_5"],
                    ['text' => '6 Hari - 70k', 'callback_data' => "duration_{$type}_6"]
                ],
                [
                    ['text' => '7 Hari - 80k', 'callback_data' => "duration_{$type}_7"],
                    ['text' => '8 Hari - 90k', 'callback_data' => "duration_{$type}_8"],
                    ['text' => '10 Hari - 100k', 'callback_data' => "duration_{$type}_10"]
                ],
                [
                    ['text' => '15 Hari - 150k', 'callback_data' => "duration_{$type}_15"],
                    ['text' => '20 Hari - 180k', 'callback_data' => "duration_{$type}_20"],
                    ['text' => '30 Hari - 250k', 'callback_data' => "duration_{$type}_30"]
                ],
                [
                    ['text' => '↩️ Kembali', 'callback_data' => 'new_order'],
                    ['text' => '🏠 Menu Utama', 'callback_data' => 'main_menu']
                ]
            ]
        ];
        
        $this->editMessageSmart($chatId, $messageId, $message, json_encode($keyboard));
    }
    
    /**
     * Show key type selection
     */
    private function showKeyTypeSelection($chatId, $messageId, $type, $duration) {
        $message = "🔑 <b>Pilih Tipe Key untuk " . strtoupper($type) . ":</b>\n\n";
        $message .= "🎲 <b>RANDOM KEY</b>\n";
        $message .= "• Username & password digenerate otomatis\n";
        $message .= "• Format: 2 huruf + 2 angka (Username), 2 angka (Password)\n\n";
        $message .= "✏️ <b>MANUAL KEY</b>\n";
        $message .= "• Input username & password manual\n";
        $message .= "• Format: <code>/username-password</code>\n\n";
        $message .= "Silakan pilih tipe key:";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🎲 RANDOM KEY', 'callback_data' => "keytype_{$type}_{$duration}_random"],
                    ['text' => '✏️ MANUAL KEY', 'callback_data' => "keytype_{$type}_{$duration}_manual"]
                ],
                [
                    ['text' => '↩️ Kembali', 'callback_data' => 'type_' . $type],
                    ['text' => '🏠 Menu Utama', 'callback_data' => 'main_menu']
                ]
            ]
        ];
        
        $this->editMessageSmart($chatId, $messageId, $message, json_encode($keyboard));
    }
    
    /**
     * Show redeem points menu
     */
    private function showRedeemPointsMenu($chatId, $messageId = null) {
        $userPoints = $this->db->getUserPoints($chatId);
        
        $message = "🎁 <b>TUKAR POINT</b>\n\n";
        $message .= "💰 <b>Point Anda:</b> $userPoints points\n\n";
        $message .= "📊 <b>Rate Penukaran:</b>\n";
        $message .= "• 1 Hari = 12 points\n";
        $message .= "• 2 Hari = 24 points\n";
        $message .= "• 3 Hari = 36 points\n";
        $message .= "• 7 Hari = 84 points\n\n";
        $message .= "Pilih durasi yang ingin ditukar:";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '1 Hari - 12 points', 'callback_data' => 'redeem_1'],
                    ['text' => '2 Hari - 24 points', 'callback_data' => 'redeem_2']
                ],
                [
                    ['text' => '3 Hari - 36 points', 'callback_data' => 'redeem_3'],
                    ['text' => '7 Hari - 84 points', 'callback_data' => 'redeem_7']
                ],
                [
                    ['text' => '↩️ Kembali', 'callback_data' => 'main_menu']
                ]
            ]
        ];
        
        if ($messageId) {
            $this->editMessageSmart($chatId, $messageId, $message, json_encode($keyboard));
        } else {
            $this->sendMessageWithImage($chatId, $message, json_encode($keyboard));
        }
    }
    
    /**
     * Process point redemption
     */
    private function processPointRedemption($chatId, $duration, $messageId) {
        $userPoints = $this->db->getUserPoints($chatId);
        $pointsNeeded = $this->calculatePointsNeededForDays($duration);
        
        if ($userPoints < $pointsNeeded) {
            $message = "❌ <b>Point tidak cukup!</b>\n\n";
            $message .= "Point yang dibutuhkan: <b>$pointsNeeded points</b>\n";
            $message .= "Point Anda: <b>$userPoints points</b>\n\n";
            $message .= "Silakan kumpulkan point lebih banyak dengan melakukan pembelian.";
            
            $this->editMessageSmart($chatId, $messageId, $message, $this->getBackButton('redeem_points'));
            return;
        }
        
        // Ask for game type
        $message = "🎮 <b>PILIH JENIS GAME</b>\n\n";
        $message .= "Anda akan menukar <b>$pointsNeeded points</b> untuk lisensi <b>$duration hari</b>\n\n";
        $message .= "Pilih jenis Free Fire:";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🎮 FREE FIRE', 'callback_data' => "redeem_ff"],
                    ['text' => '⚡ FREE FIRE MAX', 'callback_data' => "redeem_ffmax"]
                ],
                [
                    ['text' => '↩️ Kembali', 'callback_data' => 'redeem_points']
                ]
            ]
        ];
        
        $this->userManager->saveUserState($chatId, 'waiting_redeem_game', [
            'duration' => $duration,
            'points_needed' => $pointsNeeded
        ]);
        
        $this->editMessageSmart($chatId, $messageId, $message, json_encode($keyboard));
    }
    
    /**
     * Complete point redemption
     */
    private function completePointRedemption($chatId, $gameType, $duration, $messageId) {
        $pointsNeeded = $this->calculatePointsNeededForDays($duration);
        $userPoints = $this->db->getUserPoints($chatId);
        
        logMessage("DEBUG: Starting point redemption - Chat: $chatId, Game: $gameType, Duration: $duration, Points Needed: $pointsNeeded, User Points: $userPoints");
        
        if ($userPoints < $pointsNeeded) {
            $message = "❌ <b>Point tidak cukup!</b>\n\n";
            $message .= "Point yang dibutuhkan: <b>$pointsNeeded points</b>\n";
            $message .= "Point Anda: <b>$userPoints points</b>";
            
            logMessage("ERROR: Insufficient points for redemption - Chat: $chatId, Needed: $pointsNeeded, Has: $userPoints");
            $this->editMessageSmart($chatId, $messageId, $message, $this->getBackButton('redeem_points'));
            return;
        }
        
        $credentials = $this->generateRedeemCredentials();
        $table = ($gameType == 'ff') ? 'freefire' : 'ffmax';
        
        logMessage("DEBUG: Generated credentials - Username: " . $credentials['username'] . ", Password: " . $credentials['password']);
        
        $maxAttempts = 10;
        $attempts = 0;
        while ($this->db->isUsernameExists($credentials['username'], $table) && $attempts < $maxAttempts) {
            $credentials = $this->generateRedeemCredentials();
            $attempts++;
            logMessage("DEBUG: Username exists, regenerating... Attempt: $attempts, New Username: " . $credentials['username']);
        }
        
        if ($attempts >= $maxAttempts) {
            $message = "❌ <b>Gagal generate username unik!</b>\n\n";
            $message .= "Silakan coba lagi.";
            
            logMessage("ERROR: Failed to generate unique username after $maxAttempts attempts");
            $this->editMessageSmart($chatId, $messageId, $message, $this->getBackButton('redeem_points'));
            return;
        }
        
        $gameName = ($gameType == 'ff') ? 'FREE FIRE' : 'FREE FIRE MAX';
        
        logMessage("DEBUG: Attempting to redeem points - Chat: $chatId, Points: $pointsNeeded");
        if (!$this->db->redeemUserPoints($chatId, $pointsNeeded, "Penukaran lisensi $duration hari")) {
            $message = "❌ <b>Gagal menukar point!</b>\n\n";
            $message .= "Terjadi kesalahan sistem. Silakan coba lagi.";
            
            logMessage("ERROR: Failed to redeem points - Chat: $chatId, Points: $pointsNeeded");
            $this->editMessageSmart($chatId, $messageId, $message, $this->getBackButton('redeem_points'));
            return;
        }
        
        logMessage("SUCCESS: Points redeemed successfully - Chat: $chatId, Points: $pointsNeeded");
        
        if ($this->db->saveLicenseToDatabase($table, $credentials['username'], $credentials['password'], $duration, 'DIMZ1945')) {
            $expiryDate = date('d-m-Y H:i:s', strtotime("+$duration days"));
            $newUserPoints = $this->db->getUserPoints($chatId);
            
            $message = "🎉 <b>PENUKARAN POINT BERHASIL!</b>\n\n";
            $message .= "Anda berhasil menukar <b>$pointsNeeded points</b>\n";
            $message .= "Untuk lisensi <b>$gameName</b> selama <b>$duration hari</b>\n\n";
            $message .= "📱 <b>AKUN ANDA:</b>\n";
            $message .= "Username: <code>" . $credentials['username'] . "</code>\n";
            $message .= "Password: <code>" . $credentials['password'] . "</code>\n";
            $message .= "Tipe Key: <b>REDEEM (AUTO RANDOM)</b>\n\n";
            $message .= "⏰ <b>MASA AKTIF:</b>\n";
            $message .= "Berlaku hingga: <b>$expiryDate WIB</b>\n\n";
            $message .= "🎮 <b>JENIS GAME:</b> $gameName\n";
            $message .= "💰 <b>SISA POINT:</b> $newUserPoints points\n\n";
            $message .= "✨ <b>Selamat bermain!</b> 🎮\n\n";
            $message .= "📁 <b>Untuk file dan tutorial instalasi:</b>\n";
            $message .= "Klik tombol '📁 File & Cara Pasang' di bawah";
            
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '📁 File & Cara Pasang', 'url' => 'https://t.me/+RY2yMHn_jts3YzA1']
                    ],
                    [
                        ['text' => '🎁 Tukar Lagi', 'callback_data' => 'redeem_points'],
                        ['text' => '🛒 Beli Lisensi', 'callback_data' => 'new_order']
                    ],
                    [
                        ['text' => '🏠 Menu Utama', 'callback_data' => 'main_menu']
                    ]
                ]
            ];
            
            logMessage("SUCCESS: License created successfully - Chat: $chatId, Username: " . $credentials['username'] . ", Table: $table");
            
            $this->sendPhoto($chatId, WELCOME_IMAGE, $message, json_encode($keyboard), false);
            
            $adminMessage = "🎁 <b>PENUKARAN POINT BARU!</b>\n\n";
            $adminMessage .= "User ID: <code>$chatId</code>\n";
            $adminMessage .= "Jenis Game: <b>$gameName</b>\n";
            $adminMessage .= "Durasi: <b>$duration Hari</b>\n";
            $adminMessage .= "Tipe Key: <b>REDEEM (AUTO RANDOM)</b>\n";
            $adminMessage .= "Username: <code>" . $credentials['username'] . "</code>\n";
            $adminMessage .= "Password: <code>" . $credentials['password'] . "</code>\n";
            $adminMessage .= "Point Ditukar: <b>$pointsNeeded points</b>\n";
            $adminMessage .= "Masa Aktif: <b>$expiryDate WIB</b>\n";
            $adminMessage .= "Waktu: " . date('d-m-Y H:i:s');
            
            $this->notifyAdmin($adminMessage);
            
        } else {
            $this->db->addUserPoints($chatId, $pointsNeeded, "Refund gagal penukaran");
            
            $message = "❌ <b>Gagal membuat lisensi!</b>\n\n";
            $message .= "Point telah dikembalikan. Silakan coba lagi.";
            
            $this->editMessageSmart($chatId, $messageId, $message, $this->getBackButton('redeem_points'));
        }
    }
    
    /**
     * Process random key order
     */
    private function processRandomKeyOrder($chatId, $messageId, $type, $duration) {
        $prices = getPrices();
        $amount = $prices[$duration];
        $orderId = 'DIMZ' . time() . rand(100, 999);
        
        $payment = $this->payment->createPayment($orderId, $amount);
        
        if ($payment && $payment['status']) {
            $paymentData = $payment['data'];
            
            $message = "💳 <b>PEMBAYARAN " . strtoupper($type) . " (RANDOM)</b>\n\n";
            $message .= "Jenis: <b>" . strtoupper($type) . "</b>\n";
            $message .= "Durasi: <b>$duration Hari</b>\n";
            $message .= "Tipe: <b>KEY RANDOM</b>\n";
            $message .= "Harga: <b>Rp " . number_format($amount, 0, ',', '.') . "</b>\n";
            $message .= "Order ID: <code>$orderId</code>\n\n";
            $message .= "📱 <b>INSTRUKSI PEMBAYARAN:</b>\n";
            $message .= "1. Scan QR Code di bawah\n";
            $message .= "2. Bayar sesuai amount\n";
            $message .= "3. Pembayaran akan terdeteksi otomatis\n\n";
            $message .= "⏰ <b>Batas Waktu: 25 MENIT</b>\n";
            $message .= "🔄 <b>Cek Otomatis: Setiap 20 detik</b>\n";
            $message .= "QR akan otomatis terhapus setelah 25 menit jika tidak bayar\n";
            $message .= "Expired: " . $paymentData['expired'] . "\n\n";
            $message .= "🚀 <b>Pembayaran akan diproses otomatis!</b>";
            
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '🔍 Cek Status Manual', 'callback_data' => 'check_payment']
                    ],
                    [
                        ['text' => '❌ Batalkan Pesanan', 'callback_data' => 'cancel_order']
                    ]
                ]
            ];
            
            $this->db->savePendingOrder($orderId, $chatId, $type, $duration, $amount, $paymentData['kode_deposit'], 'random');
            
            $this->sendPhoto($chatId, $paymentData['link_qr'], $message, json_encode($keyboard));
        } else {
            $errorMsg = "❌ Gagal membuat pembayaran. Silakan coba lagi.";
            $this->editMessageSmart($chatId, $messageId, $errorMsg, $this->getBackButton('type_' . $type));
        }
    }
    
    /**
     * Process extend order
     */
    private function processExtendOrder($chatId, $messageId, $duration, $userState) {
        $username = $userState['data']['username'];
        $password = $userState['data']['password'];
        $userData = $userState['data']['user_data'];
        $gameType = $userState['data']['game_type'];
        $prices = getPrices();
        $amount = $prices[$duration];
        
        $orderId = 'EXTEND' . time() . rand(100, 999);
        
        $payment = $this->payment->createPayment($orderId, $amount);
        
        if ($payment && $payment['status']) {
            $paymentData = $payment['data'];
            $gameName = ($gameType == 'ff') ? 'FREE FIRE' : 'FREE FIRE MAX';
            $currentExp = date('d-m-Y H:i:s', strtotime($userData['expDate']));
            $newExpDate = date('d-m-Y H:i:s', strtotime($userData['expDate'] . " +$duration days"));
            
            $message = "💳 <b>EXTEND $gameName</b>\n\n";
            $message .= "Username: <code>$username</code>\n";
            $message .= "Password: <code>$password</code>\n";
            $message .= "Jenis: <b>$gameName</b>\n";
            $message .= "Durasi: <b>$duration Hari</b>\n";
            $message .= "Harga: <b>Rp " . number_format($amount, 0, ',', '.') . "</b>\n";
            $message .= "Masa Aktif Saat Ini: <b>$currentExp WIB</b>\n";
            $message .= "Masa Aktif Baru: <b>$newExpDate WIB</b>\n";
            $message .= "Order ID: <code>$orderId</code>\n\n";
            $message .= "📱 <b>INSTRUKSI PEMBAYARAN:</b>\n";
            $message .= "1. Scan QR Code di bawah\n";
            $message .= "2. Bayar sesuai amount\n";
            $message .= "3. Pembayaran akan terdeteksi otomatis\n\n";
            $message .= "⏰ <b>Batas Waktu: 25 MENIT</b>\n";
            $message .= "🔄 <b>Cek Otomatis: Setiap 20 detik</b>\n";
            $message .= "QR akan otomatis terhapus setelah 25 menit jika tidak bayar\n";
            $message .= "Expired: " . $paymentData['expired'] . "\n\n";
            $message .= "🚀 <b>Pembayaran akan diproses otomatis!</b>";
            
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '🔍 Cek Status Manual', 'callback_data' => 'check_extend']
                    ],
                    [
                        ['text' => '❌ Batalkan Pesanan', 'callback_data' => 'cancel_order']
                    ]
                ]
            ];
            
            $this->db->savePendingOrder($orderId, $chatId, $gameType, $duration, $amount, $paymentData['kode_deposit'], 'extend', $username, $password);
            $this->userManager->clearUserState($chatId);
            
            $this->sendPhoto($chatId, $paymentData['link_qr'], $message, json_encode($keyboard));
        } else {
            $this->editMessageSmart($chatId, $messageId, "❌ Gagal membuat pembayaran extend. Silakan coba lagi.", $this->getBackButton('extend_user'));
            $this->userManager->clearUserState($chatId);
        }
    }
    
    /**
     * Handle manual input
     */
    private function handleManualInput($chatId, $text, $userState) {
        if (strpos($text, '/') === 0) {
            $input = substr($text, 1);
            $parts = explode('-', $input, 2);
            
            if (count($parts) == 2) {
                $username = trim($parts[0]);
                $password = trim($parts[1]);
                
                if (empty($username) || empty($password)) {
                    $this->sendMessageWithImage($chatId, "❌ <b>Username dan password tidak boleh kosong!</b>\n\nFormat: <code>/username-password</code>\nContoh: <code>/kambing-1</code>", $this->getBackButton('new_order'));
                    return;
                }
                
                $gameType = $userState['data']['game_type'];
                $table = ($gameType == 'ff') ? 'freefire' : 'ffmax';
                
                if ($this->db->isUsernameExists($username, $table)) {
                    $gameName = ($gameType == 'ff') ? 'FREE FIRE' : 'FREE FIRE MAX';
                    $errorMessage = "❌ <b>Username sudah digunakan di $gameName!</b>\n\n";
                    $errorMessage .= "Username <code>$username</code> sudah terdaftar di <b>$gameName</b>.\n\n";
                    $errorMessage .= "💡 <b>Tips:</b> Gunakan username yang berbeda\n\n";
                    $errorMessage .= "📝 <b>Format:</b> <code>/username-password</code>\n";
                    $errorMessage .= "🎯 <b>Contoh:</b> <code>/player123-1</code>";
                    
                    $this->sendMessageWithImage($chatId, $errorMessage, $this->getBackButton('new_order'));
                    return;
                }
                
                $duration = $userState['data']['duration'];
                $prices = getPrices();
                $amount = $prices[$duration];
                $orderId = 'DIMZ' . time() . rand(100, 999);
                
                $payment = $this->payment->createPayment($orderId, $amount);
                
                if ($payment && $payment['status']) {
                    $paymentData = $payment['data'];
                    $gameName = strtoupper($gameType);
                    
                    $message = "💳 <b>PEMBAYARAN $gameName (MANUAL)</b>\n\n";
                    $message .= "Jenis: <b>$gameName</b>\n";
                    $message .= "Durasi: <b>$duration Hari</b>\n";
                    $message .= "Tipe: <b>KEY MANUAL</b>\n";
                    $message .= "Username: <code>$username</code>\n";
                    $message .= "Password: <code>$password</code>\n";
                    $message .= "Harga: <b>Rp " . number_format($amount, 0, ',', '.') . "</b>\n";
                    $message .= "Order ID: <code>$orderId</code>\n\n";
                    $message .= "📱 <b>INSTRUKSI PEMBAYARAN:</b>\n";
                    $message .= "1. Scan QR Code di bawah\n";
                    $message .= "2. Bayar sesuai amount\n";
                    $message .= "3. Pembayaran akan terdeteksi otomatis\n\n";
                    $message .= "⏰ <b>Batas Waktu: 25 MENIT</b>\n";
                    $message .= "🔄 <b>Cek Otomatis: Setiap 20 detik</b>\n";
                    $message .= "QR akan otomatis terhapus setelah 25 menit jika tidak bayar\n";
                    $message .= "Expired: " . $paymentData['expired'] . "\n\n";
                    $message .= "🚀 <b>Pembayaran akan diproses otomatis!</b>";
                    
                    $keyboard = [
                        'inline_keyboard' => [
                            [
                                ['text' => '🔍 Cek Status Manual', 'callback_data' => 'check_payment']
                            ],
                            [
                                ['text' => '❌ Batalkan Pesanan', 'callback_data' => 'cancel_order']
                            ]
                        ]
                    ];
                    
                    $this->db->savePendingOrder($orderId, $chatId, $gameType, $duration, $amount, $paymentData['kode_deposit'], 'manual', $username, $password);
                    $this->userManager->clearUserState($chatId);
                    
                    $this->sendPhoto($chatId, $paymentData['link_qr'], $message, json_encode($keyboard));
                } else {
                    $this->sendMessageWithImage($chatId, "❌ Gagal membuat pembayaran. Silakan coba lagi.", $this->getBackButton('new_order'));
                    $this->userManager->clearUserState($chatId);
                }
            } else {
                $this->sendMessageWithImage($chatId, "❌ <b>Format tidak valid!</b>\n\nFormat: <code>/username-password</code>\nContoh: <code>/kambing-1</code>", $this->getBackButton('new_order'));
            }
        } else {
            $this->sendMessageWithImage($chatId, "❌ <b>Gunakan format command!</b>\n\nFormat: <code>/username-password</code>\nContoh: <code>/kambing-1</code>", $this->getBackButton('new_order'));
        }
    }
    
    /**
     * Handle extend credentials
     */
    private function handleExtendCredentials($chatId, $text, $userState) {
        if (strpos($text, '/') === 0) {
            $input = substr($text, 1);
            $parts = explode('-', $input, 2);
            
            if (count($parts) == 2) {
                $username = trim($parts[0]);
                $password = trim($parts[1]);
                $gameType = $userState['data']['game_type'];
                
                $userData = $this->db->getUserByUsernameAndPassword($username, $password, $gameType);
                
                if ($userData) {
                    $this->userManager->resetUserErrorCount($chatId);
                    
                    $this->userManager->saveUserState($chatId, 'waiting_extend_duration', [
                        'username' => $username,
                        'password' => $password,
                        'user_data' => $userData,
                        'game_type' => $gameType
                    ]);
                    
                    $gameName = ($gameType == 'ff') ? 'FREE FIRE' : 'FREE FIRE MAX';
                    $currentExp = date('d-m-Y H:i:s', strtotime($userData['expDate']));
                    
                    $message = "✅ <b>USERNAME DAN PASSWORD COCOK!</b>\n\n";
                    $message .= "Username: <code>$username</code>\n";
                    $message .= "Jenis: <b>$gameName</b>\n";
                    $message .= "Masa Aktif Saat Ini: <b>$currentExp WIB</b>\n\n";
                    $message .= "💰 <b>Pilih Durasi Extend:</b>";
                    
                    $keyboard = [
                        'inline_keyboard' => [
                            [
                                ['text' => '1 Hari - 15k', 'callback_data' => "extend_duration_1"],
                                ['text' => '2 Hari - 30k', 'callback_data' => "extend_duration_2"],
                                ['text' => '3 Hari - 40k', 'callback_data' => "extend_duration_3"]
                            ],
                            [
                                ['text' => '4 Hari - 50k', 'callback_data' => "extend_duration_4"],
                                ['text' => '5 Hari - 60k', 'callback_data' => "extend_duration_5"],
                                ['text' => '6 Hari - 70k', 'callback_data' => "extend_duration_6"]
                            ],
                            [
                                ['text' => '7 Hari - 80k', 'callback_data' => "extend_duration_7"],
                                ['text' => '8 Hari - 90k', 'callback_data' => "extend_duration_8"],
                                ['text' => '10 Hari - 100k', 'callback_data' => "extend_duration_10"]
                            ],
                            [
                                ['text' => '15 Hari - 150k', 'callback_data' => "extend_duration_15"],
                                ['text' => '20 Hari - 180k', 'callback_data' => "extend_duration_20"],
                                ['text' => '30 Hari - 250k', 'callback_data' => "extend_duration_30"]
                            ],
                            [
                                ['text' => '↩️ Kembali', 'callback_data' => 'extend_type_' . $gameType],
                                ['text' => '🏠 Menu Utama', 'callback_data' => 'main_menu']
                            ]
                        ]
                    ];
                    
                    $this->sendMessageWithImage($chatId, $message, json_encode($keyboard));
                } else {
                    $currentErrorCount = $userState['error_count'] ?? 0;
                    $newErrorCount = $currentErrorCount + 1;
                    $this->userManager->updateUserErrorCount($chatId, $newErrorCount);
                    
                    $gameName = ($gameType == 'ff') ? 'FREE FIRE' : 'FREE FIRE MAX';
                    $errorMessage = "❌ <b>Username dan Password tidak cocok di $gameName!</b>\n\n";
                    
                    if ($newErrorCount >= 2) {
                        $errorMessage .= "⚠️ <b>Anda telah 2 kali melakukan kesalahan.</b>\n";
                        $errorMessage .= "Silakan mulai ulang dari menu utama.\n\n";
                        $this->userManager->clearUserState($chatId);
                        $this->sendMessageWithImage($chatId, $errorMessage, $this->getBackButton());
                    } else {
                        $errorMessage .= "Silakan coba lagi dengan username dan password yang benar:\n\n";
                        $errorMessage .= "Format: <code>/username-password</code>\n";
                        $errorMessage .= "Contoh: <code>/kambing-1</code>";
                        $this->sendMessageWithImage($chatId, $errorMessage, $this->getBackButton('extend_user'));
                    }
                }
            } else {
                $this->sendMessageWithImage($chatId, "❌ <b>Format tidak valid!</b>\n\nFormat: <code>/username-password</code>\nContoh: <code>/kambing-1</code>", $this->getBackButton('extend_user'));
            }
        } else {
            $this->sendMessageWithImage($chatId, "❌ <b>Gunakan format command!</b>\n\nFormat: <code>/username-password</code>\nContoh: <code>/kambing-1</code>", $this->getBackButton('extend_user'));
        }
    }
    
    /**
     * Check payment status
     */
    private function checkPaymentStatus($chatId, $messageId) {
        $order = $this->db->getPendingOrder($chatId);
        
        if ($order) {
            $orderTime = strtotime($order['created_at']);
            $currentTime = time();
            $timeDiff = $currentTime - $orderTime;
            
            if ($timeDiff > ORDER_TIMEOUT) {
                $this->db->updateOrderStatus($order['deposit_code'], 'expired');
                $this->editMessageSmart($chatId, $messageId, "❌ <b>Pesanan telah expired!</b>\n\nPembayaran tidak dilakukan dalam waktu 25 menit.\n\nSilakan buat pesanan baru.", $this->getBackButton('new_order'));
                return;
            }
            
            $paymentStatus = $this->payment->checkPaymentStatus($order['deposit_code']);
            
            if ($paymentStatus) {
                $this->processSuccessfulPayment($chatId, $messageId, $order);
            } else {
                $remainingTime = ORDER_TIMEOUT - $timeDiff;
                $remainingMinutes = floor($remainingTime / 60);
                $remainingSeconds = $remainingTime % 60;
                
                $statusMessage = "⏳ <b>Status Pembayaran: PENDING</b>\n\n";
                $statusMessage .= "Pembayaran Anda masih dalam proses.\n\n";
                $statusMessage .= "⏰ <b>Sisa Waktu:</b> {$remainingMinutes}m {$remainingSeconds}s\n";
                $statusMessage .= "🔄 <b>Cek otomatis setiap 20 detik</b>\n\n";
                $statusMessage .= "Silakan tunggu beberapa saat dan coba lagi.";
                
                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => '🔄 Cek Lagi', 'callback_data' => 'check_payment']
                        ],
                        [
                            ['text' => '❌ Batalkan', 'callback_data' => 'cancel_order']
                        ]
                    ]
                ];
                
                $this->editMessageSmart($chatId, $messageId, $statusMessage, json_encode($keyboard));
            }
        } else {
            $this->editMessageSmart($chatId, $messageId, "❌ Tidak ada pesanan pending ditemukan.", $this->getBackButton('new_order'));
        }
    }
    
    /**
     * Check extend status
     */
    private function checkExtendStatus($chatId, $messageId) {
        $order = $this->db->getPendingOrder($chatId);
        
        if ($order && $order['key_type'] == 'extend') {
            $orderTime = strtotime($order['created_at']);
            $currentTime = time();
            $timeDiff = $currentTime - $orderTime;
            
            if ($timeDiff > ORDER_TIMEOUT) {
                $this->db->updateOrderStatus($order['deposit_code'], 'expired');
                $this->editMessageSmart($chatId, $messageId, "❌ <b>Pesanan extend telah expired!</b>\n\nPembayaran tidak dilakukan dalam waktu 25 menit.", $this->getBackButton('extend_user'));
                return;
            }
            
            $paymentStatus = $this->payment->checkPaymentStatus($order['deposit_code']);
            
            if ($paymentStatus) {
                $this->processSuccessfulPayment($chatId, $messageId, $order);
            } else {
                $remainingTime = ORDER_TIMEOUT - $timeDiff;
                $remainingMinutes = floor($remainingTime / 60);
                $remainingSeconds = $remainingTime % 60;
                
                $statusMessage = "⏳ <b>Status Extend: PENDING</b>\n\n";
                $statusMessage .= "Pembayaran extend masih dalam proses.\n\n";
                $statusMessage .= "⏰ <b>Sisa Waktu:</b> {$remainingMinutes}m {$remainingSeconds}s\n";
                $statusMessage .= "🔄 <b>Cek otomatis setiap 20 detik</b>\n\n";
                $statusMessage .= "Silakan tunggu beberapa saat dan coba lagi.";
                
                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => '🔄 Cek Lagi', 'callback_data' => 'check_extend']
                        ],
                        [
                            ['text' => '❌ Batalkan', 'callback_data' => 'cancel_order']
                        ]
                    ]
                ];
                
                $this->editMessageSmart($chatId, $messageId, $statusMessage, json_encode($keyboard));
            }
        }
    }
    
    /**
     * Process successful payment
     */
    private function processSuccessfulPayment($chatId, $qrMessageId, $order) {
        if ($order['key_type'] == 'extend') {
            if ($this->db->extendUserLicense($order['manual_username'], $order['manual_password'], $order['duration'], $order['game_type'])) {
                $userData = $this->db->getUserByUsernameAndPassword($order['manual_username'], $order['manual_password'], $order['game_type']);
                
                if ($userData) {
                    $newExpDate = date('d-m-Y H:i:s', strtotime($userData['expDate']));
                    $this->sendExtendSuccess($chatId, $userData, $order['duration'], $newExpDate);
                    $this->db->updateOrderStatus($order['deposit_code'], 'completed');
                }
            }
        } else {
            if ($order['key_type'] == 'manual') {
                $credentials = [
                    'username' => $order['manual_username'],
                    'password' => $order['manual_password']
                ];
            } else {
                $credentials = $this->generateRandomCredentials();
            }
            
            $table = ($order['game_type'] == 'ff') ? 'freefire' : 'ffmax';
            
            if ($this->db->saveLicenseToDatabase($table, $credentials['username'], $credentials['password'], $order['duration'], MERCHANT_CODE)) {
                $this->sendLicenseToUser($chatId, $order['game_type'], $order['duration'], $credentials, $order['key_type']);
                $this->db->updateOrderStatus($order['deposit_code'], 'completed');
            }
        }
    }
    
    /**
     * Send license to user
     */
    private function sendLicenseToUser($chatId, $gameType, $duration, $credentials, $keyType = 'random', $qrMessageId = null) {
        $gameName = ($gameType == 'ff') ? 'FREE FIRE' : 'FREE FIRE MAX';
        $expiryDate = date('d-m-Y H:i:s', strtotime("+$duration days"));
        
        $pointsEarned = $this->calculatePointsForDuration($duration);
        $this->db->addUserPoints($chatId, $pointsEarned, "Pembelian lisensi $duration hari");
        
        $userPoints = $this->db->getUserPoints($chatId);
        
        $message = "🎉 <b>PEMBAYARAN BERHASIL!</b>\n\n";
        $message .= "Terima kasih telah membeli lisensi <b>$gameName</b>\n";
        $message .= "Durasi: <b>$duration Hari</b>\n";
        $message .= "Tipe Key: <b>" . ($keyType == 'manual' ? 'MANUAL' : 'RANDOM') . "</b>\n\n";
        $message .= "📱 <b>AKUN ANDA:</b>\n";
        $message .= "Username: <code>" . $credentials['username'] . "</code>\n";
        $message .= "Password: <code>" . $credentials['password'] . "</code>\n\n";
        $message .= "⏰ <b>MASA AKTIF:</b>\n";
        $message .= "Berlaku hingga: <b>$expiryDate WIB</b>\n\n";
        $message .= "🎁 <b>REWARD POINT:</b>\n";
        $message .= "Anda mendapatkan <b>$pointsEarned points</b>\n";
        $message .= "Total point Anda: <b>$userPoints points</b>\n\n";
        $message .= "✨ <b>Selamat bermain!</b> 🎮\n\n";
        $message .= "📁 <b>Untuk file dan tutorial instalasi:</b>\n";
        $message .= "Klik tombol '📁 File & Cara Pasang' di bawah";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '📁 File & Cara Pasang', 'url' => 'https://t.me/+RY2yMHn_jts3YzA1']
                ],
                [
                    ['text' => '🔄 Beli Lagi', 'callback_data' => 'new_order'],
                    ['text' => '🎁 Tukar Point', 'callback_data' => 'redeem_points']
                ],
                [
                    ['text' => '🏠 Menu Utama', 'callback_data' => 'main_menu']
                ]
            ]
        ];
        
        $this->sendPhoto($chatId, WELCOME_IMAGE, $message, json_encode($keyboard), false);
        
        $adminMessage = "💰 <b>PEMBELIAN BERHASIL!</b>\n\n";
        $adminMessage .= "User ID: <code>$chatId</code>\n";
        $adminMessage .= "Jenis Game: <b>$gameName</b>\n";
        $adminMessage .= "Durasi: <b>$duration Hari</b>\n";
        $adminMessage .= "Tipe Key: <b>" . ($keyType == 'manual' ? 'MANUAL' : 'RANDOM') . "</b>\n";
        $adminMessage .= "Username: <code>" . $credentials['username'] . "</code>\n";
        $adminMessage .= "Password: <code>" . $credentials['password'] . "</code>\n";
        $adminMessage .= "Point Diberikan: <b>$pointsEarned points</b>\n";
        $adminMessage .= "Masa Aktif: <b>$expiryDate WIB</b>\n";
        $adminMessage .= "Waktu: " . date('d-m-Y H:i:s');
        
        $this->notifyAdmin($adminMessage);
    }
    
    /**
     * Send extend success
     */
    private function sendExtendSuccess($chatId, $userData, $duration, $newExpDate, $qrMessageId = null) {
        $gameName = ($userData['game_type'] == 'ff') ? 'FREE FIRE' : 'FREE FIRE MAX';
        $currentExp = date('d-m-Y H:i:s', strtotime($userData['expDate']));
        
        $pointsEarned = $this->calculatePointsForDuration($duration);
        $this->db->addUserPoints($chatId, $pointsEarned, "Extend lisensi $duration hari");
        
        $userPoints = $this->db->getUserPoints($chatId);
        
        $message = "🎉 <b>EXTEND BERHASIL!</b>\n\n";
        $message .= "Akun Anda berhasil di-extend\n";
        $message .= "Jenis: <b>$gameName</b>\n";
        $message .= "Username: <code>" . $userData['username'] . "</code>\n";
        $message .= "Durasi Tambahan: <b>$duration Hari</b>\n";
        $message .= "Masa Aktif Lama: <b>$currentExp WIB</b>\n";
        $message .= "Masa Aktif Baru: <b>$newExpDate WIB</b>\n\n";
        $message .= "🎁 <b>REWARD POINT:</b>\n";
        $message .= "Anda mendapatkan <b>$pointsEarned points</b>\n";
        $message .= "Total point Anda: <b>$userPoints points</b>\n\n";
        $message .= "✨ <b>Selamat bermain!</b> 🎮\n\n";
        $message .= "📁 <b>Untuk file dan tutorial instalasi:</b>\n";
        $message .= "Klik tombol '📁 File & Cara Pasang' di bawah";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '📁 File & Cara Pasang', 'url' => 'https://t.me/+RY2yMHn_jts3YzA1']
                ],
                [
                    ['text' => '🔄 Extend Lagi', 'callback_data' => 'extend_user'],
                    ['text' => '🎁 Tukar Point', 'callback_data' => 'redeem_points']
                ],
                [
                    ['text' => '🔄 Beli Baru', 'callback_data' => 'new_order']
                ],
                [
                    ['text' => '🏠 Menu Utama', 'callback_data' => 'main_menu']
                ]
            ]
        ];
        
        $this->sendPhoto($chatId, WELCOME_IMAGE, $message, json_encode($keyboard), false);
        
        $adminMessage = "⏰ <b>EXTEND BERHASIL!</b>\n\n";
        $adminMessage .= "User ID: <code>$chatId</code>\n";
        $adminMessage .= "Jenis Game: <b>$gameName</b>\n";
        $adminMessage .= "Username: <code>" . $userData['username'] . "</code>\n";
        $adminMessage .= "Durasi: <b>$duration Hari</b>\n";
        $adminMessage .= "Point Diberikan: <b>$pointsEarned points</b>\n";
        $adminMessage .= "Masa Aktif Baru: <b>$newExpDate WIB</b>\n";
        $adminMessage .= "Waktu: " . date('d-m-Y H:i:s');
        
        $this->notifyAdmin($adminMessage);
    }
    
    /**
     * Handle admin broadcast
     */
    private function handleAdminBroadcast($chatId, $update, $adminState) {
        $broadcastType = str_replace('waiting_broadcast_', '', $adminState['state']);
        
        $messageType = '';
        $fileId = '';
        $caption = '';
        
        if (isset($update['message']['photo'])) {
            $messageType = 'photo';
            $photos = $update['message']['photo'];
            $fileId = end($photos)['file_id'];
            $caption = $update['message']['caption'] ?? '';
        } elseif (isset($update['message']['video'])) {
            $messageType = 'video';
            $fileId = $update['message']['video']['file_id'];
            $caption = $update['message']['caption'] ?? '';
        } elseif (isset($update['message']['document'])) {
            $messageType = 'document';
            $fileId = $update['message']['document']['file_id'];
            $caption = $update['message']['caption'] ?? '';
        } elseif (isset($update['message']['audio'])) {
            $messageType = 'audio';
            $fileId = $update['message']['audio']['file_id'];
            $caption = $update['message']['caption'] ?? '';
        } elseif (isset($update['message']['text'])) {
            $messageType = 'text';
            $caption = $update['message']['text'];
        }
        
        if (!empty($messageType)) {
            $confirmMessage = "📤 <b>KONFIRMASI BROADCAST</b>\n\n";
            $confirmMessage .= "Tipe: <b>" . ($broadcastType == 'pengumuman' ? 'Pengumuman' : 'Notifikasi/Iklan') . "</b>\n";
            $confirmMessage .= "Format: <b>" . strtoupper($messageType) . "</b>\n";
            
            if (!empty($caption)) {
                $confirmMessage .= "Caption: " . substr($caption, 0, 100) . (strlen($caption) > 100 ? '...' : '') . "\n";
            }
            
            $confirmMessage .= "\n⏳ <b>Memulai broadcast...</b>";
            
            $this->sendSimpleMessage($chatId, $confirmMessage);
            
            $result = $this->broadcastToAllUsers($messageType, $fileId, $caption, $broadcastType);
            
            $this->db->saveBroadcastHistory($chatId, $broadcastType, $messageType, $result['total'], $result['success'], $result['failed']);
            
            $this->db->clearAdminState($chatId);
            
            $resultMessage = "✅ <b>BROADCAST SELESAI!</b>\n\n";
            $resultMessage .= "📊 <b>Statistik:</b>\n";
            $resultMessage .= "• Total Pengguna: {$result['total']}\n";
            $resultMessage .= "• Berhasil: {$result['success']} ✅\n";
            $resultMessage .= "• Gagal: {$result['failed']} ❌\n";
            $resultMessage .= "• Success Rate: " . round(($result['success'] / $result['total']) * 100, 2) . "%\n\n";
            $resultMessage .= "Waktu: " . date('d-m-Y H:i:s');
            
            $this->sendSimpleMessage($chatId, $resultMessage);
        }
    }
    
    /**
     * Broadcast to all users
     */
    private function broadcastToAllUsers($messageType, $fileId, $caption, $broadcastType) {
        $users = $this->db->getAllBotUsers();
        $total = count($users);
        $success = 0;
        $failed = 0;
        
        foreach ($users as $user) {
            try {
                if ($messageType == 'text') {
                    $this->sendSimpleMessage($user['chat_id'], $caption);
                } else {
                    $this->sendMediaMessage($user['chat_id'], $messageType, $fileId, $caption);
                }
                $success++;
            } catch (Exception $e) {
                $failed++;
            }
            
            usleep(50000); // 50ms delay to avoid rate limiting
        }
        
        return [
            'total' => $total,
            'success' => $success,
            'failed' => $failed
        ];
    }
    
    /**
     * Send media message
     */
    private function sendMediaMessage($chatId, $type, $fileId, $caption = '') {
        $method = '';
        switch ($type) {
            case 'photo':
                $method = 'sendPhoto';
                break;
            case 'video':
                $method = 'sendVideo';
                break;
            case 'document':
                $method = 'sendDocument';
                break;
            case 'audio':
                $method = 'sendAudio';
                break;
            default:
                return;
        }
        
        $data = [
            'chat_id' => $chatId,
            $type => $fileId,
            'parse_mode' => 'HTML'
        ];
        
        if (!empty($caption)) {
            $data['caption'] = $caption;
        }
        
        $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/$method";
        $options = [
            'http' => [
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($data),
                'timeout' => 10
            ]
        ];
        
        $context = stream_context_create($options);
        return file_get_contents($url, false, $context);
    }
    
    /**
     * Generate random credentials
     */
    private function generateRandomCredentials() {
        $letters = ['a','b','c','d','e','f','g','h','i','j','k','l','m','n','o','p','q','r','s','t','u','v','w','x','y','z'];
        $numbers = ['0','1','2','3','4','5','6','7','8','9'];
        
        $username = $letters[array_rand($letters)] . $letters[array_rand($letters)] . 
                    $numbers[array_rand($numbers)] . $numbers[array_rand($numbers)];
        
        $password = $numbers[array_rand($numbers)] . $numbers[array_rand($numbers)];
        
        return [
            'username' => $username,
            'password' => $password
        ];
    }
    
    /**
     * Generate redeem credentials (with redeem prefix)
     */
    private function generateRedeemCredentials() {
        $letters = ['a','b','c','d','e','f','g','h','i','j','k','l','m','n','o','p','q','r','s','t','u','v','w','x','y','z'];
        $numbers = ['0','1','2','3','4','5','6','7','8','9'];
        
        $username = "redeem" . $letters[array_rand($letters)] . $numbers[array_rand($numbers)];
        $password = $numbers[array_rand($numbers)];
        
        return [
            'username' => $username,
            'password' => $password
        ];
    }
    
    /**
     * Calculate points for duration
     */
    private function calculatePointsForDuration($duration) {
        if ($duration <= 1) return 1;
        if ($duration <= 3) return 2;
        if ($duration <= 5) return 4;
        if ($duration <= 7) return 5;
        if ($duration <= 10) return 8;
        if ($duration <= 15) return 12;
        if ($duration <= 20) return 15;
        return 20;
    }
    
    /**
     * Calculate points needed for days
     */
    private function calculatePointsNeededForDays($days) {
        return $days * 12;
    }
    
    /**
     * Get back button keyboard
     */
    private function getBackButton($previousAction = '') {
        $buttons = [];
        
        if ($previousAction) {
            $buttons[] = [['text' => '↩️ Kembali', 'callback_data' => $previousAction]];
        }
        
        $buttons[] = [['text' => '🏠 Menu Utama', 'callback_data' => 'main_menu']];
        
        return json_encode([
            'inline_keyboard' => $buttons
        ]);
    }
    
    /**
     * Send message with image
     */
    private function sendMessageWithImage($chatId, $text, $replyMarkup = null) {
        $photoResult = $this->sendPhoto($chatId, WELCOME_IMAGE, $text, $replyMarkup);
        $photoData = json_decode($photoResult, true);
        
        if ($photoData && $photoData['ok']) {
            return $photoResult;
        } else {
            return $this->sendSimpleMessage($chatId, $text, $replyMarkup);
        }
    }
    
    /**
     * Send simple message
     */
    private function sendSimpleMessage($chatId, $text, $replyMarkup = null) {
        $data = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true
        ];
        
        if ($replyMarkup) {
            $data['reply_markup'] = $replyMarkup;
        }
        
        $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/sendMessage";
        $options = [
            'http' => [
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($data),
                'timeout' => 10
            ]
        ];
        
        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        
        logMessage("Sent message to $chatId: " . substr($text, 0, 100));
        return $result;
    }
    
    /**
     * Send photo
     */
    private function sendPhoto($chatId, $photoUrl, $caption = '', $replyMarkup = null, $scheduleDelete = true) {
        $data = [
            'chat_id' => $chatId,
            'photo' => $photoUrl,
            'caption' => $caption,
            'parse_mode' => 'HTML'
        ];
        
        if ($replyMarkup) {
            $data['reply_markup'] = $replyMarkup;
        }
        
        $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/sendPhoto";
        $options = [
            'http' => [
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($data),
                'timeout' => 10
            ]
        ];
        
        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        
        $resultArray = json_decode($result, true);
        if ($resultArray && $resultArray['ok']) {
            $photoMessageId = $resultArray['result']['message_id'];
            logMessage("Photo sent to $chatId with message_id: $photoMessageId");
            
            if ($scheduleDelete) {
                $this->db->scheduleAutoDelete($chatId, $photoMessageId, ORDER_TIMEOUT, 'pending');
                $this->db->startRealTimePaymentCheck($chatId, $photoMessageId);
            }
        }
        
        return $result;
    }
    
    /**
     * Edit message smart (caption or text)
     */
    private function editMessageSmart($chatId, $messageId, $text, $replyMarkup = null) {
        $captionResult = $this->editMessageCaption($chatId, $messageId, $text, $replyMarkup);
        $captionData = json_decode($captionResult, true);
        
        if ($captionData && $captionData['ok']) {
            logMessage("Successfully edited message caption - Chat: $chatId, Message: $messageId");
            return $captionResult;
        }
        
        $textResult = $this->editMessageText($chatId, $messageId, $text, $replyMarkup);
        $textData = json_decode($textResult, true);
        
        if ($textData && $textData['ok']) {
            logMessage("Successfully edited message text - Chat: $chatId, Message: $messageId");
            return $textResult;
        }
        
        logMessage("Both edit methods failed, sending new message - Chat: $chatId, Message: $messageId");
        return $this->sendMessageWithImage($chatId, $text, $replyMarkup);
    }
    
    /**
     * Edit message text
     */
    private function editMessageText($chatId, $messageId, $text, $replyMarkup = null) {
        $data = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'HTML'
        ];
        
        if ($replyMarkup) {
            $data['reply_markup'] = $replyMarkup;
        }
        
        $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/editMessageText";
        $options = [
            'http' => [
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($data),
                'timeout' => 10
            ]
        ];
        
        $context = stream_context_create($options);
        return file_get_contents($url, false, $context);
    }
    
    /**
     * Edit message caption
     */
    private function editMessageCaption($chatId, $messageId, $caption, $replyMarkup = null) {
        $data = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'caption' => $caption,
            'parse_mode' => 'HTML'
        ];
        
        if ($replyMarkup) {
            $data['reply_markup'] = $replyMarkup;
        }
        
        $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/editMessageCaption";
        $options = [
            'http' => [
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($data),
                'timeout' => 10
            ]
        ];
        
        $context = stream_context_create($options);
        return file_get_contents($url, false, $context);
    }
    
    /**
     * Delete message
     */
    private function deleteMessage($chatId, $messageId) {
        $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/deleteMessage?chat_id=$chatId&message_id=$messageId";
        $result = file_get_contents($url);
        logMessage("Deleted message $messageId from $chatId: " . $result);
        return $result;
    }
    
    /**
     * Answer callback query
     */
    private function answerCallbackQuery($callbackId, $text = '') {
        $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/answerCallbackQuery";
        $data = ['callback_query_id' => $callbackId];
        
        if (!empty($text)) {
            $data['text'] = $text;
        }
        
        $options = [
            'http' => [
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($data),
                'timeout' => 5
            ]
        ];
        
        $context = stream_context_create($options);
        return file_get_contents($url, false, $context);
    }
    
    /**
     * Notify admin
     */
    private function notifyAdmin($message) {
        global $admins;
        foreach ($admins as $adminId) {
            $this->sendSimpleMessage($adminId, $message);
        }
    }
}
