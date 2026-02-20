<?php
// Get current page to set active state
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<aside class="side-nav" data-flox="sidebar">
    <div class="side-nav-content">
        <a href="home.php" class="side-nav-item <?php echo ($currentPage == 'home.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-house"></i>
            <span>Home</span>
        </a>
        <a href="clips.php" class="side-nav-item <?php echo ($currentPage == 'clips.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-clapperboard"></i>
            <span>Clips</span>
        </a>
        <a href="chat.php" class="side-nav-item <?php echo ($currentPage == 'chat.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-comments"></i>
            <span>Chats</span>
        </a>
        
        <?php if (isset($_SESSION['user_id'])): ?>
        <div class="side-nav-divider"></div>

        <a href="history.php" class="side-nav-item <?php echo ($currentPage == 'history.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-clock-rotate-left"></i>
            <span>History</span>
        </a>
        <a href="watchlist.php" class="side-nav-item <?php echo ($currentPage == 'watchlist.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-layer-group"></i>
            <span>Watchlists</span>
        </a>
        <a href="notifications.php" class="side-nav-item <?php echo ($currentPage == 'notifications.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-bell"></i>
            <span>Notifications</span>
        </a>
        <a href="xpoints_market.php" class="side-nav-item <?php echo ($currentPage == 'xpoints_market.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-cart-shopping"></i>
            <span>XPoints Market</span>
        </a>
        <a href="extensions.php" class="side-nav-item <?php echo ($currentPage == 'extensions.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-puzzle-piece"></i>
            <span>Extensions</span>
        </a>
        <div class="side-nav-divider"></div>
        <a href="accountmanagement.php" class="side-nav-item <?php echo ($currentPage == 'accountmanagement.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-circle-user"></i>
            <span>Account</span>
        </a>
        <?php endif; ?>
    </div>
</aside>
