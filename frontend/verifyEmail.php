<?php
session_start();
$token = isset($_GET['token']) ? trim($_GET['token']) : '';

if (empty($token)) {
    header('Location: loginb.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loop - Verify Email</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="home.css"/>
    <style>
        .verification-container {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: calc(100vh - 56px);
            padding: 40px 20px;
        }
        .verification-card {
            background: var(--secondary-color);
            border-radius: 16px;
            padding: 40px;
            max-width: 500px;
            width: 100%;
            text-align: center;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-lg);
        }
        .verification-icon {
            font-size: 64px;
            color: var(--accent-color);
            margin-bottom: 24px;
        }
        .verification-card h1 {
            font-size: 24px;
            margin-bottom: 12px;
            color: var(--text-primary);
        }
        .verification-card p {
            color: var(--text-secondary);
            margin-bottom: 24px;
            line-height: 1.6;
        }
        .verification-status {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 24px;
        }
        .verification-status.success {
            background: rgba(76, 175, 80, 0.1);
            color: var(--success-color);
            border: 1px solid rgba(76, 175, 80, 0.3);
        }
        .verification-status.error {
            background: rgba(255, 68, 68, 0.1);
            color: var(--error-color);
            border: 1px solid rgba(255, 68, 68, 0.3);
        }
        .verification-card a {
            color: var(--accent-color);
            text-decoration: none;
            font-weight: 500;
        }
        .verification-card a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="verification-container">
        <div class="verification-card">
            <div class="verification-icon">
                <i class="fa-solid fa-envelope-circle-check"></i>
            </div>
            <h1>Verifying Your Request</h1>
            <div id="verificationStatus" class="verification-status" style="display: none;"></div>
            <p id="verificationMessage">Please wait while we verify your request...</p>
            <div id="verificationActions" style="display: none; margin-top: 24px;">
                <a href="accountmanagement.php" class="btn-submit" style="display: inline-block; text-decoration: none;">Go to Account Management</a>
            </div>
        </div>
    </div>

    <script>
        const token = '<?php echo addslashes($token); ?>';
        
        async function verifyToken() {
            try {
                const response = await fetch(`../backend/verifyEmailChange.php?token=${encodeURIComponent(token)}`);
                const data = await response.json();
                
                const statusDiv = document.getElementById('verificationStatus');
                const messageDiv = document.getElementById('verificationMessage');
                const actionsDiv = document.getElementById('verificationActions');
                
                statusDiv.style.display = 'block';
                
                if (data.success) {
                    statusDiv.className = 'verification-status success';
                    statusDiv.innerHTML = '<i class="fa-solid fa-check-circle"></i> ' + data.message;
                    messageDiv.textContent = 'Your changes have been verified and applied successfully!';
                    actionsDiv.style.display = 'block';
                    
                    if (data.requires_relogin) {
                        setTimeout(() => {
                            window.location.href = 'loginb.php';
                        }, 3000);
                    }
                } else {
                    statusDiv.className = 'verification-status error';
                    statusDiv.innerHTML = '<i class="fa-solid fa-exclamation-circle"></i> ' + (data.message || 'Verification failed');
                    messageDiv.textContent = 'The verification link is invalid or has expired.';
                    actionsDiv.innerHTML = '<a href="accountmanagement.php">Go to Account Management</a>';
                    actionsDiv.style.display = 'block';
                }
            } catch (error) {
                console.error('Error verifying token:', error);
                const statusDiv = document.getElementById('verificationStatus');
                statusDiv.style.display = 'block';
                statusDiv.className = 'verification-status error';
                statusDiv.innerHTML = '<i class="fa-solid fa-exclamation-circle"></i> Network error. Please try again.';
            }
        }
        
        verifyToken();
    </script>
</body>
</html>



