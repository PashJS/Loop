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
    <title>Loop - Search</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="home.css"/>
    <link rel="stylesheet" href="layout.css?v=3"/>
    <style>
        /* ========================================
           PREMIUM SEARCH PAGE STYLES
           ======================================== */
        
        :root {
            --glass-bg: rgba(255, 255, 255, 0.03);
            --glass-border: rgba(255, 255, 255, 0.08);
            --glow-accent: rgba(62, 166, 255, 0.4);
            --glow-purple: rgba(157, 0, 255, 0.3);
        }

        /* Space theme - transparent backgrounds */
        body {
            background: #000 !important;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
            display: flex;
            flex-direction: column;
            height: 100vh;
        }

        #starfield {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            pointer-events: none;
            background: radial-gradient(circle at center, #0a0a25 0%, #000000 100%);
        }

        /* Top atmospheric glow */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 400px;
            background: radial-gradient(circle at 20% 0%, rgba(0, 113, 227, 0.15), transparent 70%);
            z-index: 0;
            pointer-events: none;
        }

        /* Fixed Glass Header Overrides to match home.php */
        .top-nav {
            position: fixed !important;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 2000;
            background: rgba(2, 2, 5, 0.4) !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05) !important;
            backdrop-filter: blur(30px);
            -webkit-backdrop-filter: blur(30px);
        }

        .app-layout {
            position: relative;
            z-index: 1;
            background: transparent !important;
            flex: 1;
            display: flex;
            margin-top: 64px; /* Account for header */
        }

        /* Side Nav transparency */
        .side-nav {
            background: rgba(2, 2, 5, 0.2) !important;
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-right: 1px solid rgba(255, 255, 255, 0.05) !important;
            height: calc(100vh - 64px) !important;
        }

        /* Fix layout for fixed header */
        .main-content {
            flex: 1;
            overflow-y: auto;
            background: transparent !important;
            position: relative;
            z-index: 1;
        }
        
        .search-page-container {
            width: 100%;
            padding: 32px 40px;
            margin: 0;
            background: transparent !important;
            animation: fadeInUp 0.8s cubic-bezier(0.2, 0, 0.2, 1);
        }

        /* ========================================
           SEARCH HEADER WITH QUERY DISPLAY
           ======================================== */
        .search-query-header {
            margin-bottom: 32px;
            padding: 24px 0;
            border-bottom: 1px solid var(--glass-border);
        }

        .search-query-text {
            font-size: 28px;
            font-weight: 300;
            color: var(--text-secondary);
        }

        .search-query-text strong {
            color: var(--text-primary);
            font-weight: 600;
        }

        .search-results-count {
            font-size: 14px;
            color: var(--text-secondary);
            margin-top: 8px;
            opacity: 0.7;
        }

        /* ========================================
           SECTION TITLES - PREMIUM STYLE
           ======================================== */
        .search-section-title {
            font-size: 22px;
            font-weight: 700;
            margin: 32px 0 20px 0;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 12px;
            position: relative;
        }

        .search-section-title::before {
            content: '';
            width: 4px;
            height: 24px;
            background: linear-gradient(180deg, var(--accent-color), var(--glow-purple));
            border-radius: 2px;
        }

        .search-section-title .count-badge {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            color: var(--text-secondary);
        }

        /* ========================================
           VIDEO GRID - PREMIUM LAYOUT
           ======================================== */
        .videos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 24px;
            margin-bottom: 48px;
            width: 100%;
        }

        .channels-grid {
            display: flex;
            gap: 24px;
            margin-bottom: 32px;
            overflow-x: auto;
            padding-bottom: 16px;
        }

        /* ========================================
           VIDEO CARDS - CYBER PREMIUM
           ======================================== */
        .video-card {
            cursor: pointer;
            border-radius: 16px;
            overflow: hidden;
            background: rgba(20, 20, 40, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.06);
            transition: all 0.3s ease;
            opacity: 0;
            animation: cardFadeIn 0.5s ease forwards;
        }

        .video-card:hover {
            transform: translateY(-8px);
            border-color: rgba(62, 166, 255, 0.3);
            background: rgba(30, 30, 60, 0.8);
            box-shadow: 0 16px 32px rgba(0, 0, 0, 0.3);
        }

        /* Staggered animation for cards */
        .video-card:nth-child(1) { animation-delay: 0.05s; }
        .video-card:nth-child(2) { animation-delay: 0.1s; }
        .video-card:nth-child(3) { animation-delay: 0.15s; }
        .video-card:nth-child(4) { animation-delay: 0.2s; }
        .video-card:nth-child(5) { animation-delay: 0.25s; }
        .video-card:nth-child(6) { animation-delay: 0.3s; }
        .video-card:nth-child(7) { animation-delay: 0.35s; }
        .video-card:nth-child(8) { animation-delay: 0.4s; }

        /* ========================================
           VIDEO THUMBNAIL - WITH PREVIEW
           ======================================== */
        .video-thumbnail {
            position: relative;
            width: 100%;
            aspect-ratio: 16/9;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            overflow: hidden;
            border-radius: 12px 12px 0 0;
        }

        .video-thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275), filter 0.3s ease;
        }

        .video-card:hover .video-thumbnail img {
            transform: scale(1.1);
            filter: brightness(1.1) saturate(1.2);
        }

        /* Video duration badge */
        .video-duration {
            position: absolute;
            bottom: 8px;
            right: 8px;
            background: rgba(0, 0, 0, 0.85);
            color: #fff;
            padding: 3px 6px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            backdrop-filter: blur(4px);
        }

        /* Play overlay on hover */
        .video-thumbnail::after {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at center, rgba(62, 166, 255, 0.3) 0%, transparent 70%);
            opacity: 0;
            transition: opacity 0.3s ease;
            pointer-events: none;
        }

        .video-card:hover .video-thumbnail::after {
            opacity: 1;
        }

        .play-icon-overlay {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) scale(0.8);
            width: 60px;
            height: 60px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }

        .play-icon-overlay i {
            color: #1a1a2e;
            font-size: 24px;
            margin-left: 4px;
        }

        .video-card:hover .play-icon-overlay {
            opacity: 1;
            transform: translate(-50%, -50%) scale(1);
        }

        /* ========================================
           VIDEO META - PREMIUM TYPOGRAPHY
           ======================================== */
        .video-meta {
            padding: 16px;
            display: flex;
            gap: 12px;
            position: relative;
            z-index: 1;
            background: linear-gradient(180deg, transparent 0%, rgba(0,0,0,0.2) 100%);
        }

        .video-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent-color), #9d00ff);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 16px;
            flex-shrink: 0;
            overflow: hidden;
            position: relative;
            border: 2px solid transparent;
            transition: border-color 0.3s ease;
        }

        .video-card:hover .video-avatar {
            border-color: var(--accent-color);
        }

        .video-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }

        .video-details {
            flex: 1;
            min-width: 0;
        }

        .video-title {
            font-size: 15px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 6px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            line-height: 1.4;
            transition: color 0.3s ease;
        }

        .video-card:hover .video-title {
            color: var(--accent-color);
        }

        .video-author {
            font-size: 13px;
            color: var(--text-secondary);
            margin-bottom: 4px;
            display: inline-block;
            text-decoration: none;
            transition: color 0.2s ease;
        }

        .video-author:hover {
            color: var(--accent-color);
        }

        .video-stats {
            font-size: 12px;
            color: var(--text-secondary);
            display: flex;
            gap: 6px;
            align-items: center;
            opacity: 0.8;
        }

        /* ========================================
           CHANNEL CARDS - MASSIVE & SIMPLE
           ======================================== */
        .channels-grid {
            display: flex;
            gap: 40px;
            margin: 40px 0;
            padding: 20px 0;
            overflow-x: auto;
            scrollbar-width: none; /* Hide scrollbar Hide scrollbar */
        }

        .channels-grid::-webkit-scrollbar {
            display: none;
        }

        .channel-card {
            min-width: 450px;
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(40px);
            -webkit-backdrop-filter: blur(40px);
            border-radius: 32px;
            padding: 60px 40px;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            gap: 32px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            transition: all 0.5s cubic-bezier(0.2, 0.8, 0.2, 1);
            flex-shrink: 0;
            position: relative;
            overflow: hidden;
        }

        .channel-card:hover {
            transform: translateY(-15px) scale(1.02);
            background: rgba(255, 255, 255, 0.06);
            border-color: var(--accent-color);
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.5);
        }

        .channel-avatar {
            width: 180px;
            height: 180px;
            border-radius: 50%;
            overflow: hidden;
            background: linear-gradient(135deg, var(--accent-color), var(--glow-purple));
            display: flex;
            align-items: center;
            justify-content: center;
            border: 6px solid rgba(255, 255, 255, 0.1);
            transition: all 0.5s ease;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }

        .channel-card:hover .channel-avatar {
            transform: scale(1.1) rotate(5deg);
            border-color: var(--accent-color);
            box-shadow: 0 0 50px rgba(0, 113, 227, 0.4);
        }

        .channel-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .channel-initial {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            font-weight: 800;
            color: #fff;
            text-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }

        .channel-info {
            width: 100%;
            position: relative;
            z-index: 1;
        }

        .channel-name {
            font-size: 24px;
            font-weight: 800;
            margin-bottom: 8px;
            color: var(--text-primary);
            letter-spacing: -0.5px;
        }

        .channel-meta {
            font-size: 16px;
            color: var(--text-secondary);
            margin-bottom: 12px;
            font-weight: 500;
        }

        .channel-bio {
            font-size: 15px;
            color: var(--text-secondary);
            line-height: 1.6;
            margin-top: 12px;
            max-width: 380px;
            opacity: 0.8;
        }

        .channel-subscribe-btn {
            margin-top: 24px;
            padding: 16px 48px;
            background: var(--accent-color);
            color: white !important;
            border: none;
            border-radius: 40px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 700;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            width: auto;
            min-width: 220px;
            display: inline-block;
            text-decoration: none;
            box-shadow: 0 10px 30px rgba(0, 113, 227, 0.3);
        }

        .channel-subscribe-btn:hover {
            background: #0081ff;
            transform: scale(1.05) translateY(-2px);
            box-shadow: 0 15px 35px rgba(0, 113, 227, 0.4);
        }

        /* ========================================
           LOADING STATE - PREMIUM SPINNER
           ======================================== */
        .search-loading {
            text-align: center;
            padding: 80px 20px;
        }

        .search-loading .spinner {
            width: 50px;
            height: 50px;
            border: 3px solid var(--glass-border);
            border-top-color: var(--accent-color);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin: 0 auto 20px;
            box-shadow: 0 0 20px var(--glow-accent);
        }

        .search-loading p {
            color: var(--text-secondary);
            font-size: 16px;
            animation: pulse 1.5s ease infinite;
        }

        /* ========================================
           EMPTY STATE - BEAUTIFUL
           ======================================== */
        .search-empty {
            text-align: center;
            padding: 100px 20px;
            color: var(--text-secondary);
        }

        .search-empty i {
            font-size: 80px;
            margin-bottom: 24px;
            background: linear-gradient(135deg, var(--accent-color), #9d00ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            opacity: 0.6;
            animation: float 3s ease-in-out infinite;
        }

        .search-empty h2 {
            font-size: 24px;
            margin-bottom: 12px;
            color: var(--text-primary);
            font-weight: 600;
        }

        .search-empty p {
            font-size: 16px;
            opacity: 0.7;
        }

        /* ========================================
           VIEW MORE BUTTON - PREMIUM
           ======================================== */
        .view-more-container {
            display: flex;
            justify-content: center;
            margin: 32px 0;
        }

        .view-more-btn {
            background: var(--glass-bg);
            color: var(--text-primary);
            border: 1px solid var(--glass-border);
            padding: 14px 40px;
            border-radius: 30px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .view-more-btn::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, var(--accent-color), #9d00ff);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .view-more-btn:hover {
            border-color: var(--accent-color);
            transform: scale(1.05);
            box-shadow: 0 8px 30px var(--glow-accent);
        }

        .view-more-btn:hover::before {
            opacity: 1;
        }

        .view-more-btn span {
            position: relative;
            z-index: 1;
        }

        /* ========================================
           DIVIDERS
           ======================================== */
        .search-divider-large {
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--glass-border), transparent);
            margin: 40px 0;
        }

        /* ========================================
           BADGES
           ======================================== */
        .comment-author-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            vertical-align: middle;
            height: 16px;
            width: 16px;
            opacity: 0.9;
        }

        .comment-author-badge.pro-svg {
            width: 22px;
        }

        .comment-author-badge i {
            font-size: 13px;
        }

        /* ========================================
           ANIMATIONS
           ======================================== */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes cardFadeIn {
            from {
                opacity: 0;
                transform: translateY(20px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        @keyframes pulse {
            0%, 100% { opacity: 0.7; }
            50% { opacity: 1; }
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        @keyframes rotateGlow {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        /* ========================================
           RESPONSIVE
           ======================================== */
        @media (max-width: 1200px) {
            .videos-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 900px) {
            .videos-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .channels-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 600px) {
            .videos-grid, .channels-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }
            .search-section-title {
                font-size: 18px;
            }
            .video-card:hover {
                transform: translateY(-6px);
            }
        }
    </style>
</head>
<body>
    <!-- Starfield Background -->
    <canvas id="starfield"></canvas>
    
    <?php include 'header.php'; ?>

    <div class="app-layout">
        <?php include 'sidebar.php'; ?>
        
        <main class="main-content">
            <div class="search-page-container">
                <div class="search-results-container" id="searchResults">
                    <div class="search-empty">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <h2>Search Loop</h2>
                        <p>Type something to search for videos and channels</p>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <?php include 'mobile_footer.php'; ?>

    <script src="theme.js"></script>
    <script src="popup.js?v=5"></script>
    <script src="search-history.js?v=5"></script>
    <script src="notifications.js"></script>
    <script src="voice_search.js"></script>
    <script src="icon-replace.js"></script>
    <script src="mobile-search.js"></script>

    <script>
        // Initialize when DOM is ready
        document.addEventListener('DOMContentLoaded', () => {
             const searchInput = document.getElementById('searchInput');
             const searchBtn = document.getElementById('searchBtn');
             
             // Check URL again on load to be sure
             const urlParams = new URLSearchParams(window.location.search);
             const initialQuery = urlParams.get('q');
             if (initialQuery) {
                 if (searchInput) searchInput.value = initialQuery;
                 performSearch(initialQuery);
             }
        });

        function performSearch(query = null) {
            const searchInput = document.getElementById('searchInput');
            const searchResults = document.getElementById('searchResults');
            const searchQuery = query || (searchInput ? searchInput.value.trim() : '');

                    if (!searchQuery) {
                searchResults.innerHTML = `
                    <div class="search-empty">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <h2>Search Loop</h2>
                        <p>Type something to search for videos and channels</p>
                    </div>
                `;
                        return;
                    }

            // Add to search history (Safely)
            try {
                if (typeof SearchHistory !== 'undefined') {
                    SearchHistory.addToHistory(searchQuery);
                }
            } catch (err) {
                console.warn('Failed to save search history:', err);
            }

            // Update URL
            const newUrl = `search.php?q=${encodeURIComponent(searchQuery)}`;
            if (window.location.search !== `?q=${encodeURIComponent(searchQuery)}`) {
                window.history.pushState({}, '', newUrl);
            }

                    // Show loading
                    searchResults.innerHTML = `
                        <div class="search-loading">
                            <div class="spinner"></div>
                    <p>Searching...</p>
                        </div>
                    `;

            // Perform search for both videos and users
            Promise.all([
                fetch(`../backend/searchVideos.php?q=${encodeURIComponent(searchQuery)}`).then(r => r.json()).catch(e => ({success: false, videos: [], error: e.message})),
                fetch(`../backend/searchChannels.php?q=${encodeURIComponent(searchQuery)}`).then(r => r.json()).catch(e => ({success: false, users: [], error: e.message}))
            ]).then(([videosData, channelsData]) => {
                let html = '';
                let hasResults = false;

                // Display Users/Channels first
                if (channelsData.success && channelsData.users && channelsData.users.length > 0) {
                    const initialChannels = channelsData.users.slice(0, 4);
                    const remainingChannels = channelsData.users.slice(4);
                    
                    html += '<div class="search-section-title">Channels</div>';
                    html += '<div class="channels-grid" id="channelsGrid">';
                    html += initialChannels.map(user => createUserCard(user)).join('');
                    html += '</div>';
                    
                    if (remainingChannels.length > 0) {
                        html += `
                            <div class="view-more-container" id="channelViewAllContainer">
                                <button class="view-more-btn" onclick="showAllChannels()">View All (${channelsData.users.length})</button>
                            </div>
                        `;
                        // Store remaining for the function
                        window.remainingChannels = remainingChannels;
                    }
                    
                    html += '<div class="search-divider-large"></div>';
                    hasResults = true;
                }

                // Display Videos
                if (videosData.success && videosData.videos && videosData.videos.length > 0) {
                    const initialVideos = videosData.videos.slice(0, 16); // 16 videos = 4 rows of 4
                    const remainingVideos = videosData.videos.slice(16);
                    
                    html += '<div class="search-section-title">Videos</div>';
                    html += '<div class="videos-grid" id="videosGrid">';
                    html += initialVideos.map(video => createVideoCard(video)).join('');
                    html += '</div>';
                    
                    if (remainingVideos.length > 0) {
                        html += `
                            <div class="view-more-container" id="videoViewMoreContainer" style="display: flex; justify-content: center; margin-bottom: 48px;">
                                <button class="view-more-btn" style="background: var(--tertiary-color); color: var(--text-primary); border: 1px solid var(--border-color); padding: 12px 32px; border-radius: 24px; cursor: pointer; font-weight: 600;" onclick="showAllVideos()">VIEW MORE (${videosData.videos.length})</button>
                            </div>
                        `;
                        // Store remaining for the function
                        window.remainingVideos = remainingVideos;
                    }
                    hasResults = true;
                }

                if (!hasResults) {
                    searchResults.innerHTML = `
                        <div class="search-empty">
                            <i class="fa-solid fa-search"></i>
                            <h2 style="color: var(--text-primary);">No results found</h2>
                            <p>We couldn't find anything matching "${escapeHtml(searchQuery)}". Try different keywords.</p>
                        </div>
                    `;
                } else {
                    searchResults.innerHTML = html;

                    // Trigger thumbnail generation for those without
                    searchResults.querySelectorAll('img.generate-thumb').forEach(img => {
                        if (img.dataset.videoUrl) FloxThumbnails.generate(img.dataset.videoUrl, img);
                    });

                    // Add click handlers
                    bindVideoClicks();
                    bindChannelClicks();
                    
                    // Load avatars for authors
                    setTimeout(() => loadAuthorProfilePictures(), 100);
                }
            }).catch(error => {
                console.error('Search error:', error);
                searchResults.innerHTML = `
                    <div class="search-empty">
                        <i class="fa-solid fa-exclamation-circle"></i>
                        <h2>Error</h2>
                        <p>Failed to search. Please try again.</p>
                    </div>
                `;
            });
        }
        
        // Expose globally for search-history.js
        window.performSearch = performSearch;

        function createUserCard(user) {
            const id = escapeHtml(user.id || '');
            const username = escapeHtml(user.username || 'Unknown');
            const profilePic = escapeHtml(user.profile_picture || '');
            const bio = escapeHtml(user.bio || '');
            const subscribers = Number(user.subscribers_count) || 0;
            const subscribersText = formatSubscribers(subscribers);

            // Badge Logic
            let badgeHtml = '';
            if (user.is_pro && user.comment_badge) {
                if (user.comment_badge === 'pro') {
                    badgeHtml = `<span class="comment-author-badge pro-svg" style="margin-left: 5px; width: 20px; height: 18px; display: inline-flex;" title="Pro Member"><svg width="100%" height="100%" viewBox="0 0 340 96" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M87.5524 2.5625H121.74C128.865 2.5625 134.886 3.72917 139.802 6.0625C144.761 8.39583 148.532 11.6667 151.115 15.875C153.698 20.0833 154.99 25 154.99 30.625C154.99 38.2917 153.282 44.9375 149.865 50.5625C146.49 56.1458 141.657 60.4792 135.365 63.5625C129.073 66.6042 121.615 68.125 112.99 68.125H100.177L94.9274 92.75H68.4899L87.5524 2.5625ZM109.802 22.5625L104.24 48.5H113.177C116.261 48.5 118.948 47.8333 121.24 46.5C123.532 45.125 125.302 43.2708 126.552 40.9375C127.802 38.5625 128.427 35.9167 128.427 33C128.427 29.4167 127.427 26.7917 125.427 25.125C123.427 23.4167 120.532 22.5625 116.74 22.5625H109.802ZM153.24 92.75L172.427 2.5625H212.99C219.323 2.5625 224.698 3.75 229.115 6.125C233.573 8.45833 236.969 11.6458 239.302 15.6875C241.636 19.7292 242.802 24.3333 242.802 29.5C242.802 33.75 241.99 37.875 240.365 41.875C238.74 45.8333 236.344 49.3958 233.177 52.5625C230.052 55.7292 226.198 58.2083 221.615 60L231.615 92.75H203.99L195.49 62.5H196.115H186.177L179.74 92.75H153.24ZM194.802 22.125L189.99 44.375H201.802C204.761 44.375 207.323 43.8333 209.49 42.75C211.657 41.6667 213.323 40.1667 214.49 38.25C215.657 36.3333 216.24 34.1458 216.24 31.6875C216.24 28.4792 215.261 26.0833 213.302 24.5C211.386 22.9167 208.594 22.125 204.927 22.125H194.802ZM296.427 22.125C293.386 22.125 290.511 22.9583 287.802 24.625C285.136 26.2917 282.761 28.6042 280.677 31.5625C278.594 34.5208 276.948 37.9375 275.74 41.8125C274.573 45.6875 273.99 49.8542 273.99 54.3125C273.99 58.1042 274.615 61.4167 275.865 64.25C277.115 67.0833 278.865 69.2917 281.115 70.875C283.365 72.4167 286.011 73.1875 289.052 73.1875C292.094 73.1875 294.969 72.3542 297.677 70.6875C300.386 69.0208 302.782 66.7083 304.865 63.75C306.948 60.7917 308.573 57.375 309.74 53.5C310.948 49.5833 311.552 45.3958 311.552 40.9375C311.552 37.1042 310.927 33.7917 309.677 31C308.427 28.1667 306.657 25.9792 304.365 24.4375C302.115 22.8958 299.469 22.125 296.427 22.125ZM288.24 94.3125C279.657 94.3125 272.282 92.6458 266.115 89.3125C259.99 85.9375 255.282 81.3542 251.99 75.5625C248.74 69.7708 247.115 63.2292 247.115 55.9375C247.115 47.6875 248.407 40.1875 250.99 33.4375C253.573 26.6875 257.136 20.8958 261.677 16.0625C266.261 11.2292 271.573 7.52083 277.615 4.9375C283.657 2.3125 290.136 1 297.052 1C305.886 1 313.365 2.6875 319.49 6.0625C325.657 9.4375 330.344 14.0208 333.552 19.8125C336.802 25.5625 338.427 32.0833 338.427 39.375C338.427 47.6667 337.115 55.1875 334.49 61.9375C331.907 68.6875 328.302 74.4792 323.677 79.3125C319.094 84.1458 313.761 87.8542 307.677 90.4375C301.636 93.0208 295.157 94.3125 288.24 94.3125Z" fill="white"/></svg></span>`;
                } else if (user.comment_badge === 'crown') {
                    badgeHtml = `<span class="comment-author-badge crown" style="margin-left: 5px;" title="Crown"><i class="fa-solid fa-crown" style="color: #ffd700;"></i></span>`;
                } else if (user.comment_badge === 'bolt') {
                    badgeHtml = `<span class="comment-author-badge bolt" style="margin-left: 5px;" title="Electricity"><i class="fa-solid fa-bolt" style="color: #ffeb3b;"></i></span>`;
                } else if (user.comment_badge === 'verified') {
                    badgeHtml = `<span class="comment-author-badge verified" style="margin-left: 5px;" title="Verified"><i class="fa-solid fa-check-double" style="color: #3ea6ff;"></i></span>`;
                }
            }

            const avatarHtml = profilePic 
                ? `<img src="${profilePic}" alt="${username}" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                   <div class="channel-initial" style="display: none;">${(username || '?').charAt(0).toUpperCase()}</div>`
                : `<div class="channel-initial">${(username || '?').charAt(0).toUpperCase()}</div>`;

            return `
                <div class="channel-card" data-user-id="${id}">
                    <div class="channel-avatar">
                        ${avatarHtml}
                    </div>
                    <div class="channel-info">
                        <div class="channel-name">${username} ${badgeHtml}</div>
                        <div class="channel-meta">${subscribersText} subscribers</div>
                        ${bio ? `<div class="channel-bio">${bio}</div>` : ''}
                    </div>
                    <button class="channel-subscribe-btn" onclick="event.stopPropagation(); window.location.href='user_profile.php?user_id=${id}'">View Channel</button>
                </div>
            `;
        }

        // Helper to generate robust avatar HTML (Image with Fallback)
        function createAvatarHtml(username, profilePicUrl) {
            const initial = (username || '?').charAt(0).toUpperCase();
            
            // If no picture, just return the initial div
            if (!profilePicUrl) {
                return `<div class="avatar-fallback" style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; background:var(--accent-color); color:#fff; font-weight:bold;">${initial}</div>`;
            }

            // If picture exists, return IMG + Hidden Fallback. If IMG fails, it hides itself and shows fallback.
            return `
                <div style="position:relative; width:100%; height:100%;">
                    <img src="${profilePicUrl}" 
                         alt="${escapeHtml(username)}" 
                         style="width:100%; height:100%; object-fit:cover; border-radius:50%; position:absolute; top:0; left:0; z-index:2;"
                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <div class="avatar-fallback" style="width:100%; height:100%; display:none; align-items:center; justify-content:center; background:var(--accent-color); color:#fff; font-weight:bold; position:absolute; top:0; left:0; z-index:1;">
                        ${initial}
                    </div>
                </div>
            `;
        }

        function createVideoCard(video) {
            // Safely read values
            const id = video.id || '';
            const title = escapeHtml(video.title || 'Untitled');
            const thumb = video.thumbnail_url;
            const videoUrl = video.video_url;
            const author = video.author || {};
            const authorName = escapeHtml(author.username || 'Unknown');
            const authorId = author.id || video.user_id || '';
            const viewsText = formatViews(Number(video.views || 0));
            const timeAgo = formatTimeAgo(video.created_at || video.uploaded_at || new Date().toISOString());
            
            const hasThumb = thumb && thumb.trim() !== '' && !thumb.includes('placeholder.jpg');

            const avatarHtml = createAvatarHtml(authorName, author.profile_picture);

            return `
                <div class="video-card" data-video-id="${id}">
                    <div class="video-thumbnail">
                        <img src="${hasThumb ? escapeHtml(thumb) : 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'}" 
                             data-video-url="${escapeHtml(videoUrl)}"
                             class="${!hasThumb ? 'generate-thumb' : ''}"
                             alt="${title}" loading="lazy"
                             onerror="if(this.dataset.videoUrl && !this.classList.contains('failed')) { this.classList.add('failed'); FloxThumbnails.generate(this.dataset.videoUrl, this); }" />
                        <div class="play-icon-overlay">
                            <i class="fa-solid fa-play"></i>
                        </div>
                    </div>
                    <div class="video-meta">
                        <div class="video-avatar" data-user-id="${authorId}">
                            ${avatarHtml}
                        </div>
                        <div class="video-details">
                            <div class="video-title">${title}</div>
                            <a href="user_profile.php?user_id=${encodeURIComponent(authorId)}" class="video-author" style="text-decoration: none; color: inherit;" onclick="event.stopPropagation()">${authorName}</a>
                            <div class="video-stats">
                                <span>${viewsText} views</span>
                                <span>•</span>
                                <span>${timeAgo}</span>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }

        async function loadAuthorProfilePictures() {
            const videoCards = document.querySelectorAll('.video-card');
            videoCards.forEach(card => {
                const avatar = card.querySelector('.video-avatar[data-user-id]');
                if (avatar) {
                    const userId = avatar.dataset.userId;
                    // Skip if already has image or rich cleanup content
                    if (!userId || avatar.querySelector('img')) return;

                    fetch(`../backend/getUserProfile.php?user_id=${encodeURIComponent(userId)}`)
                        .then(res => res.json())
                        .then(data => {
                            if (data && data.success && data.user) {
                                const user = data.user;
                                // Use the same robust helper
                                const newHtml = createAvatarHtml(user.username, user.profile_picture);
                                avatar.innerHTML = newHtml;
                            }
                        })
                        .catch(err => console.debug('Could not load author profile', userId, err));
                }
            });
        }

        function formatSubscribers(count) {
            if (count >= 1000000) {
                return (count / 1000000).toFixed(1).replace(/\.0$/, '') + 'M';
            } else if (count >= 1000) {
                return (count / 1000).toFixed(1).replace(/\.0$/, '') + 'K';
            }
            return count.toString();
        }

            function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
            }

            function formatViews(count) {
            if (count >= 1000000) {
                return (count / 1000000).toFixed(1) + 'M';
            } else if (count >= 1000) {
                return (count / 1000).toFixed(1) + 'K';
            }
            return count.toString();
            }

            function formatTimeAgo(dateString) {
                const now = new Date();
                const date = new Date(dateString);
                const diffInSeconds = Math.floor((now - date) / 1000);

                if (diffInSeconds < 60) return 'just now';
                if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m ago`;
                if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h ago`;
                if (diffInSeconds < 2592000) return `${Math.floor(diffInSeconds / 86400)}d ago`;
                return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
            }

        function showAllChannels() {
            const grid = document.getElementById('channelsGrid');
            const container = document.getElementById('channelViewAllContainer');
            if (grid && window.remainingChannels) {
                grid.innerHTML += window.remainingChannels.map(user => createUserCard(user)).join('');
                if (container) container.remove();
                
                // Re-bind clicks
                bindChannelClicks();
            }
        }

        function showAllVideos() {
            const grid = document.getElementById('videosGrid');
            const container = document.getElementById('videoViewMoreContainer');
            if (grid && window.remainingVideos) {
                grid.innerHTML += window.remainingVideos.map(video => createVideoCard(video)).join('');
                if (container) container.remove();
                
                // Re-bind clicks
                bindVideoClicks();

                // Trigger thumbnail generation for new cards
                grid.querySelectorAll('img.generate-thumb').forEach(img => {
                    if (img.dataset.videoUrl && !img.src.startsWith('data:image/jpeg')) {
                        FloxThumbnails.generate(img.dataset.videoUrl, img);
                    }
                });

                // Load avatars for the new cards
                setTimeout(() => loadAuthorProfilePictures(), 100);
            }
        }

        function bindVideoClicks() {
            document.querySelectorAll('.video-card').forEach(card => {
                card.onclick = () => {
                    const videoId = card.dataset.videoId;
                    if (videoId) window.location.href = `videoid.php?id=${videoId}`;
                };
            });
        }

        function bindChannelClicks() {
            document.querySelectorAll('.channel-card').forEach(card => {
                card.onclick = () => {
                    const userId = card.dataset.userId;
                    if (userId) window.location.href = `user_profile.php?user_id=${userId}`;
                };
            });
        }

        // Event listeners
        if (searchBtn) searchBtn.addEventListener('click', () => performSearch());
        if (searchInput) {
            searchInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    performSearch();
                }
            });
        }

        // Show search history on focus - handled by search-history.js
        if (searchInput) {
            searchInput.addEventListener('focus', () => {
                const container = document.getElementById('searchContainer');
                if (container && typeof SearchHistory !== 'undefined') {
                    SearchHistory.renderDropdown(searchInput, container);
                }
            });
        }

        // Hide history when clicking outside
            document.addEventListener('click', (e) => {
                if (!e.target.closest('.search-page-input-container')) {
                    const dropdown = document.querySelector('.search-history-dropdown');
                    if (dropdown) dropdown.remove();
                }
            });
    </script>
    
    <!-- Starfield Animation Script -->
    <script>
    (function() {
        const canvas = document.getElementById('starfield');
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        let stars = [];
        const STAR_COUNT = 300; 

        function resize() {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
            if (stars.length === 0) createStars();
        }

        function createStars() {
            stars = [];
            for (let i = 0; i < STAR_COUNT; i++) {
                const isBright = Math.random() > 0.9;
                stars.push({
                    x: Math.random() * canvas.width,
                    y: Math.random() * canvas.height,
                    size: isBright ? Math.random() * 2 + 1 : Math.random() * 1 + 0.5,
                    speed: Math.random() * 0.1 + 0.02,
                    opacity: Math.random() * 0.6 + 0.2,
                    twinkle: Math.random() * 0.02,
                    isBright: isBright
                });
            }
        }

        function animate() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            
            stars.forEach(star => {
                // Draw star
                ctx.beginPath();
                ctx.arc(star.x, star.y, star.size, 0, Math.PI * 2);
                ctx.fillStyle = `rgba(255, 255, 255, ${star.opacity})`;
                
                if(star.isBright) {
                    ctx.shadowBlur = 10;
                    ctx.shadowColor = '#fff';
                } else {
                    ctx.shadowBlur = 0;
                }
                
                ctx.fill();
                
                // Move UP to match home.php direction
                star.y -= star.speed;
                
                // Wrap around
                if (star.y < 0) {
                    star.y = canvas.height;
                    star.x = Math.random() * canvas.width;
                }
                
                // Simple twinkle
                star.opacity += Math.sin(Date.now() * star.twinkle) * 0.01;
                star.opacity = Math.max(0.2, Math.min(0.9, star.opacity));
            });
            
            requestAnimationFrame(animate);
        }

        resize();
        animate();
        window.addEventListener('resize', resize);
    })();
    </script>
</body>
</html>
