// ============================================
// GLOBAL STATE
// ============================================
'use strict';

var videos = videos || [];
var currentUser = currentUser || null;
var isLoading = isLoading || false;

// ============================================
// INITIALIZATION
// ============================================
document.addEventListener('DOMContentLoaded', () => {
    initializeApp();
});

async function initializeApp() {
    // Load user data
    await loadUserData();

    // Check for search query in URL and redirect to search.php
    const urlParams = new URLSearchParams(window.location.search);
    const searchQuery = urlParams.get('search');
    if (searchQuery) {
        window.location.href = `search.php?q=${encodeURIComponent(searchQuery)}`;
        return;
    } else {
        // Load Live Streams
        try { await loadLiveStreams(); } catch (e) { console.error(e); }
        // Load videos
        try { await loadVideos(); } catch (e) { console.error(e); }
        // Load clips
        try { await loadClips(); } catch (e) { console.error(e); }
        // Load posts
        try { await loadPosts(); } catch (e) { console.error(e); }
        // Generate real chips
        try { await generateSuggestionChips(); } catch (e) { console.error(e); }
    }

    // Setup event listeners
    setupEventListeners();
}

// ============================================
// API FUNCTIONS
// ============================================
async function loadVideos(searchQuery = null) {
    const container = document.getElementById('videosContainer');
    const loadingSpinner = document.getElementById('loadingSpinner');
    const videosGrid = document.getElementById('videosGrid');
    const emptyState = document.getElementById('emptyState');

    try {
        isLoading = true;
        if (loadingSpinner) loadingSpinner.classList.remove('hidden');
        if (videosGrid) videosGrid.innerHTML = '';
        if (emptyState) emptyState.style.display = 'none';

        const response = await fetch('../backend/getRecommendedVideos.php');
        if (!response.ok) {
            throw new Error(`Server returned ${response.status}: ${response.statusText}`);
        }

        const data = await response.json();

        if (data && data.success) {
            let allVideos = data.videos || [];

            // Filter by search query if provided
            if (searchQuery) {
                const query = searchQuery.toLowerCase();
                allVideos = allVideos.filter(video => {
                    const title = (video.title || '').toLowerCase();
                    const desc = (video.description || '').toLowerCase();
                    const author = ((video.author && video.author.username) || '').toLowerCase();
                    return title.includes(query) || desc.includes(query) || author.includes(query);
                });
            }

            videos = allVideos;

            if (!videos || videos.length === 0) {
                // Show empty state
                if (loadingSpinner) loadingSpinner.classList.add('hidden');
                if (emptyState) {
                    emptyState.style.display = 'flex';
                }
                if (videosGrid) videosGrid.innerHTML = '';
            } else {
                // Populate Hero if not searching
                if (!searchQuery && videos.length > 0) {
                    populateHero(videos[0]);
                } else {
                    const hero = document.getElementById('heroCinema');
                    if (hero) hero.style.display = 'none';
                }

                // Render videos
                if (loadingSpinner) loadingSpinner.classList.add('hidden');
                if (videosGrid) renderVideos(videos, data.personalized && !searchQuery);
                if (emptyState) emptyState.style.display = 'none';
                // Load profile pictures for authors (allow DOM to render first)
                setTimeout(() => loadAuthorProfilePictures(), 100);
            }
        } else {
            throw new Error((data && data.message) || 'Failed to load videosData');
        }
    } catch (error) {
        console.error('Error loading videos:', error);
        if (loadingSpinner) loadingSpinner.classList.add('hidden');
        if (videosGrid) {
            videosGrid.innerHTML = `
                <div style="grid-column: 1/-1; text-align: center; padding: 40px; color: var(--text-secondary);">
                    <i class="fa-solid fa-triangle-exclamation" style="font-size: 32px; color: #ff4d4d; margin-bottom: 16px;"></i>
                    <p style="font-size: 18px; color: #fff; margin-bottom: 8px;">Failed to load videos</p>
                    <p style="font-size: 14px; opacity: 0.7;">${error.message}</p>
                    <button onclick="window.location.reload()" style="margin-top: 20px; padding: 10px 24px; background: var(--accent); color: #fff; border: none; border-radius: 8px; cursor: pointer;">Retry</button>
                </div>
            `;
        }
        if (emptyState) emptyState.style.display = 'none';
    } finally {
        isLoading = false;
    }
}

async function loadUserData() {
    try {
        const response = await fetch('../backend/getUser.php');
        const data = await response.json();

        if (data && data.success) {
            currentUser = data.user;
            updateUserUI(data.user);
            updateProfilePictures(data.user);
        }
    } catch (error) {
        console.error('Error loading user data:', error);
    }
}

