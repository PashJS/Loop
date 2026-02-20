<?php
session_start();
require_once '../../backend/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../loginb.php');
    exit;
}

$userId = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT created_at FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$memberSince = date("F j, Y", strtotime($user['created_at']));

// Fetch Login Activity
$stmtLogs = $pdo->prepare("SELECT * FROM login_activity WHERE user_id = ? ORDER BY login_time DESC LIMIT 5");
$stmtLogs->execute([$userId]);
$loginLogs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Settings - FloxSync</title>
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
            color: #fff;
        }

        .manage-header p {
            color: #888;
            font-size: 16px;
        }

        .settings-section {
            background: rgba(30, 30, 30, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 30px;
        }

        .settings-section-title {
            font-size: 18px;
            font-weight: 600;
            color: #fff;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .settings-section-title i {
            color: #3ea6ff;
            font-size: 16px;
        }

        .settings-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.03);
        }

        .settings-row:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }

        .settings-info h4 {
            margin: 0 0 5px 0;
            font-size: 15px;
            color: #fff;
        }

        .settings-info p {
            margin: 0;
            font-size: 13px;
            color: #888;
            line-height: 1.4;
        }

        .manage-btn {
            background: rgba(255, 255, 255, 0.05);
            color: #fff;
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 10px 20px;
            border-radius: 30px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }

        .manage-btn:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.2);
        }

        .manage-btn.danger {
            color: #ff4d4d;
            border-color: rgba(255, 77, 77, 0.2);
        }

        .manage-btn.danger:hover {
            background: rgba(255, 77, 77, 0.1);
            border-color: rgba(255, 77, 77, 0.4);
        }

        .status-badge {
            font-size: 12px;
            padding: 4px 10px;
            border-radius: 20px;
            background: rgba(46, 204, 113, 0.1);
            color: #2ecc71;
            border: 1px solid rgba(46, 204, 113, 0.2);
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

        /* Fixed Invisible Modal */
        .fs-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            backdrop-filter: blur(15px);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 10000; /* Extremely high z-index */
        }

        .fs-modal-content {
            background: #181818;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 24px;
            width: 90%;
            max-width: 500px;
            padding: 40px;
            position: relative;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.8);
            animation: fsModalPop 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        @keyframes fsModalPop {
            from { opacity: 0; transform: scale(0.9) translateY(20px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }

        .fs-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .fs-modal-header h3 {
            margin: 0;
            font-size: 20px;
            color: #fff;
            font-weight: 700;
        }

        .fs-close-modal {
            background: rgba(255, 255, 255, 0.05);
            border: none;
            color: #fff;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }

        .fs-close-modal:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: rotate(90deg);
        }

        .fs-log-item {
            padding: 16px;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 12px;
            margin-bottom: 12px;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .fs-log-device {
            color: #fff;
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 4px;
        }

        .fs-log-meta {
            color: #aaa;
            font-size: 12px;
            display: flex;
            justify-content: space-between;
        }
    </style>
</head>
<body style="background: #0f0f0f; opacity: 1; animation: none;">


    <div class="breadcrumb" style="margin: 30px auto; max-width: 800px; padding: 0 20px;">
        <a href="floxsync.php">FloxSync</a>
        <span>&gt;</span>
        <a href="manage_floxsync.php">Account Management</a>
        <span>&gt;</span>
        <a href="floxsync_account_settings.php" class="current">Account Settings</a>
    </div>

    <div class="manage-container">
        <div class="manage-header">
            <h1>Account Settings</h1>
            <p>Advanced preferences and account lifecycle management</p>
        </div>

        <!-- Account Status & History -->
        <div class="settings-section">
            <div class="settings-section-title">
                <i class="fa-solid fa-circle-info"></i>
                Account Details
            </div>
            <div class="settings-row">
                <div class="settings-info">
                    <h4>Account Status</h4>
                    <p>Your account is currently in good standing.</p>
                </div>
                <div class="status-badge">Active</div>
            </div>
            <div class="settings-row">
                <div class="settings-info">
                    <h4>Member Since</h4>
                    <p>When you first joined the community.</p>
                </div>
                <div style="color: #666; font-size: 14px;"><?php echo $memberSince; ?></div>
            </div>
        </div>

        <!-- Advanced Preferences -->
        <div class="settings-section">
            <div class="settings-section-title">
                <i class="fa-solid fa-sliders"></i>
                Advanced Preferences
            </div>
            <div class="settings-row">
                <div class="settings-info">
                    <h4>Language</h4>
                    <p>Select your preferred language for the interface.</p>
                </div>
                <button class="manage-btn">English (US)</button>
            </div>
            <div class="settings-row">
                <div class="settings-info">
                    <h4>Login Activity</h4>
                    <p>Review and manage where you are signed in.</p>
                </div>
                <button class="manage-btn" onclick="openLogsModal()">Review Activity</button>
            </div>
        </div>

        <!-- Danger Zone -->
        <div class="settings-section" style="border-color: rgba(255, 77, 77, 0.2);">
            <div class="settings-section-title" style="color: #ff4d4d;">
                <i class="fa-solid fa-triangle-exclamation" style="color: #ff4d4d;"></i>
                Danger Zone
            </div>
            <div class="settings-row">
                <div class="settings-info">
                    <h4>Delete FloxSync Account</h4>
                    <p>Permanently remove your account and all associated data. This action is irreversible.</p>
                </div>
                <button class="manage-btn danger" onclick="window.location.href='delete_floxsync.php'">Delete Account</button>
            </div>
        </div>

    </div>

    <!-- Login Activity Modal (Fixed & Renamed) -->
    <div id="logsModal" class="fs-modal-overlay">
        <div class="fs-modal-content">
            <div class="fs-modal-header">
                <h3>Recent Login Activity</h3>
                <button class="fs-close-modal" onclick="closeLogsModal()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="fs-logs-list">
                <?php if (empty($loginLogs)): ?>
                    <p style="color: #666; text-align: center; padding: 20px;">No recent login activity found.</p>
                <?php else: ?>
                    <?php foreach ($loginLogs as $log): ?>
                        <div class="fs-log-item">
                            <div class="fs-log-device"><?php echo htmlspecialchars($log['device_info']) . " " . htmlspecialchars($log['location']); ?></div>
                            <div class="fs-log-meta">
                                <span><i class="fa-solid fa-location-crosshairs" style="font-size:10px; opacity:0.5;"></i> <?php echo htmlspecialchars($log['ip_address']); ?></span>
                                <span><i class="fa-solid fa-clock" style="font-size:10px; opacity:0.5;"></i> <?php echo date("M j, H:i", strtotime($log['login_time'])); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function openLogsModal() {
            const modal = document.getElementById('logsModal');
            if (modal) {
                modal.style.display = 'flex';
                document.body.style.overflow = 'hidden';
            }
        }

        function closeLogsModal() {
            const modal = document.getElementById('logsModal');
            if (modal) {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        }

        window.addEventListener('click', function(event) {
            const modal = document.getElementById('logsModal');
            if (event.target === modal) {
                closeLogsModal();
            }
        });
    </script>
</body>
</html>
