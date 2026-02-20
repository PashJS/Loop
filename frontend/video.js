// ============================================
// VIDEO PAGE FUNCTIONALITY
// ============================================
console.log("[Loop] Video.js script loaded.");
let currentVideo = null;
let comments = [];
let currentCommentSort = 'newest';
let isLoading = false;
let videoCreatorId = null;
// Map of optimistic/pending replies that are not yet confirmed by server
let pendingReplies = {}; // { replyId: replyObject }
let expandedReplyIds = new Set();
// Robust storage for locally added comments to keep them pinned at top
window.newlyAddedCommentIds = new Set();
let currentUserData = null;

// Next video overlay state
let nextOverlay = null;
let nextVideoData = null;
let autoPlayTimer = null;
let countdownInterval = null;
let isDynamicLoad = false;
let captionsEnabled = localStorage.getItem('captionsEnabled') !== 'false';

// Settings State
let sleepTimerInterval = null;
let sleepEndTime = localStorage.getItem('floxSleepEnd') ? parseInt(localStorage.getItem('floxSleepEnd')) : null;

// Guest Check Helper
const checkGuest = (action) => {
    if (window.isGuest) {
        // Option 1: Alert and redirect
        // alert(`Please sign in to ${action}.`);
        window.location.href = 'select_profile.php';
        return true;
    }
    return false;
};

// Initialize
const initVideoPage = () => {
    console.log("[Loop] Initializing Video Page...");
    fetchCurrentUserInfo(); // Pre-fetch for optimistic updates
    loadVideo();
    if (typeof watchlistType !== 'undefined' && watchlistType !== '') {
        loadWatchlistSidebar();
    } else {
        loadRecommendations();
    }
    setupEventListeners();
    setupCustomVideoControls();
    setupVideoShortcuts();
    setupVideoSettings();
    setupNavigationState();
    initAmbientGlow();
    setupLiquidDropdown();
    setupReportFeature();
    setupCommentSort();
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initVideoPage);
} else {
    initVideoPage();
}

function setupNavigationState() {
    window.addEventListener('popstate', (e) => {
        if (e.state && e.state.videoId) {
            videoId = e.state.videoId;
            loadVideo();
        } else {
            const urlParams = new URLSearchParams(window.location.search);
            const id = parseInt(urlParams.get('id'));
            if (id && id !== videoId) {
                videoId = id;
                loadVideo();
            }
        }
    });
}

async function fetchCurrentUserInfo() {
    try {
        const res = await fetch('../backend/getUser.php');
        const data = await res.json();
        if (data.success) currentUserData = data.user;
    } catch (e) { console.error('Error fetching user info:', e); }
}

// ============================================
// API FUNCTIONS

async function loadVideo() {
    const videoLoading = document.getElementById('videoLoading');
    const videoPlayerContainer = document.getElementById('videoPlayerContainer');
    const videoInfoContainer = document.getElementById('videoInfoContainer');
    const commentsSection = document.getElementById('commentsSection');

    try {
        if (videoLoading) videoLoading.classList.remove('hidden');

        // Load video data with cache busting
        const response = await fetch(`../backend/getVideoById.php?id=${videoId}&t=${Date.now()}`);
        const data = await response.json();

        if (data.success) {
            // DEBUG: Log what API returned
            console.log('[CAPTIONS DEBUG] Raw API response data.video:', data.video);
            console.log('[CAPTIONS DEBUG] Raw API data.video.captions_url:', data.video.captions_url);

            currentVideo = data.video;
            videoCreatorId = data.video.author.id;

            // Update document title
            document.title = `Loop - ${data.video.title}`;

            // Show containers
            if (videoPlayerContainer) videoPlayerContainer.style.display = 'block';
            if (videoInfoContainer) videoInfoContainer.style.display = 'block';
            if (commentsSection) commentsSection.style.display = 'block';

            displayVideo(currentVideo);
            await loadComments();

            // Record view for analytics
            recordView(videoId);
            // Track interests for recommendations
            trackInterests(currentVideo);
        } else {
            throw new Error(data.message || 'Video not found');
        }
    } catch (error) {
        console.error('Error loading video:', error);
        if (videoLoading) {
            videoLoading.innerHTML = `
                <p style="color: var(--error-color);">Error loading video. Please try again.</p>
                <a href="home.php" style="color: var(--accent-color); margin-top: 16px;">Go back to home</a>
            `;
        }
    } finally {
        if (videoLoading) videoLoading.classList.add('hidden');
    }
}

