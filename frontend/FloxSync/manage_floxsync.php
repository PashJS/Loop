<?php
session_start();
require_once __DIR__ . '/../../backend/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../loginb.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage FloxSync Account</title>
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
            margin-bottom: 50px;
        }

        .manage-header h1 {
            font-size: 36px;
            font-weight: 800;
            margin-bottom: 15px;
            background: linear-gradient(135deg, #fff 0%, #aaa 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .manage-header p {
            color: #888;
            font-size: 17px;
        }

        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
        }

        .menu-card {
            background: rgba(30, 30, 30, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 24px;
            display: flex;
            align-items: center;
            gap: 20px;
            transition: background 0.2s, border-color 0.2s;
            cursor: pointer;
            text-decoration: none;
            position: relative;
        }

        .menu-card:hover {
            background: rgba(40, 40, 40, 0.6);
            border-color: rgba(255, 255, 255, 0.2);
        }

        .menu-icon {
            width: 48px;
            height: 48px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: #ccc;
            flex-shrink: 0;
            transition: color 0.2s;
        }

        .menu-card:hover .menu-icon {
            color: #fff;
        }

        .menu-content h2 {
            font-size: 16px;
            font-weight: 600;
            color: #fff;
            margin-bottom: 4px;
        }

        .menu-content p {
            font-size: 13px;
            color: #888;
            line-height: 1.4;
            margin: 0;
        }

        .menu-arrow {
            margin-left: auto;
            color: #666;
            font-size: 14px;
            transition: color 0.2s;
        }

        .menu-card:hover .menu-arrow {
            color: #fff;
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
        <a href="manage_floxsync.php" class="current">Account Management</a>
    </div>

    <div class="manage-container">
        <div class="manage-header">
            <h1>Manage your Account</h1>
            <p>Select a category to manage your settings</p>
        </div>

        <div class="menu-grid">
            <!-- 1. Private Information -->
            <a href="floxsync_private_information.php" class="menu-card">
                <div class="menu-icon">
                    <i class="fa-solid fa-user-shield"></i>
                </div>
                <div class="menu-content">
                    <h2>Private Information</h2>
                    <p>Manage your personal details, email, and identity information.</p>
                </div>
                <i class="fa-solid fa-chevron-right menu-arrow"></i>
            </a>

            <!-- 2. FloxSync Password -->
            <a href="floxsync_password.php" class="menu-card">
                <div class="menu-icon">
                    <i class="fa-solid fa-key"></i>
                </div>
                <div class="menu-content">
                    <h2>FloxSync Password</h2>
                    <p>Update your password securely to keep your account safe.</p>
                </div>
                <i class="fa-solid fa-chevron-right menu-arrow"></i>
            </a>

             <!-- 3. Security & Privacy -->
             <a href="floxsync_security.php" class="menu-card">
                <div class="menu-icon">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
                <div class="menu-content">
                    <h2>Security & Privacy</h2>
                    <p>Manage two-factor authentication and data privacy settings.</p>
                </div>
                <i class="fa-solid fa-chevron-right menu-arrow"></i>
            </a>

            <!-- 4. Account Settings -->
            <a href="floxsync_account_settings.php" class="menu-card">
                <div class="menu-icon">
                    <i class="fa-solid fa-gears"></i>
                </div>
                <div class="menu-content">
                    <h2>Account Settings</h2>
                    <p>Manage account deletion, status, and advanced preferences.</p>
                </div>
                <i class="fa-solid fa-chevron-right menu-arrow"></i>
            </a>

            <!-- 5. Mailbox -->
            <a href="mailbox.php" class="menu-card">
                <div class="menu-icon" style="color: #3ea6ff;">
                    <i class="fa-solid fa-inbox"></i>
                </div>
                <div class="menu-content">
                    <h2>Mailbox</h2>
                    <p>Access your security updates and important account alerts.</p>
                </div>
                <i class="fa-solid fa-chevron-right menu-arrow"></i>
            </a>
        </div>
    </div>
</body>
</html>
