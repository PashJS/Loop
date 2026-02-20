<?php
session_start();
require_once '../../backend/config.php';
require_once '../../backend/mailer.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../loginb.php');
    exit;
}

$userId = $_SESSION['user_id'];
$success = false;
$error = '';

// Handle Deletion Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deletionEmail'])) {
    $email = trim($_POST['deletionEmail']);
    
    // Fetch current user email to verify or just log who requested it
    $stmt = $pdo->prepare("SELECT email, username FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        $subject = "Account Deletion Request - FloxSync";
        $message = "
        <div style='font-family: sans-serif; padding: 20px; color: #333;'>
            <h2 style='color: #ff4d4d;'>Account Deletion Request</h2>
            <p><strong>User ID:</strong> $userId</p>
            <p><strong>Username:</strong> {$user['username']}</p>
            <p><strong>Registered Email:</strong> {$user['email']}</p>
            <p><strong>Email provided for deletion:</strong> $email</p>
            <hr>
            <p>The user has requested to permanently delete their FloxSync account. Please process this request according to security protocols.</p>
        </div>
        ";

        if (sendFloxEmail('floxxteam@gmail.com', $subject, $message, true)) {
            $success = true;
        } else {
            $error = "Failed to send deletion request. Please try again later or contact support directly.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Account - FloxSync</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../home.css?v=3">
    <link rel="stylesheet" href="../layout.css">
    <style>
        .manage-container {
            max-width: 600px;
            margin: 0 auto;
            padding: 60px 20px;
        }

        .manage-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .manage-header h1 {
            font-size: 28px;
            font-weight: 700;
            color: #ff4d4d;
            margin-bottom: 15px;
        }

        .manage-header p {
            color: #888;
            font-size: 15px;
            line-height: 1.6;
        }

        .settings-section {
            background: rgba(30, 30, 30, 0.4);
            border: 1px solid rgba(255, 77, 77, 0.2);
            border-radius: 20px;
            padding: 35px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .form-group {
            margin-bottom: 25px;
        }

        .manage-label {
            display: block;
            color: #ccc;
            font-size: 14px;
            margin-bottom: 10px;
            font-weight: 500;
        }

        .manage-input {
            width: 100%;
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 14px 18px;
            color: #fff;
            font-size: 15px;
            box-sizing: border-box;
            transition: border-color 0.2s;
        }

        .manage-input:focus {
            outline: none;
            border-color: #ff4d4d;
        }

        .manage-btn {
            width: 100%;
            background: #ff4d4d;
            color: #fff;
            border: none;
            padding: 15px;
            border-radius: 30px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.2s, background 0.2s;
        }

        .manage-btn:hover {
            background: #ff3333;
            transform: translateY(-2px);
        }

        .status-msg {
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 25px;
            font-size: 14px;
            text-align: center;
        }

        .status-msg.success {
            background: rgba(46, 204, 113, 0.1);
            color: #2ecc71;
            border: 1px solid rgba(46, 204, 113, 0.2);
        }

        .status-msg.error {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
            border: 1px solid rgba(231, 76, 60, 0.2);
        }

        .warning-box {
            background: rgba(255, 77, 77, 0.05);
            border-left: 4px solid #ff4d4d;
            padding: 15px;
            margin-bottom: 25px;
            font-size: 13px;
            color: #bbb;
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
    </style>
</head>
<body style="background: #0f0f0f; opacity: 1; animation: none;">


    <div class="breadcrumb" style="margin: 30px auto; max-width: 800px; padding: 0 20px;">
        <a href="floxsync.php">FloxSync</a>
        <span>&gt;</span>
        <a href="manage_floxsync.php">Account Management</a>
        <span>&gt;</span>
        <a href="floxsync_account_settings.php">Account Settings</a>
        <span>&gt;</span>
        <a href="delete_floxsync.php" class="current">Account Deletion</a>
    </div>

    <div class="manage-container">
        <div class="manage-header">
            <h1>Permanently Delete Account</h1>
            <p>We're sorry to see you go. Please confirm your decision below.</p>
        </div>

        <?php if ($success): ?>
            <div class="status-msg success">
                <i class="fa-solid fa-circle-check"></i> 
                Deletion request sent successfully. Our team will process your request shortly.
            </div>
            <div style="text-align: center;">
                <a href="floxsync.php" class="manage-btn" style="display:inline-block; width:auto; padding: 12px 30px; background: rgba(255,255,255,0.1);">Return to Hub</a>
            </div>
        <?php else: ?>
            <div class="settings-section">
                <?php if ($error): ?>
                    <div class="status-msg error">
                        <i class="fa-solid fa-circle-exclamation"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <div class="warning-box">
                    <strong>Warning:</strong> This will permanently delete your FloxSync identity, linked watchlists, and all personal data. This cannot be undone.
                </div>

                <form method="POST">
                    <div class="form-group">
                        <label class="manage-label">Email for account deletion</label>
                        <input type="email" name="deletionEmail" class="manage-input" placeholder="Enter your registered email" required>
                    </div>
                    <button type="submit" class="manage-btn">Confirm Deletion Request</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
