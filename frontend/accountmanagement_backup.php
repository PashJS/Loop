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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account - FloxWatch</title>
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Stylesheets -->
    <link rel="stylesheet" href="home.css?v=<?php echo time(); ?>"/>
    <link rel="stylesheet" href="layout.css?v=3"/>
    <link rel="stylesheet" href="accountmanagement.css?v=<?php echo time(); ?>"/>

    <style>
        /* Base styles for space theme transparency */
        body {
            background: transparent !important;
            margin: 0; padding: 0;
            font-family: 'Outfit', sans-serif;
            overflow-x: hidden;
            overflow-y: auto;
        }
        
        html { background: #000; }

        .app-layout { 
            position: relative;
            z-index: 1;
            background: transparent !important; 
            height: auto !important; 
        }

        .side-nav {
            background: rgba(0,0,0,0.1) !important;
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
        }

        .top-nav {
            background: rgba(0,0,0,0.4) !important;
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255,255,255,0.05) !important;
        }

        .main-content { 
            background: transparent !important; 
            min-height: 100vh;
            position: relative;
            z-index: 1;
        }

        #starfield {
            position: fixed; 
            top: 0; 
            left: 0;
            width: 100%; 
            height: 100%;
            z-index: 0; 
            pointer-events: none;
            background: #000;
        }
    </style>
