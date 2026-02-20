<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../../backend/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../loginb.php');
    exit;
}

$userId = $_SESSION['user_id'];
$is2FA = false;

try {
    // Check if column exists or just select
    $stmt = $pdo->prepare("SELECT two_factor_enabled FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $is2FA = !empty($user['two_factor_enabled']);
} catch (Exception $e) {
    // Ignore error, assume no 2FA
    error_log("Security Page Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security & Privacy - FloxSync</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../home.css?v=3">
    <link rel="stylesheet" href="../layout.css">
    <style>
        .manage-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .manage-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .manage-header h1 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #fff 0%, #aaa 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .manage-header p {
            color: #888;
            font-size: 16px;
        }

        .settings-section {
            background: rgba(30, 30, 30, 0.6);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
        }

        .settings-section-title {
            font-size: 20px;
            font-weight: 600;
            color: #fff;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .settings-section-title i {
            color: #3ea6ff;
        }

        .manage-btn {
            background: #3ea6ff;
            color: #fff;
            border: none;
            padding: 12px 24px;
            border-radius: 30px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none; /* Add link styling support */
        }

        .manage-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(62, 166, 255, 0.3);
            background: #3ea6ff; /* Keep consistent background */
        }

        .manage-btn.secondary {
            background: rgba(255, 255, 255, 0.1);
        }
        
        .manage-btn.secondary:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            background: rgba(255, 255, 255, 0.05);
            color: #aaa;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .status-badge.active {
            background: rgba(46, 204, 113, 0.15);
            color: #2ecc71;
            border-color: rgba(46, 204, 113, 0.3);
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: #666;
            margin: 20px 0 0 40px;
        }

        .breadcrumb a {
            color: #888;
            text-decoration: none;
            transition: color 0.2s;
        }

        .breadcrumb a:hover {
            color: #fff;
        }

        .breadcrumb span {
            color: #444;
        }

        .breadcrumb .current {
            color: #aaa;
            pointer-events: none;
        }
        /* Dedicated FloxSync Branding */
    </style>
</head>
<body style="background: #0f0f0f; opacity: 1; animation: none;">


    <div class="breadcrumb" style="margin: 30px auto; max-width: 800px; padding: 0 20px;">
        <a href="floxsync.php">FloxSync</a>
        <span>&gt;</span>
        <a href="manage_floxsync.php">Account Management</a>
        <span>&gt;</span>
        <a href="floxsync_security.php" class="current">Security & Privacy</a>
    </div>

    <div class="manage-container">
        <div class="manage-header">
            <h1>Security & Privacy</h1>
            <p>Secure your account and manage your data</p>
        </div>

        <div class="settings-section">
            <div class="settings-section-title">
                <i class="fa-solid fa-shield-halved"></i>
                Account Security
            </div>
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; padding-bottom: 25px; border-bottom: 1px solid rgba(255,255,255,0.05);">
                <div>
                    <div style="color: #fff; font-weight: 600; margin-bottom: 5px;">Two-Factor Authentication</div>
                    <div style="color: #888; font-size: 14px;">Add an extra layer of security to your account with 2FA</div>
                </div>
                <?php if($is2FA): ?>
                    <div class="status-badge active"><i class="fa-solid fa-check"></i> Enabled</div>
                <?php else: ?>
                    <button class="manage-btn secondary" onclick="window.location.href='../settings.php'">Set Up</button>
                <?php endif; ?>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <div style="color: #fff; font-weight: 600; margin-bottom: 5px;">Data Privacy</div>
                    <div style="color: #888; font-size: 14px;">Review your data and activity controls</div>
                </div>
                <button class="manage-btn secondary" onclick="window.location.href='../settings.php'">Manage</button>
            </div>
        </div>

        <div class="settings-section">
            <div class="settings-section-title">
                <i class="fa-solid fa-inbox"></i>
                Mailbox & Notifications
            </div>
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <div style="color: #fff; font-weight: 600; margin-bottom: 5px;">Security Mailbox</div>
                    <div style="color: #888; font-size: 14px;">View all security-related alerts and account notifications</div>
                </div>
                <button class="manage-btn" onclick="window.location.href='mailbox.php'">Open Mailbox</button>
            </div>
        </div>
    </div>
</body>
</html>
