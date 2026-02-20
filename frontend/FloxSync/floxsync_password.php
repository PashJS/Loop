<?php
session_start();
require_once '../../backend/config.php';

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
    <title>Change Password - FloxSync</title>
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

        .form-group {
            margin-bottom: 0;
        }

        .form-full {
            grid-column: 1 / -1;
            width: 100%;
        }

        .manage-label {
            display: block;
            color: #aaa;
            font-size: 13px;
            margin-bottom: 8px;
            font-weight: 500;
        }

        .manage-input {
            width: 100%;
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 12px 16px;
            color: #fff;
            font-size: 15px;
            transition: all 0.2s;
        }

        .manage-input:focus {
            outline: none;
            border-color: #3ea6ff;
            background: rgba(0, 0, 0, 0.4);
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
            margin-top: 10px;
        }

        .manage-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(62, 166, 255, 0.3);
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
        /* Dedicated FloxSync Branding */
    </style>
</head>
<body style="background: #0f0f0f; opacity: 1; animation: none;">


    <div class="breadcrumb" style="margin: 30px auto; max-width: 800px; padding: 0 20px;">
        <a href="floxsync.php">FloxSync</a>
        <span>&gt;</span>
        <a href="manage_floxsync.php">Account Management</a>
        <span>&gt;</span>
        <a href="floxsync_password.php" class="current">FloxSync Password</a>
    </div>

    <div class="manage-container">
        <div class="manage-header">
            <h1>Change Password</h1>
            <p>Update your FloxSync account password</p>
        </div>

        <div class="settings-section">
            <div class="settings-section-title">
                <i class="fa-solid fa-key"></i>
                Set New Password
            </div>
            <form id="passwordForm" onsubmit="handlePasswordChange(event)">
                <div class="form-group form-full" style="margin-bottom: 15px;">
                    <label class="manage-label">New Password</label>
                    <input type="password" class="manage-input" id="newPassword" placeholder="Enter new password" required>
                </div>
                 <div class="form-group form-full" style="margin-bottom: 20px;">
                    <label class="manage-label">Confirm New Password</label>
                    <input type="password" class="manage-input" id="confirmPassword" placeholder="Confirm new password" required>
                </div>
                <button type="submit" class="manage-btn">Update Password</button>
            </form>
        </div>
    </div>

    <script>
        function handlePasswordChange(e) {
            e.preventDefault();
            const p1 = document.getElementById('newPassword').value;
            const p2 = document.getElementById('confirmPassword').value;
            const btn = e.target.querySelector('button');

            if(p1.length < 6) {
                return;
            }

            if(p1 !== p2) {
                return;
            }

            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Updating...';
            btn.disabled = true;

            fetch('../../backend/updateUser.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ password: p1 })
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    e.target.reset();
                } else {
                }
            })
            .catch(err => {
                console.error(err);
            })
            .finally(() => {
                btn.innerHTML = 'Update Password';
                btn.disabled = false;
            });
        }
    </script>
</body>
</html>