function updateProfilePictures(user) {
    if (!user) return;

    const avatars = document.querySelectorAll('.account-avatar, .account-dropdown-avatar, #accountAvatarNav, #accountDropdownAvatar');
    avatars.forEach(avatar => {
        // Clear existing content
        avatar.innerHTML = '';

        if (user.profile_picture) {
            // create image element and fallback
            const img = document.createElement('img');
            img.src = escapeHtml(user.profile_picture);
            img.alt = 'Profile';
            img.style.cssText = 'width: 100%; height: 100%; border-radius: 50%; object-fit: cover;';
            img.addEventListener('error', () => {
                img.style.display = 'none';
                const fb = avatar.querySelector('.avatar-fallback');
                if (fb) fb.style.display = 'flex';
            });

            const fallback = document.createElement('div');
            fallback.className = 'avatar-fallback';
            fallback.style.cssText = 'display: none; width: 100%; height: 100%; align-items: center; justify-content: center; background: var(--accent-color); border-radius: 50%; color: white; font-weight: 600; font-size: 16px;';
            fallback.textContent = (user.username || '').charAt(0).toUpperCase();

            avatar.appendChild(img);
            avatar.appendChild(fallback);
        } else {
            avatar.textContent = (user.username || '').charAt(0).toUpperCase();
        }
    });
}

async function createVideo(formData) {
    try {
        const response = await fetch('../backend/createVideo.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(formData)
        });

        const data = await response.json();

        if (data && data.success) {
            return { success: true, message: data.message, video: data.video };
        } else {
            return { success: false, message: (data && data.message) || 'Failed to create video' };
        }
    } catch (error) {
        console.error('Error creating video:', error);
        return { success: false, message: 'Network error. Please try again.' };
    }
}

async function uploadVideoFile(formData) {
    try {
        const response = await fetch('../backend/uploadVideo.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data && data.success) {
            return { success: true, message: data.message, video: data.video };
        } else {
            return { success: false, message: (data && data.message) || 'Failed to upload video' };
        }
    } catch (error) {
        console.error('Error uploading video:', error);
        return { success: false, message: 'Network error. Please try again.' };
    }
}

async function updateUser(formData) {
    try {
        const response = await fetch('../backend/updateUser.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(formData)
        });

        const data = await response.json();

        if (data && data.success) {
            if (data.user) {
                currentUser = data.user;
                updateUserUI(data.user);
            }
            return { success: true, message: data.message };
        } else {
            return { success: false, message: (data && data.message) || 'Failed to update profile' };
        }
    } catch (error) {
        console.error('Error updating user:', error);
        return { success: false, message: 'Network error. Please try again.' };
    }
}

async function logout() {
    try {
        await fetch('../backend/logout.php');
    } catch (error) {
        console.error('Error logging out:', error);
    } finally {
        window.location.href = 'loginb.php';
    }
}

async function loadLiveStreams() {
    const liveSection = document.getElementById('liveStreamsSection');
    const liveGrid = document.getElementById('liveStreamsGrid');
    if (!liveSection || !liveGrid) return;

    try {
        // Fetch via PHP proxy to avoid browser console errors if port 8080 is unreachable
        const response = await fetch(`../backend/getLiveStreams.php`);

        if (!response.ok) {
            liveSection.style.display = 'none';
            return;
        }

        const data = await response.json();


        if (data && data.success && data.streams && data.streams.length > 0) {
            liveSection.style.display = 'block';
            liveGrid.innerHTML = '';

            for (const stream of data.streams) {
                const parts = stream.streamId.split('_');
                const userId = parts[1];
                if (!userId) continue;

                const userRes = await fetch(`../backend/getUserProfile.php?user_id=${userId}`);
                const userData = await userRes.json();

                if (userData && userData.success) {
                    const creator = userData.user;
                    const liveCard = createLiveCard(stream, creator);
                    liveGrid.innerHTML += liveCard;
                    initLivePreview(stream.streamId);
                }
            }
        } else {
            liveSection.style.display = 'none';
        }
    } catch (err) {
        // Fail silently - streaming server is optional
        liveSection.style.display = 'none';
    }
}

function createLiveCard(stream, creator) {
    const liveUrl = `live_view.php?id=${stream.streamId}`;
    const username = creator.username || 'Creator';
    const profilePic = creator.profile_picture || '';
    const viewerCount = stream.viewers || 0;

    let avatarHtml = username.charAt(0).toUpperCase();
    if (profilePic) {
        avatarHtml = `<img src="${escapeHtml(profilePic)}" style="width:100%; height:100%; border-radius:50%; object-fit:cover;">`;
    }

    return `
    <div class="video-card live-card" onclick="window.location.href='${liveUrl}'" style="cursor: pointer; border-color: rgba(0, 113, 227, 0.3) !important;">
        <div class="video-thumbnail" style="background: #050510; display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden;">
            <!-- Live Preview Feed -->
            <img id="preview_${stream.streamId}" src="" style="width: 100%; height: 100%; object-fit: cover; position: absolute; inset: 0; opacity: 0; transition: opacity 0.5s;">
            
            <div class="live-tag" style="position: absolute; top: 10px; left: 10px; background: #0071e3; color: #fff; padding: 4px 10px; border-radius: 6px; font-size: 10px; font-weight: 800; display: flex; align-items: center; gap: 6px; z-index: 5; box-shadow: 0 4px 10px rgba(0, 113, 227, 0.3);">
                <div style="width: 6px; height: 6px; background: #fff; border-radius: 50%; animation: pulse 1s infinite;"></div>
                LIVE
            </div>
            <div class="viewer-tag" style="position: absolute; bottom: 10px; right: 10px; background: rgba(0,0,0,0.5); color: #fff; padding: 4px 8px; border-radius: 4px; font-size: 10px; z-index: 5; backdrop-filter: blur(5px);">
                <i class="fa-solid fa-eye" style="margin-right: 4px;"></i> ${viewerCount}
            </div>
            
            <i class="fa-solid fa-video" id="icon_${stream.streamId}" style="font-size: 32px; color: rgba(0, 113, 227, 0.2); transition: opacity 0.3s;"></i>
        </div>
        <div class="video-meta">
            <div class="video-avatar">${avatarHtml}</div>
            <div class="video-details">
                <div class="video-title" style="color: #0071e3; font-weight: 700;">${username} is LIVE</div>
                <div class="video-author">${username}</div>
                <div class="video-stats" style="color: #0071e3; opacity: 0.8;">Click to join stream</div>
            </div>
        </div>
    </div>
    `;
}

