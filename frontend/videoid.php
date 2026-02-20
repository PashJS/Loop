<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);
session_start();
include '../backend/config.php';

$videoId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$watchlistType = isset($_GET['list']) ? $_GET['list'] : '';

if ($videoId === 0) {
    header('Location: home.php');
    exit;
}
?>
<script>window.isGuest = <?php echo isset($_SESSION['user_id']) ? 'false' : 'true'; ?>;</script>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loop - Video</title>
    <!-- Core App Styles -->
    <link rel="stylesheet" href="home.css">
    <link rel="stylesheet" href="layout.css?v=3">
    <link rel="stylesheet" href="video.css">
    <link rel="stylesheet" href="video_overlay.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* CRITICAL GRID FIX */
        .video-page-container {
            display: grid !important;
            grid-template-columns: 1fr 400px !important;
            gap: 24px;
            max-width: 1750px;
            margin: 0 auto;
            align-items: start;
            width: 100%;
        }

        .video-primary-col {
            min-width: 0;
            width: 100%;
        }

        .video-secondary-col {
            /* Handled in video.css */
        }

        /* Responsive Grid */
        @media (max-width: 1200px) {
            .video-page-container {
                grid-template-columns: 1fr 340px !important;
            }
            .video-secondary-col {
                width: 340px;
            }
        }

        @media (max-width: 1000px) {
            .video-page-container {
                grid-template-columns: 1fr !important;
            }
            .video-secondary-col {
                width: 100%;
                position: static;
            }
        }

        /* Settings Menu Centering Fix */
        .video-settings-menu {
            position: absolute !important;
            top: 50% !important;
            left: 50% !important;
            transform: translate(-50%, -50%) scale(0.9) !important;
            z-index: 200 !important;
        }
        .video-settings-menu.active {
            transform: translate(-50%, -50%) scale(1) !important;
        }

        .video-player-wrapper {
            position: relative;
            width: 100%;
            height: 100%;
            overflow: hidden;
            background: #000;
        }

        /* Starfield Background */
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

        .main-content {
            background: transparent !important;
            padding-top: 100px !important; /* Margin between content and header */
            position: relative;
            z-index: 1;
        }

        .video-page-container {
            position: relative;
            z-index: 2;
        }
    </style>
