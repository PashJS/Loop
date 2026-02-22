<?php
if (!headers_sent()) {
    header("Content-Security-Policy: connect-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com https://fonts.gstatic.com;");
}
if (session_status() === PHP_SESSION_NONE) session_start();
if (isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/../backend/config.php';
    $stmt_prof = $pdo->prepare("SELECT profile_picture FROM users WHERE id = ?");
    $stmt_prof->execute([$_SESSION['user_id']]);
    $u_prof = $stmt_prof->fetch();
    if ($u_prof && !empty($u_prof['profile_picture'])) {
        $pic = $u_prof['profile_picture'];
        if (strpos($pic, 'http') !== 0) {
            $pic = '../' . ltrim($pic, './');
        }
        $_SESSION['profile_picture'] = $pic;
    }

    // Fetch FloxSync Identity
    try {
        $stmt_fs = $pdo->prepare("SELECT first_name, last_name FROM floxsync_accounts WHERE user_id = ?");
        $stmt_fs->execute([$_SESSION['user_id']]);
        $fs_identity = $stmt_fs->fetch(PDO::FETCH_ASSOC);
        
        if ($fs_identity) {
            $_SESSION['display_name'] = htmlspecialchars($fs_identity['first_name'] . ' ' . $fs_identity['last_name']);
        } else {
            $_SESSION['display_name'] = htmlspecialchars($_SESSION['username'] ?? 'Guest');
        }
    } catch (PDOException $e) {
        $_SESSION['display_name'] = htmlspecialchars($_SESSION['username'] ?? 'Guest');
    }

    // Fetch XPoints balance
    try {
        $stmt_xp = $pdo->prepare("SELECT xpoints FROM users WHERE id = ?");
        $stmt_xp->execute([$_SESSION['user_id']]);
        $u_xp = $stmt_xp->fetch();
        $_SESSION['xpoints'] = $u_xp['xpoints'] ?? 0;
    } catch (PDOException $e) {
        $_SESSION['xpoints'] = 0;
        error_log("XPoints column error: " . $e->getMessage());
    }
    // Fetch Pro Status
    try {
        $stmt_pro = $pdo->prepare("SELECT is_pro FROM users WHERE id = ?");
        $stmt_pro->execute([$_SESSION['user_id']]);
        $_SESSION['is_pro'] = (bool)($stmt_pro->fetchColumn());
    } catch (PDOException $e) { $_SESSION['is_pro'] = false; }
}
echo "<!-- Debug: User: " . ($_SESSION['display_name'] ?? 'NONE') . " -->";
?>
<script>
/**
 * FloxWatch Suppressor v2.9 (Nuclear Silence - Inline)
 */
