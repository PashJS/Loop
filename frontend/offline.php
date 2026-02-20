<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Site Offline - Loop</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: #000;
            color: #fff;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            text-align: center;
        }
        .container {
            max-width: 600px;
            padding: 40px;
            animation: fadeIn 1.2s ease-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .icon {
            font-size: 80px;
            margin-bottom: 30px;
            background: linear-gradient(135deg, #ff3333, #aa0000);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: pulse 2s infinite ease-in-out;
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.05); opacity: 0.8; }
        }
        h1 {
            font-size: 32px;
            font-weight: 800;
            margin-bottom: 20px;
            letter-spacing: -0.5px;
        }
        p {
            color: #888;
            font-size: 18px;
            line-height: 1.6;
            margin-bottom: 40px;
        }
        .status-badge {
            background: rgba(255, 50, 50, 0.1);
            border: 1px solid rgba(255, 50, 50, 0.2);
            padding: 8px 16px;
            border-radius: 100px;
            color: #ff5555;
            font-size: 14px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .status-badge .dot {
            width: 8px;
            height: 8px;
            background: #ff5555;
            border-radius: 50%;
            box-shadow: 0 0 10px #ff5555;
        }
        .copyright {
            position: absolute;
            bottom: 40px;
            color: #333;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">
            <i class="fa-solid fa-power-off"></i>
        </div>
        <div class="status-badge">
            <span class="dot"></span> EMERGENCY MAINTENANCE
        </div>
        <h1 style="margin-top: 20px;">Loop is Currently Offline</h1>
        <p>We are performing critical system updates. Access to all services has been temporarily suspended. Please check back later.</p>
    </div>
    <div class="copyright">© 2026 FLOXWATCH TEAM</div>
</body>
</html>