</head>
<body>
    <canvas id="starfield"></canvas>
    <?php include 'header.php'; ?>
    
    <div class="app-layout">
        <?php include 'sidebar.php'; ?>
        
        <main class="main-content">
            <div class="video-page-container">
                <!-- Primary Column (Video + Info + Comments) -->
                <div class="video-primary-col">
                    <div class="video-container">
                        <!-- Loading State -->
                        <div class="video-loading" id="videoLoading">
                            <div class="spinner"></div>
                            <p>Loading video...</p>
                        </div>

                        <!-- Video Player Container -->
                        <div class="video-player-container" id="videoPlayerContainer" style="display: none;">



                            <div class="video-player-wrapper">

                                <video class="video-player" id="videoPlayer" data-flox="video.player" playsinline preload="metadata" crossorigin="anonymous">
                                    <!-- Source added by JS -->
                                    Your browser does not support the video tag.
                                </video>
                                <div id="videoCaptionsDisplay" class="video-captions-display"></div>
                                
                                <!-- NEW: Modern Overlay Controls -->
                                <div class="video-controls-overlay" id="videoControlsOverlay" data-flox="video.controls">
                                    <!-- Top Header info -->
                                    <div class="video-player-header" id="videoPlayerHeader">
                                        <div class="video-player-title-info">
                                            <h2 class="video-player-title" id="videoPlayerTitle"></h2>
                                            <p class="video-player-creator" id="videoPlayerCreator"></p>
                                        </div>
                                        <div class="video-header-right">
                                            <button class="video-control-btn info-btn" title="Info">
                                                <i class="fa-solid fa-circle-info"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Center Play/Pause -->
                                    <div class="video-center-controls">
                                        <button class="video-center-play-btn" id="videoCenterPlayBtn" title="Play/Pause">
                                            <i class="fa-solid fa-play" id="centerPlayIcon"></i>
                                            <i class="fa-solid fa-pause" id="centerPauseIcon" style="display: none;"></i>
                                        </button>
                                    </div>
                                    
                                    <!-- Side Nav Buttons -->
                                    <button class="video-nav-btn video-prev-btn" id="videoPrevBtn" title="Previous Video">
                                        <i class="fa-solid fa-chevron-left"></i>
                                    </button>
                                    <button class="video-nav-btn video-next-btn" id="videoNextBtn" title="Next Video">
                                        <i class="fa-solid fa-chevron-right"></i>
                                    </button>

                                    <!-- Bottom Right Action Buttons (YouTube Style) -->
                                    <div class="video-overlay-actions">
                                        <button class="overlay-action-btn" id="overlayLikeBtn" title="<?php echo isset($_SESSION['user_id']) ? 'Like' : 'Sign in to like'; ?>">
                                            <i class="fa-solid fa-thumbs-up"></i>
                                            <span id="overlayLikeCount">0</span>
                                        </button>
                                        <button class="overlay-action-btn" id="overlayDislikeBtn" title="<?php echo isset($_SESSION['user_id']) ? 'Dislike' : 'Sign in to dislike'; ?>">
                                            <i class="fa-solid fa-thumbs-down"></i>
                                        </button>
                                        <button class="overlay-action-btn" id="overlayCommentBtn">
                                            <i class="fa-solid fa-comment"></i>
                                            <span id="overlayCommentCount">0</span>
                                        </button>
                                        <button class="overlay-action-btn" id="overlayShareBtn">
                                            <i class="fa-solid fa-share"></i>
                                        </button>
                                        <button class="overlay-action-btn" id="overlayMoreBtn">
                                            <i class="fa-solid fa-ellipsis"></i>
                                        </button>
                                        
                                        <!-- Subscribe Button Overlay -->
                                        <?php if (isset($_SESSION['user_id'])): ?>
                                        <button class="btn-subscribe-overlay" id="overlaySubscribeBtn">SUBSCRIBE</button>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Bottom Controls Bar -->
                                    <div class="video-player-bottom">
                                        <div class="video-progress-container" id="videoProgressContainer">
                                            <div class="chapter-title-preview" id="chapterTitlePreview"></div>
                                            <div class="video-progress-bar" id="videoProgressBar">
                                                <div class="video-progress-filled" id="videoProgressFilled"></div>
                                                <div class="video-chapters-container" id="videoChaptersContainer"></div>
                                                <div class="video-progress-hover-preview" id="videoProgressHoverPreview">
                                                    <div class="video-preview-thumbnail" id="videoPreviewThumbnail"></div>
                                                    <div class="video-preview-time" id="videoPreviewTime"></div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Modern Bottom Control Row -->
                                        <div class="video-controls-row">
                                            <div class="controls-left">
                                                <button class="video-control-btn play-btn" id="videoPauseBtn">
                                                    <i class="fa-solid fa-play" id="playIcon"></i>
                                                    <i class="fa-solid fa-pause" id="pauseIcon" style="display: none;"></i>
                                                </button>
                                                <button class="video-control-btn" id="videoNextBtnBottom" title="Next (SHIFT+N)">
                                                    <i class="fa-solid fa-forward-step"></i>
                                                </button>
                                                <div class="video-volume-container">
                                                    <button class="video-control-btn" id="videoMuteBtn">
                                                        <i class="fa-solid fa-volume-high" id="volumeHigh"></i>
                                                        <i class="fa-solid fa-volume-low" id="volumeLow" style="display: none;"></i>
                                                        <i class="fa-solid fa-volume-xmark" id="volumeMuted" style="display: none;"></i>
                                                    </button>
                                                    <div class="volume-slider-wrapper">
                                                        <div class="volume-slider" id="volumeSlider">
                                                            <div class="volume-slider-filled" id="volumeSliderFilled"></div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="video-time-display">
                                                    <span id="videoCurrentTime">0:00</span>
                                                    <span class="time-sep">/</span>
                                                    <span id="videoDuration">0:00</span>
                                                </div>
                                                <div class="video-mini-title" id="videoMiniTitle"></div>
                                            </div>
                                            
                                            <div class="controls-right">
                                                <div class="autoplay-toggle-wrapper">
                                                    <span class="autoplay-label">Autoplay</span>
                                                    <label class="switch">
                                                        <input type="checkbox" id="autoplayToggle" checked>
                                                        <span class="slider round"></span>
                                                    </label>
                                                </div>
                                                <!-- Mini Queue Toggle -->
                                                <button class="video-control-btn" id="miniQueueBtn" title="Mini Queue">
                                                    <i class="fa-solid fa-list-ul"></i>
                                                </button>
                                                <button class="video-control-btn" id="videoCaptionsBtn" title="Subtitles/closed captions (c)">
                                                    <i class="fa-solid fa-closed-captioning"></i>
                                                </button>
                                                <button class="video-control-btn" id="videoSettingsBtn" title="Settings">
                                                    <i class="fa-solid fa-gear"></i>
                                                </button>
                                                <button class="video-control-btn" id="videoFullscreenBtn" title="Full screen (f)">
                                                    <i class="fa-solid fa-expand" id="fullscreenIcon"></i>
                                                    <i class="fa-solid fa-compress" id="exitFullscreenIcon" style="display: none;"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Video Settings Menu (Liquid Glass) -->
                                <div class="video-settings-menu" id="videoSettingsMenu">
                                    <div class="settings-header">
                                        <h3>Video Settings</h3>
                                        <button class="close-settings" id="closeSettingsBtn"><i class="fa-solid fa-xmark"></i></button>
                                    </div>
                                    
                                    <!-- Resolution -->
                                    <div class="settings-group">
                                        <div class="settings-label"><i class="fa-solid fa-film"></i> Resolution</div>
                                        <div class="settings-options">
                                            <button class="settings-option active" data-res="1080">1080p</button>
                                            <button class="settings-option" data-res="720">720p</button>
                                            <button class="settings-option" data-res="480">480p</button>
                                            <button class="settings-option" data-res="auto">Auto</button>
                                        </div>
                                    </div>

                                    <!-- Motion (Playback Speed) -->
                                    <div class="settings-group">
                                        <div class="settings-label"><i class="fa-solid fa-gauge-high"></i> Motion</div>
                                        <div class="settings-options">
                                            <button class="settings-option" data-speed="0.5">0.5x</button>
                                            <button class="settings-option active" data-speed="1">Normal</button>
                                            <button class="settings-option" data-speed="1.5">1.5x</button>
                                            <button class="settings-option" data-speed="2">2.0x</button>
                                        </div>
                                    </div>

                                        <!-- Sleep Timer -->
                                        <div class="settings-group">
                                            <div class="settings-label"><i class="fa-solid fa-moon"></i> Sleep Timer</div>
                                            <div class="settings-options">
                                                <button class="settings-option active" data-sleep="off">Off</button>
                                                <div class="manual-sleep-input">
                                                    <input type="number" id="manualSleepMinutes" placeholder="Minutes" min="1" max="1440">
                                                    <button class="settings-option" id="setManualSleep">Set</button>
                                                </div>
                                            </div>
                                            <div id="activeSleepTimer" class="active-sleep-status" style="display: none;">
                                                Timer active: <span id="sleepCountdown">00:00</span>
                                            </div>
                                        </div>
                                </div>

                                <!-- NEW: Mini Queue Panel (No Glass, No Blur) -->
                                <div class="mini-queue-panel" id="miniQueuePanel" style="display: none;">
                                    <div class="mini-queue-header">
                                        <h3 class="queue-title">Queue <span id="miniQueueCount">(0)</span></h3>
                                        <button class="queue-close-btn" id="closeMiniQueueBtn"><i class="fa-solid fa-xmark"></i></button>
                                    </div>
                                    <div class="mini-queue-list" id="miniQueueList">
                                        <!-- Populated via JS -->
                                        <div class="queue-empty-state">Queue is empty</div>
                                    </div>
                                </div>

                                <!-- NEW: Slide-in Comments Panel inside Player -->
                                <div class="video-comments-overlay" id="videoCommentsOverlay" style="display: none;">
                                    <div class="comments-header">
                                        <h3>Comments <span id="overlayCommentsHeaderCount">0</span></h3>
                                        <button id="closeOverlayComments" title="Close Panel"><i class="fa-solid fa-xmark"></i> Close</button>
                                    </div>
                                    <!-- Comment Form -->
                                    <div class="overlay-comment-form">
                                        <?php if (isset($_SESSION['user_id'])): ?>
                                        <div class="comment-input-wrapper">
                                            <input type="text" id="overlayCommentInput" placeholder="Add a comment...">
                                            <button id="overlayCommentSend"><i class="fa-solid fa-paper-plane"></i></button>
                                        </div>
                                        <?php else: ?>
                                        <a href="select_profile.php" style="display: block; text-align: center; color: var(--accent-color); padding: 12px; text-decoration: none; font-weight: 500;">Sign in to comment</a>
                                        <?php endif; ?>
                                    </div>
                                    <!-- Comments List -->
                                    <div class="overlay-comments-list" id="overlayCommentsList"></div>
                                </div>

                                <!-- NEW: Slide-in Info Panel inside Player -->
                                <div class="video-info-overlay" id="videoInfoOverlay" style="display: none;">
                                    <div class="info-header">
                                        <h3>Video Information</h3>
                                        <button id="closeOverlayInfo" title="Close Panel"><i class="fa-solid fa-xmark"></i> Close</button>
                                    </div>
                                    <div class="overlay-info-content">
                                        <div class="info-section">
                                            <h4>About</h4>
                                            <p id="overlayVideoDescription" class="overlay-desc"></p>
                                            <div class="info-meta">
                                                <span id="overlayVideoDate"></span>
                                                <span id="overlayVideoViews"></span>
                                            </div>
                                            <div class="overlay-hashtags" id="overlayHashtags"></div>
                                        </div>
                                        <div class="info-section">
                                            <h4>More from this creator</h4>
                                            <div id="overlayCreatorVideos" class="creator-videos-mini"></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- NEW: Sleep Timer Wind Down Overlay -->
                                <div class="sleep-timer-overlay" id="sleepTimerOverlay" style="display: none;">
                                    <div class="sleep-overlay-glass">
                                        <div class="sleep-icon-wrapper">
                                            <i class="fa-solid fa-moon"></i>
                                        </div>
                                        <h2>Time to wind down 🌙</h2>
                                        <p>You might want to close Loop and get some rest.<br>Have a good night 💙</p>
                                        <div class="sleep-overlay-actions">
                                            <button id="sleepStayBtn" class="sleep-action-btn secondary">Dismiss</button>
                                            <button id="sleepHomeBtn" class="sleep-action-btn primary">Go Home</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Video Info Container (Image 2 style) -->
                    <div class="video-info-container" id="videoInfoContainer" data-flox="video.info">
                        <div class="video-hashtags" id="videoHashtags"></div>
                        <h1 class="video-title" id="videoTitle"></h1>
                        
                        <div class="video-author-and-actions">
                            <div class="video-author-group">
                                <div class="video-author-avatar" id="authorAvatar"></div>
                                <div class="video-author-info">
                                    <div class="video-author-name" id="authorName"></div>
                                    <div class="video-author-subs" id="authorSubs">0 subscribers</div>
                                </div>
                                <?php if (isset($_SESSION['user_id'])): ?>
                                <button class="subscribe-btn" id="subscribeBtn" style="display: none;">
                                    <i class="fa-solid fa-bell"></i>
                                    <span class="subscribe-text">Subscribe</span>
                                </button>
                                <?php else: ?>
                                <a href="select_profile.php" class="subscribe-btn guest" style="text-decoration: none; display: flex; align-items: center; gap: 8px;">
                                    <i class="fa-solid fa-user-plus"></i>
                                    <span>Sign in to subscribe</span>
                                </a>
                                <?php endif; ?>
                            </div>

                            <div class="video-actions-group">
                                <div class="grouped-actions-pill">
                                    <button class="action-pill-btn like-btn" id="videoLikeBtn" title="<?php echo isset($_SESSION['user_id']) ? 'I like this' : 'Sign in to like this video'; ?>">
                                        <i class="fa-solid fa-thumbs-up" id="likeIcon"></i>
                                        <span id="likeCount">0</span>
                                    </button>
                                    <div class="pill-divider"></div>
                                    <button class="action-pill-btn dislike-btn" id="videoDislikeBtn" title="<?php echo isset($_SESSION['user_id']) ? 'I dislike this' : 'Sign in to dislike this video'; ?>">
                                        <i class="fa-solid fa-thumbs-down" id="dislikeIcon"></i>
                                    </button>
                                </div>
                                
                                <button class="action-pill-btn secondary-pill" id="shareBtn">
                                    <i class="fa-solid fa-share"></i>
                                    <span>Share</span>
                                </button>
                                
                                <button class="action-pill-btn secondary-pill" id="downloadBtn">
                                    <i class="fa-solid fa-download"></i>
                                    <span>Download</span>
                                </button>
                                
                                <button class="action-pill-btn secondary-pill" id="saveBtn">
                                    <i class="fa-solid fa-bookmark"></i>
                                    <span>Save</span>
                                </button>
                                
                                 <div class="liquid-dropdown-wrapper">
                                    <button class="action-pill-btn secondary-pill icon-only" id="moreOptionsBtn">
                                        <i class="fa-solid fa-ellipsis"></i>
                                    </button>
                                    <div class="liquid-dropdown-menu" id="moreOptionsMenu">
                                        <div class="liquid-dropdown-bg"></div>
                                        <div class="liquid-dropdown-content">
                                            <button class="dropdown-item"><i class="fa-solid fa-flag"></i> Report</button>
                                            <button class="dropdown-item"><i class="fa-solid fa-circle-question"></i> Help</button>
                                            <button class="dropdown-item"><i class="fa-solid fa-code"></i> Embed</button>
                                            <div class="dropdown-sep"></div>
                                            <button class="dropdown-item"><i class="fa-solid fa-circle-info"></i> Detailed Stats</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="video-description-wrapper">
                            <div class="video-stats-inline">
                                <span class="video-views" id="videoViews">0 views</span>
                                <span class="video-date" id="videoDate"></span>
                            </div>
                            <div class="video-description" id="videoDescription"></div>
                            <button class="description-more-btn" id="descriptionMoreBtn">Show more</button>
                        </div>
                    </div>

                    <!-- Main Comments Section -->
                    <div class="comments-section" id="commentsSection">
                        <div class="comments-header">
                            <h2 class="comments-title">
                                <i class="fa-solid fa-comments"></i>
                                <span id="commentsCount">0</span> Comments
                            </h2>
                            <div class="sort-dropdown-wrapper">
                                <button class="sort-trigger" id="commentSortTrigger">
                                    <i class="fa-solid fa-arrow-down-short-wide"></i>
                                    <span>Sort by</span>
                                </button>
                                <div class="sort-menu" id="commentSortMenu">
                                    <button class="sort-option active" data-sort="newest">Newest first</button>
                                    <button class="sort-option" data-sort="oldest">Oldest first</button>
                                    <button class="sort-option" data-sort="most_liked">Most liked</button>
                                    <button class="sort-option" data-sort="less_liked">Least liked</button>
                                    <button class="sort-option" data-sort="timed">Timed comments</button>
                                </div>
                            </div>
                        </div>
                        <div class="comment-form-container">
                            <?php if (isset($_SESSION['user_id'])): ?>
                            <div class="comment-avatar" id="commentFormAvatar">
                                <?php if (!empty($_SESSION['profile_picture'])): ?>
                                    <img src="<?php echo htmlspecialchars($_SESSION['profile_picture']); ?>" alt="Avatar">
                                <?php else: ?>
                                    <?php echo isset($_SESSION['username']) ? strtoupper(substr($_SESSION['username'], 0, 1)) : 'U'; ?>
                                <?php endif; ?>
                            </div>
                            <form class="comment-form" id="commentForm">
                                <textarea 
                                    class="comment-input" 
                                    id="commentInput" 
                                    placeholder="Add a comment..." 
                                    rows="2"
                                    maxlength="1000"
                                    style="resize: none;"
                                ></textarea>
                                <div class="comment-form-actions">
                                    <button type="button" class="btn-cancel" id="cancelCommentBtn">Cancel</button>
                                    <button type="submit" class="btn-submit" id="submitCommentBtn">Comment</button>
                                </div>
                            </form>
                            <?php else: ?>
                            <!-- Guest prompt -->
                            <div class="guest-comment-prompt" style="display: flex; align-items: center; gap: 12px; padding: 16px; background: var(--tertiary-color); border-radius: 12px; border: 1px solid var(--border-color);">
                                <i class="fa-solid fa-comment" style="font-size: 20px; color: var(--text-secondary);"></i>
                                <a href="select_profile.php" style="color: var(--accent-color); text-decoration: none; font-weight: 500;">Sign in to comment</a>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="comments-list" id="commentsList"></div>
                    </div>
                </div>

                <!-- Secondary Column (Up Next / Recommendations) -->
                <aside class="video-secondary-col">
                        <!-- NEW: Watchlist Context Sidebar -->
                        <div class="watchlist-sidebar-panel" id="watchlistSidebarPanel" style="display: none;">
                            <div class="watchlist-sidebar-header">
                                <div class="watchlist-header-info">
                                    <h3 id="watchlistSidebarTitle">Continue Watchlist</h3>
                                    <span id="watchlistSidebarCount">0 videos</span>
                                </div>
                                <button class="close-watchlist-panel" id="closeWatchlistPanel"><i class="fa-solid fa-xmark"></i></button>
                            </div>
                            <div class="watchlist-sidebar-items" id="watchlistSidebarItems">
                                <!-- Populated by JS -->
                            </div>
                        </div>

                        <div class="up-next-section" id="upNextSection">
                            <div class="up-next-header">
                                <h3>Up Next</h3>
                                <div class="autoplay-toggle-wrapper">
                                    <span>Autoplay</span>
                                    <label class="switch">
                                        <input type="checkbox" id="autoplayToggle" checked>
                                        <span class="slider round"></span>
                                    </label>
                                </div>
                            </div>
                            <div class="recommendations-list" id="recommendationsList">
                                <!-- Populated by JS -->
                                <div class="recommendation-skeleton"></div>
                                <div class="recommendation-skeleton"></div>
                                <div class="recommendation-skeleton"></div>
                            </div>
                        </div>
                </aside>
            </div>
        </main>
    </div>

    <?php include 'mobile_footer.php'; ?>

    <!-- Next Video Overlay (Premium) -->
    <div class="next-video-overlay" id="nextVideoOverlay">
        <div class="next-overlay-content">
            <h3>Up Next In</h3>
            <div class="countdown-wrapper">
                <svg class="countdown-svg">
                    <circle class="countdown-bg" cx="40" cy="40" r="36"></circle>
                    <circle class="countdown-progress" cx="40" cy="40" r="36"></circle>
                </svg>
                <div class="next-timer" id="nextTimer">8</div>
            </div>
            <div class="next-thumb-container">
                <img id="nextThumb" src="" alt="Thumbnail">
            </div>
            <h4 id="nextTitle">Loading next video...</h4>
            <div class="next-actions">
                <button id="nextCancel">Cancel</button>
                <button id="nextPlayNow"><i class="fa-solid fa-play"></i> Play Now</button>
            </div>
        </div>
    </div>

    <script>
        let videoId = <?php echo $videoId; ?>;
        let watchlistType = '<?php echo $watchlistType; ?>';
        window.currentUserId = <?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'null'; ?>;
    </script>
    <script src="theme.js"></script>
    <script src="popup.js"></script>
    <script src="search-history.js"></script>
    <script src="voice_search.js"></script>
    <script src="icon-replace.js"></script>
    <script src="notifications.js"></script>
    <script src="mobile-search.js"></script>
    <script src="video.js?v=<?php echo time(); ?>"></script>
    <!-- Liquid Surface Tension Engine (SVG Gooey Filter) -->
    <svg style="position: absolute; width: 0; height: 0; pointer-events: none;" xmlns="http://www.w3.org/2000/svg" version="1.1">
        <defs>
            <filter id="liquidGoo">
                <feGaussianBlur in="SourceGraphic" stdDeviation="10" result="blur" />
                <feColorMatrix in="blur" mode="matrix" values="1 0 0 0 0  0 1 0 0 0  0 0 1 0 0  0 0 0 19 -9" result="goo" />
                <feComposite in="SourceGraphic" in2="goo" operator="atop" />
            </filter>
            <!-- New surface tension filter specifically for detachment effects -->
            <filter id="surfaceTension">
                <feGaussianBlur in="SourceGraphic" stdDeviation="8" result="blur" />
                <feColorMatrix in="blur" mode="matrix" values="1 0 0 0 0  0 1 0 0 0  0 0 1 0 0  0 0 0 25 -12" result="goo" />
            </filter>
        </defs>
    </svg>

    <!-- Report Video Modal (Liquid Glass) -->
    <div class="report-modal-overlay" id="reportModalOverlay">
        <div class="report-modal">
            <div class="report-modal-bg"></div>
            <div class="report-modal-content">
                <div class="report-header">
                    <h3>Report Video</h3>
                    <button class="close-report-btn" id="closeReportModal"><i class="fa-solid fa-xmark"></i></button>
                </div>
                
                <form id="reportVideoForm">
                    <div class="report-group">
                        <label>Reason for reporting</label>
                        <div class="liquid-select-container" id="reportReasonSelect">
                            <div class="liquid-select-trigger">
                                <span id="selectedReasonText">Select a reason...</span>
                                <i class="fa-solid fa-chevron-down"></i>
                            </div>
                            <div class="liquid-select-options">
                                <div class="liquid-select-option" data-value="Abuse or Harassment">Abuse or Harassment</div>
                                <div class="liquid-select-option" data-value="Inappropriate Content">Inappropriate Content</div>
                                <div class="liquid-select-option" data-value="Spam or Misleading">Spam or Misleading</div>
                                <div class="liquid-select-option" data-value="Copyright Infringement">Copyright Infringement</div>
                                <div class="liquid-select-option" data-value="Other">Other</div>
                            </div>
                            <input type="hidden" id="reportReason" name="reason" value="">
                        </div>
                    </div>

                    <div class="report-group">
                        <label>Additional details (optional)</label>
                        <textarea id="reportDescription" placeholder="Please describe the issue in detail..." rows="4"></textarea>
                    </div>

                    <div class="report-actions">
                        <button type="button" class="report-cancel-btn" id="cancelReport">Cancel</button>
                        <button type="submit" class="report-submit-btn" id="submitReport">Submit Report</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Report Comment Modal (Liquid Glass) -->
    <div class="comment-report-overlay" id="commentReportOverlay">
        <div class="comment-report-content">
            <h2 style="margin-bottom: 20px; font-weight: 800; font-size: 24px;">Report Comment</h2>
            <div class="report-form-group">
                <label>Reason</label>
                <select id="commentReportReason" class="liquid-input reason-select">
                    <option value="Abuse or Harassment">Abuse or Harassment</option>
                    <option value="Inappropriate Content">Inappropriate Content</option>
                    <option value="Spam or Misleading">Spam or Misleading</option>
                    <option value="Copyright Infringement">Copyright Infringement</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            <div class="report-form-group">
                <label>Additional Details</label>
                <textarea id="commentReportDetails" class="liquid-input" rows="4" placeholder="How does this comment violate our rules?"></textarea>
            </div>
            <div class="report-modal-actions">
                <button class="report-btn cancel" id="cancelCommentReport">Cancel</button>
                <button class="report-btn submit" id="submitCommentReportBtn">Send Report</button>
            </div>
        </div>
    </div>
    <script src="video.js"></script>
    <script src="theme.js"></script>

    <!-- Starfield Animation -->
    <script>
        (function() {
            const canvas = document.getElementById('starfield');
            if (!canvas) return;
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
        })();
    </script>
</body>
</html>
