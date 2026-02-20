<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<aside class="settings-sidebar">
    <nav>
        <a href="settings.php" class="settings-nav-item <?php echo ($current_page == 'settings.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-user-gear"></i>
            Account
        </a>
        <a href="settings_security.php" class="settings-nav-item <?php echo ($current_page == 'settings_security.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-shield-cat"></i>
            Security
        </a>
        <a href="customization.php" class="settings-nav-item <?php echo ($current_page == 'customization.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-palette"></i>
            Customization
        </a>
        <a href="settings_notifications.php" class="settings-nav-item <?php echo ($current_page == 'settings_notifications.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-bell"></i>
            Notifications
        </a>
        <a href="settings_privacy.php" class="settings-nav-item <?php echo ($current_page == 'settings_privacy.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-shield-halved"></i>
            Privacy
        </a>
        <div style="height: 1px; background: var(--border-color); margin: 12px 0;"></div>
        <a href="floxsync.php" class="settings-nav-item">
            <span style="display: flex; align-items: center; justify-content: center; width: 24px;">
                <?php include('FloxSync/floxsyncicon.html'); ?>
            </span>
            FloxSync
        </a>
        <a href="pro.php" class="settings-nav-item">
            <span style="display: flex; align-items: center; justify-content: center; width: 24px;">
                <?php include('proicon.html'); ?>
            </span>
            Loop Pro
        </a>
    </nav>
</aside>
