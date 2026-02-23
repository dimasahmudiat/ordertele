<?php
// index.php
?>
<!DOCTYPE html>
<html>
<head>
    <title>DIMZ BOT</title>
    <style>
        body { font-family: Arial; text-align: center; padding: 50px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .container { background: rgba(255,255,255,0.1); padding: 30px; border-radius: 10px; display: inline-block; }
        h1 { font-size: 48px; margin-bottom: 10px; }
        .status { color: #4CAF50; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🤖 DIMZ BOT</h1>
        <h2 class="status">✅ BOT SEDANG BERJALAN</h2>
        <p>Server Time: <?php echo date('d-m-Y H:i:s'); ?> WIB</p>
        <p>PHP Version: <?php echo phpversion(); ?></p>
        <p>Endpoint: <code>/api/webhook.php</code></p>
    </div>
</body>
</html>
