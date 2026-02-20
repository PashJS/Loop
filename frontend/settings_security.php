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
    <title>Security Settings - Loop</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="home.css?v=3">
    <link rel="stylesheet" href="layout.css">
    <link rel="stylesheet" href="settings_layout.css">
    <style>
        .settings-btn { padding: 10px 20px; border-radius: 8px; font-weight: 500; cursor: pointer; border: none; display: inline-flex; align-items: center; gap: 8px; transition: .2s; text-decoration: none; font-size: 14px; }
        .settings-btn-primary { background: var(--accent-color); color: #fff; }
        .settings-btn-secondary { background: var(--tertiary-color); color: var(--text-primary); border: 1px solid var(--border-color); }
        .settings-btn-danger { background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); }
        .settings-btn:hover { transform: translateY(-1px); filter: brightness(1.1); }
        
        .security-badge { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .security-badge.enabled { background: rgba(34, 197, 94, 0.15); color: #22c55e; }
        .security-badge.disabled { background: rgba(239, 68, 68, 0.15); color: #ef4444; }

        .linked-account { display: flex; align-items: center; justify-content: space-between; padding: 16px; background: var(--tertiary-color); border-radius: 12px; border: 1px solid var(--border-color); width: 100%; margin-top: 8px; }
        .linked-account-info { display: flex; align-items: center; gap: 12px; }
        .linked-account-icon { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 20px; }
        .linked-account-icon.google { background: #fff; color: #ea4335; }
        
        .link-status-btn { padding: 6px 12px; border-radius: 6px; font-size: 13px; font-weight: 500; cursor: default; }
        .link-status-btn.linked { color: #22c55e; background: rgba(34, 197, 94, 0.1); }
        .link-status-btn.unlinked { background: var(--accent-color); color: white; cursor: pointer; }
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
                        <h1>Security</h1>
                        <p>Protect your account and manage access</p>
                    </div>

                    <!-- Security Card -->
                    <div class="settings-card">
                        <div class="settings-group-title">
                            <i class="fa-solid fa-shield-cat" style="color: var(--accent-color);"></i>
                            Security
                        </div>
                        
                        <div class="settings-row">
                            <div class="settings-info">
                                <div class="settings-label">Password</div>
                                <div class="settings-desc">Update your FloxSync password</div>
                            </div>
                            <a href="floxsync_password.php" class="settings-btn settings-btn-secondary">Change Password</a>
                        </div>

                         <div class="settings-row">
                            <div class="settings-info">
                                <div class="settings-label">Two-Factor Authentication</div>
                                <div class="settings-desc">Add an extra layer of protection</div>
                            </div>
                             <div style="display: flex; align-items: center; gap: 12px;">
                                <span class="security-badge disabled" id="badge2fa">
                                    <i class="fa-solid fa-lock-open"></i> Disabled
                                </span>
                                <button class="settings-btn settings-btn-secondary" id="setup2FABtn">Enable</button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Connections -->
                    <div class="settings-card">
                        <div class="settings-group-title">
                            <i class="fa-solid fa-link" style="color: var(--accent-color);"></i>
                            Connections
                        </div>
                         
                         <div class="settings-row" style="display: block;">
                             <div class="settings-label">Linked Accounts</div>
                             <div class="settings-desc">Log in with other services</div>
                             
                             <div class="linked-account">
                                <div class="linked-account-info">
                                    <div class="linked-account-icon google"><i class="fa-brands fa-google"></i></div>
                                    <div style="font-weight: 500;">Google</div>
                                </div>
                                <div id="googleBtnContainer">
                                    <button class="link-status-btn unlinked" onclick="location.href='../backend/google_auth.php'">Connect</button>
                                </div>
                             </div>
                         </div>
                    </div>
                    
                    <!-- Security Mailbox -->
                    <div class="settings-card">
                         <div class="settings-group-title">
                            <i class="fa-solid fa-envelope-open-text" style="color: var(--accent-color);"></i>
                            Security Mailbox
                        </div>
                        
                        <div id="mailboxList" class="mailbox-list" style="max-height: 400px; overflow-y: auto; display: flex; flex-direction: column; gap: 8px;">
                            <!-- Loaded via JS -->
                            <div style="text-align:center; padding: 20px; color: var(--text-secondary);">Loading logs...</div>
                        </div>
                    </div>

                    <!-- Danger Zone -->
                    <div class="settings-card" style="border-color: rgba(239, 68, 68, 0.3);">
                        <div class="settings-group-title" style="color: #ef4444;">
                            <i class="fa-solid fa-triangle-exclamation"></i> Danger Zone
                        </div>
                         <div class="settings-row">
                            <div class="settings-info">
                                <div class="settings-label">Sign Out Everywhere</div>
                                <div class="settings-desc">Log out of all active sessions</div>
                            </div>
                            <a href="FloxSync/security_signout.php" class="settings-btn settings-btn-danger">Sign Out All</a>
                        </div>
                    </div>

                </div>
            </div>
        </main>
    </div>
    
    <?php include 'mobile_footer.php'; ?>

    <script src="theme.js"></script>
    <script src="popup.js"></script>
    <script src="notifications.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
             // Fetch Security Status
            const badge2fa = document.getElementById('badge2fa');
            const setup2fa = document.getElementById('setup2FABtn');
            
            const update2FAUI = (enabled) => {
                if(enabled) {
                    badge2fa.className = 'security-badge enabled';
                    badge2fa.innerHTML = '<i class="fa-solid fa-lock"></i> Enabled';
                    setup2fa.textContent = 'Manage';
                    setup2fa.onclick = () => window.location.href = 'floxsync_security.php';
                } else {
                    badge2fa.className = 'security-badge disabled';
                    badge2fa.innerHTML = '<i class="fa-solid fa-lock-open"></i> Disabled';
                    setup2fa.textContent = 'Enable';
                    setup2fa.onclick = () => window.location.href = 'floxsync_security.php';
                }
            };
            
            fetch('../backend/getUser.php').then(r=>r.json()).then(data => {
                if(data.success && data.user) {
                    update2FAUI(data.user.two_factor_enabled);
                    
                    if(data.user.google_id) {
                         document.getElementById('googleBtnContainer').innerHTML = '<span class="link-status-btn linked"><i class="fa-solid fa-check"></i> Connected</span>';
                    }
                }
            });
            
            // -- Mailbox Logic --
            const mailboxList = document.getElementById('mailboxList');
            
            function loadMailbox() {
                fetch('../backend/getMailbox.php')
                .then(r => r.json())
                .then(data => {
                    if(data.success) {
                        renderMailbox(data.logs);
                    } else {
                        mailboxList.innerHTML = `<div style="padding: 20px;">${data.message}</div>`;
                    }
                })
                .catch(e => {
                     mailboxList.innerHTML = `<div style="padding: 20px;">Error loading logs</div>`;
                });
            }
            
            function renderMailbox(logs) {
                if(!logs || logs.length === 0) {
                    mailboxList.innerHTML = '<div style="text-align:center; padding: 20px; color: var(--text-secondary);">No security logs found.</div>';
                    return;
                }
                
                mailboxList.innerHTML = logs.map(log => {
                    let icon = '<i class="fa-solid fa-info-circle"></i>';
                    let colorClass = 'log-info';
                    
                    if(log.severity === 'warning') {
                        icon = '<i class="fa-solid fa-triangle-exclamation"></i>';
                        colorClass = 'log-warning';
                    } else if (log.severity === 'critical') {
                        icon = '<i class="fa-solid fa-ban"></i>';
                        colorClass = 'log-critical';
                    }
                    
                    const date = new Date(log.created_at).toLocaleString();
                    
                    return `
                        <div class="mailbox-item ${log.is_read ? '' : 'unread'}">
                            <div class="mailbox-icon ${colorClass}">
                                ${icon}
                            </div>
                            <div class="mailbox-content">
                                <div class="mailbox-title">${log.title}</div>
                                <div class="mailbox-msg">${log.message}</div>
                                <div class="mailbox-time">${date}</div>
                            </div>
                        </div>
                    `;
                }).join('');
            }
            
            // Add Styles for Mailbox
            const style = document.createElement('style');
            style.textContent = `
                .mailbox-item {
                    display: flex;
                    gap: 12px;
                    padding: 12px;
                    background: var(--tertiary-color);
                    border-radius: 8px;
                    border: 1px solid var(--border-color);
                    transition: .2s;
                }
                .mailbox-item.unread {
                    border-left: 3px solid var(--accent-color);
                    background: rgba(var(--accent-rgb), 0.05);
                }
                .mailbox-icon {
                    width: 32px;
                    height: 32px;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    flex-shrink: 0;
                    font-size: 14px;
                }
                .log-info { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
                .log-warning { background: rgba(234, 179, 8, 0.1); color: #eab308; }
                .log-critical { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
                
                .mailbox-content { flex: 1; }
                .mailbox-title { font-weight: 600; font-size: 14px; margin-bottom: 2px; }
                .mailbox-msg { font-size: 13px; color: var(--text-secondary); margin-bottom: 4px; }
                .mailbox-time { font-size: 11px; color: var(--text-secondary); opacity: 0.7; }
            `;
            document.head.appendChild(style);
            
            loadMailbox();
        });
    </script>
</body>
</html>
