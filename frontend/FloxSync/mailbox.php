<?php
session_start();
require_once __DIR__ . '/../../backend/config.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../loginb.php');
    exit;
}

$userId = $_SESSION['user_id'];

// Table Creation Logic (Conditional)
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS floxsync_mailbox (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        type VARCHAR(50) NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (user_id)
    )");

    // Check if user has any messages, if not, add greeting/security mocks
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM floxsync_mailbox WHERE user_id = ?");
    $stmt->execute([$userId]);
    if ($stmt->fetchColumn() == 0) {
        $mocks = [
            ['security', 'Security Shield Active', 'Your account is now protected by FloxSync Security. We will notify you here of any suspicious activity.'],
            ['alert', 'New Login Detected', 'A new login was recorded from this browser. If this wasn\'t you, please change your password immediately.'],
            ['info', 'Welcome to your Mailbox', 'All security updates, login alerts, and account status notifications will appear here.']
        ];
        $insert = $pdo->prepare("INSERT INTO floxsync_mailbox (user_id, type, title, message) VALUES (?, ?, ?, ?)");
        foreach ($mocks as $m) {
            $insert->execute([$userId, $m[0], $m[1], $m[2]]);
        }
    }
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// Fetch messages
$stmt = $pdo->prepare("SELECT * FROM floxsync_mailbox WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$userId]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Mark all as read when opening mailbox
$pdo->prepare("UPDATE floxsync_mailbox SET is_read = 1 WHERE user_id = ? AND is_read = 0")->execute([$userId]);

?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mailbox - FloxSync</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../home.css">
    <link rel="stylesheet" href="../layout.css">
    <style>
        body {
            background: #0f0f0f;
            min-height: 100vh;
        }

        .mailbox-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .mailbox-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 40px;
        }

        .mailbox-header h1 {
            font-size: 32px;
            font-weight: 800;
            color: #fff;
            margin: 0;
        }

        .message-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .message-item {
            background: rgba(30, 30, 30, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 16px;
            padding: 24px;
            display: flex;
            gap: 20px;
            transition: all 0.2s ease;
            position: relative;
            overflow: hidden;
        }

        .message-item:hover {
            border-color: rgba(255, 255, 255, 0.15);
            background: rgba(40, 40, 40, 0.5);
        }

        .message-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }

        .type-security { background: rgba(255, 77, 77, 0.1); color: #ff4d4d; }
        .type-info { background: rgba(62, 166, 255, 0.1); color: #3ea6ff; }
        .type-alert { background: rgba(255, 171, 0, 0.1); color: #ffab00; }

        .message-content h3 {
            font-size: 17px;
            font-weight: 600;
            color: #fff;
            margin: 0 0 8px 0;
        }

        .message-content p {
            font-size: 14px;
            color: #aaa;
            line-height: 1.6;
            margin: 0;
        }

        .message-meta {
            margin-top: 15px;
            font-size: 12px;
            color: #666;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .unread-dot {
            position: absolute;
            top: 24px;
            right: 24px;
            width: 8px;
            height: 8px;
            background: #3ea6ff;
            border-radius: 50%;
            box-shadow: 0 0 10px #3ea6ff;
        }

        .empty-state {
            text-align: center;
            padding: 100px 20px;
            color: #666;
        }

        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: #666;
            margin-bottom: 20px;
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

        .back-hub {
            color: #3ea6ff;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
    </style>
</head>
<body>
    <div class="mailbox-container">
        <div class="breadcrumb">
            <a href="floxsync.php">FloxSync</a>
            <span>&gt;</span>
            <span style="color: #aaa;">Mailbox</span>
        </div>

        <div class="mailbox-header">
            <div>
                <h1>Mailbox</h1>
                <p style="color: #888; margin-top: 10px;">Security updates and account notifications</p>
            </div>
        </div>

        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'other_devices_secured'): ?>
            <div style="background: rgba(46, 204, 113, 0.1); border: 1px solid rgba(46, 204, 113, 0.3); color: #2ecc71; padding: 15px; border-radius: 12px; margin-bottom: 25px; display: flex; align-items: center; gap: 12px;">
                <i class="fa-solid fa-circle-check"></i>
                <span>Success! All other active sessions have been signed out. Your account is now secure.</span>
            </div>
        <?php endif; ?>

        <div class="message-list">
            <?php if (empty($messages)): ?>
                <div class="empty-state">
                    <i class="fa-solid fa-envelope-open"></i>
                    <p>Your mailbox is empty.</p>
                </div>
            <?php else: ?>
                <?php foreach ($messages as $msg): ?>
                    <?php 
                        $isClickable = ($msg['type'] === 'alert' && strpos($msg['title'], 'Login') !== false);
                        $device = $isClickable ? str_replace('New Login: ', '', $msg['title']) : '';
                        $onClick = $isClickable ? "onclick=\"window.location.href='security_signout.php?device=" . urlencode($device) . "'\"" : "";
                    ?>
                    <div class="message-item <?php echo $isClickable ? 'clickable' : ''; ?>" <?php echo $onClick; ?> style="<?php echo $isClickable ? 'cursor: pointer;' : ''; ?>">
                        <?php if (!$msg['is_read']): ?>
                            <div class="unread-dot"></div>
                        <?php endif; ?>
                        
                        <div class="message-icon type-<?php echo $msg['type']; ?>">
                            <?php 
                                switch($msg['type']) {
                                    case 'security': echo '<i class="fa-solid fa-shield-halved"></i>'; break;
                                    case 'alert': echo '<i class="fa-solid fa-triangle-exclamation"></i>'; break;
                                    default: echo '<i class="fa-solid fa-circle-info"></i>';
                                }
                            ?>
                        </div>
                        <div class="message-content">
                            <h3><?php echo htmlspecialchars($msg['title']); ?></h3>
                            <p><?php echo htmlspecialchars($msg['message']); ?></p>
                            <div class="message-meta">
                                <span><i class="fa-solid fa-clock"></i> <?php echo date('M j, Y, g:i a', strtotime($msg['created_at'])); ?></span>
                                <span>&bull;</span>
                                <span style="text-transform: capitalize;"><?php echo $msg['type']; ?> update</span>
                                <?php if ($isClickable): ?>
                                    <span>&bull;</span>
                                    <span style="color: #3ea6ff; font-weight: 600;">Action Required</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