function initLivePreview(streamId) {
    let serverIp = window.FLOX_CTX?.wsHost || window.location.hostname;
    if (serverIp === 'localhost') serverIp = '127.0.0.1';
    const wsPreview = new WebSocket(`ws://${serverIp}:8080`);
    wsPreview.onopen = () => {
        wsPreview.send(JSON.stringify({ type: 'JOIN_STREAM', streamId: streamId }));
    };
    wsPreview.onmessage = (event) => {
        const data = JSON.parse(event.data);
        if (data.type === 'VIDEO_FRAME') {
            const img = document.getElementById(`preview_${streamId}`);
            const icon = document.getElementById(`icon_${streamId}`);
            if (img) {
                img.src = data.frame;
                img.style.opacity = '1';
                if (icon) icon.style.opacity = '0';
            }
        }
    };
    // Close preview if we leave or it errors to save resources
    wsPreview.onerror = () => wsPreview.close();
}

// ============================================
// UI RENDERING
// ============================================
function renderVideos(videosArray, personalized = false) {
    const videosGrid = document.getElementById('videosGrid');
    if (!videosGrid) return;

    if (!videosArray || videosArray.length === 0) {
        videosGrid.innerHTML = '';
        const emptyState = document.getElementById('emptyState');
        if (emptyState) emptyState.style.display = 'flex';
        return;
    }

    // Preserve existing spacers AND widgets that have been moved into the grid
    const existingSpacers = Array.from(videosGrid.querySelectorAll('.grid-spacer'));
    const existingWidgets = Array.from(videosGrid.querySelectorAll('.draggable-widget'));

    let html = '';

    if (personalized) {
        // Split into Recommended (relevance > 0) and Recent
        const recommended = videosArray.filter(v => (v.relevance || 0) > 0);
        const recent = videosArray.filter(v => !(v.relevance || 0) > 0);

        if (recommended.length > 0) {
            html += `<div class="grid-section-header recommended-header" style="grid-column: 1/-1;">
                <i class="fa-solid fa-wand-magic-sparkles"></i> Recommended for you
            </div>`;

            // Interleave Clips after first 4 videos
            const top4 = recommended.slice(0, 4);
            const remaining = recommended.slice(4);

            html += top4.map(video => createVideoCard(video)).join('');

            // Clips Placeholder
            html += `<div id="homeClipsPlaceholder" style="grid-column: 1/-1;"></div>`;

            html += remaining.map(video => createVideoCard(video)).join('');
        }

        if (recent.length > 0) {
            html += `<div class="grid-section-header recent-header" style="grid-column: 1/-1; margin-top: 32px;">
                <i class="fa-solid fa-clock"></i> New for you
            </div>`;
            html += recent.map(video => createVideoCard(video)).join('');
        }
    } else {
        html = videosArray.map(video => createVideoCard(video)).join('');
    }

    videosGrid.innerHTML = html;

    // Re-append spacers and widgets immediately to preserve layout flow
    existingSpacers.forEach(spacer => videosGrid.appendChild(spacer));
    existingWidgets.forEach(widget => {
        if (!videosGrid.contains(widget)) {
            videosGrid.appendChild(widget);
        }
    });

    // Dispatch custom event for widgets to update spacers
    window.dispatchEvent(new CustomEvent('videosRendered'));
    videosGrid.querySelectorAll('img.generate-thumb').forEach(img => {
        if (img.dataset.videoUrl) {
            FloxThumbnails.generate(img.dataset.videoUrl, img);
        }
    });

    // Add click handlers to video cards
    videosGrid.querySelectorAll('.video-card').forEach((card, index) => {
        card.addEventListener('click', (e) => {
            if (e.target.closest('.video-author')) return; // Don't trigger play if clicking author

            const videoId = card.dataset.videoId;
            if (videoId) {
                // Track interest before navigating
                const video = videosArray.find(v => v.id == videoId);
                if (video && window.FloxInterests) {
                    window.FloxInterests.track(video);
                }

                // Cyber click effect
                card.style.transform = 'scale(0.96) rotateX(5deg)';
                card.style.filter = 'brightness(1.5) saturate(1.2)';

                setTimeout(() => {
                    window.location.href = `videoid.php?id=${encodeURIComponent(videoId)}`;
                }, 150);
            }
        });

        // Hover Preview Logic
        const preview = card.querySelector('.video-preview');
        if (preview) {
            let hoverTimer;
            card.addEventListener('mouseenter', () => {
                hoverTimer = setTimeout(() => {
                    if (!preview.src) {
                        preview.src = preview.dataset.src;
                    }
                    preview.play().catch(e => { });
                    card.classList.add('preview-playing');
                }, 400);
            });

            card.addEventListener('mouseleave', () => {
                clearTimeout(hoverTimer);
                preview.pause();
                preview.currentTime = 0;
                card.classList.remove('preview-playing');
            });
        }
    });
}

