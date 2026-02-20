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
    <title>Privacy Settings - Loop</title>
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
                        <h1>Privacy & Data</h1>
                        <p>Control who sees your activity and how we use data</p>
                    </div>

                    <div class="settings-card">
                         <div class="settings-group-title">
                            <i class="fa-solid fa-eye" style="color: var(--accent-color);"></i>
                            Visibility
                        </div>
                        
                        <div class="settings-row">
                            <div class="settings-info">
                                <div class="settings-label">Profile Visibility</div>
                                <div class="settings-desc">Who can view your channel page?</div>
                            </div>
                            <div class="custom-dropdown" id="profileVisibility" data-value="public">
                                <div class="custom-dropdown-trigger">
                                    <span>Public</span>
                                    <i class="fa-solid fa-chevron-down"></i>
                                </div>
                                <div class="custom-dropdown-menu">
                                    <div class="custom-dropdown-item selected" data-value="public">Public</div>
                                    <div class="custom-dropdown-item" data-value="subscribers">Subscribers Only</div>
                                    <div class="custom-dropdown-item" data-value="private">Private</div>
                                </div>
                            </div>
                        </div>

                         <div class="settings-row">
                            <div class="settings-info">
                                <div class="settings-label">Show Watch History</div>
                                <div class="settings-desc">Allow friends to see what you watch</div>
                            </div>
                             <label class="settings-toggle">
                                <input type="checkbox" id="showWatchHistory">
                                <span class="settings-toggle-slider"></span>
                            </label>
                        </div>
                    </div>

                    <div class="settings-card">
                        <div class="settings-group-title">
                            <i class="fa-solid fa-database" style="color: var(--accent-color);"></i>
                            Data & Personalization
                        </div>
                        
                        <div class="settings-row">
                            <div class="settings-info">
                                <div class="settings-label">Personalized Recommendations</div>
                                <div class="settings-desc">Improve suggestions using your history</div>
                            </div>
                             <label class="settings-toggle">
                                <input type="checkbox" id="personalizedRecommendations">
                                <span class="settings-toggle-slider"></span>
                            </label>
                        </div>

                         <div class="settings-row">
                            <div class="settings-info">
                                <div class="settings-label">Personalized Ads</div>
                                <div class="settings-desc">Show adds relevant to your interests</div>
                            </div>
                             <label class="settings-toggle">
                                <input type="checkbox" id="personalizedAds">
                                <span class="settings-toggle-slider"></span>
                            </label>
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
             const syncPref = (key, value) => {
                 // For now, these are mostly local storage preferences unless backed by DB columns
                 // profile_visibility is DB backed
                 if(key === 'profile_visibility') {
                    fetch('../backend/update_notification_prefs.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ [key]: value })
                    }).then(r=>r.json()).then(d=>{
                        if(d.success && typeof Popup!=='undefined') Popup.show('Updated', 'success');
                    });
                 }
                 
                let s = JSON.parse(localStorage.getItem('floxwatch_settings') || '{}');
                s[key] = value;
                localStorage.setItem('floxwatch_settings', JSON.stringify(s));
            };

            // Dropdowns
            document.querySelectorAll('.custom-dropdown').forEach(dd => {
                const trig = dd.querySelector('.custom-dropdown-trigger');
                const items = dd.querySelectorAll('.custom-dropdown-item');
                trig.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const isOpen = dd.classList.contains('open');
                    document.querySelectorAll('.custom-dropdown').forEach(d => d.classList.remove('open'));
                    if(!isOpen) dd.classList.add('open');
                });
                items.forEach(item => {
                    item.addEventListener('click', () => {
                         const val = item.dataset.value;
                         dd.dataset.value = val;
                         trig.querySelector('span').textContent = item.textContent;
                         items.forEach(i => i.classList.remove('selected'));
                         item.classList.add('selected');
                         dd.classList.remove('open');
                         
                         if(dd.id === 'profileVisibility') syncPref('profile_visibility', val);
                    });
                });
            });
             document.addEventListener('click', () => document.querySelectorAll('.custom-dropdown').forEach(d => d.classList.remove('open')));

            // Initial load
             fetch('../backend/getUser.php').then(r => r.json()).then(data => {
                if (data.success && data.user) {
                    if (data.user.profile_visibility) {
                         const dd = document.getElementById('profileVisibility');
                         const item = dd.querySelector(`.custom-dropdown-item[data-value="${data.user.profile_visibility}"]`);
                         if(item) item.click();
                    }
                }
             });
             
             // Local storage defaults
             const s = JSON.parse(localStorage.getItem('floxwatch_settings') || '{}');
             if(s.showWatchHistory !== undefined) document.getElementById('showWatchHistory').checked = s.showWatchHistory;
             if(s.personalizedRecommendations !== undefined) document.getElementById('personalizedRecommendations').checked = s.personalizedRecommendations;
             if(s.personalizedAds !== undefined) document.getElementById('personalizedAds').checked = s.personalizedAds;
             
             ['showWatchHistory', 'personalizedRecommendations', 'personalizedAds'].forEach(id => {
                 document.getElementById(id).addEventListener('change', (e) => syncPref(id, e.target.checked));
             });
        });
    </script>
</body>
</html>