</head>
<body>
    <canvas id="starfield"></canvas>

    <?php include 'header.php'; ?>

    <div class="app-layout">
        <?php include 'sidebar.php'; ?>
        
        <main class="main-content">
            <div class="account-wrapper">
                
                <!-- Profile Hub (Creative Hero) -->
                <div class="profile-hub">
                    <div class="hub-avatar-container">
                        <img id="profilePictureImg" src="" alt="" class="hub-avatar">
                        <div class="avatar-placeholder hub-avatar" id="profilePicturePlaceholder">
                            <i class="fa-solid fa-user"></i>
                        </div>
                        <div class="avatar-edit-overlay" id="profilePictureBtn">
                            <i class="fa-solid fa-camera"></i>
                            <input type="file" id="profilePictureInput" accept="image/*" style="display:none;"/>
                        </div>
                    </div>
                    <div class="hub-details">
                        <h1 id="profileUsername">--</h1>
                        <span class="email-tag" id="profileEmail">--</span>
                        <div class="hub-stats">
                            <div class="stat-box">
                                <span class="stat-value" id="statVideos">0</span>
                                <span class="stat-label">Videos</span>
                            </div>
                            <div class="stat-box">
                                <span class="stat-value" id="statJoined">--</span>
                                <span class="stat-label">Member Since</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Command Layout -->
                <div class="management-grid">
                    <!-- Left Rail: Sector Navigation -->
                    <div class="management-nav">
                        <div class="nav-sector active" data-sector="account">
                            <i class="fa-solid fa-user-gear"></i>
                            <span>Profile</span>
                        </div>
                        <div class="nav-sector" data-sector="security">
                            <i class="fa-solid fa-shield-halved"></i>
                            <span>Security</span>
                        </div>
                        <div class="nav-sector" data-sector="subscriptions">
                            <i class="fa-solid fa-users"></i>
                            <span>Subscriptions</span>
                        </div>
                        <div class="nav-sector danger" id="triggerDeleteModal">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                            <span>Danger Zone</span>
                        </div>
                    </div>

                    <!-- Right Rail: Sector Content -->
                    <div class="management-content">
                        
                        <!-- Account Sector -->
                        <div class="content-sector active" id="sector-account">
                            <h2 class="sector-title">Profile Settings</h2>
                            <form id="accountForm">
                                <div class="glass-form-group">
                                    <label>Username</label>
                                    <input type="text" id="accountUsername" name="username" class="glass-input" placeholder="Display name">
                                </div>
                                <div class="glass-form-group">
                                    <label>Your Email</label>
                                    <div style="color: var(--text-secondary); margin-bottom: 8px; font-size: 14px; font-family: monospace;" id="currentEmailDisplay">--</div>
                                    <input type="email" id="accountEmail" name="email" class="glass-input" placeholder="Update email address">
                                </div>
                                <div class="glass-form-group">
                                    <label>New Password</label>
                                    <input type="password" id="accountPassword" name="password" class="glass-input" placeholder="Secure your account">
                                </div>
                                <div class="glass-form-group">
                                    <label>Bio</label>
                                    <textarea id="accountBio" name="bio" class="glass-textarea" rows="3" placeholder="Write a broadcast message..."></textarea>
                                    <div style="font-size: 12px; color: var(--accent-blue); margin-top: 8px; text-align: right;"><span id="bioCharCount">0</span>/500</div>
                                </div>
                                <button type="submit" class="btn-primary-glass" id="submitAccountBtn">Save Profile</button>
                            </form>
                        </div>

                        <!-- Security Sector -->
                        <div class="content-sector" id="sector-security">
                            <h2 class="sector-title">Security & Sessions</h2>
                            <div style="background: rgba(255,255,255,0.02); padding: 30px; border-radius: 24px; border: 1px solid var(--glass-border);">
                                <h3 style="margin-top:0;">Global Sign-out</h3>
                                <p style="color: var(--text-secondary); line-height:1.6; margin-bottom:24px;">Logout from all other devices. This action will terminate all active sessions except for your current one on this device.</p>
                                <button class="btn-primary-glass" id="secureAccountBtn">
                                    <i class="fa-solid fa-lock" style="margin-right:8px;"></i> Secure My Account
                                </button>
                            </div>
                        </div>

                        <!-- Subscriptions Sector -->
                        <div class="content-sector" id="sector-subscriptions">
                            <h2 class="sector-title">Subscription Hub</h2>
                            <div class="subs-tabs" style="display:flex; gap:16px; margin-bottom:30px;">
                                <button class="nav-sector active subs-tab-btn" data-tab="subscribed" style="padding:12px 24px; font-size:14px;">
                                    Subscribed (<span id="subscribedCount">0</span>)
                                </button>
                                <button class="nav-sector subs-tab-btn" data-tab="subscribers" style="padding:12px 24px; font-size:14px;">
                                    Subscribers (<span id="subscribersCount">0</span>)
                                </button>
                            </div>
                            
                            <div id="subscribedTabContent" class="subs-view active">
                                <div id="subscribedList" class="subs-grid" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap:20px;"></div>
                                <div id="subscribedEmpty" style="text-align:center; padding:60px; display:none; opacity:0.5;">No channels followed yet.</div>
                            </div>
                            
                            <div id="subscribersTabContent" class="subs-view" style="display:none;">
                                <div id="subscribersList" class="subs-grid" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap:20px;"></div>
                                <div id="subscribersEmpty" style="text-align:center; padding:60px; display:none; opacity:0.5;">No subscribers found yet.</div>
                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </main>
    </div>

    <!-- Modals -->
    <div class="card-modal-overlay" id="deleteAccountModalOverlay" style="position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.85); backdrop-filter:blur(15px); z-index:9000; display:none; align-items:center; justify-content:center;">
        <div class="content-sector active" style="max-width:500px; width:90%; border:1px solid var(--danger-red); background:rgba(20,10,10,0.95);">
            <h2 class="sector-title" style="color:var(--danger-red);">Erase Account</h2>
            <p style="color:var(--text-secondary); line-height:1.6; margin-bottom:24px;">This action is permanent and will destroy all data associated with your transmission channel. It cannot be undone.</p>
            <form id="deleteAccountForm">
                <div class="glass-form-group">
                    <label>Validate Identity</label>
                    <input type="password" id="deletePassword" name="password" class="glass-input" placeholder="Confirm password" required>
                </div>
                <div style="display:flex; gap:16px;">
                    <button type="button" class="btn-primary-glass" id="closeDeleteModal" style="background:transparent; border:1px solid var(--glass-border); flex:1;">Abort</button>
                    <button type="submit" class="btn-danger-glass" style="flex:1;">Erase Everything</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Starfield Animation -->
    <script>
        const canvas = document.getElementById('starfield');
        const ctx = canvas.getContext('2d');
        let stars = [];
        const starCount = 180;

        function resize() {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
            initStars();
        }

        function initStars() {
            stars = [];
            for (let i = 0; i < starCount; i++) {
                stars.push({
                    x: Math.random() * canvas.width,
                    y: Math.random() * canvas.height,
                    size: Math.random() * 1.5,
                    speed: Math.random() * 0.12 + 0.05,
                    opacity: Math.random() * 0.3 + 0.1,
                    twinkle: Math.random() * 0.01
                });
            }
        }

        function animate() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            ctx.fillStyle = '#000';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            
            stars.forEach(star => {
                star.y -= star.speed;
                if (star.y < 0) star.y = canvas.height;
                
                star.opacity += Math.sin(Date.now() * star.twinkle) * 0.01;
                ctx.fillStyle = `rgba(255, 255, 255, ${Math.max(0.1, Math.min(0.5, star.opacity))})`;
                ctx.beginPath();
                ctx.arc(star.x, star.y, star.size, 0, Math.PI * 2);
                ctx.fill();
            });
            requestAnimationFrame(animate);
        }

        window.addEventListener('resize', resize);
        resize();
        animate();
    </script>

    <!-- JS Handling Sector Switching & Modal -->
    <script>
        document.querySelectorAll('.nav-sector[data-sector]').forEach(btn => {
            btn.addEventListener('click', () => {
                const sector = btn.dataset.sector;
                document.querySelectorAll('.nav-sector').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                
                document.querySelectorAll('.content-sector').forEach(s => s.classList.remove('active'));
                document.getElementById('sector-' + sector).classList.add('active');
            });
        });

        // Delete Modal Logic
        const deleteTrigger = document.getElementById('triggerDeleteModal');
        const deleteOverlay = document.getElementById('deleteAccountModalOverlay');
        const closeDelete = document.getElementById('closeDeleteModal');

        deleteTrigger.addEventListener('click', () => {
            deleteOverlay.style.display = 'flex';
        });

        closeDelete.addEventListener('click', () => {
            deleteOverlay.style.display = 'none';
        });

        // Subs Tab Logic
        document.querySelectorAll('.subs-tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const tab = btn.dataset.tab;
                document.querySelectorAll('.subs-tab-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                
                document.querySelectorAll('.subs-view').forEach(v => v.style.display = 'none');
                document.getElementById(tab + 'TabContent').style.display = 'block';
            });
        });
    </script>

    <script src="theme.js"></script>
    <script src="popup.js"></script>
    <script src="notifications.js"></script>
    <script src="accountmanagement.js?v=<?php echo time(); ?>"></script>
</body>
</html>