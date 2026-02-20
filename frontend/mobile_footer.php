<?php
// Get current page to set active state
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<nav class="mobile-footer">
    <a href="home.php" class="footer-btn <?php echo ($currentPage == 'home.php') ? 'active' : ''; ?>">
        <i class="fa-solid fa-house"></i>
        <span>Home</span>
    </a>
    <a href="chat.php" class="footer-btn <?php echo ($currentPage == 'chat.php') ? 'active' : ''; ?>">
        <i class="fa-solid fa-comments"></i>
        <span>Messages</span>
    </a>
    <a href="create_story.php" class="footer-btn create-btn-mobile <?php echo ($currentPage == 'create_story.php') ? 'active' : ''; ?>">
        <div class="create-icon">
            <i class="fa-solid fa-plus"></i>
        </div>
    </a>
    <a href="clips.php" class="footer-btn <?php echo ($currentPage == 'clips.php') ? 'active' : ''; ?>">
        <i class="fa-solid fa-clapperboard"></i>
        <span>Clips</span>
    </a>
    <?php if (isset($_SESSION['user_id'])): ?>
    <a href="accountmanagement.php" class="footer-btn <?php echo ($currentPage == 'accountmanagement.php' || $currentPage == 'profile.php') ? 'active' : ''; ?>">
        <i class="fa-solid fa-user"></i>
        <span>You</span>
    </a>
    <?php else: ?>
    <a href="loginb.php" class="footer-btn <?php echo ($currentPage == 'loginb.php') ? 'active' : ''; ?>">
        <i class="fa-solid fa-user"></i>
        <span>You</span>
    </a>
    <?php endif; ?>
</nav>
<script src="mobile_footer_scroll.js"></script>