(function () {
    const isN = (v) => {
        if (!v) return false;
        if (typeof v === 'object') {
            if ((v.name === 'i' || v.name === 'IncompleteError') && v.code === 403) return true;
            if (v.reqInfo && (v.reqInfo.path === '/template_list' || v.reqInfo.pathPrefix === '/site_integration')) return true;
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
<script>
    window.FLOX_CTX = {
        wsHost: "<?php echo $ws_host ?? ''; ?>",
        template: {
            user: {
                name: "<?php echo addslashes($_SESSION['display_name'] ?? 'Guest'); ?>",
                username: "@<?php echo addslashes($_SESSION['username'] ?? 'guest'); ?>",
                isPro: <?php echo ($_SESSION['is_pro'] ?? false) ? 'true' : 'false'; ?>
            }
        }
    };
</script>
<script src="extension_engine.js?v=<?php echo time(); ?>"></script>
<header class="top-nav <?php echo (basename($_SERVER['PHP_SELF']) == 'home.php') ? 'has-suggestions' : ''; ?>" data-flox="header" data-gramm="false" spellcheck="false">
    <div class="top-nav-main">
        <div class="nav-left">
            <button id="sidebarToggle" class="sidebar-toggle" title="Menu">
                <i class="fa-solid fa-bars"></i>
            </button>
            <a href="home.php" class="logo-link">
                <span class="logo-icon-dark"><?php include __DIR__ . '/icon.html'; ?></span>
                <span class="logo-icon-light"><?php include __DIR__ . '/lightmodeicon.html'; ?></span>
                <span class="logo-text">Loop</span>
            </a>
        </div>
        
        <div class="nav-center">
            <div class="search-container" id="searchContainer">
                <input type="text" class="search-input" id="searchInput" placeholder="Search..." autocomplete="off" spellcheck="false" data-gramm="false"/>
                <button class="search-btn" id="searchBtn">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </button>
                <button class="mobile-search-close" id="mobileSearchClose">
                    <i class="fa-solid fa-times"></i>
                </button>
            </div>
            <button class="voice-search-btn" id="voiceSearchBtn" title="Search with your voice">
                <i class="fa-solid fa-microphone"></i>
            </button>
        </div>
        
        <div class="nav-right">
            <!-- Date & Battery status -->
            <div class="status-info" id="dateBattery" aria-live="polite">
                <div id="dateLine"></div>
                <div id="batteryLine"></div>
            </div>

            <button class="mobile-search-btn" id="mobileSearchBtn" title="Search">
                <i class="fa-solid fa-magnifying-glass"></i>
            </button>
            
            <?php if (isset($_SESSION['user_id'])): ?>
            <!-- Logged-in user UI -->
            <a href="xpoints.php" class="header-xpoints-balance" title="Your XPoints Balance">
                <span class="xp-icon"><?php include __DIR__ . '/xpointsicon.html'; ?></span>
                <span class="xp-amount"><?php echo number_format($_SESSION['xpoints']); ?></span>
            </a>

            <div class="create-wrapper">
                <button class="create-btn" id="createBtn" title="Create content">
                    <i class="fa-solid fa-circle-plus"></i>
                    <span>Create</span>
                </button>
                <div class="create-dropdown" id="createDropdown">
                    <a href="upload_video.php" class="create-dropdown-item">
                        <i class="fa-solid fa-film"></i>
                        <span>Video</span>
                    </a>
                    <a href="upload_clip.php" class="create-dropdown-item">
                        <i class="fa-solid fa-clapperboard"></i>
                        <span>Clip</span>
                    </a>

                    <a href="create_post.php" class="create-dropdown-item">
                        <i class="fa-solid fa-pen-to-square"></i>
                        <span>Post</span>
                    </a>
                    <a href="create_story.php" class="create-dropdown-item">
                        <i class="fa-solid fa-camera"></i>
                        <span>Story</span>
                    </a>
                    <a href="live_dashboard.php" class="create-dropdown-item">
                        <i class="fa-solid fa-tower-broadcast"></i>
                        <span>Live</span>
                    </a>
                </div>
            </div>
            <div class="notifications-wrapper">
                <button class="notifications-btn" id="notificationsBtn" title="Notifications">
                    <i class="fa-solid fa-bell"></i>
                    <span class="notification-badge" id="notificationBadge" style="display: none;">0</span>
                </button>
                <div class="notifications-dropdown" id="notificationsDropdown">
                    <div id="notificationsMainView">
                        <div class="notifications-header">
                            <h3>Notifications</h3>
                            <div class="notifications-header-actions">
                                <button class="notification-settings-btn" id="notificationSettingsBtn" title="Notification Options">
                                    <i class="fa-solid fa-gear"></i>
                                </button>
                                <div class="notification-settings-menu" id="notificationSettingsMenu">
                                    <div class="ns-menu-item" id="dndToggleBtn">
                                        <i class="fa-solid fa-moon"></i>
                                        <span>DND mode</span>
                                        <div class="dnd-toggle">
                                            <i class="fa-solid fa-toggle-off" id="dndIcon"></i>
                                        </div>
                                    </div>
                                    <a href="notifications.php" class="ns-menu-item">
                                        <i class="fa-solid fa-clock-rotate-left"></i>
                                        <span>Notification history</span>
                                    </a>
                                    <a href="settings_notifications.php" class="ns-menu-item">
                                        <i class="fa-solid fa-gear"></i>
                                        <span>Notification settings</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="notifications-list" id="notificationsList">
                            <div class="loading-notifications">Loading...</div>
                        </div>
                        <div class="notifications-empty" id="notificationsEmpty" style="display: none;">
                            <i class="fa-solid fa-bell-slash"></i>
                            <p>No notifications</p>
                        </div>

                        <!-- Floating Context Menu -->
                        <div id="notificationContextMenu" class="notification-context-menu" style="display: none;">
                            <!-- Dynamically populated -->
                        </div>
                    </div>

                    <!-- Comment Context View -->
                    <div id="notificationCommentView" style="display: none;">
                        <div class="notifications-header">
                            <button class="back-to-notifications-btn" id="backToNotifications" title="Back to notifications">
                                <i class="fa-solid fa-arrow-left"></i>
                            </button>
                            <h3>Comments</h3>
                        </div>
                        <div class="notification-comment-content" id="notificationCommentContent">
                            <!-- Dynamically populated -->
                        </div>
                    </div>
                </div>
            </div>
            <div class="account-menu-wrapper">
                <button class="account-btn" id="accountBtn" title="Account">
                    <div class="account-avatar">
                        <?php if (!empty($_SESSION['profile_picture'])): ?>
                            <img src="<?php echo htmlspecialchars($_SESSION['profile_picture']); ?>" alt="Avatar">
                        <?php else: ?>
                            <?php echo isset($_SESSION['display_name']) ? strtoupper(substr($_SESSION['display_name'], 0, 1)) : '?'; ?>
                        <?php endif; ?>
                    </div>
                </button>
                <div class="account-dropdown" id="accountDropdown">
                    <div class="account-dropdown-header">
                        <div class="account-dropdown-avatar">
                            <?php if (!empty($_SESSION['profile_picture'])): ?>
                                <img src="<?php echo htmlspecialchars($_SESSION['profile_picture']); ?>" alt="Avatar">
                            <?php else: ?>
                                <?php echo isset($_SESSION['display_name']) ? strtoupper(substr($_SESSION['display_name'], 0, 1)) : '?'; ?>
                            <?php endif; ?>
                        </div>
                        <div class="account-dropdown-info">
                            <div class="account-dropdown-name" id="accountUsername"><?php echo $_SESSION['display_name'] ?? 'Guest'; ?></div>
                            <div class="account-dropdown-email" id="accountEmail">Loading...</div>
                        </div>
                    </div>
                    <div class="account-dropdown-divider"></div>
                    <a href="accountmanagement.php" class="account-dropdown-item">
                        <i class="fa-solid fa-gauge-high"></i>
                        <span>Creator Studio</span>
                    </a>
                    <a href="history.php" class="account-dropdown-item">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                        <span>History</span>
                    </a>
                    <div class="account-dropdown-divider"></div>
                    <a href="xpoints.php" class="account-dropdown-item">
                        <span class="xpoints-icon"><?php include __DIR__ . '/xpointsicon.html'; ?></span>
                        <span>XPoints</span>
                    </a>
                    <a href="pro.php" class="account-dropdown-item">
                        <span class="pro-icon"><?php include __DIR__ . '/proicon.html'; ?></span>
                        <span>Loop Pro</span>
                    </a>
                    <a href="floxsync.php" class="account-dropdown-item">
                        <span class="floxsync-icon" style="color: #3ea6ff; display: flex; align-items: center; justify-content: center; width: 24px; height: 24px; margin-right: 12px;"><?php include __DIR__ . '/floxsyncicon.html'; ?></span>
                        <span>FloxSync</span>
                    </a>
                    <div class="account-dropdown-divider"></div>
                    <a href="settings.php" class="account-dropdown-item">
                        <i class="fa-solid fa-gear"></i>
                        <span>Settings</span>
                    </a>
                    <div class="account-dropdown-item" id="logoutBtn">
                        <i class="fa-solid fa-right-from-bracket"></i>
                        <span>Sign Out</span>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <!-- Guest UI - Sign In button -->
            <a href="select_profile.php" class="create-btn" style="text-decoration: none; background: var(--accent-color);" title="Sign In">
                <i class="fa-solid fa-right-to-bracket"></i>
                <span>Sign In</span>
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Premium Suggestions Row -->
    <?php if (basename($_SERVER['PHP_SELF']) == 'home.php'): ?>
    <div class="suggestions-row-wrapper" id="suggestionsRow" style="display: none;">
        <div class="suggestions-container" id="suggestionsContainer">
            <!-- Dynamically populated by home.js -->
        </div>
    </div>
    <?php endif; ?>
</header>

<script>
    window.myUserId = <?php echo $_SESSION['user_id'] ?? 'null'; ?>;
</script>
<script src="stories.js"></script>
