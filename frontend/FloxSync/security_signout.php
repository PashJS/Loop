<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require_once __DIR__ . '/../../backend/config.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../loginb.php');
    exit;
}

$userId = $_SESSION['user_id'];

// Handle Secure Account (Logout OTHER devices)
if (isset($_POST['secure_account'])) {
    // Migration check (just in case they haven't logged in since the update)
    // Migration check (Robust for older MySQL)
    try {
        $pdo->query("SELECT session_id FROM login_activity LIMIT 1");
    } catch (Exception $e) {
        try {
            $pdo->exec("ALTER TABLE login_activity ADD COLUMN session_id VARCHAR(255)");
        } catch (Exception $ex) {}
    }

    try {
        $pdo->query("SELECT is_revoked FROM login_activity LIMIT 1");
    } catch (Exception $e) {
        try {
            $pdo->exec("ALTER TABLE login_activity ADD COLUMN is_revoked TINYINT(1) DEFAULT 0");
        } catch (Exception $ex) {}
    }

    // Revoke all OTHER sessions for this user
    try {
        $currentSession = session_id();
        
        // 1. Mark specific sessions as revoked (good for new sessions)
        $stmt = $pdo->prepare("UPDATE login_activity SET is_revoked = 1 WHERE user_id = ? AND session_id != ?");
        $stmt->execute([$userId, $currentSession]);
        
        // 2. Set global logout timestamp (catches old/mobile sessions)
        try { $pdo->exec("ALTER TABLE users ADD COLUMN force_logout_before TIMESTAMP NULL DEFAULT NULL"); } catch(Exception $e) {}
        
        // Use time() - 300 (5 mins ago) for the cutoff. 
        // This effectively logs out anyone who hasn't logged in within the last 5 minutes.
        // It's safer than "NOW" because "NOW" might compete with "Just logged in NOW".
        $cutoffTime = date('Y-m-d H:i:s', time() - 300);
        $pdo->prepare("UPDATE users SET force_logout_before = ? WHERE id = ?")->execute([$cutoffTime, $userId]);
        
        // 3. Update CURRENT session so I don't get kicked out
        $_SESSION['login_timestamp'] = time(); // Normal time is fine now since cutoff is in past
        
    } catch (PDOException $e) {
        die("Error securing account: " . $e->getMessage());
    }
    
    // Also mark the notification as addressed or something? 
    // For now, just redirect with success
    header('Location: mailbox.php?msg=other_devices_secured');
    exit;
}

// Handle Yes it's me
if (isset($_POST['confirm_me'])) {
    header('Location: mailbox.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Check - FloxSync</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../home.css">
    <style>
        body {
            background: radial-gradient(circle at center, #1a1a1a 0%, #050505 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            overflow: hidden;
        }

        .check-card {
            background: rgba(30, 30, 30, 0.4);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 30px;
            padding: 50px;
            max-width: 450px;
            width: 90%;
            text-align: center;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.5);
            animation: cardAppear 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }

        @keyframes cardAppear {
            from { opacity: 0; transform: scale(0.9) translateY(20px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }

        .security-icon {
            font-size: 60px;
            color: #ffab00;
            margin-bottom: 25px;
            filter: drop-shadow(0 0 15px rgba(255, 171, 0, 0.3));
        }

        h1 {
            font-size: 32px;
            font-weight: 800;
            color: #fff;
            margin-bottom: 15px;
            letter-spacing: -1px;
        }

        p {
            color: #888;
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 40px;
        }

        .btn-group {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .btn {
            border: none;
            padding: 16px 30px;
            border-radius: 15px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            width: 100%;
        }

        .btn-yes {
            background: rgba(255, 255, 255, 0.05);
            color: #fff;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .btn-yes:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-2px);
        }

        .btn-no {
            background: #ff4d4d;
            color: #fff;
            box-shadow: 0 10px 20px rgba(255, 77, 77, 0.2);
        }

        .btn-no:hover {
            background: #ff3333;
            transform: translateY(-2px);
            box-shadow: 0 15px 30px rgba(255, 77, 77, 0.4);
        }

        .shield-decoration {
            position: absolute;
            top: -20px;
            left: 50%;
            transform: translateX(-50%);
            background: #0f0f0f;
            padding: 10px 20px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 800;
            color: #3ea6ff;
            text-transform: uppercase;
            letter-spacing: 2px;
            border: 1px solid rgba(62, 166, 255, 0.2);
        }
    </style>
</head>
<body>
    <div class="check-card">
        <div class="shield-decoration">Security Validation</div>
        <div class="security-icon">
            <i class="fa-solid fa-user-shield"></i>
        </div>
        <h1>Is this you?</h1>
        <p>A new login was recorded from <strong><?php echo htmlspecialchars($_GET['device'] ?? 'another device'); ?></strong> for your FloxSync account. Please verify if you recognize this activity.</p>
        
        <form method="POST">
            <div class="btn-group">
                <button type="submit" name="confirm_me" class="btn btn-yes">Yes, it's me</button>
                <button type="submit" name="secure_account" class="btn btn-no">No, secure my account</button>
            </div>
        </form>
    </div>
</body>
</html>