function displayVideo(video) {
    const videoPlayer = document.getElementById('videoPlayer');
    const videoPlayerContainer = document.getElementById('videoPlayerContainer');
    const videoInfoContainer = document.getElementById('videoInfoContainer');
    const commentsSection = document.getElementById('commentsSection');

    if (!videoPlayer) {
        console.error('videoPlayer element not found!');
        return;
    }

    // Set video source with proper MIME type
    const videoUrl = video.video_url;
    const extension = videoUrl.split('.').pop().toLowerCase();
    let mimeType = 'video/mp4';

    if (extension === 'webm') {
        mimeType = 'video/webm';
    } else if (extension === 'ogg' || extension === 'ogv') {
        mimeType = 'video/ogg';
    } else if (extension === 'mov') {
        mimeType = 'video/quicktime';
    }

    // Clear and set new source
    videoPlayer.crossOrigin = 'anonymous';
    videoPlayer.innerHTML = '';
    const source = document.createElement('source');
    source.src = videoUrl;
    source.type = mimeType;
    videoPlayer.appendChild(source);

    // Add Captions Track if available
    const captionsDisplay = document.getElementById('videoCaptionsDisplay');

    // DEBUG: Log the video object to see what we're receiving
    console.log('[CAPTIONS DEBUG] Video object received:', video);
    console.log('[CAPTIONS DEBUG] video.captions_url =', video.captions_url);
    console.log('[CAPTIONS DEBUG] typeof video.captions_url =', typeof video.captions_url);
    console.log('[CAPTIONS DEBUG] Boolean(video.captions_url) =', Boolean(video.captions_url));

    if (video.captions_url) {
        console.log('[CAPTIONS DEBUG] Loading captions from:', video.captions_url);

        // Remove any existing tracks first
        const existingTracks = videoPlayer.querySelectorAll('track');
        existingTracks.forEach(t => t.remove());

        const track = document.createElement('track');
        track.kind = 'captions';
        track.label = 'English';
        track.srclang = 'en';
        track.src = video.captions_url;
        track.default = true;
        videoPlayer.appendChild(track);

        // Unified Caption Handler
        window.currentManualCaptions = null;

        // Clean up any previous caption state
        if (window.manualUpdateHandler) {
            videoPlayer.removeEventListener('timeupdate', window.manualUpdateHandler);
            window.manualUpdateHandler = null;
        }

        const updateCaptionsDisplay = (text, cue = null) => {
            if (!captionsDisplay) return;
            if (text && captionsEnabled) {
                const cleanText = text.replace(/<[^>]*>/g, '').trim();
                if (captionsDisplay.getAttribute('data-last-text') === cleanText) return;
                captionsDisplay.setAttribute('data-last-text', cleanText);

                const words = cleanText.split(/\s+/).filter(w => w.length > 0);
                captionsDisplay.innerHTML = '';

                let cueDuration = (cue && cue.endTime > cue.startTime) ? (cue.endTime - cue.startTime) : 2.5;
                // Use 70% of the duration to ensure words are fully visible well before the end
                const activeDuration = cueDuration * 0.70;

                // WEIGHTING: Faster, more energetic pacing
                const weights = words.map(word => {
                    // Base weight is sqrt of length so long words don't take forever
                    let w = Math.sqrt(word.length);
                    // Minimal pause for punctuation to keep flow fast
                    if (word.match(/[.?!](['"]|\s|$)/)) w += 4;
                    else if (word.match(/[,;:-](['"]|\s|$)/)) w += 2;
                    return w;
                });

                const totalWeight = weights.reduce((a, b) => a + b, 0) || 1;

                // Speed boost: if many words, compress even more
                const speedFactor = words.length > 10 ? 0.85 : 1.0;

                let runningWeight = 0;

                words.forEach((word, i) => {
                    const span = document.createElement('span');
                    span.className = 'caption-word';
                    span.textContent = word;

                    const delay = ((runningWeight / totalWeight) * activeDuration * speedFactor);
                    span.style.animationDelay = `${delay.toFixed(3)}s`;

                    captionsDisplay.appendChild(span);
                    // Update weight for next word
                    runningWeight += weights[i]; // space is negligible
                });

                captionsDisplay.style.display = 'block';
            } else {
                captionsDisplay.style.display = 'none';
                captionsDisplay.innerHTML = '';
                captionsDisplay.setAttribute('data-last-text', '');
            }
        };

        window.forceCaptionsUpdate = () => {
            if (!captionsEnabled) {
                updateCaptionsDisplay(null);
                return;
            }

            // Check native track first
            const trk = videoPlayer.querySelector('track');
            if (trk && trk.track && trk.track.activeCues) {
                if (trk.track.activeCues.length > 0) {
                    const activeCues = Array.from(trk.track.activeCues);
                    const text = activeCues.map(cue => {
                        return cue.text || (cue.getCueAsHTML ? cue.getCueAsHTML().textContent : '');
                    }).join(' ');

                    updateCaptionsDisplay(text, activeCues[0]);
                    return;
                } else if (!window.currentManualCaptions) {
                    // If native exists but is currently empty, ensure hidden
                    updateCaptionsDisplay(null);
                    return;
                }
            }

            // Check manual captions
            if (window.currentManualCaptions) {
                const currentTime = videoPlayer.currentTime;
                const activeCue = window.currentManualCaptions.find(c => currentTime >= c.startTime && currentTime < c.endTime);
                updateCaptionsDisplay(activeCue ? activeCue.text : null, activeCue);
            } else {
                updateCaptionsDisplay(null);
            }
        };

        const loadManualCaptions = async () => {
            if (window.currentManualCaptions !== null) return;

            // Debug: Show loading state
            if (captionsDisplay) {
                captionsDisplay.innerHTML = '<span style="color: yellow; font-weight: bold; font-size: 24px;">CAPTIONS LOADING...</span>';
                captionsDisplay.style.display = 'block';
                captionsDisplay.style.opacity = '1';
                captionsDisplay.style.textShadow = '0 0 10px #000';
            }

            try {
                let fetchUrl = video.captions_url;
                // Fix path if it points to root /uploads but we are in a subdirectory
                // Assume we are in /frontend/, so we need to go up one level if url starts with /uploads
                if (fetchUrl.startsWith('/uploads')) {
                    fetchUrl = '..' + fetchUrl;
                }

                console.log('Fetching captions manually from:', fetchUrl);
                const response = await fetch(fetchUrl);
                if (!response.ok) throw new Error('Fetch failed: ' + response.status);

                const vttText = await response.text();
                const cues = [];
                const lines = vttText.split(/[\r\n]+/);
                let currentCue = null;
                console.log('Parsing VTT, lines:', lines.length);

                for (let line of lines) {
                    line = line.trim();
                    if (!line || line.startsWith('WEBVTT') || line.startsWith('NOTE')) continue;

                    // Flexible Regex: allows 1-3 digits for MS, dot or comma
                    const timeMatch = line.match(/(\d{1,2}):(\d{2}):(\d{2})[.,](\d{1,3})\s+-->\s+(\d{1,2}):(\d{2}):(\d{2})[.,](\d{1,3})/) ||
                        line.match(/(\d{1,2}):(\d{2})[.,](\d{1,3})\s+-->\s+(\d{1,2}):(\d{2})[.,](\d{1,3})/);

                    if (timeMatch) {
                        if (currentCue && currentCue.text) cues.push(currentCue);

                        let start, end;
                        // HH:MM:SS.mmm
                        if (timeMatch.length === 9) {
                            start = parseInt(timeMatch[1]) * 3600 + parseInt(timeMatch[2]) * 60 + parseInt(timeMatch[3]) + parseInt(timeMatch[4]) / 1000;
                            end = parseInt(timeMatch[5]) * 3600 + parseInt(timeMatch[6]) * 60 + parseInt(timeMatch[7]) + parseInt(timeMatch[8]) / 1000;
                        }
                        // MM:SS.mmm
                        else {
                            start = parseInt(timeMatch[1]) * 60 + parseInt(timeMatch[2]) + parseInt(timeMatch[3]) / 1000;
                            end = parseInt(timeMatch[4]) * 60 + parseInt(timeMatch[5]) + parseInt(timeMatch[6]) / 1000;
                        }
                        currentCue = { startTime: start, endTime: end, text: '' };
                    } else if (currentCue && line && isNaN(line)) {
                        const clean = line.replace(/<[^>]*>/g, '');
                        currentCue.text += (currentCue.text ? ' ' : '') + clean;
                    }
                }
                if (currentCue && currentCue.text) cues.push(currentCue);

                window.currentManualCaptions = cues;
                console.log('Loaded', cues.length, 'manual captions');

                // Clear loading text
                if (captionsDisplay) captionsDisplay.innerHTML = '';

                window.manualUpdateHandler = () => window.forceCaptionsUpdate();
                videoPlayer.addEventListener('timeupdate', window.manualUpdateHandler);
                window.forceCaptionsUpdate();

            } catch (error) {
                console.error('Manual captions failed:', error);
                if (captionsDisplay) {
                    captionsDisplay.innerHTML = `<span style="color: red;">CAPTIONS ERROR: ${error.message}</span>`;
                    setTimeout(() => { if (captionsDisplay) captionsDisplay.style.display = 'none'; }, 5000);
                }
            }
        };

        const setupTrackHandler = (textTrack) => {
            if (!textTrack) {
                loadManualCaptions();
                return;
            }

            textTrack.mode = 'hidden';
            textTrack.oncuechange = () => {
                console.log('Cue change, active:', textTrack.activeCues?.length);
                window.forceCaptionsUpdate();
            };

            // Check if already has cues
            if (textTrack.cues && textTrack.cues.length > 0) {
                console.log('Native track has cues');
                window.forceCaptionsUpdate();
            } else {
                setTimeout(() => {
                    if (!textTrack.cues || textTrack.cues.length === 0) {
                        console.warn('Native track empty after 2s, falling back');
                        loadManualCaptions();
                    }
                }, 2000);
            }
        };

        // Try to setup native track with fallbacks
        if (!track.track) {
            track.onload = () => setupTrackHandler(track.track);
            track.onerror = () => loadManualCaptions();
            // Timeout fallback
            setTimeout(() => { if (!window.currentManualCaptions && (!track.track || !track.track.cues)) loadManualCaptions(); }, 3000);
        } else {
            setupTrackHandler(track.track);
        }
    } else {
        // Clear captions display if no captions
        if (captionsDisplay) {
            captionsDisplay.style.display = 'none';
            captionsDisplay.textContent = '';
        }
        console.log('No captions URL provided');

        // IMPORTANT: Define this stub so other functions (toggleCaptions) don't crash
        // if they try to call it when no URL was present.
        // IMPORTANT: Define this stub so other functions (toggleCaptions) don't crash
        // if they try to call it when no URL was present.
        const updateCaptionsDisplay = (text, cue = null) => {
            if (captionsDisplay) {
                captionsDisplay.style.display = 'none';
                captionsDisplay.innerHTML = '';
            }
        };
        // Expose to window scope so toggleCaptions can find it
        window.updateCaptionsDisplay = updateCaptionsDisplay;
        // Also clear any global manual handler
        window.currentManualCaptions = null;
        if (window.manualUpdateHandler) {
            videoPlayer.removeEventListener('timeupdate', window.manualUpdateHandler);
            window.manualUpdateHandler = null;
        }
        window.forceCaptionsUpdate = () => { };
    }

    videoPlayer.load(); // Reload video element
    videoPlayer.removeAttribute('controls'); // Remove native controls

    // Render Chapters
    const chaptersContainer = document.getElementById('videoChaptersContainer');
    if (chaptersContainer && video.chapters && video.chapters.length > 0) {
        chaptersContainer.innerHTML = '';
        // Wait for metadata to get duration for positioning
        videoPlayer.onloadedmetadata = () => {
            const duration = videoPlayer.duration;
            video.chapters.forEach(chapter => {
                const marker = document.createElement('div');
                marker.className = 'video-chapter-marker';
                const percentage = ((chapter.end_time - chapter.start_time) / duration) * 100;
                marker.style.width = percentage + '%';
                chaptersContainer.appendChild(marker);
            });
        };
    } else if (chaptersContainer) {
        chaptersContainer.innerHTML = '';
    }

    // Ensure ambient glow is ready for new source
    if (typeof initAmbientGlow === 'function') initAmbientGlow();

    if (isDynamicLoad) {
        videoPlayer.play().catch(e => console.log('Autoplay blocked:', e));
        isDynamicLoad = false;
    }

    // Update OVERLAY Action Buttons
    const overlayLikeBtn = document.getElementById('overlayLikeBtn');
    const overlayDislikeBtn = document.getElementById('overlayDislikeBtn');
    const overlayCommentBtn = document.getElementById('overlayCommentBtn');

    if (overlayLikeBtn) {
        overlayLikeBtn.classList.toggle('liked', !!video.is_liked);
        const count = overlayLikeBtn.querySelector('span');
        if (count) count.textContent = formatViews(video.likes || 0);
    }

    const videoLikeBtn = document.getElementById('videoLikeBtn');
    if (videoLikeBtn) {
        videoLikeBtn.classList.toggle('liked', !!video.is_liked);
        const count = videoLikeBtn.querySelector('span') || document.getElementById('likeCount');
        if (count) count.textContent = formatViews(video.likes || 0);
    }

    if (overlayDislikeBtn) {
        overlayDislikeBtn.classList.toggle('disliked', !!video.is_disliked);
    }

    const videoDislikeBtn = document.getElementById('videoDislikeBtn');
    if (videoDislikeBtn) {
        videoDislikeBtn.classList.toggle('disliked', !!video.is_disliked);
    }

    if (overlayCommentBtn) {
        const count = overlayCommentBtn.querySelector('span');
        if (count) count.textContent = '...';
    }

    const overlayHeartBtn = document.getElementById('overlayHeartBtn');
    if (overlayHeartBtn) overlayHeartBtn.classList.toggle('favorited', !!video.is_favorited);

    const overlaySaveBtn = document.getElementById('overlaySaveBtn');
    if (overlaySaveBtn) overlaySaveBtn.classList.toggle('saved', !!video.is_saved);

    const saveBtn = document.getElementById('saveBtn');
    if (saveBtn) {
        saveBtn.classList.toggle('saved', !!video.is_saved);
        const saveText = saveBtn.querySelector('span');
        if (saveText) saveText.textContent = video.is_saved ? 'Saved' : 'Save';
    }

    // Set video info
    const titleEl = document.getElementById('videoTitle');
    if (titleEl) titleEl.textContent = video.title;

    // Set video player header info
    const videoPlayerHeaderTitle = document.getElementById('videoPlayerTitle');
    const videoPlayerHeaderCreator = document.getElementById('videoPlayerCreator');
    const videoMiniTitle = document.getElementById('videoMiniTitle');
    if (videoPlayerHeaderTitle) videoPlayerHeaderTitle.textContent = video.title;
    if (videoPlayerHeaderCreator) videoPlayerHeaderCreator.textContent = video.author.username;
    if (videoMiniTitle) videoMiniTitle.textContent = '• ' + video.title;

    // Display hashtags
    const hashtagsContainer = document.getElementById('videoHashtags');
    if (hashtagsContainer) {
        hashtagsContainer.innerHTML = '';
        if (video.hashtags && video.hashtags.length > 0) {
            video.hashtags.forEach(tag => {
                const link = document.createElement('a');
                link.href = `home.php?search=%23${encodeURIComponent(tag)}`;
                link.className = 'video-hashtag';
                link.textContent = `#${tag}`;
                hashtagsContainer.appendChild(link);
            });
        }
    }

    // Views and Date
    const viewsEl = document.getElementById('videoViews');
    if (viewsEl) viewsEl.textContent = `${formatViews(video.views)} views`;
    const dateEl = document.getElementById('videoDate');
    if (dateEl) dateEl.textContent = formatTimeAgo(video.created_at);

    // Author Info
    const authorNameEl = document.getElementById('authorName');
    if (authorNameEl) {
        const author = video.author;
        let badgeHtml = '';
        if (author.is_pro && author.comment_badge) {
            if (author.comment_badge === 'pro') {
                badgeHtml = `<span class="comment-author-badge pro-svg" style="margin-left: 5px; width: 22px; height: 16px; display: inline-flex;" title="Pro Member"><svg width="100%" height="100%" viewBox="0 0 340 96" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M87.5524 2.5625H121.74C128.865 2.5625 134.886 3.72917 139.802 6.0625C144.761 8.39583 148.532 11.6667 151.115 15.875C153.698 20.0833 154.99 25 154.99 30.625C154.99 38.2917 153.282 44.9375 149.865 50.5625C146.49 56.1458 141.657 60.4792 135.365 63.5625C129.073 66.6042 121.615 68.125 112.99 68.125H100.177L94.9274 92.75H68.4899L87.5524 2.5625ZM109.802 22.5625L104.24 48.5H113.177C116.261 48.5 118.948 47.8333 121.24 46.5C123.532 45.125 125.302 43.2708 126.552 40.9375C127.802 38.5625 128.427 35.9167 128.427 33C128.427 29.4167 127.427 26.7917 125.427 25.125C123.427 23.4167 120.532 22.5625 116.74 22.5625H109.802ZM153.24 92.75L172.427 2.5625H212.99C219.323 2.5625 224.698 3.75 229.115 6.125C233.573 8.45833 236.969 11.6458 239.302 15.6875C241.636 19.7292 242.802 24.3333 242.802 29.5C242.802 33.75 241.99 37.875 240.365 41.875C238.74 45.8333 236.344 49.3958 233.177 52.5625C230.052 55.7292 226.198 58.2083 221.615 60L231.615 92.75H203.99L195.49 62.5H196.115H186.177L179.74 92.75H153.24ZM194.802 22.125L189.99 44.375H201.802C204.761 44.375 207.323 43.8333 209.49 42.75C211.657 41.6667 213.323 40.1667 214.49 38.25C215.657 36.3333 216.24 34.1458 216.24 31.6875C216.24 28.4792 215.261 26.0833 213.302 24.5C211.386 22.9167 208.594 22.125 204.927 22.125H194.802ZM296.427 22.125C293.386 22.125 290.511 22.9583 287.802 24.625C285.136 26.2917 282.761 28.6042 280.677 31.5625C278.594 34.5208 276.948 37.9375 275.74 41.8125C274.573 45.6875 273.99 49.8542 273.99 54.3125C273.99 58.1042 274.615 61.4167 275.865 64.25C277.115 67.0833 278.865 69.2917 281.115 70.875C283.365 72.4167 286.011 73.1875 289.052 73.1875C292.094 73.1875 294.969 72.3542 297.677 70.6875C300.386 69.0208 302.782 66.7083 304.865 63.75C306.948 60.7917 308.573 57.375 309.74 53.5C310.948 49.5833 311.552 45.3958 311.552 40.9375C311.552 37.1042 310.927 33.7917 309.677 31C308.427 28.1667 306.657 25.9792 304.365 24.4375C302.115 22.8958 299.469 22.125 296.427 22.125ZM288.24 94.3125C279.657 94.3125 272.282 92.6458 266.115 89.3125C259.99 85.9375 255.282 81.3542 251.99 75.5625C248.74 69.7708 247.115 63.2292 247.115 55.9375C247.115 47.6875 248.407 40.1875 250.99 33.4375C253.573 26.6875 257.136 20.8958 261.677 16.0625C266.261 11.2292 271.573 7.52083 277.615 4.9375C283.657 2.3125 290.136 1 297.052 1C305.886 1 313.365 2.6875 319.49 6.0625C325.657 9.4375 330.344 14.0208 333.552 19.8125C336.802 25.5625 338.427 32.0833 338.427 39.375C338.427 47.6667 337.115 55.1875 334.49 61.9375C331.907 68.6875 328.302 74.4792 323.677 79.3125C319.094 84.1458 313.761 87.8542 307.677 90.4375C301.636 93.0208 295.157 94.3125 288.24 94.3125Z" fill="white"/></svg></span>`;
            } else if (author.comment_badge === 'crown') {
                badgeHtml = `<span class="comment-author-badge crown" style="margin-left: 5px;" title="Crown"><i class="fa-solid fa-crown" style="color: #ffd700;"></i></span>`;
            } else if (author.comment_badge === 'bolt') {
                badgeHtml = `<span class="comment-author-badge bolt" style="margin-left: 5px;" title="Electricity"><i class="fa-solid fa-bolt" style="color: #ffeb3b;"></i></span>`;
            } else if (author.comment_badge === 'verified') {
                badgeHtml = `<span class="comment-author-badge verified" style="margin-left: 5px;" title="Verified"><i class="fa-solid fa-check-double" style="color: #3ea6ff;"></i></span>`;
            }
        }
        authorNameEl.innerHTML = `<a href="user_profile.php?user_id=${author.id}" style="text-decoration: none; color: inherit; display: flex; align-items: center;">${escapeHtml(author.username)} ${badgeHtml}</a>`;
    }

    const authorSubsEl = document.getElementById('authorSubs');
    if (authorSubsEl) {
        const subCount = video.author.subscriber_count || 0;
        const subText = subCount === 1 ? 'subscriber' : 'subscribers';
        authorSubsEl.textContent = `${formatViews(subCount)} ${subText}`;
    }

    // Fetch and Set Author Avatar
    const authorAvatar = document.getElementById('authorAvatar');
    if (authorAvatar) {
        fetch(`../backend/getUserProfile.php?user_id=${video.author.id}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const authorInitial = (video.author && video.author.username) ? video.author.username.charAt(0).toUpperCase() : '?';
                    const authorDisplay = (video.author && video.author.username) ? video.author.username : 'Unknown';

                    if (video.author && video.author.profile_picture) {
                        authorAvatar.innerHTML = `<a href="user_profile.php?user_id=${video.author.id}" style="display: block; width: 100%; height: 100%;"><img src="${escapeHtml(video.author.profile_picture)}" alt="${escapeHtml(authorDisplay)}" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;"></a>`;
                    } else {
                        authorAvatar.innerHTML = `<a href="user_profile.php?user_id=${video.author ? video.author.id : '#'}" style="display: flex; width: 100%; height: 100%; align-items: center; justify-content: center; background: var(--accent-color); border-radius: 50%; color: white; font-weight: 600; text-decoration: none;">${authorInitial}</a>`;
                    }
                    setupSubscribeButton(data.user.id, data.user.is_subscribed, data.user.subscriber_count);
                } else {
                    setupSubscribeButton(video.author.id, false, video.author.subscriber_count || 0);
                }
            })
            .catch(() => {
                setupSubscribeButton(video.author.id, false, video.author.subscriber_count || 0);
            });
    }

    const descEl = document.getElementById('videoDescription');
    if (descEl) descEl.textContent = video.description || 'No description';

    // Global Action Buttons State
    updateLikeButton(video.likes, video.is_liked);
    updateDislikeButton(video.dislikes, video.is_disliked);
    updateFavoriteButton(video.favorites, video.is_favorited);

    // Final UI sanity check
    if (videoPlayerContainer) videoPlayerContainer.style.display = 'block';
}

async function loadComments() {
    try {
        window.allCommentsData = null; // Clear cache to force fresh render
        const response = await fetch(`../backend/getComments.php?video_id=${videoId}`);
        const data = await response.json();

        if (data.success) {
            comments = data.comments || [];

            // Merge any pending optimistic replies
            const pendingIds = Object.keys(pendingReplies).map(id => parseInt(id, 10));
            pendingIds.forEach(id => {
                const pending = pendingReplies[id];
                if (!findCommentById(comments, id)) {
                    const attached = addReplyToComments(comments, pending.parent_id, pending);
                    if (!attached) {
                        if (!findCommentById(comments, pending.id)) {
                            comments.unshift(Object.assign({}, pending, { replies: [], _pending: true }));
                        }
                    }
                } else {
                    delete pendingReplies[id];
                }
            });

            // PRESERVE _isNew STATUS from old comments array to new one
            // This prevents "sticky" new comments from jumping down if a background reload happens
            if (window.allCommentsData) {
                window.allCommentsData.forEach(oldC => {
                    if (oldC._isNew) {
                        const newC = comments.find(c => c.id === oldC.id);
                        if (newC) newC._isNew = true;
                    }
                });
            }

            displayComments(comments);
        }
    } catch (error) {
        console.error('Error loading comments:', error);
    }
}

function displayComments(commentsArray) {
    window.allCommentsData = commentsArray;
    const overlayList = document.getElementById('overlayCommentsList');
    const mainList = document.getElementById('commentsList');

    const videoCreatorId = currentVideo ? currentVideo.author.id : null;

    function countAllComments(arr) {
        let count = 0;
        if (!arr || !Array.isArray(arr)) return 0;
        arr.forEach(c => {
            count += 1;
            if (c.replies) count += countAllComments(c.replies);
        });
        return count;
    }

    const totalComments = countAllComments(commentsArray);

    // Update UI counters
    const overlayHeaderCount = document.getElementById('overlayCommentsHeaderCount');
    const mainCommentsCount = document.getElementById('commentsCount');
    if (overlayHeaderCount) overlayHeaderCount.textContent = totalComments;
    if (mainCommentsCount) mainCommentsCount.textContent = totalComments;

    const overlayCommentBtn = document.getElementById('overlayCommentBtn');
    if (overlayCommentBtn) {
        const span = overlayCommentBtn.querySelector('span');
        if (span) span.textContent = formatViews(totalComments);
    }

    if (commentsArray.length === 0) {
        const emptyHtml = `<div class="empty-comments" style="text-align: center; padding: 20px; opacity: 0.6;"><p>No comments yet.</p></div>`;
        if (overlayList) overlayList.innerHTML = emptyHtml;
        if (mainList) mainList.innerHTML = emptyHtml;
        return;
    }

    // Sorting Logic - BUCKET STRATEGY
    // 1. Sticky (my new comments)
    // 2. Pinned
    // 3. Regular (sorted by criteria)

    const isSticky = (c) => {
        return window.newlyAddedCommentIds.has(c.id) ||
            window.newlyAddedCommentIds.has(String(c.id)) ||
            window.newlyAddedCommentIds.has(parseInt(c.id)) ||
            (c.author && c.author.id == window.currentUserId && (Date.now() - new Date(c.created_at).getTime() < 60000));
    };

    const stickyComments = [];
    const pinnedComments = [];
    const regularComments = [];

    commentsArray.forEach(c => {
        if (isSticky(c)) {
            stickyComments.push(c);
        } else if (c.is_pinned) {
            pinnedComments.push(c);
        } else {
            regularComments.push(c);
        }
    });

    // Sort the regular bucket
    regularComments.sort((a, b) => {
        switch (currentCommentSort) {
            case 'oldest':
                return new Date(a.created_at) - new Date(b.created_at);
            case 'most_liked':
                return (b.likes || 0) - (a.likes || 0);
            case 'less_liked':
                return (a.likes || 0) - (b.likes || 0);
            case 'timed':
                const timestampRegex = /(?:([0-9]+):)?([0-5]?[0-9]):([0-5][0-9])/;
                const aHasTime = timestampRegex.test(a.comment) ? 1 : 0;
                const bHasTime = timestampRegex.test(b.comment) ? 1 : 0;
                if (aHasTime !== bHasTime) return bHasTime - aHasTime;
                return new Date(b.created_at) - new Date(a.created_at);
            case 'newest':
            default:
                return new Date(b.created_at) - new Date(a.created_at);
        }
    });

    // Combine buckets
    // Newest sticky first
    stickyComments.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));

    const finalSorted = [...stickyComments, ...pinnedComments, ...regularComments];
    const commentsHtml = finalSorted.map(comment => renderCommentHTML(comment, videoCreatorId, 0)).join('');
    if (overlayList) overlayList.innerHTML = commentsHtml;
    if (mainList) mainList.innerHTML = commentsHtml;

    setupCommentInteractions();

    // Restore expanded states for both main and overlay lists
    expandedReplyIds.forEach(id => {
        const repliesDivs = document.querySelectorAll(`[id="replies-${id}"]`);
        repliesDivs.forEach(div => {
            div.style.display = 'flex';
            const wrapper = div.closest('.replies-wrapper');
            const btn = wrapper ? wrapper.querySelector('.toggle-replies-btn') : null;
            if (btn) {
                const icon = btn.querySelector('i');
                const span = btn.querySelector('span');
                if (icon) { icon.classList.replace('fa-caret-down', 'fa-caret-up'); }
                if (span) span.textContent = 'Hide replies';
            }
        });
    });
}

function renderCommentHTML(comment, creatorId, depth = 0, parentAuthorName = null) {
    const isPinned = comment.is_pinned || false;
    const isCreator = comment.author.id === creatorId;
    const canEdit = comment.author.id === window.currentUserId;
    const canDelete = canEdit || window.currentUserId === creatorId;
    const canPin = window.currentUserId === creatorId;
    const isOwner = comment.author.id === window.currentUserId;
    const profilePic = comment.author.profile_picture || null;
    const author = comment.author;

    // Badge Logic
    let badgeHtml = '';
    if (author.is_pro && author.comment_badge) {
        if (author.comment_badge === 'pro') {
            badgeHtml = `<span class="comment-author-badge pro-svg" style="width: 22px; height: 16px; display: inline-flex;" title="Pro Member"><svg width="100%" height="100%" viewBox="0 0 340 96" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M87.5524 2.5625H121.74C128.865 2.5625 134.886 3.72917 139.802 6.0625C144.761 8.39583 148.532 11.6667 151.115 15.875C153.698 20.0833 154.99 25 154.99 30.625C154.99 38.2917 153.282 44.9375 149.865 50.5625C146.49 56.1458 141.657 60.4792 135.365 63.5625C129.073 66.6042 121.615 68.125 112.99 68.125H100.177L94.9274 92.75H68.4899L87.5524 2.5625ZM109.802 22.5625L104.24 48.5H113.177C116.261 48.5 118.948 47.8333 121.24 46.5C123.532 45.125 125.302 43.2708 126.552 40.9375C127.802 38.5625 128.427 35.9167 128.427 33C128.427 29.4167 127.427 26.7917 125.427 25.125C123.427 23.4167 120.532 22.5625 116.74 22.5625H109.802ZM153.24 92.75L172.427 2.5625H212.99C219.323 2.5625 224.698 3.75 229.115 6.125C233.573 8.45833 236.969 11.6458 239.302 15.6875C241.636 19.7292 242.802 24.3333 242.802 29.5C242.802 33.75 241.99 37.875 240.365 41.875C238.74 45.8333 236.344 49.3958 233.177 52.5625C230.052 55.7292 226.198 58.2083 221.615 60L231.615 92.75H203.99L195.49 62.5H196.115H186.177L179.74 92.75H153.24ZM194.802 22.125L189.99 44.375H201.802C204.761 44.375 207.323 43.8333 209.49 42.75C211.657 41.6667 213.323 40.1667 214.49 38.25C215.657 36.3333 216.24 34.1458 216.24 31.6875C216.24 28.4792 215.261 26.0833 213.302 24.5C211.386 22.9167 208.594 22.125 204.927 22.125H194.802ZM296.427 22.125C293.386 22.125 290.511 22.9583 287.802 24.625C285.136 26.2917 282.761 28.6042 280.677 31.5625C278.594 34.5208 276.948 37.9375 275.74 41.8125C274.573 45.6875 273.99 49.8542 273.99 54.3125C273.99 58.1042 274.615 61.4167 275.865 64.25C277.115 67.0833 278.865 69.2917 281.115 70.875C283.365 72.4167 286.011 73.1875 289.052 73.1875C292.094 73.1875 294.969 72.3542 297.677 70.6875C300.386 69.0208 302.782 66.7083 304.865 63.75C306.948 60.7917 308.573 57.375 309.74 53.5C310.948 49.5833 311.552 45.3958 311.552 40.9375C311.552 37.1042 310.927 33.7917 309.677 31C308.427 28.1667 306.657 25.9792 304.365 24.4375C302.115 22.8958 299.469 22.125 296.427 22.125ZM288.24 94.3125C279.657 94.3125 272.282 92.6458 266.115 89.3125C259.99 85.9375 255.282 81.3542 251.99 75.5625C248.74 69.7708 247.115 63.2292 247.115 55.9375C247.115 47.6875 248.407 40.1875 250.99 33.4375C253.573 26.6875 257.136 20.8958 261.677 16.0625C266.261 11.2292 271.573 7.52083 277.615 4.9375C283.657 2.3125 290.136 1 297.052 1C305.886 1 313.365 2.6875 319.49 6.0625C325.657 9.4375 330.344 14.0208 333.552 19.8125C336.802 25.5625 338.427 32.0833 338.427 39.375C338.427 47.6667 337.115 55.1875 334.49 61.9375C331.907 68.6875 328.302 74.4792 323.677 79.3125C319.094 84.1458 313.761 87.8542 307.677 90.4375C301.636 93.0208 295.157 94.3125 288.24 94.3125Z" fill="currentColor"/></svg></span>`;
        } else if (author.comment_badge === 'crown') {
            badgeHtml = `<span class="comment-author-badge crown" title="Crown"><i class="fa-solid fa-crown"></i></span>`;
        } else if (author.comment_badge === 'bolt') {
            badgeHtml = `<span class="comment-author-badge bolt" title="Electricity"><i class="fa-solid fa-bolt"></i></span>`;
        } else if (author.comment_badge === 'verified') {
            badgeHtml = `<span class="comment-author-badge verified" title="Verified"><i class="fa-solid fa-check-double"></i></span>`;
        }
    }

    // Pro Name Badge (Box) Logic
    let authorDisplay = '';
    if (author.is_pro && author.name_badge === 'on') {
        authorDisplay = `
            <a href="user_profile.php?user_id=${author.id}" class="premium-nickname-box">
                <i class="fa-solid fa-bolt mini-icon" style="color: #ffeb3b;"></i>
                <span>${escapeHtml(author.username)}</span>
                <i class="fa-solid fa-crown mini-icon" style="color: #ffd700;"></i>
                ${badgeHtml}
            </a>`;
    } else {
        authorDisplay = `
            <a href="user_profile.php?user_id=${author.id}" class="comment-author">${escapeHtml(author.username)}</a>
            ${badgeHtml}`;
    }

    let replyingToHtml = '';
    if (depth > 0 && parentAuthorName) {
        replyingToHtml = `
            <div class="replying-to-indicator">
                <i class="fa-solid fa-reply"></i>
                <span><strong>${escapeHtml(comment.author.username)}</strong> * <strong>@${escapeHtml(parentAuthorName)}</strong></span>
            </div>`;
    }

    const nextDepth = depth + 1;
    let repliesHtml = '';

    if (comment.replies && comment.replies.length > 0) {
        // SORT REPLIES: Sticky (New) -> Oldest First (Standard Thread order)
        // We want new user replies to be at the TOP (Newest) or just "First in visual order"
        // Wait, standard threads usually go Top->Down (Oldest->Newest).
        // But the user specifically asked: "my reply needs to be in the top of the other replies"
        // So we want: Sticky -> Oldest -> Newest (rest)

        const isStickyReply = (c) => {
            return window.newlyAddedCommentIds.has(c.id) ||
                window.newlyAddedCommentIds.has(String(c.id)) ||
                window.newlyAddedCommentIds.has(parseInt(c.id)) ||
                (c.author && c.author.id == window.currentUserId && (Date.now() - new Date(c.created_at).getTime() < 60000));
        };

        const sortedReplies = [...comment.replies].sort((a, b) => {
            const aSticky = isStickyReply(a);
            const bSticky = isStickyReply(b);

            if (aSticky && !bSticky) return -1; // Sticky first
            if (!aSticky && bSticky) return 1;

            // Default: Oldest first for threads? Or Newest first?
            // Usually replies are chronological.
            return new Date(a.created_at) - new Date(b.created_at);
        });

        repliesHtml = `
        <div class="replies-wrapper">
            <button class="toggle-replies-btn" data-comment-id="${comment.id}" data-count="${comment.replies.length}">
                <i class="fa-solid fa-caret-down"></i>
                <span>Show ${comment.replies.length} repl${comment.replies.length === 1 ? 'y' : 'ies'}</span>
            </button>
            <div class="comment-replies" id="replies-${comment.id}" style="display: none; flex-direction: column;">
                ${sortedReplies.map(r => renderCommentHTML(r, creatorId, nextDepth, author.username)).join('')}
            </div>
        </div>`;
    }

    const reactionsHtml = renderEmojiReactions(comment.reactions || [], comment.user_reaction, comment.id);

    return `
        <div class="comment-item ${isPinned ? 'pinned-comment' : ''} ${comment._pending ? 'pending-comment' : ''} ${depth > 0 ? 'reply-item' : ''}" data-comment-id="${comment.id}">
            <div class="comment-main-block">
                <div class="comment-avatar-small">
                    ${profilePic ? `<img src="${escapeHtml(profilePic)}" alt="Avatar">` : `<span>${(comment.author && comment.author.username ? comment.author.username : '?').charAt(0).toUpperCase()}</span>`}
                </div>
                <div class="comment-content">
                    <div class="comment-header">
                        <div class="comment-author-group">
                            ${isPinned ? '<span class="pinned-badge"><i class="fa-solid fa-thumbtack"></i> Pinned</span>' : ''}
                            ${authorDisplay}
                            ${isCreator ? '<span class="creator-badge">Creator</span>' : ''}
                            <span class="comment-date">${formatTimeAgo(comment.created_at)}</span>
                        </div>
                        <div class="comment-more-container">
                            <button class="comment-more-btn"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                            <div class="comment-more-menu">
                                ${canPin ? `<button class="more-menu-item pin" data-action="pin" data-id="${comment.id}" data-pinned="${isPinned}"><i class="fa-solid fa-thumbtack"></i> ${isPinned ? 'Unpin' : 'Pin'}</button>` : ''}
                                ${canEdit ? `<button class="more-menu-item edit" data-action="edit" data-id="${comment.id}"><i class="fa-solid fa-pencil"></i> Edit</button>` : ''}
                                ${canDelete ? `<button class="more-menu-item delete" data-action="delete" data-id="${comment.id}"><i class="fa-solid fa-trash"></i> Delete</button>` : ''}
                                <button class="more-menu-item report" data-action="report" data-id="${comment.id}"><i class="fa-solid fa-flag"></i> Report</button>
                                <button class="more-menu-item hide" data-action="hide" data-id="${comment.id}"><i class="fa-solid fa-eye-slash"></i> Hide</button>
                            </div>
                        </div>
                    </div>
                    ${replyingToHtml}
                    <div class="comment-text" id="comment-text-${comment.id}">${parseTimestamps(escapeHtml(comment.comment))}</div>

                    <div class="comment-reactions-bar">
                        ${reactionsHtml}
                    </div>

                    <div class="comment-actions">
                        <div class="comment-reactions-group">
                            <button class="comment-reaction-btn ${comment.is_liked ? 'liked' : ''}" data-comment-id="${comment.id}" data-type="like" title="${window.isGuest ? 'Sign in to like' : 'Like'}">
                                <i class="fa-solid fa-thumbs-up"></i>
                                <span>${comment.likes || 0}</span>
                            </button>
                            <button class="comment-reaction-btn ${comment.is_disliked ? 'disliked' : ''}" data-comment-id="${comment.id}" data-type="dislike" title="${window.isGuest ? 'Sign in to dislike' : 'Dislike'}">
                                <i class="fa-solid fa-thumbs-down"></i>
                            </button>
                            <button class="comment-emoji-react-btn" data-comment-id="${comment.id}" title="Add Reaction">
                                <i class="fa-regular fa-face-smile"></i>
                            </button>
                        </div>
                        <button class="comment-reply-btn" data-comment-id="${comment.id}">Reply</button>
                    </div>

                    <div class="comment-reply-form" style="display: none;">
                        <textarea class="reply-input" placeholder="Add a reply..."></textarea>
                        <div class="reply-form-actions">
                            <button class="btn-cancel-reply" data-cancel-reply="${comment.id}">Cancel</button>
                            <button class="btn-submit-reply" data-submit-reply="${comment.id}">Reply</button>
                        </div>
                    </div>
                    <div class="comment-edit-form" style="display: none;">
                        <textarea class="edit-input">${escapeHtml(comment.comment)}</textarea>
                        <div class="edit-form-actions">
                            <button class="btn-cancel-edit" data-cancel-edit="${comment.id}">Cancel</button>
                            <button class="btn-submit-edit" data-submit-edit="${comment.id}">Save</button>
                        </div>
                    </div>
                </div>
            </div>
            ${repliesHtml}
        </div>`;
}

function renderEmojiReactions(reactions, userReaction, commentId) {
    // Debug logging
    console.log(`[Reactions Debug] Comment ${commentId}:`, {
        reactions: JSON.parse(JSON.stringify(reactions || [])),
        userReaction,
        total: (reactions || []).reduce((sum, r) => sum + r.count, 0)
    });

    // Ensure we handle the user's reaction even if it's not in the passed list (though backend should return it)
    // Actually, backend returns separate `user_reaction` string.
    // If that reaction isn't in `reactions` array (e.g. count 1), we should construct it?
    // Backend `reactions` array comes from `SELECT ... GROUP BY`. It should contain ALL emojis with count > 0.

    if (!reactions) reactions = [];

    // Sort: User's reaction first? Or just Count?
    // Let's ensure if I reacted, that pill is visible.

    const sorted = [...reactions].sort((a, b) => b.count - a.count);
    let displayList = sorted.slice(0, 3);

    // If user has a reaction, ensure it's visible (prioritized)
    if (userReaction) {
        const inDisplayList = displayList.find(r => r.emoji === userReaction);
        if (!inDisplayList) {
            // Find it in the full sorted list
            const myReaction = sorted.find(r => r.emoji === userReaction);
            if (myReaction) {
                // Put user's reaction first, remove from current list if somehow present
                displayList = displayList.filter(r => r.emoji !== userReaction);
                displayList.unshift(myReaction);
                // Keep max 4 items if we added one
                if (displayList.length > 4) displayList = displayList.slice(0, 4);
            } else {
                // Fallback: User has reaction but it wasn't in server list? Make it.
                displayList.unshift({ emoji: userReaction, count: 1 });
                if (displayList.length > 4) displayList = displayList.slice(0, 4);
            }
        }
    }

    // Final deduplication by emoji (just in case)
    const seenEmojis = new Set();
    displayList = displayList.filter(r => {
        if (seenEmojis.has(r.emoji)) return false;
        seenEmojis.add(r.emoji);
        return true;
    });

    if (displayList.length === 0) return '';

    const hasMore = sorted.length > displayList.length;

    let html = displayList.map(r => `
        <div class="emoji-reaction-pill ${userReaction === r.emoji ? 'active' : ''}" data-emoji="${r.emoji}" data-id="${commentId}">
            <span class="emoji">${r.emoji}</span>
            <span class="count">${r.count}</span>
        </div>
    `).join('');

    if (hasMore) {
        html += `<button class="view-all-reactions-btn" data-id="${commentId}">View all</button>`;
    }

    return html;
}

// Interaction Handlers (Robust Delegation)
function setupCommentInteractions() {
    // We delegate to body so it catches events from both main comments and overlay/sidebar comments
    if (document.body._commentDelegationActive) return;

    document.body.addEventListener('click', async (e) => {
        const target = e.target;

        // 1. Emoji Reaction Pill
        const pill = target.closest('.emoji-reaction-pill');
        if (pill) {
            e.preventDefault();
            e.stopPropagation();
            const id = parseInt(pill.dataset.id);
            const emoji = pill.dataset.emoji;
            if (!id || !emoji) return; // Safety check
            await toggleEmojiReaction(id, emoji);
            return;
        }

        // 2. Emoji Option (In Picker)
        const option = target.closest('.emoji-option');
        if (option) {
            e.preventDefault();
            const id = parseInt(option.dataset.id);
            const emoji = option.dataset.emoji;
            option.closest('.emoji-picker-popover')?.classList.remove('active');
            await toggleEmojiReaction(id, emoji);
            return;
        }

        // 3. More Menu Toggle
        const moreBtn = target.closest('.comment-more-btn');
        if (moreBtn) {
            e.preventDefault();
            const container = moreBtn.closest('.comment-more-container');
            const menu = container.querySelector('.comment-more-menu');

            if (menu.classList.contains('active')) {
                menu.classList.remove('active');
            } else {
                // Close others
                document.querySelectorAll('.comment-more-menu.active').forEach(m => m.classList.remove('active'));
                container.classList.add('animating');
                menu.classList.add('active');
                setTimeout(() => container.classList.remove('animating'), 700);
            }
            return;
        }

        // 4. More Menu Item Click
        const menuItem = target.closest('.more-menu-item');
        if (menuItem) {
            e.preventDefault();
            const action = menuItem.dataset.action;
            const id = parseInt(menuItem.dataset.id);
            const menu = menuItem.closest('.comment-more-menu');
            menu?.classList.remove('active');

            if (action === 'react') {
                if (checkGuest('react')) return;
                const picker = menuItem.closest('.comment-item').querySelector('.emoji-picker-popover');
                document.querySelectorAll('.emoji-picker-popover.active').forEach(p => p.classList.remove('active'));
                picker?.classList.add('active');
            } else if (action === 'report') {
                if (checkGuest('report')) return;
                openCommentReportModal(id);
            } else if (action === 'delete') {
                if (confirm('Delete this comment?')) deleteComment(id);
            } else if (action === 'pin') {
                pinComment(id, menuItem.dataset.pinned === 'true');
            } else if (action === 'edit') {
                const item = menuItem.closest('.comment-item');
                item.querySelector('.comment-text').style.display = 'none';
                item.querySelector('.comment-edit-form').style.display = 'block';
                item.querySelector('.edit-input').focus();
            } else if (action === 'hide') {
                const item = menuItem.closest('.comment-item');
                item.style.opacity = '0.3';
                item.style.filter = 'blur(15px)';
                item.style.pointerEvents = 'none';
            }
            return;
        }

        // 5. Like/Dislike (Classic Buttons)
        const reactionBtn = target.closest('.comment-reaction-btn');
        if (reactionBtn) {
            e.preventDefault();
            const id = parseInt(reactionBtn.dataset.commentId);
            const type = reactionBtn.dataset.type;
            if (checkGuest(type)) return;
            if (type === 'like') toggleCommentLike(id);
            else toggleCommentDislike(id);
            return;
        }

        // 5b. Emoji React Button (Opens Native Emoji Picker)
        const emojiReactBtn = target.closest('.comment-emoji-react-btn');
        if (emojiReactBtn) {
            e.preventDefault();
            if (checkGuest('react')) return;
            const commentId = parseInt(emojiReactBtn.dataset.commentId);
            openNativeEmojiPicker(commentId, emojiReactBtn);
            return;
        }


        // 6. View All Reactions
        const viewAllBtn = target.closest('.view-all-reactions-btn');
        if (viewAllBtn) {
            e.preventDefault();
            openReactionsModal(parseInt(viewAllBtn.dataset.id));
            return;
        }

        // 7. Toggle Replies
        const toggleBtn = target.closest('.toggle-replies-btn');
        if (toggleBtn) {
            e.preventDefault();
            const id = toggleBtn.dataset.commentId;
            const wrapper = toggleBtn.closest('.replies-wrapper');
            const div = wrapper?.querySelector('.comment-replies');
            if (!div) return;

            const icon = toggleBtn.querySelector('i');
            const span = toggleBtn.querySelector('span');
            const count = toggleBtn.dataset.count;

            if (div.style.display === 'none') {
                div.style.display = 'flex';
                icon?.classList.replace('fa-caret-down', 'fa-caret-up');
                if (span) span.textContent = 'Hide replies';
                expandedReplyIds.add(id);
            } else {
                div.style.display = 'none';
                icon?.classList.replace('fa-caret-up', 'fa-caret-down');
                if (span) span.textContent = `Show ${count} repl${count == 1 ? 'y' : 'ies'} `;
                expandedReplyIds.delete(id);
            }
            return;
        }

        // 8. Reply Toggle
        const replyBtn = target.closest('.comment-reply-btn');
        if (replyBtn) {
            e.preventDefault();
            if (checkGuest('reply')) return;
            const item = replyBtn.closest('.comment-item');
            const form = item?.querySelector('.comment-reply-form');
            if (form) {
                form.style.display = 'block';
                form.querySelector('textarea')?.focus();
            }
            return;
        }

        // 9. Cancel Reply
        const cancelReplyBtn = target.closest('.btn-cancel-reply');
        if (cancelReplyBtn) {
            e.preventDefault();
            cancelReplyBtn.closest('.comment-reply-form').style.display = 'none';
            return;
        }

        // 10. Submit Reply
        const submitReplyBtn = target.closest('.btn-submit-reply');
        if (submitReplyBtn) {
            e.preventDefault();
            const id = parseInt(submitReplyBtn.dataset.submitReply);
            const input = submitReplyBtn.closest('.comment-reply-form').querySelector('textarea');
            if (input && input.value.trim()) addReply(id, input.value.trim(), submitReplyBtn);
            return;
        }

        // 11. Cancel Edit
        const cancelEditBtn = target.closest('.btn-cancel-edit');
        if (cancelEditBtn) {
            e.preventDefault();
            const item = cancelEditBtn.closest('.comment-item');
            item.querySelector('.comment-text').style.display = 'block';
            item.querySelector('.comment-edit-form').style.display = 'none';
            return;
        }

        // 12. Submit Edit
        const submitEditBtn = target.closest('.btn-submit-edit');
        if (submitEditBtn) {
            e.preventDefault();
            const id = parseInt(submitEditBtn.dataset.submitEdit);
            const input = submitEditBtn.closest('.comment-edit-form').querySelector('textarea');
            if (input && input.value.trim()) editComment(id, input.value.trim(), submitEditBtn);
            return;
        }

        // 13. Timestamp Click

        const timestamp = target.closest('.comment-timestamp');
        if (timestamp) {
            e.preventDefault();
            const timeStr = timestamp.dataset.time;
            const seconds = timestampToSeconds(timeStr);
            const video = document.getElementById('videoPlayer');
            if (video) {
                video.currentTime = seconds;
                video.play().catch(() => { });
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
            return;
        }
    });

    // Handle outside clicks to close menus (Inside setup but outside delegation listener)
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.comment-more-container')) {
            document.querySelectorAll('.comment-more-menu.active').forEach(m => m.classList.remove('active'));
        }
        if (!e.target.closest('.emoji-picker-container')) {
            document.querySelectorAll('.emoji-picker-popover.active').forEach(p => p.classList.remove('active'));
        }
    });

    // Close delegation gate
    document.body._commentDelegationActive = true;
}

function setupCommentSort() {
    const trigger = document.getElementById('commentSortTrigger');
    const menu = document.getElementById('commentSortMenu');
    if (!trigger || !menu) return;

    trigger.addEventListener('click', (e) => {
        e.stopPropagation();
        menu.classList.toggle('active');
    });

    const options = menu.querySelectorAll('.sort-option');
    options.forEach(opt => {
        opt.addEventListener('click', () => {
            const sortType = opt.dataset.sort;
            currentCommentSort = sortType;

            // Update UI
            options.forEach(o => o.classList.remove('active'));
            opt.classList.add('active');
            menu.classList.remove('active');

            // Re-render comments with the new sort
            if (window.allCommentsData) {
                displayComments(window.allCommentsData);
            }
        });
    });

    // Close menu when clicking outside
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.sort-dropdown-wrapper')) {
            menu.classList.remove('active');
        }
    });
}

