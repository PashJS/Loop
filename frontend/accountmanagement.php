<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: loginb.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" data-gramm="false" spellcheck="false">
<head>
    <script>
    /**
     * FloxWatch Suppressor v2.9 (Nuclear Silence - Inline Head)
     */
    (function () {
        const isN = (v) => {
            if (!v) return false;
            if (typeof v === 'object') {
                if ((v.name === 'i' || v.name === 'IncompleteError') && v.code === 403) return true;
                if (v.reqInfo && (v.reqInfo.path === '/template_list' || v.reqInfo.pathPrefix === '/site_integration')) return true;
                if (v.message === 'permission error' || (v.data && v.data.msg === 'permission error')) return true;
            }
            try {
                const s = (v.stack || v.message || JSON.stringify(v) || "").toLowerCase();
                return ['grammarly', 'jiifimnepkibjfjbppnjble', 'extension', 'isolated-world'].some(p => s.includes(p));
            } catch (e) { return false; }
        };
        const sink = () => new Promise(() => {});
        const oF = window.fetch;
        window.fetch = function (...a) {
            const u = a[0] ? a[0].toString() : '';
            if (u.includes('/site_integration') || u.includes('/template_list')) return sink();
            return oF.apply(this, a).catch(e => isN(e) ? sink() : Promise.reject(e));
        };
        const rej = (e) => { if (isN(e.reason)) { e.preventDefault(); e.stopImmediatePropagation(); } };
        window.addEventListener('unhandledrejection', rej, true);
        window.onunhandledrejection = rej;
        console.error = (function(o){ return function(...a){ if(!a.some(isN)) o.apply(console, a); }; })(console.error);
    })();
    </script>
    <script src="suppress_errors.js?v=<?php echo time(); ?>"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account - Loop</title>
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Stylesheets -->
    <link rel="stylesheet" href="home.css?v=<?php echo time(); ?>"/>
    <link rel="stylesheet" href="layout.css?v=3"/>
    <link rel="stylesheet" href="accountmanagement.css?v=<?php echo time(); ?>"/>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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
