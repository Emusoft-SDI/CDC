<?php
// Ensure this is a raw HTTP response without relying on config.php
http_response_code(503);
header('Retry-After: 3600');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NATCODEV | Essential Maintenance</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1B5E20;
            --primary-light: #2E7D32;
        }
        body, html {
            margin: 0;
            padding: 0;
            height: 100%;
            font-family: 'Inter', sans-serif;
            background-color: #0d1117;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .bg {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: url('/win/assets/maintenance_bg.jpg') no-repeat center center;
            background-size: cover;
            z-index: 1;
            opacity: 0.6;
            animation: slowPan 40s linear infinite alternate;
        }
        @keyframes slowPan {
            0% { transform: scale(1); }
            100% { transform: scale(1.05); }
        }
        .overlay {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: linear-gradient(135deg, rgba(0,0,0,0.8) 0%, rgba(27,94,32,0.4) 100%);
            z-index: 2;
        }
        .glass-card {
            position: relative;
            z-index: 3;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            padding: 50px 40px;
            max-width: 500px;
            text-align: center;
            color: #fff;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            animation: float 6s ease-in-out infinite;
        }
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0px); }
        }
        .logo-container {
            margin-bottom: 30px;
        }
        .logo-container img {
            max-height: 80px;
            filter: drop-shadow(0 4px 6px rgba(0,0,0,0.3));
        }
        h1 {
            font-size: 28px;
            font-weight: 800;
            margin: 0 0 15px 0;
            letter-spacing: -0.5px;
        }
        p {
            font-size: 16px;
            line-height: 1.6;
            color: #d1d5db;
            margin: 0 0 30px 0;
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            background: rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 8px 16px;
            border-radius: 30px;
            font-size: 14px;
            font-weight: 600;
        }
        .dot {
            width: 10px;
            height: 10px;
            background-color: #ef4444;
            border-radius: 50%;
            margin-right: 10px;
            box-shadow: 0 0 10px #ef4444;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(239, 68, 68, 0); }
            100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
        }
    </style>
</head>
<body>
    <div class="bg"></div>
    <div class="overlay"></div>
    
    <div class="glass-card">
        <div class="logo-container">
            <!-- Ensure fallback to text if logo image is missing -->
            <img src="/win/assets/logo.svg" alt="NATCODEV" onerror="this.style.display='none'; document.getElementById('logo-fallback').style.display='block';">
            <h1 id="logo-fallback" style="display:none; color: #4ade80;">NATCODEV</h1>
        </div>
        
        <h1>We are planting new seeds.</h1>
        <p>NATCODEV is currently undergoing essential maintenance to improve our platform infrastructure. We'll be back online shortly.</p>
        
        <div class="status-badge">
            <div class="dot"></div>
            System Offline
        </div>
    </div>
</body>
</html>