function populateHero(video) {
    const hero = document.getElementById('heroCinema');
    if (!hero || !video) return;

    const title = document.getElementById('heroTitle');
    const backdrop = document.getElementById('heroBackdrop');
    const author = document.getElementById('heroAuthor');
    const views = document.getElementById('heroViews');
    const watchBtn = document.getElementById('heroWatchBtn');
    const infoBtn = document.getElementById('heroInfoBtn');

    title.textContent = video.title || 'Featured';
    backdrop.src = video.thumbnail_url || '';
    author.textContent = video.author.username || 'Loop';
    views.textContent = formatViews(video.views);
    watchBtn.href = `videoid.php?id=${video.id}`;
    infoBtn.href = `videoid.php?id=${video.id}`;

    hero.style.display = 'flex';
}

function createVideoCard(video) {
    // Safely read values
    const id = video.id || '';
    const title = escapeHtml(video.title || 'Untitled');
    const thumb = video.thumbnail_url;
    const videoUrl = video.video_url;
    const author = video.author || {};
    const authorName = escapeHtml(author.username || 'Unknown');
    const authorId = author.id || '';
    const viewsText = formatViews(Number(video.views || 0));
    const timeAgo = formatTimeAgo(video.created_at || video.uploaded_at || new Date().toISOString());

    // If no thumbnail, we'll generate one after render
    const hasThumb = thumb && thumb.trim() !== '';

    // Badge Logic for Author
    let badgeHtml = '';
    if (author.is_pro && author.comment_badge) {
        if (author.comment_badge === 'pro') {
            badgeHtml = `<span class="comment-author-badge pro-svg" style="margin-left: 5px; width: 22px; height: 16px; display: inline-flex;" title="Pro Member"><svg width="100%" height="100%" viewBox="0 0 340 96" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M87.5524 2.5625H121.74C128.865 2.5625 134.886 3.72917 139.802 6.0625C144.761 8.39583 148.532 11.6667 151.115 15.875C153.698 20.0833 154.99 25 154.99 30.625C154.99 38.2917 153.282 44.9375 149.865 50.5625C146.49 56.1458 141.657 60.4792 135.365 63.5625C129.073 66.6042 121.615 68.125 112.99 68.125H100.177L94.9274 92.75H68.4899L87.5524 2.5625ZM109.802 22.5625L104.24 48.5H113.177C116.261 48.5 118.948 47.8333 121.24 46.5C123.532 45.125 125.302 43.2708 126.552 40.9375C127.802 38.5625 128.427 35.9167 128.427 33C128.427 29.4167 127.427 26.7917 125.427 25.125C123.427 23.4167 120.532 22.5625 116.74 22.5625H109.802ZM153.24 92.75L172.427 2.5625H212.99C219.323 2.5625 224.698 3.75 229.115 6.125C233.573 8.45833 236.969 11.6458 239.302 15.6875C241.636 19.7292 242.802 24.3333 242.802 29.5C242.802 33.75 241.99 37.875 240.365 41.875C238.74 45.8333 236.344 49.3958 233.177 52.5625C230.052 55.7292 226.198 58.2083 221.615 60L231.615 92.75H203.99L195.49 62.5H196.115H186.177L179.74 92.75H153.24ZM194.802 22.125L189.99 44.375H201.802C204.761 44.375 207.323 43.8333 209.49 42.75C211.657 41.6667 213.323 40.1667 214.49 38.25C215.657 36.3333 216.24 34.1458 216.24 31.6875C216.24 28.4792 215.261 26.0833 213.302 24.5C211.386 22.9167 208.594 22.125 204.927 22.125H194.802ZM296.427 22.125C293.386 22.125 290.511 22.9583 287.802 24.625C285.136 26.2917 282.761 28.6042 280.677 31.5625C278.594 34.5208 276.948 37.9375 275.74 41.8125C274.573 45.6875 273.99 49.8542 273.99 54.3125C273.99 58.1042 274.615 61.4167 275.865 64.25C277.115 67.0833 278.865 69.2917 281.115 70.875C283.365 72.4167 286.011 73.1875 289.052 73.1875C292.094 73.1875 294.969 72.3542 297.677 70.6875C300.386 69.0208 302.782 66.7083 304.865 63.75C306.948 60.7917 308.573 57.375 309.74 53.5C310.948 49.5833 311.552 45.3958 311.552 40.9375C311.552 37.1042 310.927 33.7917 309.677 31C308.427 28.1667 306.657 25.9792 304.365 24.4375C302.115 22.8958 299.469 22.125 296.427 22.125ZM288.24 94.3125C279.657 94.3125 272.282 92.6458 266.115 89.3125C259.99 85.9375 255.282 81.3542 251.99 75.5625C248.74 69.7708 247.115 63.2292 247.115 55.9375C247.115 47.6875 248.407 40.1875 250.99 33.4375C253.573 26.6875 257.136 20.8958 261.677 16.0625C266.261 11.2292 271.573 7.52083 277.615 4.9375C283.657 2.3125 290.136 1 297.052 1C305.886 1 313.365 2.6875 319.49 6.0625C325.657 9.4375 330.344 14.0208 333.552 19.8125C336.802 25.5625 338.427 32.0833 338.427 39.375C338.427 47.6667 337.115 55.1875 334.49 61.9375C331.907 68.6875 328.302 74.4792 323.677 79.3125C319.094 84.1458 313.761 87.8542 307.677 90.4375C301.636 93.0208 295.157 94.3125 288.24 94.3125Z" fill="currentColor"/></svg></span>`;
        } else if (author.comment_badge === 'crown') {
            badgeHtml = `<span class="comment-author-badge crown" style="margin-left: 5px;" title="Crown"><i class="fa-solid fa-crown" style="color: #ffd700;"></i></span>`;
        } else if (author.comment_badge === 'bolt') {
            badgeHtml = `<span class="comment-author-badge bolt" style="margin-left: 5px;" title="Electricity"><i class="fa-solid fa-bolt" style="color: #ffeb3b;"></i></span>`;
        } else if (author.comment_badge === 'verified') {
            badgeHtml = `<span class="comment-author-badge verified" style="margin-left: 5px;" title="Verified"><i class="fa-solid fa-check-double" style="color: #3ea6ff;"></i></span>`;
        }
    }

    // Author Avatar Logic
    let avatarContent = authorName.charAt(0).toUpperCase();
    if (author.profile_picture) {
        avatarContent = `<img src="${escapeHtml(author.profile_picture)}" 
                               alt="${authorName}" 
                               style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;"
                               onerror="this.style.display='none'; this.parentElement.textContent='${authorName.charAt(0).toUpperCase()}'">`;
    }

    return `
    <div class="video-card" data-video-id="${id}">
      <div class="video-thumbnail">
        <img src="${hasThumb ? escapeHtml(thumb) : 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'}" 
             data-video-url="${escapeHtml(videoUrl)}"
             class="${!hasThumb ? 'generate-thumb' : ''}"
             alt="${title}" loading="lazy"
             onerror="if(this.dataset.videoUrl && !this.classList.contains('failed')) { this.classList.add('failed'); FloxThumbnails.generate(this.dataset.videoUrl, this); }" />
        <video class="video-preview" muted playsinline loop preload="none" data-src="${escapeHtml(videoUrl)}"></video>
      </div>
      <div class="video-meta">
        <div class="video-avatar" data-user-id="${authorId}">${avatarContent}</div>
        <div class="video-details">
          <div class="video-title">${title}</div>
          <a href="user_profile.php?user_id=${encodeURIComponent(authorId)}" class="video-author" style="text-decoration: none; color: inherit; display: flex; align-items: center;">${authorName} ${badgeHtml}</a>
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

// Load profile pictures for video authors (Fallback for cases where video data is missing avatar)
async function loadAuthorProfilePictures() {
    const videoCards = document.querySelectorAll('.video-card');
    if (!videoCards || videoCards.length === 0) return;

    videoCards.forEach(card => {
        const avatar = card.querySelector('.video-avatar[data-user-id]');
        if (avatar && !avatar.querySelector('img')) {
            const userId = avatar.dataset.userId;
            if (!userId) return;

            fetch(`../backend/getUserProfile.php?user_id=${encodeURIComponent(userId)}`)
                .then(res => res.json())
                .then(data => {
                    if (data && data.success && data.user) {
                        const user = data.user;
                        avatar.innerHTML = '';

                        if (user.profile_picture) {
                            const img = document.createElement('img');
                            img.src = escapeHtml(user.profile_picture);
                            img.alt = escapeHtml(user.username || 'User');
                            img.style.cssText = 'width: 100%; height: 100%; border-radius: 50%; object-fit: cover;';
                            img.addEventListener('error', () => {
                                img.style.display = 'none';
                                const fb = avatar.querySelector('.avatar-fallback');
                                if (fb) fb.style.display = 'flex';
                            });

                            const fallback = document.createElement('div');
                            fallback.className = 'avatar-fallback';
                            fallback.style.cssText = 'display: none; width: 100%; height: 100%; align-items: center; justify-content: center; background: var(--accent-color); border-radius: 50%; color: white; font-weight: 600; font-size: 14px;';
                            fallback.textContent = (user.username || '').charAt(0).toUpperCase();

                            avatar.appendChild(img);
                            avatar.appendChild(fallback);
                        } else {
                            avatar.textContent = (user.username || '').charAt(0).toUpperCase();
                        }
                    }
                })
                .catch(err => {
                    // silently ignore per original behavior, but log for debugging
                    console.debug('Could not load author profile for user', userId, err);
                });
        }
    });
}

function updateUserUI(user) {
    if (!user) return;

    // Update account dropdown
    const accountUsername = document.getElementById('accountUsername');
    const accountEmail = document.getElementById('accountEmail');
    const accountDropdownEmail = document.querySelector('.account-dropdown-email');
    const accountDropdownName = document.querySelector('.account-dropdown-name');

    if (accountUsername) {
        accountUsername.textContent = user.username || '';
    }
    if (accountEmail) {
        accountEmail.textContent = user.email || '';
    }
    if (accountDropdownEmail) {
        accountDropdownEmail.textContent = user.email || '';
    }
    if (accountDropdownName) {
        accountDropdownName.textContent = user.username || '';
    }

    // Update profile pictures
    updateProfilePictures(user);
}

// ============================================
// EVENT LISTENERS
// ============================================
function setupEventListeners() {
    // Account dropdown toggle
    const accountBtn = document.getElementById('accountBtn');
    const accountDropdown = document.getElementById('accountDropdown');

    if (accountBtn && accountDropdown) {
        accountBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            accountDropdown.classList.toggle('active');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!accountBtn.contains(e.target) && !accountDropdown.contains(e.target)) {
                accountDropdown.classList.remove('active');
            }
        });
    }

    // Logout
    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', async () => {
            confirmed = true;

            if (confirmed) {
                logout();
            }
        });
    }

    // Search functionality is handled by search-history.js
    // No need to duplicate event listeners here



    // Sidebar Toggle
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sideNav = document.querySelector('.side-nav');
    if (sidebarToggle && sideNav) {
        sidebarToggle.addEventListener('click', () => {
            sideNav.classList.toggle('collapsed');
            document.body.classList.toggle('sidebar-collapsed');
            // Trigger a resize event to let widgets re-snap
            window.dispatchEvent(new Event('resize'));
        });
    }

    // Base Setup
    setupHeaderHeightObserver();

    // Suggestion Chips
    setupSuggestionChips();

    // Search Fallback (in case search-history.js fails)
    const searchInput = document.getElementById('searchInput');
    const searchBtn = document.getElementById('searchBtn');
    if (searchInput) {
        searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                const query = searchInput.value.trim();
                if (query) window.location.href = `search.php?q=${encodeURIComponent(query)}`;
            }
        });
    }
    if (searchBtn) {
        searchBtn.addEventListener('click', () => {
            const query = searchInput ? searchInput.value.trim() : '';
            if (query) window.location.href = `search.php?q=${encodeURIComponent(query)}`;
        });
    }
}

/**
 * Monitor Header Height for Padding Adjustments
 */
function setupHeaderHeightObserver() {
    const updateHeight = () => {
        const header = document.querySelector('.top-nav');
        if (header) {
            const height = header.getBoundingClientRect().height;
            document.documentElement.style.setProperty('--header-height', `${height}px`);
        }
    };

    // Initial
    updateHeight();

    // Resize Observer
    const header = document.querySelector('.top-nav');
    if (header) {
        new ResizeObserver(updateHeight).observe(header);
    }

    // Window Resize fallback
    window.addEventListener('resize', updateHeight);
}

/**
 * Generate Real Suggestion Chips based on cookies and subscriptions
 */
async function generateSuggestionChips() {
    const row = document.getElementById('suggestionsRow');
    const container = document.getElementById('suggestionsContainer');
    if (!row || !container) return;

    try {
        // 1. Get Interests from Cookie
        let interests = [];
        if (window.FloxInterests) {
            interests = window.FloxInterests.getCookie();
        }

        // 2. Get Subscriptions from Backend
        let subscriptions = [];
        if (currentUser && currentUser.id) {
            const subRes = await fetch(`../backend/getSubscriptions.php?user_id=${currentUser.id}`);
            const subData = await subRes.json();
            if (subData.success) {
                subscriptions = subData.subscriptions || [];
            }
        }

        // Filter and map interests (top 10 based on score)
        const interestChips = interests
            .filter(i => i.score > 1) // Only show scoring interests
            .slice(0, 10)
            .map(i => ({
                label: (i.value || '').charAt(0).toUpperCase() + (i.value || '').slice(1),
                category: i.value
            }));

        // Map subscriptions
        const subChips = subscriptions.map(s => ({
            label: `From ${s.username}`,
            category: `From ${s.username}`,
            isSub: true,
            username: s.username
        }));

        // Combine
        const allChips = [
            { label: 'All', category: 'all', active: true },
            ...subChips,
            ...interestChips
        ];

        // If no data (only "All" exists), hide the row
        if (allChips.length <= 1) {
            row.style.display = 'none';
            return;
        }

        // Render
        row.style.display = 'flex';
        container.innerHTML = allChips.map(chip => `
            <button class="suggestion-chip ${chip.active ? 'active' : ''}" 
                    data-category="${escapeHtml(chip.category)}"
                    ${chip.isSub ? `data-username="${escapeHtml(chip.username)}"` : ''}>
                ${escapeHtml(chip.label)}
            </button>
        `).join('');

    } catch (err) {
        console.error('Error generating suggestion chips:', err);
        row.style.display = 'none';
    }
}

/**
 * YouTube-style Suggestion Chips Filtering
 */
function setupSuggestionChips() {
    const container = document.getElementById('suggestionsContainer');
    if (!container) return;

    container.addEventListener('click', (e) => {
        const chip = e.target.closest('.suggestion-chip');
        if (!chip) return;

        // Update UI state
        container.querySelectorAll('.suggestion-chip').forEach(c => c.classList.remove('active'));
        chip.classList.add('active');

        const category = chip.dataset.category;
        const username = chip.dataset.username;
        const videosGrid = document.getElementById('videosGrid');

        if (category === 'all') {
            // Restore original recommended view
            renderVideos(videos, true);
        } else {
            const query = category.toLowerCase();
            const filtered = videos.filter(v => {
                const title = (v.title || '').toLowerCase();
                const desc = (v.description || '').toLowerCase();
                const author = (v.author && v.author.username || '').toLowerCase();

                // If it's a subscription chip, match username exactly
                if (username) {
                    return author === username.toLowerCase();
                }

                // Otherwise match category in title/desc
                return title.includes(query) || desc.includes(query);
            });

            // Render filtered set (flattened, no sections)
            renderVideos(filtered, false);

            // If no results, show a tiny hint in the grid
            if (filtered.length === 0 && videosGrid) {
                videosGrid.innerHTML = `
                    <div style="grid-column: 1/-1; text-align: center; padding: 60px 20px; color: var(--text-secondary);">
                        <i class="fa-solid fa-magnifying-glass" style="font-size: 32px; margin-bottom: 16px; opacity: 0.3;"></i>
                        <p>No videos found for "${chip.textContent.trim()}"</p>
                    </div>
                `;
            }
        }

        // Scroll chip into view smoothly
        chip.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });

        // Hide clips section if filtering
        const clipsSection = document.getElementById('homeClipsSection');
        const clipsPlaceholder = document.getElementById('homeClipsPlaceholder');
        if (clipsSection) {
            clipsSection.style.display = (category === 'all') ? 'block' : 'none';
        }
        if (clipsPlaceholder) {
            clipsPlaceholder.style.display = (category === 'all') ? 'block' : 'none';
        }

        const postsSection = document.getElementById('homePostsSection');
        if (postsSection) {
            postsSection.style.display = (category === 'all') ? 'block' : 'none';
        }
    });
}

/**
 * Load and render clips for the home page
 */
async function loadClips() {
    const clipsSection = document.getElementById('homeClipsSection');
    const clipsTrack = document.getElementById('clipsTrack');
    const clipsPlaceholder = document.getElementById('homeClipsPlaceholder');
    if (!clipsSection || !clipsTrack) return;

    try {
        const response = await fetch('../backend/getClips.php');
        const data = await response.json();

        if (data && data.success && data.clips && data.clips.length > 0) {
            // Move clips section to placeholder if it exists, otherwise hide it
            if (clipsPlaceholder) {
                clipsPlaceholder.appendChild(clipsSection);
                clipsSection.style.display = 'block';
            } else {
                // If no placeholder (not personalized or not All), hide it
                clipsSection.style.display = 'none';
            }

            clipsTrack.innerHTML = data.clips.map(clip => `
                <div class="clip-card" onclick="window.location.href='clips.php?id=${clip.id}'">
                    <div class="clip-thumbnail">
                        <img src="${clip.thumbnail_url || 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'}" 
                             alt="${escapeHtml(clip.title)}" 
                             loading="lazy">
                    </div>
                    <div class="clip-overlay">
                        <div class="clip-title">${escapeHtml(clip.title)}</div>
                        <div class="clip-views">${formatViews(clip.views)} views</div>
                    </div>
                </div >
        `).join('');
        } else {
            clipsSection.style.display = 'none';
        }
    } catch (error) {
        console.error('Error loading clips:', error);
        if (clipsSection) clipsSection.style.display = 'none';
    }
}


// ============================================
// UTILITY FUNCTIONS
// ============================================
function escapeHtml(text) {
    if (text === undefined || text === null) return '';
    const div = document.createElement('div');
    div.textContent = String(text);
    return div.innerHTML;
}

function formatViews(count) {
    const n = Number(count) || 0;
    if (n >= 1000000) {
        return (n / 1000000).toFixed(1).replace(/\.0$/, '') + 'M';
    } else if (n >= 1000) {
        return (n / 1000).toFixed(1).replace(/\.0$/, '') + 'K';
    }
    return n.toString();
}

function formatTimeAgo(dateString) {
    const now = new Date();
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return 'some time ago';

    const diffInSeconds = Math.floor((now - date) / 1000);

    if (diffInSeconds < 60) {
        return 'just now';
    } else if (diffInSeconds < 3600) {
        const minutes = Math.floor(diffInSeconds / 60);
        return `${minutes} minute${minutes !== 1 ? 's' : ''} ago`;
    } else if (diffInSeconds < 86400) {
        const hours = Math.floor(diffInSeconds / 3600);
        return `${hours} hour${hours !== 1 ? 's' : ''} ago`;
    } else if (diffInSeconds < 2592000) {
        const days = Math.floor(diffInSeconds / 86400);
        return `${days} day${days !== 1 ? 's' : ''} ago`;
    } else if (diffInSeconds < 31536000) {
        const months = Math.floor(diffInSeconds / 2592000);
        return `${months} month${months !== 1 ? 's' : ''} ago`;
    } else {
        const years = Math.floor(diffInSeconds / 31536000);
        return `${years} year${years !== 1 ? 's' : ''} ago`;
    }
}

async function loadPosts() {
    const postsSection = document.getElementById('homePostsSection');
    const postsGrid = document.getElementById('homePostsGrid');
    if (!postsSection || !postsGrid) return;

    try {
        const response = await fetch('../backend/getPosts.php');
        const data = await response.json();

        if (data && data.success && data.posts && data.posts.length > 0) {
            postsSection.style.display = 'block';
            postsGrid.innerHTML = data.posts.map(post => createPostCard(post)).join('');
        } else {
            postsSection.style.display = 'none';
        }
    } catch (error) {
        console.error('Error loading posts:', error);
        postsSection.style.display = 'none';
    }
}

function createPostCard(post) {
    const avatar = post.profile_picture ?
        `<img src="${escapeHtml(post.profile_picture)}" style="width:100%; height:100%; object-fit:cover;">` :
        `<div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; background:var(--accent-color); color:#fff; font-weight:700;">${post.username.charAt(0).toUpperCase()}</div>`;

    return `
        <div class="home-post-card" data-post-id="${post.id}" onclick="if(!event.target.closest('.post-stat')) window.location.href='view_post.php?id=${post.id}'" style="cursor:pointer;">
            <div class="home-post-header">
                <div class="home-post-avatar">${avatar}</div>
                <div class="home-post-user-info">
                    <div class="home-post-username">${escapeHtml(post.username)}</div>
                    <div class="home-post-date">${formatTimeAgo(post.created_at)}</div>
                </div>
            </div>
            <div class="home-post-body">${escapeHtml(post.content)}</div>
            <div class="home-post-footer">
                <div class="post-stat like-btn" onclick="likePost(${post.id}, this)">
                    <i class="fa-solid fa-heart ${post.is_liked ? 'active' : ''}"></i> <span class="like-count">${formatMetric(post.likes)}</span>
                </div>
                <div class="post-stat" onclick="toggleComments(${post.id}, this)">
                    <i class="fa-solid fa-comment"></i> <span class="comment-count">${formatMetric(post.comments)}</span>
                </div>
            </div>
            <div class="post-comments-section" id="comments-${post.id}" style="display:none;" onclick="event.stopPropagation()">
                <div class="comments-loading">Loading comments...</div>
                <div class="comments-list"></div>
                <div class="comment-input-wrapper">
                    <input type="text" placeholder="Add a comment..." class="post-comment-input">
                    <button onclick="submitComment(${post.id}, this)"><i class="fa-solid fa-paper-plane"></i></button>
                </div>
            </div>
        </div>
    `;
}

