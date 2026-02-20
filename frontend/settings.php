<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: loginb.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Account - Loop</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="home.css?v=3">
    <link rel="stylesheet" href="layout.css?v=3">
    <link rel="stylesheet" href="settings_layout.css">
    <style>
        .info-group {
            background: var(--tertiary-color);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            margin-bottom: 24px;
            overflow: hidden;
        }
        .info-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 20px;
            border-bottom: 1px solid var(--border-color);
        }
        .info-row:last-child { border-bottom: none; }
        .info-label { color: var(--text-secondary); font-size: 14px; font-weight: 500; }
        .info-value { color: var(--text-primary); font-size: 15px; font-weight: 500; }
        
        .account-list { display: flex; flex-direction: column; gap: 8px; }
        .account-item {
            display: flex; align-items: center; justify-content: space-between;
            padding: 12px 16px; border-radius: 8px;
            background: var(--tertiary-color); border: 1px solid var(--border-color);
            transition: .2s; cursor: pointer;
        }
        .account-item:hover { background: var(--hover-bg); border-color: var(--accent-color); }
        .account-item.active { border-color: var(--accent-color); background: rgba(var(--accent-rgb), 0.05); cursor: default; }
        .acct-left { display: flex; align-items: center; gap: 12px; }
        .acct-avatar { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; background: #333; display: flex; align-items: center; justify-content: center; font-size: 12px; color: #fff; }
        
        .add-account { color: var(--accent-color); font-weight: 500; font-size: 14px; padding: 12px 0; display: inline-flex; align-items: center; gap: 8px; cursor: pointer; text-decoration: none; }
        .sign-out-link { display: inline-block; margin-top: 24px; color: #ef4444; text-decoration: none; font-size: 14px; font-weight: 500; opacity: 0.8; transition: .2s; }
        .sign-out-link:hover { opacity: 1; text-decoration: underline; }
        h2.section-label { font-size: 13px; text-transform: uppercase; letter-spacing: 1px; color: var(--text-secondary); margin: 0 0 12px 4px; font-weight: 600; }
        
        .switching-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); z-index: 9999; display: none; align-items: center; justify-content: center; flex-direction: column; gap: 16px; }
        .switching-overlay.active { display: flex; }
        .switching-spinner { width: 40px; height: 40px; border: 3px solid var(--border-color); border-top-color: var(--accent-color); border-radius: 50%; animation: spin 0.8s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    <div class="app-layout">
        <?php include 'sidebar.php'; ?>
        <main class="main-content">
            <div class="settings-wrapper">
                <?php include 'settings_sidebar.php'; ?>
                
                <div class="settings-content">
                    <div class="settings-page-header">
                        <h1>Account</h1>
                    </div>

                    <h2 class="section-label">Identity</h2>
                    <div class="info-group">
                        <div class="info-row">
                            <span class="info-label">Display Name</span>
                            <span class="info-value" id="val-displayname">...</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Username</span>
                            <span class="info-value" id="val-username">...</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Email</span>
                            <span class="info-value" id="val-email">...</span>
                        </div>
                    </div>

                    <h2 class="section-label">Accounts</h2>
                    <div id="inlineAccountList" class="account-list"></div>
                    
                    <a href="loginb.php" class="add-account">
                        <i class="fa-solid fa-plus"></i> Add Account
                    </a>

                    <div>
                        <a href="../backend/logout.php" class="sign-out-link">Sign Out</a>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Switching Overlay -->
    <div id="switchingOverlay" class="switching-overlay">
        <div class="switching-spinner"></div>
        <div style="color: #fff; font-size: 14px;">Switching account...</div>
    </div>
    
    <?php include 'mobile_footer.php'; ?>
    
    <script src="theme.js"></script>
    <script src="popup.js"></script>
    <script src="notifications.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const safeChar = (str) => str ? str.charAt(0).toUpperCase() : '?';
            let currentUserId = null;

            // Fetch current user and generate switch token
            fetch('../backend/getUser.php')
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.user) {
                        const u = data.user;
                        currentUserId = u.id;
                        
                        document.getElementById('val-displayname').textContent = u.username;
                        document.getElementById('val-username').textContent = '@' + u.username;
                        document.getElementById('val-email').textContent = u.email;
                        
                        // Generate switch token for this user
                        fetch('../backend/generate_switch_token.php')
                            .then(r => r.json())
                            .then(tokenData => {
                                if (tokenData.success) {
                                    saveAccountWithToken(u, tokenData.token);
                                }
                                renderAccountsList();
                            });
                    }
                });

            function saveAccountWithToken(user, token) {
                let saved = JSON.parse(localStorage.getItem('floxwatch_saved_accounts') || '[]');
                saved = saved.filter(a => a.id !== user.id);
                saved.unshift({
                    id: user.id,
                    username: user.username,
                    email: user.email,
                    avatar: user.profile_picture,
                    token: token
                });
                localStorage.setItem('floxwatch_saved_accounts', JSON.stringify(saved));
            }

            function renderAccountsList() {
                const list = document.getElementById('inlineAccountList');
                const accounts = JSON.parse(localStorage.getItem('floxwatch_saved_accounts') || '[]');
                
                if (accounts.length === 0) {
                    list.innerHTML = '<div style="color: var(--text-secondary); padding: 8px 0;">No saved accounts</div>';
                    return;
                }
                
                list.innerHTML = accounts.map(acc => {
                    const isCurrent = acc.id === currentUserId;
                    let av = `<div class="acct-avatar">${safeChar(acc.username)}</div>`;
                    if (acc.avatar) av = `<img src="${acc.avatar}" class="acct-avatar">`;
                    
                    return `
                        <div class="account-item ${isCurrent ? 'active' : ''}" ${!isCurrent ? `onclick="switchUser(${acc.id})"` : ''}>
                            <div class="acct-left">
                                ${av}
                                <span style="font-weight: 500; font-size: 14px;">${acc.username}</span>
                            </div>
                            ${isCurrent ? '<i class="fa-solid fa-circle-check" style="color: var(--accent-color);"></i>' : ''}
                        </div>
                    `;
                }).join('');
            }

            window.switchUser = (id) => {
                const accounts = JSON.parse(localStorage.getItem('floxwatch_saved_accounts') || '[]');
                const target = accounts.find(a => a.id == id);
                
                if (!target || !target.token) {
                    // No token, need to re-login
                    window.location.href = 'loginb.php';
                    return;
                }
                
                // Show switching overlay
                document.getElementById('switchingOverlay').classList.add('active');
                
                // 1-click switch with token
                fetch('../backend/switch_with_token.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ user_id: id, token: target.token })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        window.location.reload();
                    } else {
                        document.getElementById('switchingOverlay').classList.remove('active');
                        if (data.reauth) {
                            // Token invalid/expired, remove from saved and go to login
                            let saved = JSON.parse(localStorage.getItem('floxwatch_saved_accounts') || '[]');
                            saved = saved.filter(a => a.id != id);
                            localStorage.setItem('floxwatch_saved_accounts', JSON.stringify(saved));
                            window.location.href = 'loginb.php';
                        } else {
                        }
                    }
                })
                .catch(() => {
                    document.getElementById('switchingOverlay').classList.remove('active');
                });
            };
        });
    </script>
</body>
</html>
