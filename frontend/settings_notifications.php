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
    <title>Notification Settings - Loop</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="home.css?v=3">
    <link rel="stylesheet" href="layout.css">
    <link rel="stylesheet" href="settings_layout.css">
    <style>
        .settings-toggle { position: relative; width: 48px; height: 26px; display: inline-block; }
        .settings-toggle input { opacity: 0; width: 0; height: 0; }
        .settings-toggle-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background: var(--tertiary-color); border-radius: 26px; transition: .3s; }
        .settings-toggle-slider:before { position: absolute; content: ""; height: 20px; width: 20px; left: 3px; bottom: 3px; background: white; border-radius: 50%; transition: .3s; }
        .settings-toggle input:checked + .settings-toggle-slider { background: var(--accent-color); }
        .settings-toggle input:checked + .settings-toggle-slider:before { transform: translateX(22px); }
        
        /* Dropdown reuse */
        .custom-dropdown { position: relative; min-width: 180px; }
        .custom-dropdown-trigger { background: var(--tertiary-color); border: 1px solid var(--border-color); border-radius: 8px; padding: 10px 14px; display: flex; justify-content: space-between; align-items: center; cursor: pointer; }
        .custom-dropdown-menu { position: absolute; top: 100%; left: 0; right: 0; background: var(--secondary-color); border: 1px solid var(--border-color); border-radius: 8px; margin-top: 4px; display: none; z-index: 100; overflow: hidden; }
        .custom-dropdown.open .custom-dropdown-menu { display: block; }
        .custom-dropdown-item { padding: 10px 14px; cursor: pointer; transition: background 0.2s; }
        .custom-dropdown-item:hover { background: var(--hover-bg); }
        .custom-dropdown-item.selected { color: var(--accent-color); background: rgba(var(--accent-rgb), 0.1); }
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
                        <h1>Notifications</h1>
                        <p>Manage how and when we alert you</p>
                    </div>

                    <!-- Channels Card -->
                    <div class="settings-card">
                         <div class="settings-group-title">
                            <i class="fa-solid fa-tower-broadcast" style="color: var(--accent-color);"></i>
                            Delivery Channels
                        </div>
                        
                        <div class="settings-row">
                            <div class="settings-info">
                                <div class="settings-label">Push Notifications</div>
                                <div class="settings-desc">Receive real-time alerts in your browser</div>
                            </div>
                             <label class="settings-toggle">
                                <input type="checkbox" id="pushNotifications">
                                <span class="settings-toggle-slider"></span>
                            </label>
                        </div>
                        
                        <div class="settings-row">
                            <div class="settings-info">
                                <div class="settings-label">Email Notifications</div>
                                <div class="settings-desc">Get important updates sent to your inbox</div>
                            </div>
                             <label class="settings-toggle">
                                <input type="checkbox" id="emailNotifications">
                                <span class="settings-toggle-slider"></span>
                            </label>
                        </div>
                    </div>

                    <!-- Preferences Card -->
                    <div class="settings-card">
                        <div class="settings-group-title">
                            <i class="fa-solid fa-sliders" style="color: var(--accent-color);"></i>
                            Preferences
                        </div>
                        
                        <div class="settings-row">
                            <div class="settings-info">
                                <div class="settings-label">Subscription Uploads</div>
                                <div class="settings-desc">Notify me when channels I subscribe to upload new videos</div>
                            </div>
                             <label class="settings-toggle">
                                <input type="checkbox" id="subscriptionNotifications">
                                <span class="settings-toggle-slider"></span>
                            </label>
                        </div>

                         <div class="settings-row">
                            <div class="settings-info">
                                <div class="settings-label">Email Frequency</div>
                                <div class="settings-desc">How often should we email you?</div>
                            </div>
                            <div class="custom-dropdown" id="notificationFrequency" data-value="daily">
                                <div class="custom-dropdown-trigger">
                                    <span>Daily Digest</span>
                                    <i class="fa-solid fa-chevron-down"></i>
                                </div>
                                <div class="custom-dropdown-menu">
                                    <div class="custom-dropdown-item" data-value="immediate">Immediate</div>
                                    <div class="custom-dropdown-item selected" data-value="daily">Daily Digest</div>
                                    <div class="custom-dropdown-item" data-value="weekly">Weekly Summary</div>
                                    <div class="custom-dropdown-item" data-value="never">Never</div>
                                </div>
                            </div>
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
             // -- Sync Helper --
             const syncPref = (key, value) => {
                fetch('../backend/update_notification_prefs.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ [key]: value })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        if(typeof Popup !== 'undefined') Popup.show('Preference Updated', 'success');
                    }
                });
                
                // Also update local storage cache for consistency
                let s = JSON.parse(localStorage.getItem('floxwatch_settings') || '{}');
                s[key] = value;
                localStorage.setItem('floxwatch_settings', JSON.stringify(s));
            };

            // -- Generic Dropdown Logic --
            document.querySelectorAll('.custom-dropdown').forEach(dd => {
                const trigger = dd.querySelector('.custom-dropdown-trigger');
                const items = dd.querySelectorAll('.custom-dropdown-item');
                
                trigger.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const isOpen = dd.classList.contains('open');
                    document.querySelectorAll('.custom-dropdown').forEach(d => d.classList.remove('open'));
                    if(!isOpen) dd.classList.add('open');
                });
                
                items.forEach(item => {
                    item.addEventListener('click', () => {
                         const val = item.dataset.value;
                         dd.dataset.value = val;
                         trigger.querySelector('span').textContent = item.textContent;
                         items.forEach(i => i.classList.remove('selected'));
                         item.classList.add('selected');
                         dd.classList.remove('open');
                         
                         // Specific Logic
                         if(dd.id === 'notificationFrequency') syncPref('notification_frequency', val);
                    });
                });
            });
            document.addEventListener('click', () => document.querySelectorAll('.custom-dropdown').forEach(d => d.classList.remove('open')));

            // -- Load Initial State from Backend --
            fetch('../backend/getUser.php')
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.user) {
                        const u = data.user;
                        document.getElementById('emailNotifications').checked = u.email_notifications;
                        document.getElementById('subscriptionNotifications').checked = u.subscription_notifications;
                        
                        // Freq
                        const freq = u.notification_frequency || 'daily';
                        const dd = document.getElementById('notificationFrequency');
                        const item = dd.querySelector(`.custom-dropdown-item[data-value="${freq}"]`);
                        if(item) item.click(); // simulate selection to update UI
                    }
                });
            
            // -- Toggles --
            const emailToggle = document.getElementById('emailNotifications');
            emailToggle.addEventListener('change', () => syncPref('email_notifications', emailToggle.checked));
            
            const subToggle = document.getElementById('subscriptionNotifications');
            subToggle.addEventListener('change', () => syncPref('subscription_notifications', subToggle.checked));
            
            const pushToggle = document.getElementById('pushNotifications');
            // Push toggles usually just check permission or save to local storage preference
            const savedPush = JSON.parse(localStorage.getItem('floxwatch_settings') || '{}').pushNotifications;
            if(savedPush !== undefined) pushToggle.checked = savedPush;
            else pushToggle.checked = (Notification.permission === 'granted');
            
            pushToggle.addEventListener('change', () => {
                if(pushToggle.checked) {
                    if(Notification.permission !== 'granted') {
                        Notification.requestPermission().then(p => {
                            if(p !== 'granted') pushToggle.checked = false;
                            syncPref('pushNotifications', pushToggle.checked);
                        });
                    } else {
                        syncPref('pushNotifications', true);
                    }
                } else {
                    syncPref('pushNotifications', false);
                }
            });
        });
    </script>
</body>
</html>