window.toggleComments = async function (postId, el) {
    const section = document.getElementById(`comments-${postId}`);
    if (section.style.display === 'none') {
        section.style.display = 'block';
        loadPostComments(postId);
    } else {
        section.style.display = 'none';
    }
};

async function loadPostComments(postId) {
    const section = document.getElementById(`comments-${postId}`);
    const list = section.querySelector('.comments-list');
    const loading = section.querySelector('.comments-loading');

    try {
        const res = await fetch(`../backend/getPostComments.php?post_id=${postId}`);
        const data = await res.json();
        if (data.success) {
            loading.style.display = 'none';
            if (!data.comments || data.comments.length === 0) {
                list.innerHTML = '<div style="padding:10px; opacity:0.5; font-size:12px;">No comments yet.</div>';
            } else {
                list.innerHTML = data.comments.map(c => `
                    <div class="post-comment-item">
                        <img src="${c.profile_picture || 'assets/default-avatar.png'}" class="comment-avatar">
                        <div class="comment-content">
                            <span class="comment-user">${escapeHtml(c.username)}</span>
                            <span class="comment-text">${escapeHtml(c.comment)}</span>
                        </div>
                    </div>
                `).join('');
            }
        } else {
            console.error('Comments error:', data.message);
            loading.textContent = 'Error: ' + data.message;
        }
    } catch (e) {
        console.error('Failed to load comments');
    }
}