// Track pending reaction requests to prevent duplicates
const pendingReactions = new Set();

async function toggleEmojiReaction(commentId, emoji) {
    if (emoji) emoji = emoji.trim();

    // Debounce: prevent multiple requests for same comment
    const key = `${commentId}`;
    if (pendingReactions.has(key)) {
        console.log('Reaction already in progress for comment:', commentId);
        return;
    }

    console.log('Sending reaction:', { commentId, emoji });
    if (!window.currentUserId) {
        alert('Please login to react.');
        return;
    }

    pendingReactions.add(key);

    try {
        const response = await fetch('../backend/toggleCommentReaction.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ comment_id: commentId, emoji: emoji })
        });
        const data = await response.json();
        if (data.success) {
            // Re-render comments to show updated counts
            await loadComments();
        } else {
            alert(data.message);
        }
    } catch (e) {
        console.error('Reaction error:', e);
    } finally {
        pendingReactions.delete(key);
    }
}

// Native Emoji Picker - Creates an input that triggers OS emoji keyboard
function openNativeEmojiPicker(commentId, buttonElement) {
    // Remove any existing picker
    const existing = document.getElementById('nativeEmojiInput');
    if (existing) existing.remove();

    // Create hidden input positioned near the button
    const rect = buttonElement.getBoundingClientRect();
    const input = document.createElement('input');
    input.type = 'text';
    input.id = 'nativeEmojiInput';
    input.style.cssText = `
        position: fixed;
        left: ${Math.max(10, rect.left)}px;
        top: ${rect.top}px;
        opacity: 0;
        width: 1px;
        height: 1px;
        border: none;
        pointer-events: none;
    `;
    input.setAttribute('inputmode', 'none'); // Prevent keyboard on some browsers

    // document.body.appendChild(input); 
    // Wait, we append below.

    // Position handled in cssText above.

    const container = document.fullscreenElement || document.body;
    container.appendChild(input);

    // Handle emoji input
    input.addEventListener('input', async (e) => {
        const emoji = e.target.value;
        if (emoji && emoji.trim()) {
            // Check if it's actually an emoji (basic check)
            const emojiRegex = /[\u{1F300}-\u{1F9FF}]|[\u{2600}-\u{26FF}]|[\u{2700}-\u{27BF}]|[\u{1F600}-\u{1F64F}]|[\u{1F680}-\u{1F6FF}]|[\u{1FA00}-\u{1FAFF}]/u;
            if (emojiRegex.test(emoji) || emoji.length <= 4) {
                await toggleEmojiReaction(commentId, emoji);
            }
        }
        input.remove();
    });

    // Focus and trigger emoji picker
    input.focus();

    // Also show a fallback picker for desktop browsers
    showFallbackEmojiPicker(commentId, buttonElement);
}

