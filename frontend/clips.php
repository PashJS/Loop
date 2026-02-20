<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FloxClips</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="home.css?v=1"/>
    <link rel="stylesheet" href="layout.css?v=2"/>
    <link rel="stylesheet" href="clips.css?v=10"/>
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="app-layout">
        <?php include 'sidebar.php'; ?>
        
        <main class="main-content">
            <div class="clips-container">
                <div class="clips-feed" id="clipsFeed">
                    <!-- Clips will be injected here -->
                    <div class="loading-spinner">
                        <i class="fa-solid fa-circle-notch fa-spin"></i>
                        <span>Fetching the best clips for you...</span>
                    </div>
                </div>

                <!-- Navigation Arrows (Desktop) -->
                <div class="clips-navigation">
                    <button class="nav-arrow up" id="navUp"><i class="fa-solid fa-arrow-up"></i></button>
                    <button class="nav-arrow down" id="navDown"><i class="fa-solid fa-arrow-down"></i></button>
                </div>
            </div>
        </main>
    </div>


    <!-- Templates -->
    <template id="clipTemplate">
        <div class="clip-item">
            <div class="clip-stage">
            <div class="clip-main-row">
                <div class="clip-info-left">
                    <div class="author-row">
                        <img src="" alt="" class="author-avatar-small">
                        <span class="author-handle"></span>
                        <button class="subscribe-btn">Subscribe</button>
                    </div>
                    <div class="clip-text-info">
                        <h3 class="clip-caption"></h3>
                        <p class="clip-description-text"></p>
                    </div>
                </div>

                <div class="clip-video-container">
                    <video class="clip-video" loop playsinline>
                        <!-- Source added via JS -->
                    </video>
                    
                    <div class="clip-video-overlay"></div>
                    <div class="ambient-video-glow">
                        <canvas class="ambient-canvas"></canvas>
                    </div>
                    
                    <!-- Top Controls Overlay -->
                    <div class="clip-top-controls">
                        <div class="top-left">
                            <button class="top-ctrl-btn play-pause-btn"><i class="fa-solid fa-pause"></i></button>
                            <button class="top-ctrl-btn volume-btn"><i class="fa-solid fa-volume-high"></i></button>
                        </div>
                        <div class="top-right">
                            <button class="top-ctrl-btn"><i class="fa-solid fa-closed-captioning"></i></button>
                            <button class="top-ctrl-btn"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                            <button class="top-ctrl-btn"><i class="fa-solid fa-expand"></i></button>
                        </div>
                    </div>

                    <!-- Blue Progress Bar -->
                    <div class="clip-progress-container">
                        <div class="clip-progress-bar"></div>
                    </div>

                    <!-- Play/Pause Ping Overlay -->
                    <div class="play-pause-overlay">
                        <i class="fa-solid fa-play"></i>
                    </div>
                </div>

                <div class="clip-sidebar">
                    <button class="action-btn like-btn">
                        <div class="action-icon">
                            <i class="fa-solid fa-thumbs-up"></i>
                        </div>
                        <span class="count">0</span>
                    </button>

                    <button class="action-btn">
                        <div class="action-icon">
                            <i class="fa-solid fa-thumbs-down"></i>
                        </div>
                        <span class="count">Dislike</span>
                    </button>

                    <button class="action-btn comment-btn">
                        <div class="action-icon">
                            <i class="fa-solid fa-comment"></i>
                        </div>
                        <span class="count">0</span>
                    </button>

                    <button class="action-btn share-btn">
                        <div class="action-icon">
                            <i class="fa-solid fa-share"></i>
                        </div>
                        <span class="count">Share</span>
                    </button>

                    <button class="action-btn">
                        <div class="action-icon">
                            <i class="fa-solid fa-rotate"></i>
                        </div>
                        <span class="count">Remix</span>
                    </button>

                    <div class="action-author-avatar-box">
                        <img src="" alt="" class="author-avatar-right">
                    </div>
                </div>

                <!-- Comments Side Panel (YouTube Style) -->
                <div class="comments-panel">
                    <div class="panel-header">
                        <div class="header-left">
                            <span class="panel-header-title">Comments</span>
                            <span class="panel-header-count">0</span>
                        </div>
                        <div class="header-right">
                            <button class="panel-ctrl-btn"><i class="fa-solid fa-bars-staggered"></i></button>
                            <button class="panel-ctrl-btn panel-close-btn"><i class="fa-solid fa-xmark"></i></button>
                        </div>
                    </div>
                    
                    <div class="panel-comments-list">
                        <!-- Loaded via JS -->
                    </div>

                    <div class="panel-input-container">
                        <div class="current-user-avatar-container"></div>
                        <div class="panel-input-wrapper">
                            <input type="text" placeholder="Add a comment..." class="panel-comment-input">
                            <button class="panel-post-btn">Post</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        </div>
    </template>



    <!-- Mobile Footer Navigation -->
    <!-- Mobile Footer Navigation -->
    <?php include 'mobile_footer.php'; ?>

    <script src="theme.js"></script>
    <script src="popup.js?v=5"></script>
    <script src="search-history.js?v=5"></script>
    <script src="notifications.js"></script>
    <script src="voice_search.js"></script>
    <script src="icon-replace.js"></script>
    <script src="mobile-search.js"></script>
    <script src="clips.js?v=6"></script>
</body>
</html>