window.submitComment = async function (postId, btn) {
    const input = btn.parentElement.querySelector('input');
    const comment = input.value.trim();
    if (!comment) return;

    btn.disabled = true;
    try {
        const res = await fetch('../backend/addPostComment.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ post_id: postId, comment: comment })
        });
        const data = await res.json();
        if (data.success) {
            input.value = '';
            loadPostComments(postId);
            // Update count in card
            const card = document.querySelector(`.home-post-card[data-post-id="${postId}"]`);
            if (card) {
                const count = card.querySelector('.comment-count');
                count.textContent = parseInt(count.textContent) + 1;
            }
        } else {
        }
    } catch (e) {
        console.error('Comment submission failed');
    } finally {
        btn.disabled = false;
    }
};

window.likePost = async function (postId, el) {
    const icon = el.querySelector('i');
    const label = el.querySelector('.like-count');

    // Optimistic UI
    const isLiked = icon.classList.contains('active');
    icon.classList.toggle('active');
    icon.style.transform = 'scale(1.3)';
    setTimeout(() => icon.style.transform = 'scale(1)', 200);

    try {
        const res = await fetch('../backend/likePost.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ post_id: postId })
        });
        const data = await res.json();
        if (data.success) {
            label.textContent = formatMetric(data.likes);
            if (data.status === 'liked') icon.classList.add('active');
            else icon.classList.remove('active');
        }
    } catch (e) {
        console.error('Silent like error');
    }
};

function formatMetric(num) {
    num = parseInt(num) || 0;
    if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
    if (num >= 1000) return (num / 1000).toFixed(1) + 'K';
    return num;
}