// Fallback emoji picker for desktop
function showFallbackEmojiPicker(commentId, buttonElement) {
    // Remove existing
    const existing = document.getElementById('fallbackEmojiPicker');
    if (existing) existing.remove();

    // Get button position for picker placement
    const rect = buttonElement.getBoundingClientRect();

    const picker = document.createElement('div');
    picker.id = 'fallbackEmojiPicker';
    picker.className = 'emoji-fallback-picker';

    // Popular emojis grid
    const emojis = [
        '❤️', '😂', '😮', '😢', '😡', '👍', '👎', '🔥', '💯', '✨',
        '🙌', '👏', '💎', '🎉', '😍', '🥳', '🤔', '😎', '🥺', '💀',
        '😭', '🤣', '😊', '🙏', '💪', '🎯', '⭐', '🌟', '💖', '💙',
        '💔', '🤡', '😔', '🤯', '🙏', '✌️', '😄', '😁', '😆', '😇',
        '😉', '😊', '😋', '😌', '😍', '😖', '😯', '💩', '🥳', '😑'
    ];

    picker.innerHTML = `
        <div class="picker-header">
            <span>Pick an emoji</span>
            <button class="picker-close"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="picker-grid">
            ${emojis.map(e => `<span class="picker-emoji" data-emoji="${e}">${e}</span>`).join('')}
        </div>

    `;

    // Position near the button
    // Smart Positioning (Up or Down)
    const viewportHeight = window.innerHeight;
    const pickerHeight = 250; // Estimated height of picker
    const spaceBelow = viewportHeight - rect.bottom;

    let topPos;
    if (spaceBelow < pickerHeight && rect.top > pickerHeight) {
        // Not enough space below, but enough above -> Show ABOVE
        topPos = rect.top - pickerHeight - 20;
    } else {
        // Default: Show BELOW
        topPos = rect.bottom + 8;
    }

    picker.style.cssText = `
        position: fixed;
        left: ${Math.max(10, rect.left - 250)}px;
        top: ${topPos}px;
        z-index: 100000;
    `;

    const targetContainer = document.fullscreenElement || document.body;
    targetContainer.appendChild(picker);

    // Handle emoji click
    picker.querySelectorAll('.picker-emoji').forEach(emojiEl => {
        emojiEl.addEventListener('click', async (e) => {
            e.preventDefault();
            e.stopPropagation(); // Prevent bubbling to body handler
            const emoji = emojiEl.dataset.emoji;
            picker.remove(); // Remove picker FIRST to prevent double clicks
            document.getElementById('nativeEmojiInput')?.remove();
            await toggleEmojiReaction(commentId, emoji);
        });
    });

    // Close button
    picker.querySelector('.picker-close').addEventListener('click', () => {
        picker.remove();
        document.getElementById('nativeEmojiInput')?.remove();
    });

    // Close on outside click
    setTimeout(() => {
        document.addEventListener('click', function closeHandler(e) {
            if (!picker.contains(e.target) && !buttonElement.contains(e.target)) {
                picker.remove();
                document.getElementById('nativeEmojiInput')?.remove();
                document.removeEventListener('click', closeHandler);
            }
        });
    }, 100);
}

