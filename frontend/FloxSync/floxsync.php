<?php
session_start();
require_once __DIR__ . '/../../backend/config.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../loginb.php');
    exit;
}

// Fetch current user details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$currentUser = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch linked accounts
$stmt = $pdo->prepare("SELECT * FROM floxsync_accounts WHERE user_id = ? AND is_verified = 1 ORDER BY created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$linkedAccounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch unread mailbox messages count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM floxsync_mailbox WHERE user_id = ? AND is_read = 0");
try {
    $stmt->execute([$_SESSION['user_id']]);
    $unreadCount = $stmt->fetchColumn();
} catch (PDOException $e) {
    // If table doesn't exist, count is 0
    $unreadCount = 0;
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FloxSync - Account Management</title>
    <meta name="description" content="Manage your FloxSync identity, linked accounts, and synced apps in one secure, unified hub.">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../home.css">
    <link rel="stylesheet" href="../layout.css">
    <style>
        body {
            background: linear-gradient(135deg, #0a0a0a 0%, #1a1a1a 100%);
            min-height: 100vh;
        }

        .floxsync-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            animation: fadeIn 0.6s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        
        .floxsync-header {
            text-align: center;
            margin-bottom: 50px;
            position: relative;
        }
        
        .floxsync-logo {
            width: 72px;
            height: 72px;
            color: #3ea6ff;
            margin-bottom: 20px;
            /* Serious mode: removed drop-shadow glow */
        }
        
        .floxsync-title {
            font-size: 36px;
            font-weight: 800;
            margin-bottom: 10px;
            color: #fff; /* Removed gradient text for seriousness */
            letter-spacing: -1px;
        }
        
        .floxsync-subtitle {
            color: #888;
            font-size: 18px;
            font-weight: 400;
        }

        .accounts-card {
            background: rgba(20, 20, 20, 0.6);
            backdrop-filter: blur(12px);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            /* overflow: hidden; Removed to allow dropdown overlay */
            margin-bottom: 30px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            transition: transform 0.3s;
        }
        
        .accounts-card:hover {
            transform: translateY(-2px);
            border-color: rgba(255, 255, 255, 0.12);
        }

        .card-header {
            padding: 24px 30px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
            background: rgba(255, 255, 255, 0.02);
            border-radius: 20px 20px 0 0;
        }

        .card-title {
            font-size: 20px;
            font-weight: 700;
            color: #dfdfdf;
        }

        .accounts-list {
            padding: 0;
        }

        .account-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 24px 30px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.04);
            transition: background 0.2s;
        }

        .account-item:last-child {
            border-bottom: none;
        }

        .account-item:hover {
            background: rgba(255, 255, 255, 0.03);
        }

        .account-item.clickable {
            cursor: pointer;
        }

        .account-item.clickable:hover {
            background: rgba(255, 255, 255, 0.08);
            transform: scale(1.01);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            border-color: rgba(255,255,255,0.1);
            z-index: 10;
        }

        .account-info {
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .account-avatar {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            background: #2a2a2a;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: 600;
            color: #fff;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            border: 2px solid rgba(255,255,255,0.05);
        }
        
        .account-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .account-details h4 {
            margin: 0 0 4px 0;
            font-size: 17px;
            font-weight: 600;
        }

        .account-details p {
            margin: 0;
            color: #999;
            font-size: 14px;
        }

        .account-badge {
            background: rgba(62, 166, 255, 0.15);
            color: #3ea6ff;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            margin-left: 10px;
            letter-spacing: 0.5px;
            /* Serious mode: removed box-shadow */
        }
        
        .active-badge {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.05));
            color: #fff;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: none;
        }

        .actions-row {
            padding: 24px 30px;
            display: flex;
            justify-content: center;
            background: rgba(0,0,0,0.1);
        }

        .add-account-btn {
            background: linear-gradient(135deg, #3ea6ff 0%, #0078d4 100%);
            color: #fff;
            border: none;
            padding: 14px 28px;
            border-radius: 30px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 15px;
            transition: all 0.3s;
            /* Serious mode: removed shadow */
        }

        .add-account-btn:hover {
            transform: translateY(-2px);
            background: linear-gradient(135deg, #4eb0ff 0%, #1084e0 100%);
        }
        
        .add-account-btn:active {
            transform: translateY(0);
        }

        /* FloxSync Modal Styles */
        .fs-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            z-index: 1000;
            backdrop-filter: blur(12px);
            align-items: center;
            justify-content: center;
        }
        
        .fs-modal.active {
            display: flex !important;
        }

        .fs-modal-content {
            background: linear-gradient(145deg, #161616, #111);
            border-radius: 28px;
            width: 100%;
            max-width: 480px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            position: relative;
            animation: fsSlideUp 0.4s cubic-bezier(0.19, 1, 0.22, 1);
            overflow: hidden;
            box-shadow: 0 40px 80px -20px rgba(0, 0, 0, 0.6);
        }

        @keyframes fsSlideUp {
            from { opacity: 0; transform: translateY(40px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .fs-modal-header {
            padding: 28px 36px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            z-index: 2;
        }

        .fs-modal-title {
            font-size: 20px;
            font-weight: 700;
            color: #fff;
            letter-spacing: -0.5px;
        }

        .fs-close-btn {
            background: rgba(255, 255, 255, 0.05);
            border: none;
            color: #aaa;
            cursor: pointer;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        
        .fs-close-btn:hover {
            background: rgba(255, 255, 255, 0.15);
            color: #fff;
            transform: rotate(90deg);
        }

        .fs-modal-body {
            padding: 0;
            overflow: hidden;
        }

        .fs-slide-container {
            display: flex;
            width: 400%;
            transition: transform 0.5s cubic-bezier(0.23, 1, 0.32, 1);
        }

        .fs-step {
            width: 25%;
            padding: 10px 45px 50px 45px;
            box-sizing: border-box;
            flex-shrink: 0;
            opacity: 0;
            transition: opacity 0.4s;
            pointer-events: none;
        }
        
        .fs-step.active {
            opacity: 1;
            pointer-events: auto;
        }

        .fs-step-title {
            font-size: 30px;
            font-weight: 700;
            margin: 0 0 30px 0;
            background: linear-gradient(to right, #fff, #999);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: -1px;
        }

        /* Custom Inputs */
        .form-group {
            margin-bottom: 24px;
        }

        .fs-input {
            width: 100%;
            padding: 18px 20px;
            background: #1c1c1c;
            border: 2px solid transparent;
            border-radius: 16px;
            color: #fff;
            font-size: 16px;
            font-family: inherit;
            transition: all 0.25s;
            outline: none;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.2);
        }

        .fs-input:focus {
            background: #222;
            border-color: #3ea6ff;
            /* Serious mode: removed glow shadow */
            transform: translateY(-1px);
        }
        
        .fs-input::placeholder {
            color: #555;
            font-weight: 500;
        }

        /* Custom Date Selectors */
        .fs-date-selector {
            display: flex;
            gap: 14px;
            margin-bottom: 30px;
        }

        .fs-select-wrapper {
            position: relative;
            flex: 1;
        }
        
        .fs-select-wrapper::after {
            content: '\f107';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: #fff;
            pointer-events: none;
            font-size: 12px;
            opacity: 0.7;
        }

        .fs-select {
            width: 100%;
            appearance: none;
            -webkit-appearance: none;
            background: #1c1c1c;
            border: 2px solid transparent;
            color: #fff;
            padding: 18px 20px;
            border-radius: 16px;
            font-size: 16px;
            font-family: inherit;
            cursor: pointer;
            outline: none;
            transition: all 0.25s;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.2);
        }

        .fs-select:focus {
            background: #222;
            border-color: #3ea6ff;
            /* Serious mode: removed glow shadow */
        }

        /* Step Buttons */
        .fs-btn-group {
            display: flex;
            gap: 15px;
            margin-top: 40px;
        }

        .fs-btn-next, .fs-btn-finish {
            flex: 2;
            padding: 18px;
            background: #fff;
            color: #000;
            border: none;
            border-radius: 40px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 5px 15px rgba(255, 255, 255, 0.1);
        }
        
        .fs-btn-next:hover, .fs-btn-finish:hover {
            transform: scale(1.03);
            background: #f0f0f0;
            box-shadow: 0 8px 20px rgba(255, 255, 255, 0.15);
        }

        .fs-btn-back {
            flex: 1;
            padding: 18px;
            background: rgba(255, 255, 255, 0.05);
            color: #fff;
            border: none;
            border-radius: 40px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }

        .fs-btn-back:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        /* Synced Apps Grid */
        .apps-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
            gap: 25px;
            padding: 10px 0;
        }

        .app-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .app-item:hover {
            transform: translateY(-5px);
        }

        .app-icon {
            width: 64px;
            height: 64px;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.4);
            font-size: 26px;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .app-icon::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(255,255,255,0.1), transparent);
        }
        
        .app-icon img, .app-icon svg {
            width: 36px;
            height: 36px;
            fill: #fff;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));
            z-index: 1;
        }
        
        .app-name {
            font-size: 14px;
            color: #ccc;
            font-weight: 500;
            text-align: center;
        }
        
        /* Error State */
        .fs-input.error, .fs-select.error {
            border-color: #ff4444;
            animation: shake 0.4s ease-in-out;
            box-shadow: 0 0 0 4px rgba(255, 68, 68, 0.15);
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-6px); }
            75% { transform: translateX(6px); }
        }

        /* Dropdown & Modes */
        .add-account-dropdown {
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%) translateY(10px);
            background: #1a1a1a;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 8px;
            min-width: 240px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.6);
            opacity: 0;
            visibility: hidden;
            transition: all 0.2s;
            z-index: 1000;
        }

        .add-account-dropdown.active {
            opacity: 1;
            visibility: visible;
            transform: translateX(-50%) translateY(15px);
        }

        .add-account-option {
            padding: 14px 16px;
            color: #ccc;
            border-radius: 12px;
            cursor: pointer;
            transition: background 0.2s;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            font-weight: 500;
        }

        .add-account-option:hover {
            background: rgba(255, 255, 255, 0.08);
            color: #fff;
        }

        .add-account-option i {
            width: 20px;
            text-align: center;
            color: #3ea6ff;
            font-size: 16px;
        }
        
        .fs-mode-section {
            display: none;
            width: 100%;
        }
        
        .fs-mode-section.active {
            display: block;
        }
        .manage-account-btn {
            background: transparent;
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #fff;
            padding: 10px 24px;
            border-radius: 30px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 15px;
        }
        .manage-account-btn:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: #fff;
        }
        
        .active-user-header {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 15px;
            margin-bottom: 40px;
        }
        
        .active-user-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #2a2a2a;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            font-weight: 700;
            color: #fff;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
            border: 3px solid rgba(255,255,255,0.1);
        }
        
        .active-user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .active-user-welcome {
            font-size: 24px;
            font-weight: 700;
            color: #fff;
        }
        
        .active-user-email {
            color: #888;
            font-size: 15px;
        }
        .unread-badge {
            padding: 4px 10px;
            background: #3ea6ff;
            color: #fff;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }
        
        .mailbox-card {
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .mailbox-card:hover {
            background: rgba(62, 166, 255, 0.05);
            border-color: rgba(62, 166, 255, 0.3);
        }
    </style>
</head>
<body>


    <div class="floxsync-container">


        <!-- Header: Active User Display -->
        <div class="active-user-header">
            <div class="active-user-avatar">
                <?php if (!empty($currentUser['profile_picture'])): ?>
                    <?php 
                        $pic = $currentUser['profile_picture'];
                        // Handle various path formats stored in DB
                        if (strpos($pic, 'http') === 0) {
                            // Already a full URL, use as-is
                        } elseif (strpos($pic, 'uploads/') === 0) {
                            // Path like 'uploads/...' - go up two levels from FloxSync
                            $pic = '../../' . $pic;
                        } elseif (strpos($pic, './uploads/') === 0) {
                            // Path like './uploads/...'
                            $pic = '../../' . substr($pic, 2);
                        } elseif (strpos($pic, '../') === 0) {
                            // Already relative, add one more level
                            $pic = '../' . $pic;
                        } else {
                            // Default: go up two levels
                            $pic = '../../' . ltrim($pic, './');
                        }
                    ?>
                    <img src="<?php echo htmlspecialchars($pic); ?>" alt="Avatar">
                <?php else: ?>
                    <?php echo strtoupper(substr($currentUser['username'], 0, 1)); ?>
                <?php endif; ?>
            </div>
            <div style="text-align: center;">
                <div class="active-user-welcome">Welcome, <?php echo htmlspecialchars($currentUser['username']); ?></div>
                <div class="active-user-email"><?php echo htmlspecialchars($currentUser['email']); ?></div>
            </div>
            <button class="manage-account-btn" onclick="window.location.href='manage_floxsync.php'">Manage your FloxSync account</button>
        </div>

        <div class="accounts-card" style="position: relative; z-index: 100;">
            <div class="card-header">
                <div class="card-title">Connected Accounts</div>
            </div>
            <div class="accounts-list">
                <!-- Current Account -->
                <div class="account-item" id="currentAccountItem">
                    <div class="account-info">
                        <div class="account-avatar" id="currentAvatar">
                            <?php if (!empty($currentUser['profile_picture'])): ?>
                                <?php 
                                    $pic2 = $currentUser['profile_picture'];
                                    if (strpos($pic2, 'http') === 0) {
                                        // Already a full URL
                                    } elseif (strpos($pic2, 'uploads/') === 0) {
                                        $pic2 = '../../' . $pic2;
                                    } elseif (strpos($pic2, './uploads/') === 0) {
                                        $pic2 = '../../' . substr($pic2, 2);
                                    } elseif (strpos($pic2, '../') === 0) {
                                        $pic2 = '../' . $pic2;
                                    } else {
                                        $pic2 = '../../' . ltrim($pic2, './');
                                    }
                                ?>
                                <img src="<?php echo htmlspecialchars($pic2); ?>" alt="Avatar">
                            <?php else: ?>
                                <?php echo strtoupper(substr($currentUser['username'], 0, 1)); ?>
                            <?php endif; ?>
                        </div>
                        <div class="account-details">
                            <h4 id="currentUsername"><?php echo htmlspecialchars($currentUser['username']); ?></h4>
                            <p id="currentEmail"><?php echo htmlspecialchars($currentUser['email']); ?></p>
                        </div>
                    </div>
                    <span class="account-badge active-badge">Synced</span>
                </div>

                <!-- Linked Accounts -->
                <div id="linkedAccountsList">
                    <?php foreach ($linkedAccounts as $acc): ?>
                    <div class="account-item clickable" onclick="switchAccount(<?php echo $acc['id']; ?>)">
                        <div class="account-info">
                            <div class="account-avatar" style="background: #444;">
                                <?php 
                                    $displayName = trim($acc['first_name'] . ' ' . $acc['last_name']);
                                    if(empty($displayName)) $displayName = $acc['email'];
                                    echo strtoupper(substr($displayName, 0, 1)); 
                                ?>
                            </div>
                            <div class="account-details">
                                <h4><?php echo htmlspecialchars($displayName); ?></h4>
                                <p><?php echo htmlspecialchars($acc['email']); ?></p>
                            </div>
                        </div>
                        <span class="account-badge active-badge">Synced</span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="actions-row">
                <div style="position: relative;">
                    <button class="add-account-btn" id="addAccountBtn" type="button" 
                            onclick="toggleAddDropdown()"
                            style="z-index: 999; position: relative; cursor: pointer;">
                        <i class="fa-solid fa-plus"></i>
                        <span>Add Account</span>
                    </button>
                    <div class="add-account-dropdown" id="addDropdown">
                         <div class="add-account-option" onclick="openCreateMode()">
                             <i class="fa-solid fa-user-plus"></i>
                             <span>New FloxSync Account</span>
                         </div>
                         <div class="add-account-option" onclick="openLinkMode()">
                             <i class="fa-solid fa-link"></i>
                             <span>Link Existing Account</span>
                         </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="accounts-card mailbox-card" onclick="window.location.href='mailbox.php'">
            <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                <div class="card-title">
                    <i class="fa-solid fa-inbox" style="margin-right: 10px; color: #3ea6ff;"></i>
                    Mailbox
                </div>
                <?php if ($unreadCount > 0): ?>
                    <span class="unread-badge"><?php echo $unreadCount; ?> New</span>
                <?php endif; ?>
            </div>
            <div class="card-body" style="padding: 24px;">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <div style="width: 40px; height: 40px; background: rgba(62, 166, 255, 0.1); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #3ea6ff;">
                        <i class="fa-solid fa-shield-halved"></i>
                    </div>
                    <div>
                        <h4 style="margin: 0; font-size: 15px; color: #fff;">Security Updates</h4>
                        <p style="margin: 5px 0 0; font-size: 13px; color: #888;">View latest security alerts and account activity.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="accounts-card">
            <div class="card-header">
                <div class="card-title">Synced Apps</div>
            </div>
            <div class="card-body" style="padding: 24px;">
                <p style="color: #aaa; margin-bottom: 20px;">These apps are currently using your FloxSync identity.</p>
                <div class="apps-grid">

                </div>
            </div>
        </div>
    </div>

    <!-- Add Account Modal -->
    <div class="fs-modal" id="accountModal">
        <div class="fs-modal-content">
            <div class="fs-modal-header">
                <div class="fs-modal-title" id="modalTitle">Use Another Account</div>
                <button class="fs-close-btn" id="closeModal">&times;</button>
            </div>
            <div class="fs-modal-body">
                
                <!-- MODE 1: Create New (Sliding Steps) -->
                <div class="fs-mode-section" id="wrapperCreate">
                    <div class="fs-slide-container" id="fsSlideContainer">
                        <!-- Step 1: Name -->
                        <div class="fs-step active" id="step1">
                            <h3 class="fs-step-title">What's your name?</h3>
                            <div class="form-group">
                                <input type="text" class="fs-input" placeholder="First Name" id="firstName">
                            </div>
                            <div class="form-group">
                                <input type="text" class="fs-input" placeholder="Last Name" id="lastName">
                            </div>
                            <div class="fs-btn-group">
                                 <button class="fs-btn-next" onclick="nextStep(2)">Next <i class="fa-solid fa-arrow-right"></i></button>
                            </div>
                            <!-- Toggle Back to Link -->
                             <p class="fs-toggle-link">
                                Already have an account? <span onclick="openLinkMode()">Link existing</span>
                            </p>
                        </div>

                        <!-- Step 2: Birthday -->
                        <div class="fs-step" id="step2">
                             <h3 class="fs-step-title">When is your birthday?</h3>
                             <div class="fs-date-selector">
                                <div class="fs-select-wrapper">
                                    <select id="birthMonth" class="fs-select">
                                        <option value="" disabled selected>Month</option>
                                        <option value="1">January</option>
                                        <option value="2">February</option>
                                        <option value="3">March</option>
                                        <option value="4">April</option>
                                        <option value="5">May</option>
                                        <option value="6">June</option>
                                        <option value="7">July</option>
                                        <option value="8">August</option>
                                        <option value="9">September</option>
                                        <option value="10">October</option>
                                        <option value="11">November</option>
                                        <option value="12">December</option>
                                    </select>
                                </div>
                                <div class="fs-select-wrapper">
                                    <select id="birthDay" class="fs-select">
                                        <option value="" disabled selected>Day</option>
                                        <!-- JS will populate -->
                                    </select>
                                </div>
                                 <div class="fs-select-wrapper">
                                    <select id="birthYear" class="fs-select">
                                        <option value="" disabled selected>Year</option>
                                         <!-- JS will populate -->
                                    </select>
                                </div>
                             </div>
                             <div class="fs-btn-group">
                                <button class="fs-btn-back" type="button" onclick="prevStep(1)">Back</button>
                                <button class="fs-btn-next" onclick="nextStep(3)">Next <i class="fa-solid fa-arrow-right"></i></button>
                             </div>
                        </div>

                        <!-- Step 3: Credentials -->
                        <div class="fs-step" id="step3">
                            <h3 class="fs-step-title">Create your account</h3>
                             <div class="form-group">
                                <input type="email" class="fs-input" placeholder="Email Address" id="email">
                            </div>
                            <div class="form-group">
                                <input type="password" class="fs-input" placeholder="Password" id="password">
                            </div>
                             <div class="fs-btn-group">
                                <button class="fs-btn-back" type="button" onclick="prevStep(2)">Back</button>
                                <button class="fs-btn-finish" type="button" onclick="submitCreate()">Create Account</button>
                             </div>
                        </div>

                        <!-- Step 4: Verification (Hidden initially) -->
                        <div class="fs-step" id="step4">
                            <h3 class="fs-step-title">Verify Email</h3>
                            <p style="text-align:center; color:#888; margin-bottom: 20px;">We sent a 6-digit code to <br><span id="verifyEmailDisplay" style="color:#fff;"></span></p>
                            <div class="form-group">
                                <input type="text" class="fs-input" placeholder="Enter 6-digit Code" id="verifyCode" maxlength="6" style="text-align: center; letter-spacing: 5px; font-size: 20px;">
                            </div>
                            <!-- Hidden temp ID -->
                            <input type="hidden" id="tempAccountId">
                             <div class="fs-btn-group">
                                <button class="fs-btn-finish" type="button" onclick="submitVerification()">Verify & Create</button>
                             </div>
                             <p class="fs-toggle-link" style="margin-top: 20px;">
                                Didn't receive it? <span>Resend</span>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- MODE 2: Link Existing -->
                <div class="fs-mode-section" id="wrapperLink">
                    <div style="padding: 10px 45px 50px 45px;">
                        <h3 class="fs-step-title">Link your account</h3>
                        <div class="form-group">
                            <input type="email" class="fs-input" placeholder="Email Address" id="linkEmail">
                        </div>
                        <div class="form-group">
                            <input type="password" class="fs-input" placeholder="Password" id="linkPassword">
                        </div>
                         <div class="fs-btn-group">
                            <button class="fs-btn-finish" type="button" onclick="submitLink()">Link Account</button>
                         </div>
                         <!-- Toggle to Create -->
                         <p class="fs-toggle-link">
                            Don't have an account? <span onclick="openCreateMode()">Create New Account</span>
                         </p>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script>
        // Modal & Dropdown Logic
        const modal = document.getElementById('accountModal');
        const closeModalBtn = document.getElementById('closeModal');
        const slideContainer = document.getElementById('fsSlideContainer');
        const steps = document.querySelectorAll('.fs-step');

        // Toggle Add Dropdown
        function toggleAddDropdown() {
            document.getElementById('addDropdown').classList.toggle('active');
        }

        // Close dropdown when clicking outside
        window.addEventListener('click', function(e) {
            if (!e.target.closest('#addAccountBtn') && !e.target.closest('#addDropdown')) {
                document.getElementById('addDropdown').classList.remove('active');
            }
        });

        // Close Modal Logic
        closeModalBtn.addEventListener('click', () => {
            modal.classList.remove('active');
        });
        
        window.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.classList.remove('active');
            }
        });

        // Open Modal in Create Mode
        function openCreateMode() {
            document.getElementById('addDropdown').classList.remove('active');
            modal.classList.add('active');
            document.getElementById('modalTitle').innerText = 'Create New Identity';
            
            // Show Create, Hide Link
            document.getElementById('wrapperCreate').classList.add('active');
            document.getElementById('wrapperLink').classList.remove('active');
            
            // Reset sliding steps
            currentStep = 1;
            updateSlide();
            resetInputs('#wrapperCreate');
            
            // Ensure step 4 is hidden/reset if needed in future
        }

        // Open Modal in Link Mode
        function openLinkMode() {
            document.getElementById('addDropdown').classList.remove('active');
            modal.classList.add('active');
            document.getElementById('modalTitle').innerText = 'Link Existing Account';
            
            // Show Link, Hide Create
            document.getElementById('wrapperLink').classList.add('active');
            document.getElementById('wrapperCreate').classList.remove('active');
            
            resetInputs('#wrapperLink');
        }

        function resetInputs(selector) {
            document.querySelectorAll(selector + ' input').forEach(i => {
                i.value = '';
                i.classList.remove('error');
            });
        }

         // Remove error class on input
         document.querySelectorAll('.fs-input, .fs-select').forEach(el => {
            el.addEventListener('input', () => {
                el.classList.remove('error');
            });
        });

        // Sliding Logic for Create Mode
        let currentStep = 1;

        function nextStep(step) {
            let valid = true;
            
            if(step === 2) {
                 const fname = document.getElementById('firstName');
                 const lname = document.getElementById('lastName');
                 
                 if(!fname.value.trim()) { fname.classList.add('error'); valid = false; }
                 if(!lname.value.trim()) { lname.classList.add('error'); valid = false; }
            }
            
             if(step === 3) {
                 const month = document.getElementById('birthMonth');
                 const day = document.getElementById('birthDay');
                 const year = document.getElementById('birthYear');
                 
                 if(!month.value) { month.classList.add('error'); valid = false; }
                 if(!day.value) { day.classList.add('error'); valid = false; }
                 if(!year.value) { year.classList.add('error'); valid = false; }
            }

            if (!valid) return;

            currentStep = step;
            updateSlide();
        }

        function prevStep(step) {
            currentStep = step;
            updateSlide();
        }

        function updateSlide() {
            if(slideContainer) {
                // We have 4 steps now (Name, Birthday, Credentials, Verification)
                // Container is 400% width, each step is 25% width.
                const translateX = -(currentStep - 1) * 25; 
                
                slideContainer.style.transform = `translateX(${translateX}%)`;
                steps.forEach((s, index) => {
                    if (index + 1 === currentStep) {
                        s.classList.add('active');
                    } else {
                        s.classList.remove('active');
                    }
                });
            }
        }

        // SUBMIT CREATE (New Account)
        function submitCreate() {
            const fname = document.getElementById('firstName');
            const lname = document.getElementById('lastName');
            const bMonth = document.getElementById('birthMonth');
            const bDay = document.getElementById('birthDay');
            const bYear = document.getElementById('birthYear');
            const email = document.getElementById('email');
            const password = document.getElementById('password');
            
            let valid = true;
            if(!fname.value.trim()) { fname.classList.add('error'); valid = false; }
            if(!lname.value.trim()) { lname.classList.add('error'); valid = false; }
            if(!email.value.trim()) { email.classList.add('error'); valid = false; }
            if(!password.value) { password.classList.add('error'); valid = false; }
            
            if(!valid) return;

            const btn = document.querySelector('#wrapperCreate .fs-btn-finish');
            const dob = `${bYear.value}-${bMonth.value.padStart(2,'0')}-${bDay.value.padStart(2,'0')}`;
            
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Creating...';
            btn.disabled = true;

            fetch('../backend/link_floxsync.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    first_name: fname.value,
                    last_name: lname.value,
                    dob: dob,
                    email: email.value,
                    password: password.value
                })
            })
            .then(res => res.json())
            .then(data => {
                btn.innerHTML = 'Create Account';
                btn.disabled = false;

                if(data.success) {
                    if (data.verification_required) {
                        // Go to Step 4
                        document.getElementById('verifyEmailDisplay').innerText = data.email;
                        document.getElementById('tempAccountId').value = data.temp_account_id;
                        currentStep = 4;
                        updateSlide();
                    } else {
                        // Direct success (shouldn't happen with new logic but safe fallback)
                        window.location.href = 'floxsync.php';
                    }
                } else {
                    }
                }
            })
            .catch(err => {
                console.error(err);
                btn.innerHTML = 'Create Account';
                btn.disabled = false;
            });
        }
        
        function submitVerification() {
            const code = document.getElementById('verifyCode');
            const tempId = document.getElementById('tempAccountId').value;
            const btn = document.querySelector('#step4 .fs-btn-finish');

            if (code.value.length < 6) {
                return;
            }

            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Verifying...';
            btn.disabled = true;

            fetch('../backend/verify_floxsync_email.php', {
                 method: 'POST',
                 headers: { 'Content-Type': 'application/json' },
                 body: JSON.stringify({
                     code: code.value,
                     temp_account_id: tempId
                 })
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    window.location.href = 'floxsync.php';
                } else {
                    btn.innerHTML = 'Verify & Create';
                    btn.disabled = false;
                }
            })
            .catch(err => {
                 btn.innerHTML = 'Verify & Create';
                 btn.disabled = false;
            });
        }

        // SUBMIT LINK (Existing Account)
        function submitLink() {
            const email = document.getElementById('linkEmail');
            const password = document.getElementById('linkPassword');
            const btn = document.querySelector('#wrapperLink .fs-btn-finish');

            let valid = true;
            if(!email.value.trim()) { email.classList.add('error'); valid = false; }
            if(!password.value) { password.classList.add('error'); valid = false; }

            if(!valid) return;

            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Linking...';
            btn.disabled = true;

            fetch('../backend/link_existing.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    email: email.value,
                    password: password.value
                })
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    window.location.href = 'floxsync.php';
                } else {
                    btn.innerHTML = 'Link Account';
                    btn.disabled = false;
                }
            })
            .catch(err => {
                console.error(err);
                btn.innerHTML = 'Failed';
                btn.disabled = false;
            });
        }

        // Populate Dates
        const daySelect = document.getElementById('birthDay');
        if(daySelect) {
            for(let i=1; i<=31; i++) {
                let opt = document.createElement('option');
                opt.value = i;
                opt.innerHTML = i;
                daySelect.appendChild(opt);
            }
        }

        const yearSelect = document.getElementById('birthYear');
        if(yearSelect) {
            const currentYear = new Date().getFullYear();
            for(let i=currentYear; i>=1900; i--) {
                let opt = document.createElement('option');
                opt.value = i;
                opt.innerHTML = i;
                yearSelect.appendChild(opt);
            }
        }

        function switchAccount(accountId) {
             // Visual feedback
             const container = document.querySelector('.accounts-card');
             container.style.opacity = '0.5';
             container.style.pointerEvents = 'none';

             fetch('../backend/switch_account.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ account_id: accountId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('SUCCESS');
                    // Update DOM without reload
                    updateUI(data.currentUser, data.linkedAccounts);
                    container.style.opacity = '1';
                    container.style.pointerEvents = 'auto';
                } else {
                    console.error('Switch failed:', data.error);
                    container.style.opacity = '1';
                    container.style.pointerEvents = 'auto';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                container.style.opacity = '1';
                container.style.pointerEvents = 'auto';
            });
        }

        function updateUI(user, accounts) {
            // Update Current User
            document.getElementById('currentUsername').innerText = user.username;
            document.getElementById('currentEmail').innerText = user.email;
            
            const avatarDiv = document.getElementById('currentAvatar');
            if (user.profile_picture) {
                avatarDiv.innerHTML = `<img src="${user.profile_picture}" alt="Avatar">`;
            } else {
                avatarDiv.innerHTML = user.username.charAt(0).toUpperCase();
            }

            // Update Header Display (NEW)
            const headerAvatar = document.querySelector('.active-user-avatar');
             if (user.profile_picture) {
                headerAvatar.innerHTML = `<img src="${user.profile_picture}" alt="Avatar">`;
            } else {
                headerAvatar.innerHTML = user.username.charAt(0).toUpperCase();
            }
            document.querySelector('.active-user-welcome').innerText = 'Welcome, ' + user.username;
            document.querySelector('.active-user-email').innerText = user.email;

            // Update Linked Accounts List
            const listDiv = document.getElementById('linkedAccountsList');
            if(listDiv) {
                listDiv.innerHTML = ''; // Clear current list

                accounts.forEach(acc => {
                    const item = document.createElement('div');
                    item.className = 'account-item clickable';
                    item.onclick = function() { switchAccount(acc.id); };
                    item.style.animation = 'fadeIn 0.4s ease-out';
                    
                    const displayName = (acc.first_name + ' ' + acc.last_name).trim() || acc.email;
                    const initials = (displayName.charAt(0) || '?').toUpperCase();

                    item.innerHTML = `
                        <div class="account-info">
                            <div class="account-avatar" style="background: #444;">
                                ${initials}
                            </div>
                            <div class="account-details">
                                <h4>${escapeHtml(displayName)}</h4>
                                <p>${escapeHtml(acc.email)}</p>
                            </div>
                        </div>
                        <span class="account-badge active-badge">Synced</span>
                    `;
                    listDiv.appendChild(item);
                });
            }
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.innerText = text;
            return div.innerHTML;
        }
    </script>
</body>
</html>
