<?php
session_start();
require_once '../../backend/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../loginb.php');
    exit;
}

$userId = $_SESSION['user_id'];

// Fetch User Data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: ../loginb.php');
    exit;
}

// Try to find extended details (Name, DOB) from floxsync_accounts
$stmtExt = $pdo->prepare("SELECT first_name, last_name, dob FROM floxsync_accounts WHERE email = ? ORDER BY id DESC LIMIT 1");
$stmtExt->execute([$user['email']]);
$extInfo = $stmtExt->fetch(PDO::FETCH_ASSOC);

$firstName = $extInfo['first_name'] ?? '';
$lastName = $extInfo['last_name'] ?? '';
$dob = $extInfo['dob'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Private Information - FloxSync</title>
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

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-group {
            margin-bottom: 0;
        }

        .form-full {
            grid-column: 1 / -1;
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

        .manage-input[readonly] {
            opacity: 0.7;
            cursor: not-allowed;
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

        .contact-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: transparent;
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #888;
            padding: 10px 18px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 15px;
        }

        .contact-btn:hover {
            border-color: rgba(255, 255, 255, 0.4);
            color: #fff;
        }

        .support-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }

        .support-modal {
            background: #1a1a1a;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            width: 90%;
            max-width: 500px;
            padding: 30px;
        }

        .support-modal h3 {
            color: #fff;
            font-size: 20px;
            margin-bottom: 10px;
        }

        .support-modal p {
            color: #888;
            font-size: 13px;
            margin-bottom: 20px;
        }

        .support-modal textarea {
            width: 100%;
            min-height: 120px;
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 12px;
            color: #fff;
            font-size: 14px;
            resize: vertical;
            margin-bottom: 20px;
        }

        .support-modal textarea:focus {
            outline: none;
            border-color: rgba(255, 255, 255, 0.3);
        }

        .support-modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .support-modal-actions button {
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-cancel {
            background: transparent;
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #888;
        }

        .btn-cancel:hover {
            border-color: rgba(255, 255, 255, 0.4);
            color: #fff;
        }

        .btn-send {
            background: #3ea6ff;
            border: none;
            color: #000;
            font-weight: 600;
        }

        .btn-send:hover {
            background: #5ab5ff;
        }

        .btn-send:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .success-msg {
            color: #2ecc71;
            font-size: 14px;
            margin-top: 15px;
            display: none;
        }
    </style>
</head>
<body style="background: #0f0f0f; opacity: 1; animation: none;">


    <div class="breadcrumb" style="margin: 30px auto; max-width: 800px; padding: 0 20px;">
        <a href="floxsync.php">FloxSync</a>
        <span>&gt;</span>
        <a href="manage_floxsync.php">Account Management</a>
        <span>&gt;</span>
        <a href="floxsync_private_information.php" class="current">Private Information</a>
    </div>

    <div class="manage-container">
        <div class="manage-header">
            <h1>Private Information</h1>
            <p>Your personal details associated with this account</p>
        </div>

        <div class="settings-section">
            <div class="settings-section-title">
                <i class="fa-solid fa-user-shield"></i>
                Private Details
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label class="manage-label">First Name</label>
                    <input type="text" class="manage-input" value="<?php echo htmlspecialchars($firstName); ?>" readonly placeholder="Not set">
                </div>
                <div class="form-group">
                    <label class="manage-label">Last Name</label>
                    <input type="text" class="manage-input" value="<?php echo htmlspecialchars($lastName); ?>" readonly placeholder="Not set">
                </div>
                <div class="form-group form-full">
                    <label class="manage-label">Email Address</label>
                    <input type="email" class="manage-input" value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                </div>
                <div class="form-group">
                    <label class="manage-label">Date of Birth</label>
                    <input type="text" class="manage-input" value="<?php echo htmlspecialchars($dob); ?>" readonly placeholder="Not set">
                </div>
            </div>
            <p style="color: #666; font-size: 12px; margin-top: 20px;">
                <i class="fa-solid fa-circle-info"></i> To update your name or date of birth, please contact support.
            </p>
            <button class="contact-btn" onclick="openSupportModal()">
                <i class="fa-solid fa-envelope"></i>
                Contact Support
            </button>
        </div>
    </div>

    <!-- Support Modal -->
    <div class="support-modal-overlay" id="supportModal">
        <div class="support-modal">
            <h3>Request Information Change</h3>
            <p>Describe the changes you'd like to make to your private information. Our team will review and verify your request.</p>
            <form id="supportForm">
                <textarea name="message" id="supportMessage" placeholder="Example: Please change my first name from John to Jonathan..." required></textarea>
                <div class="support-modal-actions">
                    <button type="button" class="btn-cancel" onclick="closeSupportModal()">Cancel</button>
                    <button type="submit" class="btn-send" id="sendBtn">Send Request</button>
                </div>
            </form>
            <div class="success-msg" id="successMsg">
                <i class="fa-solid fa-check-circle"></i> Your request has been sent. We'll get back to you soon.
            </div>
        </div>
    </div>

    <script>
        function openSupportModal() {
            document.getElementById('supportModal').style.display = 'flex';
        }

        function closeSupportModal() {
            document.getElementById('supportModal').style.display = 'none';
            document.getElementById('supportMessage').value = '';
            document.getElementById('successMsg').style.display = 'none';
            document.getElementById('supportForm').style.display = 'block';
        }

        document.getElementById('supportForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = document.getElementById('sendBtn');
            const msg = document.getElementById('supportMessage').value.trim();
            
            if (!msg) return;

            btn.disabled = true;
            btn.textContent = 'Sending...';

            try {
                const response = await fetch('../../backend/send_support_request.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ message: msg })
                });

                const data = await response.json();

                if (data.success) {
                    document.getElementById('supportForm').style.display = 'none';
                    document.getElementById('successMsg').style.display = 'block';
                    setTimeout(closeSupportModal, 3000);
                } else {
                }
            } catch (err) {
            }

            btn.disabled = false;
            btn.textContent = 'Send Request';
        });

        // Close on overlay click
        document.getElementById('supportModal').addEventListener('click', function(e) {
            if (e.target === this) closeSupportModal();
        });
    </script>
</body>
</html>