function openReactionsModal(commentId) {

    // We could fetch detailed reactions here if we wanted to show WHO reacted
    // But for now, we'll just show the breakdown in a liquid modal
    const comment = findCommentById(window.allCommentsData, commentId);
    if (!comment || !comment.reactions) return;

    let modal = document.getElementById('reactionsModal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'reactionsModal';
        modal.className = 'reactions-modal-overlay';
        document.body.appendChild(modal);
    }

    modal.innerHTML = `
        < div class="reactions-modal-content" >
            <div class="modal-header">
                <h3>Reactions</h3>
                <button class="close-modal"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="reactions-list-full">
                ${comment.reactions.map(r => `
                    <div class="reaction-item-full">
                        <span class="emoji">${r.emoji}</span>
                        <span class="count">${r.count}</span>
                    </div>
                `).join('')}
            </div>
        </div >
        `;

    modal.classList.add('active');
    modal.querySelector('.close-modal').onclick = () => modal.classList.remove('active');
    modal.onclick = (e) => { if (e.target === modal) modal.classList.remove('active'); };
}

async function toggleCommentLike(commentId) {
    if (checkGuest('like comments')) return;
    if (!window.currentUserId) {
        alert('Please login to like comments.');
        return;
    }
    try {
        const res = await fetch('../backend/toggleCommentLike.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ comment_id: commentId })
        });
        const data = await res.json();
        if (data.success) {
            loadComments(); // Refresh to show new counts
        } else {
            alert(data.message);
        }
    } catch (e) {
        console.error('Comment like error:', e);
    }
}

async function toggleCommentDislike(commentId) {
    if (checkGuest('dislike comments')) return;
    if (!window.currentUserId) {
        alert('Please login to dislike comments.');
        return;
    }
    try {
        const res = await fetch('../backend/toggleCommentDislike.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ comment_id: commentId })
        });
        const data = await res.json();
        if (data.success) {
            loadComments();
        } else {
            alert(data.message);
        }
    } catch (e) {
        console.error('Comment dislike error:', e);
    }
}

function findCommentById(comments, id) {
    for (const c of comments) {
        if (c.id === id) return c;
        if (c.replies && c.replies.length > 0) {
            const found = findCommentById(c.replies, id);
            if (found) return found;
        }
    }
    return null;
}

// VIDEO INTERACTION FUNCTIONS
async function toggleLike() {
    if (checkGuest('like')) return;
    if (isLoading) return;
    try {
        isLoading = true;
        const response = await fetch('../backend/toggleLike.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ video_id: videoId })
        });
        const data = await response.json();
        if (data.success) {
            updateLikeButton(data.likes, data.is_liked);
            updateDislikeButton(data.dislikes, false);
        }
    } catch (e) { console.error(e); } finally { isLoading = false; }
}

async function toggleDislike() {
    if (checkGuest('dislike')) return;
    if (isLoading) return;
    try {
        isLoading = true;
        const response = await fetch('../backend/toggleDislike.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ video_id: videoId })
        });
        const data = await response.json();
        if (data.success) {
            updateDislikeButton(data.dislikes, data.is_disliked);
            updateLikeButton(data.likes, false);
        }
    } catch (e) { console.error(e); } finally { isLoading = false; }
}

async function toggleFavorite() {
    if (checkGuest('favorite')) return;
    if (isLoading) return;
    try {
        isLoading = true;
        const response = await fetch('../backend/toggleFavorite.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ video_id: videoId })
        });
        const data = await response.json();
        if (data.success) updateFavoriteButton(data.is_favorited);
    } catch (e) { console.error(e); } finally { isLoading = false; }
}

async function toggleSave() {
    if (checkGuest('save')) return;
    if (isLoading) return;
    isLoading = true;
    try {
        const response = await fetch('../backend/toggleSave.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ video_id: videoId })
        });
        const data = await response.json();
        if (data.success) updateSaveButton(data.is_saved);
    } catch (e) { console.error(e); } finally { isLoading = false; }
}

let lastSyncTime = 0;
function syncProgress(currentTime, duration) {
    const now = Date.now();
    if (now - lastSyncTime < 4000) return; // Throttle
    lastSyncTime = now;

    fetch('../backend/updateWatchProgress.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ video_id: videoId, progress: currentTime, duration: duration })
    });
}

function updateLikeButton(likes, isLiked) {
    const btns = [document.getElementById('videoLikeBtn'), document.getElementById('overlayLikeBtn')].filter(b => b);
    btns.forEach(btn => {
        const span = btn.querySelector('span');
        if (span) span.textContent = formatViews(likes);
        btn.classList.toggle('liked', isLiked);
    });
}

function updateDislikeButton(dislikes, isDisliked) {
    [document.getElementById('videoDislikeBtn'), document.getElementById('overlayDislikeBtn')].filter(b => b)
        .forEach(b => b.classList.toggle('disliked', isDisliked));
}

function updateFavoriteButton(isFavorited) {
    const btns = [document.getElementById('overlayHeartBtn')].filter(b => b);
    btns.forEach(btn => {
        btn.classList.toggle('favorited', isFavorited);
        const icon = btn.querySelector('i');
        if (icon) {
            icon.className = isFavorited ? 'fa-solid fa-heart' : 'fa-regular fa-heart';
        }
    });
}

function updateSaveButton(isSaved) {
    const btns = [document.getElementById('saveBtn'), document.getElementById('overlaySaveBtn')].filter(b => b);
    btns.forEach(btn => {
        btn.classList.toggle('saved', isSaved);
        const icon = btn.querySelector('i');
        const text = btn.querySelector('span');
        if (icon) {
            icon.className = isSaved ? 'fa-solid fa-bookmark' : 'fa-regular fa-bookmark';
        }
        if (text && btn.id === 'saveBtn') {
            text.textContent = isSaved ? 'Saved' : 'Save';
        }
    });
}

// SETUP EVENT LISTENERS
function setupEventListeners() {
    // Helper to prevent fullscreen exit on button clicks
    const safeClick = (handler) => (e) => {
        e.stopPropagation();
        handler(e);
    };

    const likeBtns = [document.getElementById('videoLikeBtn'), document.getElementById('overlayLikeBtn')].filter(b => b);
    likeBtns.forEach(b => b.addEventListener('click', safeClick(toggleLike)));

    const dislikeBtns = [document.getElementById('videoDislikeBtn'), document.getElementById('overlayDislikeBtn')].filter(b => b);
    dislikeBtns.forEach(b => b.addEventListener('click', safeClick(toggleDislike)));

    // Heart button (FAVORITE)
    const heartBtn = document.getElementById('overlayHeartBtn');
    if (heartBtn) {
        heartBtn.addEventListener('click', safeClick(toggleFavorite));
    }

    // Save buttons (BOOKMARK)
    const saveBtns = [document.getElementById('saveBtn'), document.getElementById('overlaySaveBtn')].filter(b => b);
    saveBtns.forEach(b => b.addEventListener('click', safeClick(toggleSave)));

    const shareBtns = [document.getElementById('shareBtn'), document.getElementById('overlayShareBtn')].filter(b => b);
    shareBtns.forEach(b => b.addEventListener('click', safeClick(() => {
        if (navigator.share) {
            navigator.share({ title: currentVideo?.title, url: window.location.href });
        } else {
            navigator.clipboard.writeText(window.location.href);
            Popup.show('Link copied!', 'success');
        }
    })));

    const downloadBtn = document.getElementById('downloadBtn');
    if (downloadBtn) {
        downloadBtn.addEventListener('click', () => {
            if (!currentVideo?.video_url) return;
            const a = document.createElement('a');
            a.href = currentVideo.video_url;
            a.download = currentVideo.title + '.mp4';
            a.click();
        });
    }

    // Comment form
    const commentForm = document.getElementById('commentForm');
    const commentInput = document.getElementById('commentInput');
    if (commentForm) {
        commentForm.addEventListener('submit', (e) => {
            e.preventDefault();
            if (commentInput.value.trim()) addComment(commentInput.value.trim());
        });
    }

    // Overlay panels
    const overlayPanel = document.getElementById('videoCommentsOverlay');
    const infoPanel = document.getElementById('videoInfoOverlay');
    const container = document.getElementById('videoPlayerContainer');

    // Overlay comment open
    const overlayCommentBtn = document.getElementById('overlayCommentBtn');
    if (overlayCommentBtn && overlayPanel) {
        overlayCommentBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            if (infoPanel) {
                infoPanel.style.display = 'none';
                if (container) container.classList.remove('info-sidebar-active');
            }
            overlayPanel.style.display = 'flex';
        });
    }

    // Unified Sidebar Close Handler (Delegated)
    document.addEventListener('click', (e) => {
        const closeBtn = e.target.closest('#closeOverlayComments, #closeOverlayInfo');
        if (closeBtn) {
            e.stopPropagation();
            if (overlayPanel) overlayPanel.style.display = 'none';
            if (infoPanel) {
                infoPanel.style.display = 'none';
                if (container) container.classList.remove('info-sidebar-active');
            }
        }
    });

    // Info sidebar open
    const infoBtn = document.querySelector('.info-btn');
    const overlayMoreBtn = document.getElementById('overlayMoreBtn');

    const openInfoSidebar = (e) => {
        e.stopPropagation();
        if (overlayPanel) overlayPanel.style.display = 'none';
        if (infoPanel) {
            infoPanel.style.display = 'flex';
            if (container) container.classList.add('info-sidebar-active');
            populateInfoSidebar();
        }
    };

    if (infoBtn) infoBtn.addEventListener('click', openInfoSidebar);
    if (overlayMoreBtn) overlayMoreBtn.addEventListener('click', openInfoSidebar);

    // Overlay Comment Send
    const overlaySend = document.getElementById('overlayCommentSend');
    const overlayInput = document.getElementById('overlayCommentInput');
    if (overlaySend && overlayInput) {
        overlaySend.addEventListener('click', () => {
            if (overlayInput.value.trim()) addComment(overlayInput.value.trim());
        });
        overlayInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && overlayInput.value.trim()) addComment(overlayInput.value.trim());
        });
        overlayInput.addEventListener('keydown', (e) => e.stopPropagation());
    }

    // Nav buttons - prevent fullscreen exit on click
    const nextBtn = document.getElementById('videoNextBtn');
    if (nextBtn) {
        nextBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            playNextVideo(true);
        });
    }

    const prevBtn = document.getElementById('videoPrevBtn');
    if (prevBtn) {
        prevBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            playPrevVideo();
        });
    }

    // Description More Toggle
    const descBtn = document.getElementById('descriptionMoreBtn');
    const descEl = document.getElementById('videoDescription');
    if (descBtn && descEl) {
        descBtn.addEventListener('click', () => {
            const isExpanded = descEl.classList.toggle('expanded');
            descBtn.textContent = isExpanded ? 'Show less' : 'Show more';
        });
    }

    // Fullscreen Class Toggle for actions navbar
    const videoContainer = document.getElementById('videoPlayerContainer');
    if (videoContainer) {
        const syncFS = () => {
            const isFS = !!(document.fullscreenElement || document.webkitFullscreenElement || document.mozFullScreenElement || document.msFullscreenElement);
            videoContainer.classList.toggle('is-fullscreen', isFS);

            const fsIcon = document.getElementById('fullscreenIcon');
            const exitIcon = document.getElementById('exitFullscreenIcon');
            if (fsIcon) fsIcon.style.display = isFS ? 'none' : 'block';
            if (exitIcon) exitIcon.style.display = isFS ? 'block' : 'none';
        };
        document.addEventListener('fullscreenchange', syncFS);
        document.addEventListener('webkitfullscreenchange', syncFS);
        document.addEventListener('mozfullscreenchange', syncFS);
        document.addEventListener('MSFullscreenChange', syncFS);
    }

    // Subscribe overlay
    const overlaySubBtn = document.getElementById('overlaySubscribeBtn');
    if (overlaySubBtn) {
        overlaySubBtn.addEventListener('click', safeClick(() => {
            const subBtn = document.getElementById('subscribeBtn');
            if (subBtn) subBtn.click();
        }));
    }

    // Watchlist Sidebar Close
    const closeWatchlist = document.getElementById('closeWatchlistPanel');
    if (closeWatchlist) {
        closeWatchlist.addEventListener('click', () => {
            const panel = document.getElementById('watchlistSidebarPanel');
            const upNext = document.getElementById('upNextSection');
            if (panel) panel.style.display = 'none';
            if (upNext) upNext.style.display = 'block';

            // Clear watchlist state
            watchlistType = '';
            window.currentWatchlist = null;

            // Re-load standard recommendations
            loadRecommendations();

            // Update URL to remove list param
            const url = new URL(window.location);
            url.searchParams.delete('list');
            window.history.replaceState({}, '', url);
        });
    }
}

// SHORTCUTS AND CONTROLS
function setupVideoShortcuts() {
    if (window.videoShortcutsAttached) return;
    window.videoShortcutsAttached = true;

    window.addEventListener('keydown', (e) => {
        const active = document.activeElement;
        const isTyping = active && (
            active.tagName === 'INPUT' ||
            active.tagName === 'TEXTAREA' ||
            active.isContentEditable ||
            active.tagName === 'SELECT'
        );
        if (isTyping) return;

        const video = document.getElementById('videoPlayer');
        if (!video) return;

        const key = e.key.toLowerCase();

        const code = e.code;

        // Space or K (Play/Pause)
        if (code === 'Space' || key === ' ' || key === 'k') {
            e.preventDefault();
            if (active && active.tagName === 'BUTTON') active.blur();
            togglePlay(video);
            return;
        }

        // F (Fullscreen)
        if (code === 'KeyF' || key === 'f') {
            e.preventDefault();
            const container = document.getElementById('videoPlayerContainer');
            toggleFullscreen(container || video.parentElement);
            return;
        }

        // M (Mute)
        if (code === 'KeyM' || key === 'm') {
            e.preventDefault();
            video.muted = !video.muted;
            if (window.updateVolumeUI) window.updateVolumeUI();
            return;
        }

        // ArrowRight (Forward 5s)
        if (code === 'ArrowRight' || key === 'arrowright') {
            e.preventDefault();
            video.currentTime = Math.min(video.duration || Infinity, video.currentTime + 5);
            return;
        }

        // ArrowLeft (Back 5s)
        if (code === 'ArrowLeft' || key === 'arrowleft') {
            e.preventDefault();
            video.currentTime = Math.max(0, video.currentTime - 5);
            return;
        }

        // C (Captions)
        if (code === 'KeyC' || key === 'c') {
            e.preventDefault();
            if (typeof toggleCaptions === 'function') toggleCaptions();
            return;
        }

        // Shift + N (Next)
        if (e.shiftKey && (code === 'KeyN' || key === 'n')) {
            e.preventDefault();
            if (typeof playNextVideo === 'function') playNextVideo(true);
            return;
        }
    }, true); // Use capture phase to ensure it runs first

    // Autoplay on end
    const video = document.getElementById('videoPlayer');
    if (video) {
        video.onended = () => {
            const autoplay = document.getElementById('autoplayToggle');
            if (!autoplay || autoplay.checked) playNextVideo();
        };
    }
}

