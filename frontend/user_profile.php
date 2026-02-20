<?php
session_start();
$profileUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$profileUsername = isset($_GET['username']) ? trim($_GET['username']) : '';

if ($profileUserId <= 0 && empty($profileUsername)) {
    header('Location: home.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loop - User Profile</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="home.css?v=1"/>
    <link rel="stylesheet" href="layout.css?v=3"/>
    <link rel="stylesheet" href="user_profile.css?v=2"/>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="app-layout">
        <?php include 'sidebar.php'; ?>
        
        <main class="main-content">
            <!-- Main Content -->
            <div class="profile-main">
        <div class="profile-container" id="profileContainer">
            <div class="loading-spinner" id="loadingSpinner">
                <div class="spinner"></div>
                <p>Loading profile...</p>
            </div>
            
            <div class="profile-content" id="profileContent" style="display: none;">
                <!-- Profile Banner -->
                <div class="profile-banner-container" id="profileBanner">
                    <!-- Banner image will be set here -->
                </div>

                <!-- Profile Header -->
                <div class="profile-header-section">
                    <div class="profile-header-avatar" id="profileHeaderAvatar">
                        <div class="avatar-placeholder">Loading...</div>
                    </div>
                    <div class="profile-header-info">
                        <div class="profile-header-main">
                            <h1 class="profile-display-name" id="profileDisplayName">Loading...</h1>
                            <div class="profile-sub-info">
                                <span id="profileHandle">@username</span>
                                <span class="dot-separator">•</span>
                                <span id="profileSubCount">0 subscribers</span>
                                <span class="dot-separator">•</span>
                                <span id="profileVideoCount">0 videos</span>
                            </div>
                            <div class="profile-bio-container">
                                <p class="profile-bio" id="profileBio"></p>
                                <span class="more-toggle">...more</span>
                            </div>
                        </div>
                        <div class="profile-header-actions" id="headerActions">
                            <!-- Action button injected here -->
                        </div>
                    </div>
                </div>

                <!-- Profile Tabs Navigation -->
                <div class="profile-tabs-nav">
                    <button class="tab-btn active" data-tab="videos">
                        <i class="fa-solid fa-play"></i> Videos
                    </button>
                    <button class="tab-btn" data-tab="clips">
                        <i class="fa-solid fa-bolt"></i> Clips
                    </button>
                    <button class="tab-btn" data-tab="about">
                        <i class="fa-solid fa-circle-info"></i> About
                    </button>
                </div>

                <!-- Tab Contents -->
                <div class="profile-tab-content active" id="videosTab">
                    <div class="section-filters-chips">
                        <button class="chip-btn active" data-sort="latest">Latest</button>
                        <button class="chip-btn" data-sort="popular">Popular</button>
                        <button class="chip-btn" data-sort="oldest">Oldest</button>
                    </div>
                    <div class="videos-grid" id="profileVideosGrid"></div>
                    <div class="empty-state" id="emptyVideos" style="display: none;">
                        <i class="fa-solid fa-film"></i>
                        <h2>No videos yet</h2>
                        <p>This creator hasn't shared any videos yet.</p>
                    </div>
                </div>

                <div class="profile-tab-content" id="clipsTab">
                    <div class="section-header">
                        <h2 class="section-title">Clips</h2>
                    </div>
                    <div class="clips-grid" id="profileClipsGrid"></div>
                    <div class="empty-state" id="emptyClips">
                        <i class="fa-solid fa-clapperboard"></i>
                        <h2>No clips yet</h2>
                        <p>This creator hasn't shared any clips yet.</p>
                    </div>
                </div>

                <div class="profile-tab-content" id="aboutTab">
                    <div class="section-header">
                        <h2 class="section-title">About</h2>
                    </div>
                    <div class="about-card">
                        <div class="about-item">
                            <i class="fa-solid fa-calendar-days"></i>
                            <div>
                                <h3>Joined Loop</h3>
                                <p id="aboutJoinDate">--</p>
                            </div>
                        </div>
                        <div class="about-item">
                            <i class="fa-solid fa-chart-line"></i>
                            <div>
                                <h3>Total Views</h3>
                                <p id="aboutTotalViews">0 views</p>
                            </div>
                        </div>
                        <div class="about-description">
                            <h3>Description</h3>
                            <p id="aboutFullBio">No channel description provided.</p>
                        </div>
                    </div>
                </div>
                </div> <!-- profile-content -->
            </div> <!-- profile-container -->
        </div> <!-- profile-main -->
    </main>
</div> <!-- app-layout -->

    <!-- Mobile Footer Navigation -->
    <?php include 'mobile_footer.php'; ?>

    <script>

        const profileUserId = <?php echo $profileUserId; ?>;
        const profileUsername = '<?php echo addslashes($profileUsername); ?>';
    </script>
    <script src="theme.js"></script>
    <script src="popup.js?v=1"></script>
    <script src="search-history.js?v=1"></script>
    <script src="icon-replace.js?v=1"></script>
    <script src="user_profile.js?v=2"></script>
</body>
</html>

