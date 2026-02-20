<?php
session_start();
require_once '../backend/config.php';

// Check if we have ban info in URL params (from login redirect) or session
$userId = $_GET['uid'] ?? $_SESSION['banned_user_id'] ?? null;
$banInfo = null;

if ($userId) {
    $stmt = $pdo->prepare("SELECT username, banned_until, ban_reason, ban_proof, ban_note FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $banInfo = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Calculate time remaining
$timeRemaining = '';
if ($banInfo && !empty($banInfo['banned_until'])) {
    $banEnd = strtotime($banInfo['banned_until']);
    $now = time();
    $diff = $banEnd - $now;
    
    if ($diff > 0) {
        $days = floor($diff / 86400);
        $hours = floor(($diff % 86400) / 3600);
        $minutes = floor(($diff % 3600) / 60);
        
        if ($days > 365) {
            $timeRemaining = "Permanent";
        } elseif ($days > 0) {
            $timeRemaining = "{$days} day(s), {$hours} hour(s)";
        } elseif ($hours > 0) {
            $timeRemaining = "{$hours} hour(s), {$minutes} minute(s)";
        } else {
            $timeRemaining = "{$minutes} minute(s)";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Suspended - Loop</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #0a0a0a 0%, #1a0a0a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .banned-container {
            background: #111;
            border: 1px solid #ff4444;
            border-radius: 16px;
            max-width: 600px;
            width: 100%;
            overflow: hidden;
        }
        .banned-header {
            background: linear-gradient(135deg, #ff4444 0%, #cc0000 100%);
            padding: 30px;
            text-align: center;
        }
        .banned-header i {
            font-size: 48px;
            color: rgba(255,255,255,0.9);
            margin-bottom: 15px;
        }
        .banned-header h1 {
            color: #fff;
            font-size: 24px;
            font-weight: 600;
        }
        .banned-header p {
            color: rgba(255,255,255,0.8);
            font-size: 14px;
            margin-top: 5px;
        }
        .banned-body {
            padding: 30px;
        }
        .info-section {
            margin-bottom: 25px;
        }
        .info-section:last-child {
            margin-bottom: 0;
        }
        .info-label {
            color: #666;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }
        .info-value {
            background: rgba(255,255,255,0.05);
            border: 1px solid #222;
            border-radius: 8px;
            padding: 15px;
            color: #ccc;
            font-size: 14px;
            line-height: 1.6;
        }
        .info-value.highlight {
            color: #ff6666;
            font-weight: 600;
            font-size: 18px;
        }
        .info-value.reason {
            border-left: 3px solid #ff4444;
        }
        .info-value.proof {
            border-left: 3px solid #ff8800;
            white-space: pre-wrap;
            word-break: break-word;
        }
        .info-value.note {
            border-left: 3px solid #666;
            font-style: italic;
        }
        .countdown {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .countdown i {
            color: #ff4444;
        }
        .back-btn {
            display: block;
            text-align: center;
            background: transparent;
            border: 1px solid #333;
            color: #888;
            padding: 14px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 14px;
            margin-top: 20px;
            transition: all 0.2s;
        }
        .back-btn:hover {
            border-color: #555;
            color: #fff;
        }
        .appeal-note {
            background: rgba(62, 166, 255, 0.1);
            border: 1px solid rgba(62, 166, 255, 0.2);
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
            font-size: 13px;
            color: #888;
        }
        .appeal-note a {
            color: #3ea6ff;
        }
    </style>
</head>
<body>
    <div class="banned-container">
        <div class="banned-header">
            <i class="fa-solid fa-ban"></i>
            <h1>Account Suspended</h1>
            <p><?php echo htmlspecialchars($banInfo['username'] ?? 'User'); ?></p>
        </div>
        
        <div class="banned-body">
            <?php if (!$banInfo): ?>
                <div class="info-section">
                    <div class="info-value">Unable to load ban information. Please contact support.</div>
                </div>
            <?php else: ?>
                <div class="info-section">
                    <div class="info-label">Time Remaining</div>
                    <div class="info-value highlight">
                        <div class="countdown">
                            <i class="fa-solid fa-clock"></i>
                            <?php echo $timeRemaining ?: 'Calculating...'; ?>
                        </div>
                    </div>
                </div>
                
                <?php if (!empty($banInfo['ban_reason'])): ?>
                <div class="info-section">
                    <div class="info-label">Reason for Suspension</div>
                    <div class="info-value reason"><?php echo htmlspecialchars($banInfo['ban_reason']); ?></div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($banInfo['ban_proof'])): ?>
                <div class="info-section">
                    <div class="info-label">Evidence / Proof</div>
                    <div class="info-value proof"><?php echo nl2br(htmlspecialchars($banInfo['ban_proof'])); ?></div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($banInfo['ban_note'])): ?>
                <div class="info-section">
                    <div class="info-label">Additional Notes</div>
                    <div class="info-value note"><?php echo nl2br(htmlspecialchars($banInfo['ban_note'])); ?></div>
                </div>
                <?php endif; ?>
                
                <div class="info-section">
                    <div class="info-label">Ban Expires</div>
                    <div class="info-value"><?php echo date('F j, Y \a\t g:i A', strtotime($banInfo['banned_until'])); ?></div>
                </div>
            <?php endif; ?>
            
            <div class="appeal-note">
                <i class="fa-solid fa-circle-info"></i>
                If you believe this suspension was made in error, you can appeal by contacting 
                <a href="mailto:floxxteam@gmail.com">floxxteam@gmail.com</a>
            </div>
            
            <a href="loginb.php" class="back-btn">Back to Login</a>
        </div>
    </div>
</body>
</html>