function setupCustomVideoControls() {
    const video = document.getElementById('videoPlayer');
    const centerBtn = document.getElementById('videoCenterPlayBtn');
    const pauseBtn = document.getElementById('videoPauseBtn');
    const progressBar = document.getElementById('videoProgressBar');
    const progressFilled = document.getElementById('videoProgressFilled');
    const currentTimeEl = document.getElementById('videoCurrentTime');
    const durationEl = document.getElementById('videoDuration');

    if (!video || !centerBtn) return;

    const updatePlayState = () => {
        const isPaused = video.paused;
        const playIcon = document.getElementById('playIcon');
        const pauseIcon = document.getElementById('pauseIcon');
        const centerPlay = document.getElementById('centerPlayIcon');
        const centerPause = document.getElementById('centerPauseIcon');

        if (playIcon) playIcon.style.display = isPaused ? 'block' : 'none';
        if (pauseIcon) pauseIcon.style.display = isPaused ? 'none' : 'block';
        if (centerPlay) centerPlay.style.display = isPaused ? 'block' : 'none';
        if (centerPause) centerPause.style.display = isPaused ? 'none' : 'block';

        const overlay = document.getElementById('videoControlsOverlay');
        if (!isPaused) {
            // Hide center play btn after delay when playing
            setTimeout(() => { if (!video.paused) centerBtn.style.opacity = '0'; }, 300); // Faster fade
        } else {
            centerBtn.style.opacity = '1';
        }
    };

    // Global Click Handler for Video Area (YouTube style click-anywhere-to-play)
    const playerWrapper = document.querySelector('.video-player-wrapper');
    const container = document.getElementById('videoPlayerContainer');
    let clickTimer = null;

    if (playerWrapper) {
        playerWrapper.addEventListener('click', (e) => {
            // Ignore if clicking on controls/buttons
            if (e.target.closest('button') ||
                (e.target.closest('.video-controls-overlay') && e.target !== document.querySelector('.video-controls-overlay')) ||
                e.target.closest('.video-comments-overlay') ||
                e.target.closest('.video-info-overlay') ||
                e.target.closest('.video-settings-menu')) {
                return;
            }

            if (clickTimer) {
                // Double Click -> Fullscreen
                clearTimeout(clickTimer);
                clickTimer = null;
                toggleFullscreen(container);
            } else {
                // Single Click -> Play/Pause
                clickTimer = setTimeout(() => {
                    clickTimer = null;
                    if (video) togglePlay(video);
                }, 300); // 300ms is standard
            }
        });
    }

    centerBtn.addEventListener('click', (e) => { e.stopPropagation(); togglePlay(video); });
    pauseBtn.addEventListener('click', (e) => { e.stopPropagation(); togglePlay(video); });
    video.addEventListener('play', updatePlayState);
    video.addEventListener('pause', updatePlayState);

    video.addEventListener('timeupdate', () => {
        const p = (video.currentTime / video.duration) * 100;
        if (progressFilled) progressFilled.style.width = p + '%';
        if (currentTimeEl) currentTimeEl.textContent = formatTime(video.currentTime);

        // Track Progress every 5 seconds
        if (Math.floor(video.currentTime) % 5 === 0 && !video.paused) {
            syncProgress(video.currentTime, video.duration);
        }
    });

    video.addEventListener('loadedmetadata', () => {
        if (durationEl) durationEl.textContent = formatTime(video.duration);
    });

    if (progressBar) {
        let isDragging = false;

        const updateSeeking = (e) => {
            const rect = progressBar.getBoundingClientRect();
            let pos = (e.clientX - rect.left) / rect.width;
            pos = Math.max(0, Math.min(1, pos));
            video.currentTime = pos * video.duration;
            if (progressFilled) progressFilled.style.width = (pos * 100) + '%';
        };

        progressBar.addEventListener('mousedown', (e) => {
            isDragging = true;
            updateSeeking(e);
        });

        window.addEventListener('mousemove', (e) => {
            if (isDragging) updateSeeking(e);
        });

        window.addEventListener('mouseup', () => {
            isDragging = false;
        });

        progressBar.addEventListener('click', (e) => {
            if (!isDragging) updateSeeking(e);
        });

        // Chapter Hover Preview
        const chapterPreview = document.getElementById('chapterTitlePreview');
        progressBar.addEventListener('mousemove', (e) => {
            if (!currentVideo || !currentVideo.chapters || currentVideo.chapters.length === 0) return;

            const rect = progressBar.getBoundingClientRect();
            const pos = (e.clientX - rect.left) / rect.width;
            const hoverTime = pos * video.duration;

            const chapter = currentVideo.chapters.find(c => hoverTime >= c.start_time && hoverTime < c.end_time);

            if (chapter && chapterPreview) {
                chapterPreview.innerHTML = `< i class="fa-solid fa-bookmark" ></i > ${chapter.title} `;
                chapterPreview.style.left = (pos * 100) + '%';
                chapterPreview.classList.add('active');
            } else if (chapterPreview) {
                chapterPreview.classList.remove('active');
            }
        });

        progressBar.addEventListener('mouseleave', () => {
            if (chapterPreview) chapterPreview.classList.remove('active');
        });
    }

    // Fullscreen
    const fsBtn = document.getElementById('videoFullscreenBtn');
    if (fsBtn) fsBtn.addEventListener('click', () => toggleFullscreen(document.getElementById('videoPlayerContainer')));

    // CAPTION TOGGLE LOGIC
    function toggleCaptions() {
        const video = document.getElementById('videoPlayer');
        const captionsBtn = document.getElementById('videoCaptionsBtn');
        const captionsDisplay = document.getElementById('videoCaptionsDisplay');
        if (!video) return;

        captionsEnabled = !captionsEnabled;
        localStorage.setItem('captionsEnabled', captionsEnabled);

        if (captionsBtn) captionsBtn.classList.toggle('active', captionsEnabled);

        // Update track mode
        const trk = video.querySelector('track');
        if (trk && trk.track) {
            trk.track.mode = captionsEnabled ? 'hidden' : 'disabled';
        }

        // Immediately update overlay display
        if (!captionsEnabled) {
            updateCaptionsDisplay(null);
        } else if (window.forceCaptionsUpdate) {
            window.forceCaptionsUpdate();
        }
    }

    function setupCaptionsToggle() {
        const captionsBtn = document.getElementById('videoCaptionsBtn');
        if (captionsBtn) {
            captionsBtn.classList.toggle('active', captionsEnabled);
            captionsBtn.onclick = (e) => {
                e.stopPropagation();
                toggleCaptions();
            };
        }
    }

    // VOLUME CONTROL
    const muteBtn = document.getElementById('videoMuteBtn');
    const volumeSlider = document.getElementById('volumeSlider');
    const volFilled = document.getElementById('volumeSliderFilled');
    const volHigh = document.getElementById('volumeHigh');
    const volLow = document.getElementById('volumeLow');
    const volMuted = document.getElementById('volumeMuted');

    const updateVolumeUI = () => {
        if (!video) return;
        const vol = video.volume;
        const isMuted = video.muted || vol === 0;

        if (volFilled) volFilled.style.width = (isMuted ? 0 : vol * 100) + '%';

        if (volMuted) volMuted.style.display = isMuted ? 'block' : 'none';
        if (volLow) volLow.style.display = (!isMuted && vol < 0.5) ? 'block' : 'none';
        if (volHigh) volHigh.style.display = (!isMuted && vol >= 0.5) ? 'block' : 'none';
    };

    // Expose for shortcuts
    window.updateVolumeUI = updateVolumeUI;

    if (muteBtn) {
        muteBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            video.muted = !video.muted;
            updateVolumeUI();
        });
    }

    if (volumeSlider) {
        let isDraggingVolume = false;
        const updateVol = (e) => {
            const rect = volumeSlider.getBoundingClientRect();
            let pos = (e.clientX - rect.left) / rect.width;
            pos = Math.max(0, Math.min(1, pos));
            video.volume = pos;
            video.muted = (pos === 0);
            updateVolumeUI();
        };

        volumeSlider.addEventListener('mousedown', (e) => {
            isDraggingVolume = true;
            updateVol(e);
        });

        window.addEventListener('mousemove', (e) => {
            if (isDraggingVolume) updateVol(e);
        });

        window.addEventListener('mouseup', () => {
            isDraggingVolume = false;
        });
    }

    // NEXT BUTTON
    const nextBtnBottom = document.getElementById('videoNextBtnBottom');
    if (nextBtnBottom) {
        nextBtnBottom.addEventListener('click', (e) => {
            e.stopPropagation();
            playNextVideo(true);
        });
    }

    video.addEventListener('volumechange', updateVolumeUI);
    updateVolumeUI(); // Initial

    // INACTIVITY TRACKING (Hide UI/Cursor)
    let inactivityTimeout;
    let lastX, lastY;

    const hideUI = () => {
        if (!video.paused && !['INPUT', 'TEXTAREA'].includes(document.activeElement.tagName)) {
            container.classList.add('user-inactive');
        }
    };

    const resetInactivity = (e) => {
        // Ignore tiny/spurious mouse movements that shouldn't wake the UI
        if (e && e.type === 'mousemove') {
            if (Math.abs(e.clientX - (lastX || 0)) < 2 && Math.abs(e.clientY - (lastY || 0)) < 2) return;
            lastX = e.clientX;
            lastY = e.clientY;
        }

        container.classList.remove('user-inactive');
        clearTimeout(inactivityTimeout);
        if (!video.paused) {
            inactivityTimeout = setTimeout(hideUI, 3000);
        }
    };

    if (container) {
        container.addEventListener('mousemove', resetInactivity, true);
        container.addEventListener('mousedown', resetInactivity, true);
        container.addEventListener('touchstart', resetInactivity, true);
        window.addEventListener('keydown', resetInactivity, true);

        video.addEventListener('play', resetInactivity);
        video.addEventListener('pause', () => {
            container.classList.remove('user-inactive');
            clearTimeout(inactivityTimeout);
        });
        video.addEventListener('ended', () => {
            container.classList.remove('user-inactive');
            clearTimeout(inactivityTimeout);
        });

        resetInactivity();
    }
}

function togglePlay(video) {
    if (!video) return;
    if (video.paused) {
        const playPromise = video.play();
        if (playPromise !== undefined) {
            playPromise.catch(error => {
                console.error("Playback failed:", error);
            });
        }
    } else {
        video.pause();
    }
}

function toggleFullscreen(elem) {
    if (!elem) return;
    const isFS = !!(document.fullscreenElement || document.webkitFullscreenElement || document.mozFullScreenElement || document.msFullscreenElement);

    if (!isFS) {
        if (elem.requestFullscreen) elem.requestFullscreen();
        else if (elem.webkitRequestFullscreen) elem.webkitRequestFullscreen();
        else if (elem.mozRequestFullScreen) elem.mozRequestFullScreen();
        else if (elem.msRequestFullscreen) elem.msRequestFullscreen();
    } else {
        if (document.exitFullscreen) document.exitFullscreen();
        else if (document.webkitExitFullscreen) document.webkitExitFullscreen();
        else if (document.mozCancelFullScreen) document.mozCancelFullScreen();
        else if (document.msExitFullscreen) document.msExitFullscreen();
    }
}

// UTILS
function formatTime(s) {
    const m = Math.floor(s / 60);
    const sec = Math.floor(s % 60);
    return `${m}:${sec.toString().padStart(2, '0')} `;
}

function formatViews(n) {
    if (n >= 1000000) return (n / 1000000).toFixed(1) + 'M';
    if (n >= 1000) return (n / 1000).toFixed(1) + 'K';
    return n;
}

function formatTimeAgo(str) {
    const d = new Date(str);
    const now = new Date();
    const diff = Math.floor((now - d) / 1000);
    if (diff < 60) return 'Just now';
    if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
    return d.toLocaleDateString();
}

function escapeHtml(t) {
    const d = document.createElement('div');
    d.textContent = t;
    return d.innerHTML;
}

function timestampToSeconds(timestamp) {
    const parts = timestamp.split(':').reverse();
    let seconds = 0;
    if (parts[0]) seconds += parseInt(parts[0]);
    if (parts[1]) seconds += parseInt(parts[1]) * 60;
    if (parts[2]) seconds += parseInt(parts[2]) * 3600;
    return seconds;
}

function parseTimestamps(text) {
    const regex = /(?:([0-9]+):)?([0-5]?[0-9]):([0-5][0-9])/g;
    return text.replace(regex, (match) => {
        return `<span class="comment-timestamp" data-time="${match}">${match}</span>`;
    });
}

// UP NEXT & RECOMMENDATIONS (Cookied logic)
async function playNextVideo(manual = false) {
    if (window.currentWatchlist && window.currentWatchlist.length > 0) {
        const currentIndex = window.currentWatchlist.findIndex(v => v.id == videoId);
        if (currentIndex !== -1 && currentIndex < window.currentWatchlist.length - 1) {
            const nextVideo = window.currentWatchlist[currentIndex + 1];
            if (manual) {
                loadDynamicVideo(nextVideo.id);
            } else {
                showNextOverlay({
                    video_id: nextVideo.id,
                    title: nextVideo.title,
                    thumbnail_url: nextVideo.thumbnail_url
                });
            }
            return;
        }
    }

    try {
        const res = await fetch('../backend/getNextVideo.php?current_id=' + videoId);
        const data = await res.json();
        if (data.success) {
            if (manual) {
                loadDynamicVideo(data.video_id);
            } else {
                showNextOverlay(data);
            }
        }
    } catch (e) {
        console.error('Error fetching next video:', e);
    }
}

async function playPrevVideo() {
    if (window.currentWatchlist && window.currentWatchlist.length > 0) {
        const currentIndex = window.currentWatchlist.findIndex(v => v.id == videoId);
        if (currentIndex > 0) {
            const prevVideo = window.currentWatchlist[currentIndex - 1];
            loadDynamicVideo(prevVideo.id);
            return;
        }
    }

    try {
        const res = await fetch('../backend/getNextVideo.php?current_id=' + videoId + '&direction=prev');
        const data = await res.json();
        if (data.success) {
            // Go directly to previous video (no overlay for manual previous)
            loadDynamicVideo(data.video_id);
        }
    } catch (e) { console.error(e); }
}

