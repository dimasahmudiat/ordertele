<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DIMZ BOT - Order 24 Jam</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .container {
            max-width: 600px;
            width: 100%;
        }
        
        .card {
            background: white;
            border-radius: 20px;
            padding: 40px 30px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            text-align: center;
            animation: fadeIn 0.8s ease-in;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .icon {
            font-size: 80px;
            margin-bottom: 20px;
            animation: bounce 2s infinite;
        }
        
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {
                transform: translateY(0);
            }
            40% {
                transform: translateY(-20px);
            }
            60% {
                transform: translateY(-10px);
            }
        }
        
        h1 {
            color: #333;
            font-size: 32px;
            margin-bottom: 15px;
            font-weight: 700;
        }
        
        .status {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 25px;
            border-radius: 50px;
            display: inline-block;
            margin: 20px 0;
            font-weight: 600;
            font-size: 18px;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .status i {
            margin-right: 8px;
        }
        
        .info {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 25px;
            margin: 25px 0;
            text-align: left;
        }
        
        .info-item {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            padding: 10px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .info-item:last-child {
            margin-bottom: 0;
        }
        
        .info-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: white;
            font-size: 20px;
        }
        
        .info-text {
            flex: 1;
        }
        
        .info-text h3 {
            color: #333;
            font-size: 16px;
            margin-bottom: 3px;
        }
        
        .info-text p {
            color: #666;
            font-size: 14px;
        }
        
        .features {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin: 25px 0;
        }
        
        .feature {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 12px;
            transition: transform 0.3s;
        }
        
        .feature:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .feature span {
            font-size: 28px;
            display: block;
            margin-bottom: 8px;
        }
        
        .feature p {
            color: #555;
            font-size: 13px;
            font-weight: 500;
        }
        
        .button-group {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 25px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 12px 25px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.6);
        }
        
        .btn-secondary {
            background: #f8f9fa;
            color: #333;
            border: 2px solid #e0e0e0;
        }
        
        .btn-secondary:hover {
            background: #e0e0e0;
            transform: translateY(-2px);
        }
        
        .footer {
            margin-top: 30px;
            color: rgba(255,255,255,0.8);
            font-size: 14px;
        }
        
        .footer a {
            color: white;
            text-decoration: none;
            font-weight: 600;
        }
        
        .footer a:hover {
            text-decoration: underline;
        }
        
        .dot {
            height: 12px;
            width: 12px;
            background-color: #4CAF50;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(76, 175, 80, 0.7);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(76, 175, 80, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(76, 175, 80, 0);
            }
        }
        
        @media (max-width: 480px) {
            .card {
                padding: 30px 20px;
            }
            
            h1 {
                font-size: 24px;
            }
            
            .features {
                grid-template-columns: 1fr;
            }
            
            .button-group {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="icon">🤖</div>
            <h1>DIMZ BOT - ORDER 24 JAM</h1>
            
            <div class="status">
                <span class="dot"></span>
                <i>⚡</i> BOT SEDANG BERJALAN
            </div>
            
            <div class="info">
                <div class="info-item">
                    <div class="info-icon">🤖</div>
                    <div class="info-text">
                        <h3>Status Bot</h3>
                        <p><strong style="color: #4CAF50;">AKTIF</strong> - Online 24/7</p>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-icon">📅</div>
                    <div class="info-text">
                        <h3>Waktu Server</h3>
                        <p><?php echo date('d-m-Y H:i:s'); ?> WIB</p>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-icon">📊</div>
                    <div class="info-text">
                        <h3>Versi Bot</h3>
                        <p>v2.0.0 - Full Feature</p>
                    </div>
                </div>
            </div>
            
            <h3 style="margin-bottom: 15px; color: #333;">✨ FITUR BOT ✨</h3>
            
            <div class="features">
                <div class="feature">
                    <span>🛒</span>
                    <p>Beli Lisensi Baru</p>
                </div>
                <div class="feature">
                    <span>⏰</span>
                    <p>Extend Masa Aktif</p>
                </div>
                <div class="feature">
                    <span>🎁</span>
                    <p>Tukar Point</p>
                </div>
                <div class="feature">
                    <span>💳</span>
                    <p>Pembayaran QRIS</p>
                </div>
                <div class="feature">
                    <span>🎮</span>
                    <p>Free Fire & MAX</p>
                </div>
                <div class="feature">
                    <span>📢</span>
                    <p>Auto Broadcast</p>
                </div>
            </div>
            
            <div class="button-group">
                <a href="https://t.me/dimasvip1120" target="_blank" class="btn btn-primary">
                    <span>📱</span> Kontak Admin
                </a>
                <a href="https://t.me/+RY2yMHn_jts3YzA1" target="_blank" class="btn btn-secondary">
                    <span>📁</span> File & Tutorial
                </a>
            </div>
            
            <div style="margin-top: 25px; padding-top: 20px; border-top: 2px dashed #e0e0e0;">
                <p style="color: #666; margin-bottom: 10px;">🔍 Cara Menggunakan Bot:</p>
                <p style="color: #888; font-size: 14px;">1. Klik link bot: <a href="https://t.me/your_bot_username" target="_blank" style="color: #667eea;">@your_bot_username</a></p>
                <p style="color: #888; font-size: 14px;">2. Ketik /start untuk memulai</p>
                <p style="color: #888; font-size: 14px;">3. Pilih menu yang tersedia</p>
            </div>
        </div>
        
        <div class="footer">
            <p>© 2024 DIMZ BOT - All Rights Reserved | Powered by <a href="https://dimzmods.my.id" target="_blank">DIMZMODS</a></p>
        </div>
    </div>
    
    <?php
    // Log akses ke halaman
    $logFile = __DIR__ . '/logs/access_log.txt';
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    
    // Buat folder logs jika belum ada
    if (!file_exists(__DIR__ . '/logs')) {
        mkdir(__DIR__ . '/logs', 0755, true);
    }
    
    file_put_contents($logFile, "[$timestamp] IP: $ip - UA: $userAgent\n", FILE_APPEND);
    ?>
</body>
</html>