<body data-gramm="false" spellcheck="false">
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

                <!-- Studio Navigation -->
                <nav class="studio-nav">
                    <div class="nav-links">
                        <button class="nav-tab active" data-sector="videos">Videos</button>
                        <button class="nav-tab" data-sector="clips">Clips</button>
                        <button class="nav-tab" data-sector="posts">Posts</button>
                        <button class="nav-tab" data-sector="polls">Polls</button>
                        <button class="nav-tab" data-sector="settings">Settings</button>
                    </div>
                    <div class="nav-tools">
                        <div class="studio-search-box">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input type="text" placeholder="Search..." id="studioSearchInput" spellcheck="false" data-gramm="false">
                        </div>
                        <div class="filter-wrapper" style="position:relative;">
                            <button class="tool-btn" id="studioFilterToggle" title="Filter">
                                <i class="fa-solid fa-filter"></i>
                            </button>
                            <div class="custom-dropdown-menu" id="studioFilterMenu" style="display:none;">
                                <div class="dropdown-item" data-sort="newest">Latest</div>
                                <div class="dropdown-item" data-sort="oldest">Oldest</div>
                                <div class="dropdown-item" data-sort="views_desc">Most Viewed</div>
                                <div class="dropdown-item" data-sort="views_asc">Less Viewed</div>
                                <div class="dropdown-item" data-sort="likes_desc">Most Liked</div>
                                <div class="dropdown-item" data-sort="likes_asc">Less Liked</div>
                            </div>
                        </div>
                    </div>
                </nav>

                <!-- Studio Content Container -->
                <div class="studio-content-wrapper">
                    
                    <!-- VIDEOS SECTOR -->
                    <div class="content-sector active" id="sector-videos">
                        <div class="sector-header">
                            <h2 class="sector-title">Video Library</h2>
                        </div>


                            <!-- Growth/Stats Summary -->
                            <div class="studio-stats-grid">
                                <div class="stat-glass-card">
                                    <span class="stat-label">Total Views</span>
                                    <span class="stat-value" id="vidTotalViews">0</span>
                                </div>
                                <div class="stat-glass-card">
                                    <span class="stat-label">Engagement</span>
                                    <span class="stat-value" id="vidTotalEngagement">0</span>
                                </div>
                            </div>

                            <!-- Video List -->
                            <div class="videos-container">
                                <div class="loading-spinner" id="vidLoading">
                                    <div class="spinner"></div>Loading content...
                                </div>
                                <div class="videos-grid" id="videosGrid"></div>
                                <div class="empty-state" id="vidEmpty" style="display:none;">
                                    <p>No long-form videos found.</p>
                                </div>
                            </div>
                        </div>

                        <!-- CLIPS SECTOR -->
                        <div class="content-sector" id="sector-clips">
                            <div class="sector-header">
                                <h2 class="sector-title">Short Clips</h2>
                            </div>

                            <div class="videos-container">
                                <div class="loading-spinner" id="clipLoading">
                                    <div class="spinner"></div>Loading clips...
                                </div>
                                <div class="videos-grid" id="clipsGrid"></div>
                                <div class="empty-state" id="clipEmpty" style="display:none;">
                                    <p>No shorts/clips found.</p>
                                </div>
                            </div>
                        </div>

                        <!-- POSTS SECTOR -->
                        <div class="content-sector" id="sector-posts">
                            <div class="sector-header">
                                <h2 class="sector-title">Community Posts</h2>
                                <div style="display:flex; gap:10px;">
                                    <a href="create_poll.php" class="btn-primary-glass btn-sm" style="background: rgba(157, 0, 255, 0.1); border-color: rgba(157, 0, 255, 0.3); color: #c07bff;"><i class="fa-solid fa-chart-simple"></i> New Poll</a>
                                    <a href="create_post.php" class="btn-primary-glass btn-sm"><i class="fa-solid fa-plus"></i> New Post</a>
                                </div>
                            </div>
                            
                            <div class="posts-container">
                                <div class="loading-spinner" id="postsLoading">
                                    <div class="spinner"></div>Loading posts...
                                </div>
                                <div class="posts-grid" id="postsGrid"></div>
                                <div class="empty-state" id="postsEmpty" style="display:none;">
                                    <div class="empty-glow-icon"><i class="fa-solid fa-pen-fancy"></i></div>
                                    <h3>No Posts Yet</h3>
                                    <p>Share updates with your audience by creating your first post.</p>
                                </div>
                            </div>
                        </div>

                        <!-- POLLS SECTOR -->
                        <div class="content-sector" id="sector-polls">
                            <div class="sector-header">
                                <h2 class="sector-title">Interactive Polls</h2>
                                <a href="create_poll.php" class="btn-primary-glass btn-sm" style="background: rgba(157, 0, 255, 0.1); border-color: rgba(157, 0, 255, 0.3); color: #c07bff;"><i class="fa-solid fa-chart-simple"></i> New Poll</a>
                            </div>
                            
                            <div class="polls-container">
                                <div class="loading-spinner" id="pollsLoading">
                                    <div class="spinner"></div>Loading polls...
                                </div>
                                <div class="polls-grid" id="pollsGrid"></div>
                                <div class="empty-state" id="pollsEmpty" style="display:none;">
                                    <div class="empty-glow-icon"><i class="fa-solid fa-square-poll-vertical"></i></div>
                                    <h3>No Polls Created</h3>
                                    <p>Gather audience feedback by creating your first community poll.</p>
                                </div>
                            </div>
                        </div>

                        <!-- SETTINGS SECTOR (Old Profile/Security/Subs) -->
                        <div class="content-sector" id="sector-settings">
                            <h2 class="sector-title">Channel Settings</h2>
                            
                            <!-- Sub-nav for Settings -->
                            <div class="settings-subnav" style="display:flex; gap:10px; margin-bottom:30px; border-bottom:1px solid var(--glass-border); padding-bottom:20px;">
                                <button class="nav-tab active" onclick="switchSettingsTab('profile')">Profile</button>
                                <button class="nav-tab" onclick="switchSettingsTab('security')">Security</button>
                                <button class="nav-tab" onclick="switchSettingsTab('subs')">Subscriptions</button>
                                <button class="nav-tab" onclick="switchSettingsTab('danger')" style="color:var(--danger-red);">Danger Zone</button>
                            </div>

                            <!-- Profile Settings -->
                            <div id="setting-tab-profile">
                                <form id="accountForm">
                                    <div class="glass-form-group">
                                        <label>Username</label>
                                        <input type="text" id="accountUsername" name="username" class="glass-input" placeholder="Display name" spellcheck="false" data-gramm="false">
                                    </div>
                                    <div class="glass-form-group">
                                        <label>Your Email</label>
                                        <div style="color: var(--text-secondary); margin-bottom: 8px; font-size: 14px; font-family: monospace;" id="currentEmailDisplay">--</div>
                                        <input type="email" id="accountEmail" name="email" class="glass-input" placeholder="Update email address" spellcheck="false" data-gramm="false">
                                    </div>
                                    <div class="glass-form-group">
                                        <label>Bio</label>
                                        <textarea id="accountBio" name="bio" class="glass-textarea" rows="3" placeholder="Write a broadcast message..." spellcheck="false" data-gramm="false"></textarea>
                                        <div style="font-size: 12px; color: var(--accent-blue); margin-top: 8px; text-align: right;"><span id="bioCharCount">0</span>/500</div>
                                    </div>
                                    <button type="submit" class="btn-primary-glass" id="submitAccountBtn">Save Profile</button>
                                </form>
                            </div>

                            <!-- Security Settings -->
                            <div id="setting-tab-security" style="display:none;">
                                <form id="securityForm">
                                    <div class="glass-form-group">
                                        <label>New Password</label>
                                        <input type="password" id="accountPassword" name="password" class="glass-input" placeholder="Set new password" spellcheck="false" data-gramm="false">
                                    </div>
                                    <div style="background: rgba(255,255,255,0.02); padding: 20px; border-radius: 16px; margin-top:20px;">
                                        <h3 style="margin-top:0; font-size:16px;">Global Sign-out</h3>
                                        <p style="color: var(--text-secondary); font-size:14px; margin-bottom:16px;">Logout from all other devices.</p>
                                        <button type="button" class="btn-primary-glass" id="secureAccountBtn">
                                            <i class="fa-solid fa-lock" style="margin-right:8px;"></i> Secure My Account
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <!-- Subscriptions -->
                            <div id="setting-tab-subs" style="display:none;">
                                <div class="subs-tabs" style="display:flex; gap:16px; margin-bottom:20px;">
                                    <button type="button" class="nav-sector active subs-tab-btn" data-tab="subscribed" style="padding:10px 20px; font-size:13px;">
                                        Subscribed (<span id="subscribedCount">0</span>)
                                    </button>
                                    <button type="button" class="nav-sector subs-tab-btn" data-tab="subscribers" style="padding:10px 20px; font-size:13px;">
                                        Subscribers (<span id="subscribersCount">0</span>)
                                    </button>
                                </div>
                                <div id="subscribedTabContent" class="subs-view active">
                                    <div id="subscribedList" class="subs-grid" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap:16px;"></div>
                                    <div id="subscribedEmpty" style="text-align:center; padding:40px; display:none; opacity:0.5;">No channels followed.</div>
                                </div>
                                <div id="subscribersTabContent" class="subs-view" style="display:none;">
                                    <div id="subscribersList" class="subs-grid" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap:16px;"></div>
                                    <div id="subscribersEmpty" style="text-align:center; padding:40px; display:none; opacity:0.5;">No subscribers yet.</div>
                                </div>
                            </div>

                            <!-- Danger Zone -->
                            <div id="setting-tab-danger" style="display:none;">
                                <div style="border: 1px solid var(--danger-red); background: rgba(255,77,77,0.05); padding: 24px; border-radius: 16px;">
                                    <h3 style="color: var(--danger-red); margin-top:0;">Delete Channel</h3>
                                    <p style="color: var(--text-secondary); margin-bottom:20px;">Permanently delete your account and all content. This cannot be undone.</p>
                                    <button type="button" class="btn-danger-glass" id="triggerDeleteModal">Delete Account</button>
                                </div>
                            </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Modals -->
    <!-- Modal for Editing Video -->
    <div id="editVideoModal" class="popup-overlay" style="display:none;">
        <div class="popup-dialog glass-modal">
            <div class="popup-dialog-header">
                <h3><i class="fa-solid fa-pen-to-square"></i> Edit Content</h3>
                <button class="popup-close" onclick="closeEditModal()">×</button>
            </div>
            <div class="popup-dialog-body">
                <form id="editVideoForm">
                    <input type="hidden" id="editVideoId">
                    <div class="input-group">
                        <label>Title</label>
                        <input type="text" id="editTitle" required placeholder="Title" spellcheck="false" data-gramm="false">
                    </div>
                    <div class="input-group">
                        <label>Description</label>
                        <textarea id="editDescription" rows="4" placeholder="Description" spellcheck="false" data-gramm="false"></textarea>
                    </div>
                    <div class="input-group">
                        <label>Visibility</label>
                        <select id="editStatus">
                            <option value="published">Public</option>
                            <option value="draft">Private/Draft</option>
                            <option value="archived">Archived</option>
                        </select>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="cancel-btn" onclick="closeEditModal()">Cancel</button>
                        <button type="submit" class="save-btn">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal for Analytics -->
    <div id="analyticsModal" class="popup-overlay" style="display:none;">
        <div class="popup-dialog glass-modal analytics-wide">
            <div class="popup-dialog-header">
                <h3 id="analyticsTitle"><i class="fa-solid fa-chart-line"></i> Analytics</h3>
                <button class="popup-close" onclick="closeAnalyticsModal()">×</button>
            </div>
            <div class="popup-dialog-body">
                <div class="analytics-top-grid">
                    <div class="analytics-main-chart">
                        <canvas id="viewsChart"></canvas>
                    </div>
                    <div class="analytics-side-stats">
                        <div class="side-stat-card">
                            <div id="modalTotalViews" class="val">0</div>
                            <div class="lab">Total Views</div>
                        </div>
                        <div class="side-stat-card">
                            <div id="modalLikeCount" class="val">0</div>
                            <div class="lab">Likes</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="card-modal-overlay" id="deleteAccountModalOverlay" style="position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.85); backdrop-filter:blur(15px); z-index:9000; display:none; align-items:center; justify-content:center;">
        <div class="content-sector active" style="max-width:500px; width:90%; border:1px solid var(--danger-red); background:rgba(20,10,10,0.95);">
            <h2 class="sector-title" style="color:var(--danger-red);">Erase Account</h2>
            <p style="color:var(--text-secondary); line-height:1.6; margin-bottom:24px;">This action is permanent and will destroy all data associated with your transmission channel. It cannot be undone.</p>
            <form id="deleteAccountForm">
                <div class="glass-form-group">
                    <label>Validate Identity</label>
                    <input type="password" id="deletePassword" name="password" class="glass-input" placeholder="Confirm password" required spellcheck="false" data-gramm="false">
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



    <script src="theme.js"></script>
    <script src="popup.js"></script>
    <script src="notifications.js"></script>
    <script src="accountmanagement_v2.js?v=<?php echo time(); ?>"></script>
</body>
</html>