function loadDynamicVideo(newVideoId) {
    if (autoPlayTimer) clearTimeout(autoPlayTimer);
    if (countdownInterval) clearInterval(countdownInterval);
    if (nextOverlay) nextOverlay.style.display = 'none';

    // Update global videoId
    videoId = newVideoId;
    isDynamicLoad = true;

    // Update URL without reload
    const newUrl = watchlistType ? `videoid.php ? id = ${newVideoId}& list=${watchlistType} ` : `videoid.php ? id = ${newVideoId} `;
    window.history.pushState({ videoId: newVideoId, list: watchlistType }, '', newUrl);

    // Update Title in Browser
    // Note: We'll update the actual title in loadVideo's success handler

    // Reload content
    loadVideo();

    // Scroll to top
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function showNextOverlay(data) {
    nextVideoData = data;
    if (!nextOverlay) {
        nextOverlay = document.createElement('div');
        nextOverlay.id = 'nextVideoOverlay';
        nextOverlay.className = 'next-video-overlay';
        document.getElementById('videoPlayerContainer').appendChild(nextOverlay);
    }

    // Super Minimal HTML structure
    nextOverlay.innerHTML = `
        <div class="next-overlay-minimal">
            <div class="next-minimal-top">
                <div class="next-minimal-thumb">
                    <img src="${data.thumbnail_url}" alt="${data.title}">
                    <div class="countdown-overlay">
                        <svg class="countdown-svg-mini" viewBox="0 0 40 40">
                            <circle class="countdown-bg" cx="20" cy="20" r="18"></circle>
                            <circle class="countdown-progress" id="countdownProgress" cx="20" cy="20" r="18"></circle>
                        </svg>
                        <span id="nextTimer">5</span>
                    </div>
                </div>
                <div class="next-minimal-info">
                    <span class="next-label">Next video in <span class="timer-bold" id="nextTimerLabel">5s</span></span>
                    <h3 class="next-title">${data.title}</h3>
                </div>
            </div>
            <div class="next-minimal-actions">
                <button id="nextCancel" class="minimal-btn secondary">Cancel</button>
                <button id="nextPlayNow" class="minimal-btn primary">Play</button>
            </div>
        </div>
    `;
    nextOverlay.style.display = 'flex';

    let left = 5;
    const timer = nextOverlay.querySelector('#nextTimer');
    const timerLabel = nextOverlay.querySelector('#nextTimerLabel');
    const progress = nextOverlay.querySelector('#countdownProgress');
    const totalDash = 113; // 2 * PI * 18

    // Initial state
    if (progress) {
        progress.style.strokeDasharray = totalDash;
        progress.style.strokeDashoffset = 0;
    }

    if (countdownInterval) clearInterval(countdownInterval);

    countdownInterval = setInterval(() => {
        left--;
        if (timer) timer.textContent = Math.max(0, left);
        if (timerLabel) timerLabel.textContent = Math.max(0, left) + 's';

        // Update progress circle
        if (progress) {
            const offset = ((5 - left) / 5) * totalDash;
            progress.style.strokeDashoffset = offset;
        }

        if (left <= 0) {
            clearInterval(countdownInterval);
            startNextVideo();
        }
    }, 1000);

    nextOverlay.querySelector('#nextCancel').onclick = (e) => {
        e.stopPropagation();
        clearInterval(countdownInterval);
        nextOverlay.style.display = 'none';

        // Pause video if it was somehow playing
        const video = document.getElementById('videoPlayer');
        if (video) video.pause();
    };

    nextOverlay.querySelector('#nextPlayNow').onclick = (e) => {
        e.stopPropagation();
        startNextVideo();
    };
}

function startNextVideo() {
    if (countdownInterval) clearInterval(countdownInterval);
    if (nextVideoData) {
        loadDynamicVideo(nextVideoData.video_id);
    }
}
// VIDEO SETTINGS (Graphics, Motion, Sleep)
function setupVideoSettings() {
    const settingsBtn = document.getElementById('videoSettingsBtn');
    const settingsMenu = document.getElementById('videoSettingsMenu');
    const closeBtn = document.getElementById('closeSettingsBtn');
    const video = document.getElementById('videoPlayer');

    if (!settingsBtn || !settingsMenu || !video) return;

    // Toggle Menu
    settingsBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        settingsMenu.classList.toggle('active');
    });

    closeBtn.addEventListener('click', () => {
        settingsMenu.classList.remove('active');
    });

    // Close on click outside
    document.addEventListener('click', (e) => {
        if (!settingsMenu.contains(e.target) && e.target !== settingsBtn) {
            settingsMenu.classList.remove('active');
        }
    });

    // Resolution
    const resBtns = settingsMenu.querySelectorAll('[data-res]');
    resBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const res = btn.dataset.res;
            resBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            // Mock resolution change
            Popup.show(`Quality changed to ${res === 'auto' ? 'Auto' : res + 'p'} `, 'success');

            // In a real scenario, you would swap the <video> source here
            // const currentTime = video.currentTime;
            // source.src = videoUrl_res;
            // video.load();
            // video.currentTime = currentTime;
            // video.play();
        });
    });

    // Motion (Playback Speed)
    const speedBtns = settingsMenu.querySelectorAll('[data-speed]');
    speedBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const speed = parseFloat(btn.dataset.speed);
            speedBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            video.playbackRate = speed;
        });
    });

    // Sleep Timer
    const sleepBtns = settingsMenu.querySelectorAll('[data-sleep]');
    const sleepStatus = document.getElementById('activeSleepTimer');
    const sleepDisplay = document.getElementById('sleepCountdown');

    const updateSleepUI = () => {
        if (!sleepEndTime) {
            if (sleepStatus) sleepStatus.style.display = 'none';
            if (sleepTimerInterval) clearInterval(sleepTimerInterval);
            return;
        }

        if (sleepStatus) sleepStatus.style.display = 'block';

        if (sleepTimerInterval) clearInterval(sleepTimerInterval);
        sleepTimerInterval = setInterval(() => {
            const now = Date.now();
            const remaining = sleepEndTime - now;

            if (remaining <= 0) {
                clearInterval(sleepTimerInterval);
                video.pause();
                sleepEndTime = null;
                localStorage.removeItem('floxSleepEnd');
                updateSleepUI();

                // Show In-Player Overlay
                const overlay = document.getElementById('sleepTimerOverlay');
                if (overlay) {
                    overlay.style.display = 'flex';

                    // Handle Buttons
                    const stayBtn = document.getElementById('sleepStayBtn');
                    const homeBtn = document.getElementById('sleepHomeBtn');

                    if (stayBtn) stayBtn.onclick = () => overlay.style.display = 'none';
                    if (homeBtn) homeBtn.onclick = () => window.location.href = 'home.php';
                }
            } else {
                const mins = Math.floor(remaining / 60000);
                const secs = Math.floor((remaining % 60000) / 1000);
                if (sleepDisplay) {
                    sleepDisplay.textContent = `${mins}:${secs.toString().padStart(2, '0')} `;
                }
            }
        }, 1000);
    };

    const manualSleepBtn = document.getElementById('setManualSleep');
    const manualSleepInput = document.getElementById('manualSleepMinutes');

    sleepBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const minutes = btn.dataset.sleep;
            if (minutes === 'off') {
                sleepBtns.forEach(b => b.classList.remove('active'));
                if (manualSleepBtn) manualSleepBtn.classList.remove('active');
                btn.classList.add('active');
                sleepEndTime = null;
                localStorage.removeItem('floxSleepEnd');
                Popup.show('Sleep timer turned off', 'info');
                updateSleepUI();
            }
        });
    });

    if (manualSleepBtn && manualSleepInput) {
        manualSleepBtn.addEventListener('click', () => {
            const minutes = parseInt(manualSleepInput.value);
            if (isNaN(minutes) || minutes <= 0) {
                Popup.show('Please enter a valid number of minutes', 'error');
                return;
            }

            sleepBtns.forEach(b => b.classList.remove('active'));
            manualSleepBtn.classList.add('active');

            const durationMs = minutes * 60 * 1000;
            sleepEndTime = Date.now() + durationMs;
            localStorage.setItem('floxSleepEnd', sleepEndTime);
            Popup.show(`Sleep timer set for ${minutes} minutes`, 'success');
            updateSleepUI();
        });

        // Prevent key events from bubbling up to video player
        manualSleepInput.addEventListener('keydown', (e) => e.stopPropagation());
    }

    // Initial sync
    if (sleepEndTime) {
        // Find correct active button
        const now = Date.now();
        if (sleepEndTime < now) {
            sleepEndTime = null;
            localStorage.removeItem('floxSleepEnd');
        } else {
            updateSleepUI();
        }
    }
}

async function loadWatchlistSidebar() {
    const panel = document.getElementById('watchlistSidebarPanel');
    const itemsContainer = document.getElementById('watchlistSidebarItems');
    const titleEl = document.getElementById('watchlistSidebarTitle');
    const countEl = document.getElementById('watchlistSidebarCount');
    const upNextSection = document.getElementById('upNextSection');

    if (!panel || !itemsContainer) return;

    try {
        const res = await fetch(`../ backend / getWatchlists.php ? t = ${Date.now()} `);
        const data = await res.json();

        if (data.success && data.lists[watchlistType]) {
            const list = data.lists[watchlistType];

            // Show panel, hide standard recs
            panel.style.display = 'flex';
            if (upNextSection) upNextSection.style.display = 'none';

            // Set Title
            const titles = {
                liked: 'Liked Videos',
                favorites: 'Favorites',
                saved: 'Saved Videos',
                watched: 'Watched History',
                partial: 'Continue Watching'
            };
            titleEl.textContent = titles[watchlistType] || 'Watchlist';
            countEl.textContent = `${list.length} videos`;

            // Render Items
            itemsContainer.innerHTML = list.map((v, index) => `
        < a href = "videoid.php?id=${v.id}&list=${watchlistType}" class="watchlist-item ${v.id == videoId ? 'active' : ''}" id = "watchlist-item-${v.id}" >
                    <div class="watchlist-item-thumb">
                        <img src="${v.thumbnail_url}" alt="${v.title}">
                    </div>
                    <div class="watchlist-item-info">
                        <div class="watchlist-item-title">${escapeHtml(v.title)}</div>
                        <div class="watchlist-item-author">${escapeHtml(v.author.username)}</div>
                    </div>
                </a >
        `).join('');

            // Scroll active into view
            setTimeout(() => {
                const active = document.getElementById(`watchlist - item - ${videoId} `);
                if (active) active.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }, 500);

            // Update Global Navigation
            window.currentWatchlist = list;
        }
    } catch (e) {
        console.error('Error loading watchlist sidebar:', e);
    }
}

// RECOMMENDATIONS SIDEBAR FETCHING
async function loadRecommendations() {
    // If watchlist is active, don't overlap unless closed
    if (watchlistType && watchlistType !== '' && document.getElementById('watchlistSidebarPanel').style.display !== 'none') {
        return;
    }

    const recList = document.getElementById('recommendationsList');
    if (!recList) return;

    try {
        const res = await fetch('../backend/getRecommendedVideos.php?limit=30&exclude=' + videoId);
        const data = await res.json();

        if (data.success && data.videos) {
            const allVideos = data.videos.filter(v => v.id !== videoId);

            // Partition into Long Videos and Clips
            const longVideos = allVideos.filter(v => v.is_clip === 0);
            const clips = allVideos.filter(v => v.is_clip === 1);

            let html = '';

            // 1. Show first 3 long videos
            const firstThree = longVideos.slice(0, 3);
            html += firstThree.map(v => renderLongVideoItem(v)).join('');

            // 2. Add divider
            if (firstThree.length > 0) {
                html += '<hr class="rec-divider">';
            }

            // 3. Add clips section (9:16 horizontal overflow)
            if (clips.length > 0) {
                html += `
        < div class="clips-sidebar-container" >
                        <div class="clips-sidebar-header">
                            <i class="fa-solid fa-clapperboard"></i>
                            <span>Clips</span>
                        </div>
                        <div class="clips-horizontal-scroll">
                            ${clips.map(v => `
                                <a href="videoid.php?id=${v.id}" class="clip-sidebar-item" onclick="handleRecommendationClick(event, ${v.id})">
                                    <div class="clip-sidebar-thumb">
                                        <img src="${v.thumbnail_url}" alt="${v.title}">
                                    </div>
                                    <div class="clip-sidebar-title">${escapeHtml(v.title)}</div>
                                    <div class="clip-sidebar-views">${formatViews(v.views)} views</div>
                                </a>
                            `).join('')}
                        </div>
                    </div >
        <hr class="rec-divider">
            `;
            }

            // 4. Show remaining long videos
            const remaining = longVideos.slice(3);
            html += remaining.map(v => renderLongVideoItem(v)).join('');

            recList.innerHTML = html || '<p style="opacity:0.5; font-size:12px;">No recommendations found</p>';
        }
    } catch (e) {
        console.error('Error loading recommendations:', e);
        recList.innerHTML = '<p style="opacity:0.5; font-size:12px;">No recommendations found</p>';
    }
}

function handleRecommendationClick(e, id) {
    if (window.innerWidth > 768) {
        e.preventDefault();
        loadDynamicVideo(id);
        loadRecommendations();
    }
}

function renderLongVideoItem(v) {
    return `
            <a href="videoid.php?id=${v.id}" class="rec-item" onclick="handleRecommendationClick(event, ${v.id})">
                <div class="rec-thumbnail">
                    <img src="${v.thumbnail_url}" alt="${v.title}">
                </div>
                <div class="rec-info">
                    <h4 class="rec-title">${escapeHtml(v.title)}</h4>
                    <div class="rec-meta">
                        <span>${escapeHtml(v.author.username)}</span>
                        <span>${formatViews(v.views)} views • ${formatTimeAgo(v.created_at)}</span>
                    </div>
                </div>
            </a>
            `;
}

// INTEREST TRACKING
function trackInterests(v) {
    if (window.FloxInterests) {
        window.FloxInterests.track(v);
    }
}

// SUBSCRIBE HELPER
function setupSubscribeButton(cid, subbed, count) {
    const btn = document.getElementById('subscribeBtn');
    if (!btn) return;
    btn.style.display = 'flex';
    const text = btn.querySelector('.subscribe-text');

    const overlayBtn = document.getElementById('overlaySubscribeBtn');

    const updateUI = (s, c) => {
        btn.classList.toggle('subscribed', s);
        if (text) text.textContent = s ? 'Subscribed' : 'Subscribe';
        const icon = btn.querySelector('i');
        if (icon) {
            icon.className = s ? 'fa-solid fa-bell-slash' : 'fa-solid fa-bell';
        }
        const authorSubs = document.getElementById('authorSubs');
        if (authorSubs) authorSubs.textContent = `${formatViews(c)} subscribers`;

        // Update overlay button
        if (overlayBtn) {
            overlayBtn.style.display = s ? 'none' : 'block';
        }
    };

    updateUI(subbed, count);

    btn.onclick = async () => {
        const res = await fetch('../backend/subscribe.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ channel_id: cid })
        });
        const data = await res.json();
        if (data.success) updateUI(data.is_subscribed, data.subscriber_count);
    };
}

// COMMENT HELPER UTILS
function findCommentById(arr, id) {
    for (const c of arr) {
        if (c.id === id) return c;
        if (c.replies) {
            const found = findCommentById(c.replies, id);
            if (found) return found;
        }
    }
    return null;
}

function addReplyToComments(arr, pid, reply) {
    const parent = findCommentById(arr, pid);
    if (parent) {
        parent.replies = parent.replies || [];
        parent.replies.unshift(reply);
        return true;
    }
    return false;
}

// Record view
async function recordView(vid) {
    fetch('../backend/recordView.php', { method: 'POST', body: 'video_id=' + vid, headers: { 'Content-Type': 'application/x-www-form-urlencoded' } });
}

// Comments management async
async function addReply(pid, txt, btn) {
    if (!txt.trim()) return;

    // OPTIMISTIC REPLY
    const tempId = 'temp_rep_' + Date.now();
    const optimisticReply = {
        id: tempId,
        parent_id: pid,
        comment: txt,
        created_at: new Date().toISOString(),
        likes: 0,
        is_liked: false,
        is_disliked: false,
        is_pinned: false,
        _pending: true,
        author: currentUserData || {
            id: window.currentUserId,
            username: 'You',
            profile_picture: null,
            is_pro: false
        },
        replies: []
    };

    // Attach to parent locally
    const attached = addReplyToComments(comments, pid, optimisticReply);
    if (attached) {
        // Expand the replies section if it wasn't already
        expandedReplyIds.add(pid);
        displayComments(comments);

        const repDiv = document.getElementById(`replies-${pid}`);
        if (repDiv) repDiv.style.display = 'flex';
    }

    try {
        const res = await fetch('../backend/addReply.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ video_id: videoId, parent_id: pid, comment: txt })
        });
        const data = await res.json();
        if (data.success) {
            // Refresh logic: replace the optimistic reply with data from server if needed,
            // but for simplicity, we can loadComments() which is already optimized
            // to handle pending entries if they exist.
            // Actually, just calling loadComments() is fine as it will fetch the real IDs.
            setTimeout(loadComments, 500);
            Popup.show('Reply posted', 'success');
        } else {
            // Rollback optimistic reply
            const parent = findCommentById(comments, pid);
            if (parent && parent.replies) {
                parent.replies = parent.replies.filter(r => r.id !== tempId);
                displayComments(comments);
            }
            Popup.show(data.message || 'Error posting reply', 'error');
        }
    } catch (e) {
        console.error('Error adding reply:', e);
    }
}

async function addComment(txt) {
    if (!txt.trim()) return;

    // OPTIMISTIC UPDATE
    const tempId = 'temp_' + Date.now();
    const optimisticComment = {
        id: tempId,
        comment: txt,
        created_at: new Date().toISOString(),
        likes: 0,
        is_liked: false,
        is_disliked: false,
        is_pinned: false,
        _pending: true,
        _isNew: true,
        author: currentUserData || {
            id: window.currentUserId,
            username: 'You',
            profile_picture: null,
            is_pro: false
        },
        replies: []
    };

    // Prepend to top as requested
    window.newlyAddedCommentIds.add(tempId);
    comments.unshift(optimisticComment);
    displayComments(comments);

    // Clear inputs immediately
    const inp = document.getElementById('commentInput');
    if (inp) inp.value = '';
    const oin = document.getElementById('overlayCommentInput');
    if (oin) oin.value = '';

    try {
        const res = await fetch('../backend/addComment.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ video_id: videoId, comment: txt })
        });
        const data = await res.json();

        if (data.success) {
            // Replace optimistic comment with real one from server
            const index = comments.findIndex(c => c.id === tempId);
            if (index !== -1) {
                // Register the REAL ID so it sticks (Paranoid Mode)
                if (data.comment_id) {
                    window.newlyAddedCommentIds.add(data.comment_id); // As returned
                    window.newlyAddedCommentIds.add(String(data.comment_id));
                    window.newlyAddedCommentIds.add(parseInt(data.comment_id));
                }
                if (data.comment && data.comment.id) {
                    window.newlyAddedCommentIds.add(data.comment.id);
                    window.newlyAddedCommentIds.add(String(data.comment.id));
                    window.newlyAddedCommentIds.add(parseInt(data.comment.id));
                }

                // Keep it at the top for this session
                const serverComment = data.comment;
                if (serverComment) {
                    comments[index] = serverComment;
                } else {
                    comments[index].id = data.comment_id;
                    comments[index]._pending = false;
                }

                displayComments(comments);
            }
        } else {
            // Rollback on error
            comments = comments.filter(c => c.id !== tempId);
            displayComments(comments);
            Popup.show(data.message || 'Error posting comment', 'error');
        }
    } catch (e) {
        console.error('Error adding comment:', e);
        comments = comments.filter(c => c.id !== tempId);
        displayComments(comments);
    }
}
async function toggleCommentLike(cid) {
    const res = await fetch('../backend/toggleCommentLike.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ comment_id: cid }) });
    const data = await res.json();
    if (data.success) {
        // Simple way: just reload all comments to reflect counts
        loadComments();
    }
}
async function toggleCommentDislike(cid) {
    const res = await fetch('../backend/toggleCommentDislike.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ comment_id: cid }) });
    const data = await res.json();
    if (data.success) {
        loadComments();
    }
}
async function pinComment(cid, pinned) {
    const res = await fetch('../backend/pinComment.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ comment_id: cid }) });
    const data = await res.json();
    if (data.success) loadComments();
}
async function deleteComment(cid) {
    if (await Popup.confirm('Delete comment?')) {
        const res = await fetch('../backend/deleteComment.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ comment_id: cid }) });
        const data = await res.json();
        if (data.success) loadComments();
    }
}
async function editComment(cid, txt, btn) {
    const res = await fetch('../backend/editComment.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ comment_id: cid, comment: txt }) });
    const data = await res.json();
    if (data.success) loadComments();
}

/**
 * AMBIENT GLOW COMPONENT - Real-Time Video Sampling
/**
 * AMBIENT GLOW COMPONENT - GPU Accelerated Accumulation
 * Uses hardware compositing to create a smooth, temporal gradient field
 * without CPU-intensive pixel manipulation. 60FPS guaranteed.
 */
class AmbientGlow {
    constructor(videoSelector, canvasSelector) {
        this.video = document.querySelector(videoSelector);
        this.canvas = document.querySelector(canvasSelector);

        if (!this.video || !this.canvas) return;

        // Optimized Context - Enable alpha for correct blending
        this.ctx = this.canvas.getContext('2d', { alpha: true });

        // Resolution: Low resolution relies on CSS scaling for free smoothing
        this.fieldWidth = 64; // Increased slightly for better color fidelity
        this.fieldHeight = 36;

        this.canvas.width = this.fieldWidth;
        this.canvas.height = this.fieldHeight;

        // Configuration
        this.isActive = false;
        this.interval = null;
        this.frameCount = 0;

        this.init();
    }

    init() {
        const updateState = () => {
            // Ensure readyState is sufficient
            if (!this.video.paused && !this.video.ended && this.video.readyState >= 2) {
                this.start();
            } else {
                this.stop();
            }
        };

        this.video.addEventListener('play', updateState);
        this.video.addEventListener('playing', updateState);
        this.video.addEventListener('pause', updateState);
        this.video.addEventListener('ended', updateState);
        this.video.addEventListener('canplay', updateState);

        if (this.video.readyState >= 2 && !this.video.paused) this.start();
    }

    start() {
        if (this.isActive) return;
        this.isActive = true;

        const container = document.getElementById('videoPlayerContainer');
        if (container) container.classList.add('is-playing');

        const loop = () => {
            if (!this.isActive) return;

            // Update every frame - the alpha blending creates smooth transitions
            this.update();
            this.interval = requestAnimationFrame(loop);
        };
        this.interval = requestAnimationFrame(loop);
    }

    stop() {
        this.isActive = false;
        if (this.interval) cancelAnimationFrame(this.interval);
    }

    update() {
        if (!this.video || this.video.readyState < 2) return;

        // TEMPORAL BLENDING for ultra-smooth color transitions
        // Instead of replacing the previous frame, we blend it with the new frame
        // using low alpha, creating a slow fade effect
        this.ctx.globalAlpha = 0.03; // Very slow blend (3% new color per frame)
        this.ctx.drawImage(this.video, 0, 0, this.fieldWidth, this.fieldHeight);
        this.ctx.globalAlpha = 1.0; // Reset for next operations
    }
}

// Global ambient instance
let ambientInstance = null;
function initAmbientGlow() {
    if (!ambientInstance) {
        ambientInstance = new AmbientGlow('#videoPlayer', '#ambientCanvas');
    } else {
        if (!ambientInstance.video.paused) ambientInstance.start();
    }
}

// LIQUID DROPDOWN ENGINE (iOS 26 Style)
function setupLiquidDropdown() {
    const moreBtn = document.getElementById('moreOptionsBtn');
    const moreMenu = document.getElementById('moreOptionsMenu');

    if (!moreBtn || !moreMenu) return;

    const toggleMenu = (e) => {
        if (e) e.stopPropagation();
        const isActive = moreMenu.classList.contains('active');

        if (!isActive) {
            // Emergence phase
            moreMenu.classList.add('active');
            moreMenu.classList.remove('was-active');

            // Optional: Subtle haptic feedback feel via scale on button
            moreBtn.style.transform = 'scale(0.85)';
            setTimeout(() => moreBtn.style.transform = '', 200);
        } else {
            closeLiquidMenu();
        }
    };

    function closeLiquidMenu() {
        if (moreMenu.classList.contains('active')) {
            moreMenu.classList.remove('active');
            moreMenu.classList.add('was-active');

            // Wait for retract animation
            setTimeout(() => {
                moreMenu.classList.remove('was-active');
            }, 500);
        }
    }

    moreBtn.addEventListener('click', toggleMenu);

    // Global listener for closing
    document.addEventListener('click', (e) => {
        if (!moreMenu.contains(e.target) && !moreBtn.contains(e.target)) {
            closeLiquidMenu();
        }
    });

    // Escape listener
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeLiquidMenu();
    });

    // Fluid distortion hover effect for items
    const items = moreMenu.querySelectorAll('.dropdown-item');
    items.forEach(item => {
        item.addEventListener('mouseenter', () => {
            // Ripple effect logic could go here if needed
        });
    });

    // Handle internal button clicks
    const reportBtn = moreMenu.querySelector('.dropdown-item:first-child'); // Report is first
    if (reportBtn) {
        reportBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            closeLiquidMenu();
            openReportModal();
        });
    }
}

// REPORT FEATURE (Liquid Glass Modal)
function setupReportFeature() {
    const overlay = document.getElementById('reportModalOverlay');
    const form = document.getElementById('reportVideoForm');
    const closeBtn = document.getElementById('closeReportModal');
    const cancelBtn = document.getElementById('cancelReport');
    const selectContainer = document.getElementById('reportReasonSelect');
    const selectTrigger = selectContainer ? selectContainer.querySelector('.liquid-select-trigger') : null;
    const selectedText = document.getElementById('selectedReasonText');
    const hiddenInput = document.getElementById('reportReason');

    if (!overlay || !form) return;

    // Custom Select Toggle
    if (selectTrigger) {
        selectTrigger.addEventListener('click', (e) => {
            e.stopPropagation();
            selectContainer.classList.toggle('active');
        });

        const options = selectContainer.querySelectorAll('.liquid-select-option');
        options.forEach(option => {
            option.addEventListener('click', () => {
                const val = option.getAttribute('data-value');
                hiddenInput.value = val;
                selectedText.textContent = val;

                options.forEach(opt => opt.classList.remove('selected'));
                option.classList.add('selected');

                selectContainer.classList.remove('active');
            });
        });

        // Close select on outside click
        document.addEventListener('click', () => {
            if (selectContainer) selectContainer.classList.remove('active');
        });
    }

    window.openReportModal = () => {
        overlay.classList.add('active');
        document.body.style.overflow = 'hidden'; // Lock scroll
    };

    const closeReportModal = () => {
        overlay.classList.remove('active');
        document.body.style.overflow = '';
        form.reset();
    };

    closeBtn.addEventListener('click', closeReportModal);
    cancelBtn.addEventListener('click', closeReportModal);

    // Close on backdrop click
    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) closeReportModal();
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const submitBtn = form.querySelector('.report-submit-btn');
        const originalText = submitBtn.textContent;

        submitBtn.disabled = true;
        submitBtn.textContent = 'Submitting...';

        const payload = {
            videoId: videoId,
            reason: document.getElementById('reportReason').value,
            description: document.getElementById('reportDescription').value
        };

        try {
            const res = await fetch('../backend/report_video.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            const data = await res.json();

            if (data.success) {
                submitBtn.textContent = 'Report Sent!';
                submitBtn.style.background = '#28a745';
                setTimeout(() => {
                    closeReportModal();
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                    submitBtn.style.background = '';
                }, 1500);
            } else {
                alert(data.message || 'Error sending report.');
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        } catch (err) {
            console.error('Report error:', err);
            alert('Failed to connect to the server.');
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    });
}

// COMMENT REPORTING
let currentReportCommentId = null;

function openCommentReportModal(commentId) {
    currentReportCommentId = commentId;
    const overlay = document.getElementById('commentReportOverlay');
    if (overlay) {
        overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeCommentReportModal() {
    const overlay = document.getElementById('commentReportOverlay');
    if (overlay) {
        overlay.classList.remove('active');
        document.body.style.overflow = '';
    }
    const details = document.getElementById('commentReportDetails');
    if (details) details.value = '';
    currentReportCommentId = null;
}

// Attach comment report listeners when DOM is ready
function setupCommentReportListeners() {
    const cancelBtn = document.getElementById('cancelCommentReport');
    const submitBtn = document.getElementById('submitCommentReportBtn');
    const overlay = document.getElementById('commentReportOverlay');

    if (cancelBtn) {
        cancelBtn.addEventListener('click', closeCommentReportModal);
    }

    // Click on backdrop to close
    if (overlay) {
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) closeCommentReportModal();
        });
    }

    if (submitBtn) {
        submitBtn.addEventListener('click', async () => {

            if (!currentReportCommentId) return;

            const btn = document.getElementById('submitCommentReportBtn');
            const originalText = btn.textContent;
            btn.disabled = true;
            btn.textContent = 'Sending...';

            const payload = {
                comment_id: currentReportCommentId,
                reason: document.getElementById('commentReportReason').value,
                details: document.getElementById('commentReportDetails').value
            };

            try {
                const res = await fetch('../backend/reportComment.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (data.success) {
                    btn.textContent = 'Sent!';
                    btn.style.background = '#28a745';
                    setTimeout(() => {
                        closeCommentReportModal();
                        btn.disabled = false;
                        btn.textContent = originalText;
                        btn.style.background = '';
                    }, 1500);
                } else {
                    alert(data.message || 'Error sending report.');
                    btn.disabled = false;
                    btn.textContent = originalText;
                }
            } catch (err) {
                console.error('Report error:', err);
                alert('Failed to connect to server.');
                btn.disabled = false;
                btn.textContent = originalText;
            }
        });
    }
}

// Helper to load new video dynamically
function loadNewVideo(newId) {
    if (newId === videoId) return;

    // Close any overlays
    const overlays = ['videoCommentsOverlay', 'videoInfoOverlay'];
    overlays.forEach(id => {
        const el = document.getElementById(id);
        if (el) el.style.display = 'none';
    });
    const container = document.getElementById('videoPlayerContainer');
    if (container) container.classList.remove('info-sidebar-active');

    // Update ID and push state
    videoId = newId;
    window.history.pushState({ videoId: newId }, '', `videoid.php?id=${newId}`);

    // Scroll to top
    window.scrollTo({ top: 0, behavior: 'smooth' });

    // Reload video
    loadVideo();
}

// Call setup when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', setupCommentReportListeners);
} else {
    setupCommentReportListeners();
}

async function populateInfoSidebar() {
    if (!currentVideo) return;

    const desc = document.getElementById('overlayVideoDescription');
    const date = document.getElementById('overlayVideoDate');
    const views = document.getElementById('overlayVideoViews');
    const hashtags = document.getElementById('overlayHashtags');
    const creatorVideos = document.getElementById('overlayCreatorVideos');

    if (desc) desc.textContent = currentVideo.description || 'No description';
    if (date) date.textContent = formatTimeAgo(currentVideo.created_at);
    if (views) views.textContent = formatViews(currentVideo.views) + ' views';

    if (hashtags) {
        hashtags.innerHTML = '';
        if (currentVideo.hashtags && currentVideo.hashtags.length > 0) {
            currentVideo.hashtags.forEach(tag => {
                const span = document.createElement('span');
                span.className = 'overlay-tag';
                span.textContent = '#' + tag;
                hashtags.appendChild(span);
            });
        }
    }

    if (creatorVideos) {
        creatorVideos.innerHTML = '<div class="mini-loader"></div>';
        try {
            const resp = await fetch(`../backend/getVideosByUser.php?user_id=${currentVideo.author.id}&limit=5&exclude=${videoId}`);
            const data = await resp.json();
            if (data.success && data.videos) {
                creatorVideos.innerHTML = data.videos.map(v => `
                    <div class="mini-video-item" onclick="loadNewVideo(${v.id})" style="cursor: pointer;">
                        <img src="${v.thumbnail_url}" alt="${v.title}">
                        <div class="mini-video-info">
                            <h5>${escapeHtml(v.title)}</h5>
                            <span>${formatViews(v.views)} views</span>
                        </div>
                    </div>
                `).join('') || '<p>No other videos from this creator.</p>';
            } else {
                creatorVideos.innerHTML = '<p>No other videos found.</p>';
            }
        } catch (e) {
            console.error('Error fetching creator videos:', e);
            creatorVideos.innerHTML = '<p>Error loading videos.</p>';
        }
    }
}
