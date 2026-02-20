document.addEventListener('DOMContentLoaded', () => {
    initClips();
});

let clips = [];
let currentClipIndex = 0;
let isMuted = true;
let observer;

async function initClips() {
    const feed = document.getElementById('clipsFeed');

    // Load clips
    try {
        const response = await fetch('../backend/getClips.php');
        const data = await response.json();

        if (data.success && data.clips.length > 0) {
            clips = data.clips;
            renderClips(clips);
            setupObserver();
            setupKeyboardNavigation();
        } else {
            feed.innerHTML = '<div class="no-clips">No clips found. Be the first to create one!</div>';
        }
    } catch (error) {
        console.error('Error loading clips:', error);
        feed.innerHTML = '<div class="error">Failed to load clips.</div>';
    }
}

function renderClips(clipsData) {
    const feed = document.getElementById('clipsFeed');
    const template = document.getElementById('clipTemplate');

    feed.innerHTML = ''; // Clear loading

    clipsData.forEach((clip, index) => {
        const clone = template.content.cloneNode(true);
        const item = clone.querySelector('.clip-item');
        const video = clone.querySelector('video');

        // Set IDs and Data
        item.dataset.id = clip.id;
        item.dataset.index = index;
        item.id = `clip-${index}`;

        // Video Source - Now handled robustly by backend
        const videoPath = clip.video_url;
        const thumbnailPath = clip.thumbnail_url;

        video.src = videoPath;
        video.poster = thumbnailPath;
        video.muted = isMuted;

        // Info - Profile Picture
        const authorAvatar = clone.querySelector('.author-avatar-small');
        const authorAvatarRight = clone.querySelector('.author-avatar-right');

        const setAvatar = (imgEl) => {
            if (clip.author && clip.author.profile_picture) {
                imgEl.src = clip.author.profile_picture;
                imgEl.onerror = function () {
                    this.src = '../assets/default-avatar.png';
                };
            } else {
                imgEl.src = '../assets/default-avatar.png';
            }
        };

        if (authorAvatar) setAvatar(authorAvatar);
        if (authorAvatarRight) setAvatar(authorAvatarRight);

        // Badge Logic for Clip Author
        let badgeHtml = '';
        if (clip.is_pro && clip.comment_badge) {
            if (clip.comment_badge === 'pro') {
                badgeHtml = `<span class="comment-author-badge pro-svg" style="margin-left: 5px; width: 20px; height: 18px; display: inline-flex;" title="Pro Member"><svg width="100%" height="100%" viewBox="0 0 340 96" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M87.5524 2.5625H121.74C128.865 2.5625 134.886 3.72917 139.802 6.0625C144.761 8.39583 148.532 11.6667 151.115 15.875C153.698 20.0833 154.99 25 154.99 30.625C154.99 38.2917 153.282 44.9375 149.865 50.5625C146.49 56.1458 141.657 60.4792 135.365 63.5625C129.073 66.6042 121.615 68.125 112.99 68.125H100.177L94.9274 92.75H68.4899L87.5524 2.5625ZM109.802 22.5625L104.24 48.5H113.177C116.261 48.5 118.948 47.8333 121.24 46.5C123.532 45.125 125.302 43.2708 126.552 40.9375C127.802 38.5625 128.427 35.9167 128.427 33C128.427 29.4167 127.427 26.7917 125.427 25.125C123.427 23.4167 120.532 22.5625 116.74 22.5625H109.802ZM153.24 92.75L172.427 2.5625H212.99C219.323 2.5625 224.698 3.75 229.115 6.125C233.573 8.45833 236.969 11.6458 239.302 15.6875C241.636 19.7292 242.802 24.3333 242.802 29.5C242.802 33.75 241.99 37.875 240.365 41.875C238.74 45.8333 236.344 49.3958 233.177 52.5625C230.052 55.7292 226.198 58.2083 221.615 60L231.615 92.75H203.99L195.49 62.5H196.115H186.177L179.74 92.75H153.24ZM194.802 22.125L189.99 44.375H201.802C204.761 44.375 207.323 43.8333 209.49 42.75C211.657 41.6667 213.323 40.1667 214.49 38.25C215.657 36.3333 216.24 34.1458 216.24 31.6875C216.24 28.4792 215.261 26.0833 213.302 24.5C211.386 22.9167 208.594 22.125 204.927 22.125H194.802ZM296.427 22.125C293.386 22.125 290.511 22.9583 287.802 24.625C285.136 26.2917 282.761 28.6042 280.677 31.5625C278.594 34.5208 276.948 37.9375 275.74 41.8125C274.573 45.6875 273.99 49.8542 273.99 54.3125C273.99 58.1042 274.615 61.4167 275.865 64.25C277.115 67.0833 278.865 69.2917 281.115 70.875C283.365 72.4167 286.011 73.1875 289.052 73.1875C292.094 73.1875 294.969 72.3542 297.677 70.6875C300.386 69.0208 302.782 66.7083 304.865 63.75C306.948 60.7917 308.573 57.375 309.74 53.5C310.948 49.5833 311.552 45.3958 311.552 40.9375C311.552 37.1042 310.927 33.7917 309.677 31C308.427 28.1667 306.657 25.9792 304.365 24.4375C302.115 22.8958 299.469 22.125 296.427 22.125ZM288.24 94.3125C279.657 94.3125 272.282 92.6458 266.115 89.3125C259.99 85.9375 255.282 81.3542 251.99 75.5625C248.74 69.7708 247.115 63.2292 247.115 55.9375C247.115 47.6875 248.407 40.1875 250.99 33.4375C253.573 26.6875 257.136 20.8958 261.677 16.0625C266.261 11.2292 271.573 7.52083 277.615 4.9375C283.657 2.3125 290.136 1 297.052 1C305.886 1 313.365 2.6875 319.49 6.0625C325.657 9.4375 330.344 14.0208 333.552 19.8125C336.802 25.5625 338.427 32.0833 338.427 39.375C338.427 47.6667 337.115 55.1875 334.49 61.9375C331.907 68.6875 328.302 74.4792 323.677 79.3125C319.094 84.1458 313.761 87.8542 307.677 90.4375C301.636 93.0208 295.157 94.3125 288.24 94.3125Z" fill="white"/></svg></span>`;
            }
            else if (clip.comment_badge === 'crown') {
                badgeHtml = `<span class="comment-author-badge crown" style="margin-left: 5px;" title="Crown"><i class="fa-solid fa-crown" style="color: #ffd700;"></i></span>`;
            } else if (clip.comment_badge === 'bolt') {
                badgeHtml = `<span class="comment-author-badge bolt" style="margin-left: 5px;" title="Electricity"><i class="fa-solid fa-bolt" style="color: #ffeb3b;"></i></span>`;
            } else if (clip.comment_badge === 'verified') {
                badgeHtml = `<span class="comment-author-badge verified" style="margin-left: 5px;" title="Verified"><i class="fa-solid fa-check-double" style="color: #3ea6ff;"></i></span>`;
            }
        }

        const authorName = clone.querySelector('.author-handle');
        authorName.innerHTML = '@' + clip.username + badgeHtml;

        clone.querySelector('.clip-caption').textContent = clip.title;

        const descriptionEl = clone.querySelector('.clip-description-text');
        if (descriptionEl) {
            descriptionEl.textContent = clip.description || '';
        }

        // Counts
        clone.querySelector('.like-btn .count').textContent = formatCount(clip.likes || 0);
        const commentBtnCount = clone.querySelector('.comment-btn .count');
        const countText = formatCount(clip.comments_count || 0);
        commentBtnCount.textContent = countText;

        // Panel Header Count
        const panelCount = clone.querySelector('.panel-header-count');
        if (panelCount) panelCount.textContent = countText;

        // Current User Avatar in Panel
        const userAvatarContainer = clone.querySelector('.current-user-avatar-container');
        if (userAvatarContainer) {
            fetch('../backend/getUser.php')
                .then(r => r.json())
                .then(u => {
                    if (u.success) {
                        userAvatarContainer.innerHTML = getAvatarHtml(u.user, 'current-user-avatar-tiny');
                    }
                });
        }

        // User State
        if (clip.user_liked) clone.querySelector('.like-btn').classList.add('active');
        if (clip.is_subscribed) {
            const subBtn = clone.querySelector('.subscribe-btn');
            subBtn.textContent = 'Subscribed';
            subBtn.style.background = 'rgba(255,255,255,0.1)';
            subBtn.style.color = 'white';
            subBtn.style.boxShadow = 'none';
        }

        // Event Listeners
        setupClipInteractions(item, clip, video);

        feed.appendChild(item);
    });
}

function setupClipInteractions(element, clip, video) {
    // Ambient Glow Engine
    const ambientCanvas = element.querySelector('.ambient-canvas');
    if (ambientCanvas) {
        new AmbientGlow(video, ambientCanvas);
    }

    // Play/Pause on click
    const videoWrapper = element.querySelector('.clip-video-container');
    const playOverlay = element.querySelector('.play-pause-overlay');

    videoWrapper.addEventListener('click', (e) => {
        if (e.target.closest('.action-btn') || e.target.closest('.sound-toggle')) return;
        togglePlay(video, playOverlay);
    });

    // Double click to like
    videoWrapper.addEventListener('dblclick', (e) => {
        if (e.target.closest('.action-btn')) return;
        handleReaction(clip.id, 'like', element.querySelector('.like-btn'), e);
        showHeartBurst(e.clientX, e.clientY);
    });

    // Smart Aspect Ratio / Size Detection
    video.addEventListener('loadedmetadata', () => {
        const ratio = video.videoWidth / video.videoHeight;
        // If the video is horizontal or square-ish, we expand the stage width
        if (ratio > 0.8) {
            videoWrapper.classList.add('wide-clip');
            if (ratio > 1.25) {
                videoWrapper.classList.add('horizontal-clip');
            }
        }
    });

    // Sound Toggle
    const soundBtn = element.querySelector('.volume-btn');
    if (soundBtn) {
        soundBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            isMuted = !isMuted;
            document.querySelectorAll('video').forEach(v => v.muted = isMuted);
            document.querySelectorAll('.volume-btn i').forEach(icon => updateSoundIcon(icon));
        });
    }

    // Top Controls Logic... (Existing)

    // Progress Bar
    const progressBar = element.querySelector('.clip-progress-bar');
    if (progressBar) {
        video.addEventListener('timeupdate', () => {
            if (video.duration) progressBar.style.width = (video.currentTime / video.duration) * 100 + '%';
        });
    }

    // Reaction (Video Like)
    const likeBtn = element.querySelector('.like-btn');
    if (likeBtn) likeBtn.addEventListener('click', () => handleReaction(clip.id, 'like', likeBtn, null));

    // Subscribe
    const subBtn = element.querySelector('.subscribe-btn');
    if (subBtn) subBtn.addEventListener('click', () => handleSubscribe(clip.user_id, subBtn));

    // COMMENTS PANEL LOGIC (LOCAL SCOPE)
    let localReplyingToId = null;
    const commentBtn = element.querySelector('.comment-btn');
    const panel = element.querySelector('.comments-panel');
    const panelClose = element.querySelector('.panel-close-btn');
    const postBtn = element.querySelector('.panel-post-btn');
    const commentInput = element.querySelector('.panel-comment-input');

    const resetLocalReply = () => {
        localReplyingToId = null;
        commentInput.placeholder = 'Add a comment...';
        commentInput.value = '';
    };

    if (commentBtn) {
        commentBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            const isOpen = panel.classList.toggle('open');
            element.classList.toggle('panel-open', isOpen);

            // Global state for arrows
            const container = document.querySelector('.clips-container');
            if (container) container.classList.toggle('panel-active', isOpen);

            if (isOpen) {
                loadCommentsForPanel(clip.id, element, (commentId, username) => {
                    localReplyingToId = commentId;
                    commentInput.focus();
                    commentInput.placeholder = `Replying to @${username}...`;
                });
            }
        });
    }

    if (panelClose) {
        panelClose.addEventListener('click', () => {
            panel.classList.remove('open');
            element.classList.remove('panel-open');
            const container = document.querySelector('.clips-container');
            if (container) container.classList.remove('panel-active');
        });
    }

    if (commentInput) {
        const updatePostBtn = () => {
            postBtn.disabled = !commentInput.value.trim();
        };
        commentInput.addEventListener('input', updatePostBtn);
        updatePostBtn(); // Initial state

        commentInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && !postBtn.disabled) {
                postCommentFromPanel(clip.id, element, localReplyingToId, () => {
                    resetLocalReply();
                    updatePostBtn();
                });
            }
        });
    }

    if (postBtn) {
        postBtn.addEventListener('click', () => {
            postCommentFromPanel(clip.id, element, localReplyingToId, () => {
                resetLocalReply();
                updatePostBtn();
            });
        });
    }

    // Share
    const shareBtn = element.querySelector('.share-btn');
    if (shareBtn) {
        shareBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            navigator.clipboard.writeText(window.location.origin + '/FloxWatch/frontend/videoid.php?id=' + clip.id);
            showToast('Link copied!');
        });
    }
}

async function loadCommentsForPanel(videoId, element, onReply) {
    const list = element.querySelector('.panel-comments-list');
    list.innerHTML = '<div class="loading"><i class="fa-solid fa-circle-notch fa-spin"></i></div>';

    try {
        const response = await fetch(`../backend/getComments.php?video_id=${videoId}&t=${Date.now()}`);
        const data = await response.json();

        if (data.success && data.comments) {
            renderCommentsInPanel(data.comments, list, onReply);
            const count = element.querySelector('.panel-header-count');
            if (count) count.textContent = formatCount(data.comments.length);
        }
    } catch (e) {
        list.innerHTML = '<div class="error">Failed to load</div>';
    }
}

// Premium Avatar Logic (Robust Fallback)
function getAvatarHtml(user, className = 'comment-avatar-tiny') {
    if (!user) return `<div class="${className} letter-avatar" style="background: #444">?</div>`;

    const username = user.username || 'User';
    const letter = username.charAt(0).toUpperCase();
    const colors = ['#FF8A65', '#9575CD', '#4DB6AC', '#64B5F6', '#F06292', '#81C784', '#D4E157', '#FFD54F', '#A1887F', '#90A4AE'];
    const colorIndex = Math.abs(username.split('').reduce((a, b) => { a = ((a << 5) - a) + b.charCodeAt(0); return a & a; }, 0) % colors.length);
    const color = colors[colorIndex];

    const letterHtml = `<div class="${className} letter-avatar" style="background: ${color}; display: flex; align-items: center; justify-content: center;">${letter}</div>`;

    if (user.profile_picture) {
        // Robust Path handling: don't double up ../ if backend already added it
        let pfp = user.profile_picture;
        if (!pfp.startsWith('http') && !pfp.startsWith('..')) {
            pfp = '../' + pfp.replace(/^\.\//, '');
        }

        return `
            <div class="avatar-wrapper">
                <img src="${pfp}" class="${className}" onload="this.style.opacity=1" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                <div class="${className} letter-avatar" style="background: ${color}; display: none; align-items: center; justify-content: center;">${letter}</div>
            </div>
        `;
    }
    return letterHtml;
}

function renderCommentsInPanel(comments, listContainer, onReply) {
    if (comments.length === 0) {
        listContainer.innerHTML = '<div class="no-comments">No comments yet</div>';
        return;
    }

    listContainer.innerHTML = '';

    comments.forEach(c => {
        const node = document.createElement('div');
        node.className = 'comment-node-wrapper';

        const count = c.replies ? c.replies.length : 0;
        const replyWord = count === 1 ? 'reply' : 'replies';

        node.innerHTML = `
            <div class="comment-node" data-id="${c.id}">
                ${getAvatarHtml(c.author)}
                <div class="comment-main">
                    <div class="comment-node-header">
                        <span class="node-handle">@${escapeHtml(c.author.username)}</span>
                        <span class="node-dot">•</span>
                        <span class="node-time">${timeAgo(c.created_at)}</span>
                    </div>
                    <div class="node-text">${escapeHtml(c.comment)}</div>
                    <div class="node-actions">
                        <button class="node-action-btn like-node-btn ${c.is_liked ? 'liked' : ''}">
                            <i class="fa-solid fa-heart"></i>
                            <span>${c.likes || 0}</span>
                        </button>
                        <button class="node-action-btn toggle-node-dislike ${c.is_disliked ? 'disliked' : ''}">
                            <i class="fa-solid fa-thumbs-down"></i>
                        </button>
                        <button class="node-action-btn emoji-react-node-btn" data-id="${c.id}">
                            <i class="fa-regular fa-face-smile"></i>
                        </button>
                        <button class="node-action-btn reply-node-btn">Reply</button>
                    </div>
                    <div class="comment-reactions-bar">
                        ${renderEmojiReactions(c.reactions || [], c.user_reaction, c.id)}
                    </div>
                    ${count > 0 ? `
                    <div class="view-replies" data-target="replies-${c.id}">
                        <i class="fa-solid fa-chevron-down"></i>
                        ${count} ${replyWord}
                    </div>
                    <div class="replies-container" id="replies-${c.id}">
                        <div class="replies-inner">
                            ${c.replies.map(r => `
                                <div class="comment-node reply-node">
                                    ${getAvatarHtml(r.author)}
                                    <div class="comment-main">
                                        <div class="comment-node-header">
                                            <span class="node-handle">@${escapeHtml(r.author.username)}</span>
                                            ${r.parent_author ? `<i class="fa-solid fa-caret-right node-caret"></i> <span class="node-handle secondary">@${escapeHtml(r.parent_author)}</span>` : ''}
                                            <span class="node-dot">•</span>
                                            <span class="node-time">${timeAgo(r.created_at)}</span>
                                        </div>
                                        <div class="node-text">${escapeHtml(r.comment)}</div>
                                        <div class="node-actions">
                                            <button class="node-action-btn like-node-btn ${r.is_liked ? 'liked' : ''}" onclick="likeComment(${r.id}, this)">
                                                <i class="fa-solid fa-heart"></i>
                                                <span>${r.likes || 0}</span>
                                            </button>
                                            <button class="node-action-btn toggle-node-dislike ${r.is_disliked ? 'disliked' : ''}" data-id="${r.id}">
                                                <i class="fa-solid fa-thumbs-down"></i>
                                            </button>
                                            <button class="node-action-btn emoji-react-node-btn" data-id="${r.id}">
                                                <i class="fa-regular fa-face-smile"></i>
                                            </button>
                                            <button class="node-action-btn reply-node-btn-nested" data-id="${r.id}" data-user="${escapeHtml(r.author.username)}">Reply</button>
                                        </div>
                                        <div class="comment-reactions-bar">
                                            ${renderEmojiReactions(r.reactions || [], r.user_reaction, r.id)}
                                        </div>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    </div>` : ''}
                </div>
            </div>
        `;

        // Add Listeners
        const likeBtn = node.querySelector('.like-node-btn');
        if (likeBtn) likeBtn.addEventListener('click', () => likeComment(c.id, likeBtn));

        const replyBtn = node.querySelector('.reply-node-btn');
        if (replyBtn) replyBtn.addEventListener('click', () => onReply(c.id, c.author.username));

        // Reaction Listeners (Emoji Bar)
        node.querySelectorAll('.comment-reactions-bar').forEach(bar => {
            bar.addEventListener('click', (e) => {
                const pill = e.target.closest('.emoji-reaction-pill');
                if (pill) {
                    const id = parseInt(pill.dataset.id);
                    const emoji = pill.dataset.emoji;
                    toggleEmojiReaction(id, emoji, () => {
                        loadCommentsForPanel(clips[currentClipIndex].id, listContainer.closest('.comments-panel'), onReply);
                    });
                }
            });
        });

        // Emoji React Button (Picker)
        node.querySelectorAll('.emoji-react-node-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const id = parseInt(btn.dataset.id);
                openNativeEmojiPicker(id, btn, () => {
                    loadCommentsForPanel(clips[currentClipIndex].id, listContainer.closest('.comments-panel'), onReply);
                });
            });
        });

        // Dislike Toggle
        node.querySelectorAll('.toggle-node-dislike').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = parseInt(btn.dataset.id || c.id);
                toggleCommentDislike(id, btn);
            });
        });

        // Listener for nested replies
        node.querySelectorAll('.reply-node-btn-nested').forEach(btn => {
            btn.addEventListener('click', () => {
                onReply(c.id, btn.dataset.user); // Reply to the parent thread but mention the specific user
            });
        });

        const viewRepliesBtn = node.querySelector('.view-replies');
        if (viewRepliesBtn) {
            viewRepliesBtn.addEventListener('click', () => {
                const targetId = viewRepliesBtn.dataset.target;
                const container = node.querySelector(`#${targetId}`);
                const isShowing = container.classList.toggle('show');
                const count = c.replies ? c.replies.length : 0;
                const replyWord = count === 1 ? 'reply' : 'replies';
                viewRepliesBtn.innerHTML = isShowing ?
                    `<i class="fa-solid fa-chevron-up"></i> Hide ${replyWord}` :
                    `<i class="fa-solid fa-chevron-down"></i> ${count} ${replyWord}`;
            });
        }

        listContainer.appendChild(node);
    });
}

async function postCommentFromPanel(videoId, element, parentId, onSuccess) {
    const input = element.querySelector('.panel-comment-input');
    const text = input.value.trim();
    if (!text) return;

    const endpoint = parentId ? '../backend/addReply.php' : '../backend/addComment.php';
    const body = { video_id: videoId, comment: text };
    if (parentId) body.parent_id = parentId;

    try {
        const response = await fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        });
        const data = await response.json();
        if (data.success) {
            onSuccess();
            loadCommentsForPanel(videoId, element, (cid, uname) => {
                // Re-bind the reply logic if re-rendered
                // In a real app we'd use event delegation
            });
            showToast(parentId ? 'Reply posted!' : 'Comment posted!');
        }
    } catch (e) {
        showToast('Failed to post', 'error');
    }
}

function togglePlay(video, overlay) {
    if (video.paused) {
        video.play();
        overlay.innerHTML = '<i class="fa-solid fa-play"></i>';
        overlay.classList.add('show');
        setTimeout(() => overlay.classList.remove('show'), 500);
    } else {
        video.pause();
        overlay.innerHTML = '<i class="fa-solid fa-pause"></i>';
        overlay.classList.add('show');
    }
}

function updateSoundIcon(icon) {
    if (isMuted) {
        icon.className = 'fa-solid fa-volume-xmark';
    } else {
        icon.className = 'fa-solid fa-volume-high';
    }
}

function setupObserver() {
    const options = {
        root: document.getElementById('clipsFeed'),
        threshold: 0.6
    };

    observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            const video = entry.target.querySelector('video');
            if (entry.isIntersecting) {
                video.play().catch(e => console.log('Autoplay prevented:', e));
                currentClipIndex = parseInt(entry.target.dataset.index);
                // Track interests for clips
                if (clips[currentClipIndex]) {
                    trackInterests(clips[currentClipIndex]);
                }
            } else {
                video.pause();
                video.currentTime = 0;
            }
        });
    }, options);

    document.querySelectorAll('.clip-item').forEach(item => {
        observer.observe(item);
    });
}

// ============================================
// INTEREST TRACKING & RECOMMENDATIONS (COOKIES)
// ============================================

function trackInterests(video) {
    if (window.FloxInterests) {
        window.FloxInterests.track(video);
    }
}


function setupKeyboardNavigation() {
    document.addEventListener('keydown', (e) => {
        // Ignore if typing in an input or textarea
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;

        if (e.key === 'ArrowDown' || e.key === 'PageDown') {
            e.preventDefault();
            scrollToClip(currentClipIndex + 1);
        } else if (e.key === 'ArrowUp' || e.key === 'PageUp') {
            e.preventDefault();
            scrollToClip(currentClipIndex - 1);
        } else if (e.key === ' ') {
            e.preventDefault();
            const currentVideo = document.querySelector(`#clip-${currentClipIndex} video`);
            if (currentVideo) {
                const overlay = document.querySelector(`#clip-${currentClipIndex} .play-pause-overlay`);
                togglePlay(currentVideo, overlay);
            }
        } else if (e.key === 'm' || e.key === 'M') {
            const soundToggle = document.querySelector('.sound-toggle');
            if (soundToggle) soundToggle.click();
        }
    });

    // Mouse wheel snap helper for PC
    const feed = document.getElementById('clipsFeed');
    let isWhereling = false;
    if (feed) {
        feed.addEventListener('wheel', (e) => {
            if (Math.abs(e.deltaY) < 50 || isWhereling) return;

            isWhereling = true;
            const direction = e.deltaY > 0 ? 1 : -1;
            scrollToClip(currentClipIndex + direction);

            setTimeout(() => {
                isWhereling = false;
            }, 1000); // 1s debounce to prevent multiple scrolls
        }, { passive: true });
    }
}

function scrollToClip(index) {
    if (index < 0 || index >= clips.length) return;

    const target = document.getElementById(`clip-${index}`);
    if (target) {
        target.scrollIntoView({ behavior: 'smooth' });
    }
}

async function handleReaction(videoId, type, btn, otherBtn) {
    try {
        const response = await fetch('../backend/video_interaction.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ video_id: videoId, type: type })
        });

        const data = await response.json();
        if (data.success) {
            if (data.action === 'added') {
                btn.classList.add('active');
                if (otherBtn) otherBtn.classList.remove('active');
            } else {
                btn.classList.remove('active');
            }

            const countSpan = btn.querySelector('.count');
            if (countSpan && !isNaN(parseInt(countSpan.textContent))) {
                let count = parseInt(countSpan.textContent);
                count = data.action === 'added' ? count + 1 : Math.max(0, count - 1);
                countSpan.textContent = formatCount(count);
            }
        } else if (data.message && data.message.includes('login')) {
            window.location.href = 'loginb.php';
        }
    } catch (error) {
        console.error('Reaction error:', error);
    }
}

async function handleSubscribe(channelId, btn) {
    try {
        const response = await fetch('../backend/subscribe.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ channel_id: channelId })
        });

        const data = await response.json();
        if (data.success) {
            if (data.status === 'subscribed') {
                btn.textContent = 'Subscribed';
                btn.style.background = 'rgba(255,255,255,0.1)';
                btn.style.color = 'white';
                btn.style.boxShadow = 'none';
            } else {
                btn.textContent = 'Subscribe';
                btn.style.background = 'var(--accent-color)';
                btn.style.color = 'white';
                btn.style.boxShadow = '0 4px 12px var(--accent-glow)';
            }
        } else if (data.message && data.message.includes('login')) {
            window.location.href = 'loginb.php';
        }
    } catch (error) {
        console.error('Subscribe error:', error);
    }
}
// ============================================
// COMMENT DRAWER FUNCTIONALITY
// ============================================

let currentVideoId = null;
let replyingToId = null;

function openCommentDrawer(videoId) {
    currentVideoId = videoId;
    replyingToId = null; // Reset reply state
    resetCommentInput();

    const drawer = document.getElementById('commentDrawer');
    const overlay = document.getElementById('drawerOverlay');

    drawer.classList.add('open');
    loadComments(videoId);

    // Pause current video
    const currentVideo = document.querySelector(`#clip-${currentClipIndex} video`);
    if (currentVideo && !currentVideo.paused) {
        currentVideo.pause();
    }
}

function closeCommentDrawer() {
    const drawer = document.getElementById('commentDrawer');
    drawer.classList.remove('open');
    currentVideoId = null;
    replyingToId = null;
    resetCommentInput();
}

function resetCommentInput() {
    const input = document.getElementById('commentInput');
    const wrapper = document.querySelector('.comment-input-wrapper');

    if (input) {
        input.value = '';
        input.placeholder = 'Add a comment...';
    }
    replyingToId = null;

    // Remove any existing cancel button
    const existingCancel = document.getElementById('cancelReplyBtn');
    if (existingCancel) existingCancel.remove();
}

async function loadComments(videoId) {
    const commentsList = document.getElementById('commentsList');
    const commentCount = document.getElementById('commentCount');

    try {
        // Add timestamp to prevent caching
        const response = await fetch(`../backend/getComments.php?video_id=${videoId}&t=${Date.now()}`);

        // Check if response is OK before parsing
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const text = await response.text();
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.error('Invalid JSON response from server:', text);
            throw new Error('Invalid server response: ' + text.substring(0, 100));
        }

        if (data.success && data.comments) {
            commentCount.textContent = data.comments.length; // Note: this might need total count including replies

            if (data.comments.length === 0) {
                commentsList.innerHTML = '<div class="no-clips">No comments yet. Be the first!</div>';
            } else {
                renderComments(data.comments);
            }
        }
    } catch (error) {
        console.error('Error loading comments:', error);
        // Don't overwrite if we are just refreshing and it failed silently
        if (commentsList.innerHTML.includes('loading')) {
            commentsList.innerHTML = '<div class="error">Failed to load comments</div>';
        }
    }
}


function renderComments(comments) {
    const commentsList = document.getElementById('commentsList');

    // Helper to render a single comment
    const renderCommentNode = (comment, isReply = false) => {
        let avatarUrl = comment.author.profile_picture;
        if (!avatarUrl) {
            avatarUrl = '../assets/default-avatar.png';
        }

        const indentClass = isReply ? 'reply-item' : '';
        const replyStyle = isReply ? 'margin-left: 40px; border-left: 2px solid var(--border-color);' : '';
        const author = comment.author;

        // Badge Logic
        let badgeHtml = '';
        if (author.is_pro && author.comment_badge) {
            if (author.comment_badge === 'pro') {
                badgeHtml = `<span class="comment-author-badge pro-svg" style="width: 22px; height: 16px; display: inline-flex;" title="Pro Member"><svg width="100%" height="100%" viewBox="0 0 340 96" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M87.5524 2.5625H121.74C128.865 2.5625 134.886 3.72917 139.802 6.0625C144.761 8.39583 148.532 11.6667 151.115 15.875C153.698 20.0833 154.99 25 154.99 30.625C154.99 38.2917 153.282 44.9375 149.865 50.5625C146.49 56.1458 141.657 60.4792 135.365 63.5625C129.073 66.6042 121.615 68.125 112.99 68.125H100.177L94.9274 92.75H68.4899L87.5524 2.5625ZM109.802 22.5625L104.24 48.5H113.177C116.261 48.5 118.948 47.8333 121.24 46.5C123.532 45.125 125.302 43.2708 126.552 40.9375C127.802 38.5625 128.427 35.9167 128.427 33C128.427 29.4167 127.427 26.7917 125.427 25.125C123.427 23.4167 120.532 22.5625 116.74 22.5625H109.802ZM153.24 92.75L172.427 2.5625H212.99C219.323 2.5625 224.698 3.75 229.115 6.125C233.573 8.45833 236.969 11.6458 239.302 15.6875C241.636 19.7292 242.802 24.3333 242.802 29.5C242.802 33.75 241.99 37.875 240.365 41.875C238.74 45.8333 236.344 49.3958 233.177 52.5625C230.052 55.7292 226.198 58.2083 221.615 60L231.615 92.75H203.99L195.49 62.5H196.115H186.177L179.74 92.75H153.24ZM194.802 22.125L189.99 44.375H201.802C204.761 44.375 207.323 43.8333 209.49 42.75C211.657 41.6667 213.323 40.1667 214.49 38.25C215.657 36.3333 216.24 34.1458 216.24 31.6875C216.24 28.4792 215.261 26.0833 213.302 24.5C211.386 22.9167 208.594 22.125 204.927 22.125H194.802ZM296.427 22.125C293.386 22.125 290.511 22.9583 287.802 24.625C285.136 26.2917 282.761 28.6042 280.677 31.5625C278.594 34.5208 276.948 37.9375 275.74 41.8125C274.573 45.6875 273.99 49.8542 273.99 54.3125C273.99 58.1042 274.615 61.4167 275.865 64.25C277.115 67.0833 278.865 69.2917 281.115 70.875C283.365 72.4167 286.011 73.1875 289.052 73.1875C292.094 73.1875 294.969 72.3542 297.677 70.6875C300.386 69.0208 302.782 66.7083 304.865 63.75C306.948 60.7917 308.573 57.375 309.74 53.5C310.948 49.5833 311.552 45.3958 311.552 40.9375C311.552 37.1042 310.927 33.7917 309.677 31C308.427 28.1667 306.657 25.9792 304.365 24.4375C302.115 22.8958 299.469 22.125 296.427 22.125ZM288.24 94.3125C279.657 94.3125 272.282 92.6458 266.115 89.3125C259.99 85.9375 255.282 81.3542 251.99 75.5625C248.74 69.7708 247.115 63.2292 247.115 55.9375C247.115 47.6875 248.407 40.1875 250.99 33.4375C253.573 26.6875 257.136 20.8958 261.677 16.0625C266.261 11.2292 271.573 7.52083 277.615 4.9375C283.657 2.3125 290.136 1 297.052 1C305.886 1 313.365 2.6875 319.49 6.0625C325.657 9.4375 330.344 14.0208 333.552 19.8125C336.802 25.5625 338.427 32.0833 338.427 39.375C338.427 47.6667 337.115 55.1875 334.49 61.9375C331.907 68.6875 328.302 74.4792 323.677 79.3125C319.094 84.1458 313.761 87.8542 307.677 90.4375C301.636 93.0208 295.157 94.3125 288.24 94.3125Z" fill="white"/></svg></span>`;
            }
            else if (author.comment_badge === 'crown') {
                badgeHtml = `<span class="comment-author-badge crown" title="Crown"><i class="fa-solid fa-crown"></i></span>`;
            } else if (author.comment_badge === 'bolt') {
                badgeHtml = `<span class="comment-author-badge bolt" title="Electricity"><i class="fa-solid fa-bolt"></i></span>`;
            } else if (author.comment_badge === 'verified') {
                badgeHtml = `<span class="comment-author-badge verified" title="Verified"><i class="fa-solid fa-check-double"></i></span>`;
            }
        }

        // Pro Name Badge (Box) Logic
        let headerHtml = '';
        if (author.is_pro && author.name_badge === 'on') {
            headerHtml = `
                <a href="profile.php?id=${author.id}" class="premium-nickname-box" style="margin-bottom: 4px;">
                    <i class="fa-solid fa-bolt mini-icon" style="color: #ffeb3b;"></i>
                    <span>${escapeHtml(author.username)}</span>
                    <i class="fa-solid fa-crown mini-icon" style="color: #ffd700;"></i>
                    ${badgeHtml}
                </a>`;
        } else {
            headerHtml = `<span class="comment-author">${escapeHtml(author.username)}</span>${badgeHtml}`;
        }

        if (comment.is_creator) {
            headerHtml += `<span class="creator-badge" style="background:var(--accent-color); color:white; padding:2px 6px; border-radius:4px; font-size:10px; margin-left:6px;">Creator</span>`;
        }

        // If it's a reply and we have a parent author, show "User > Parent" style
        if (comment.parent_author) {
            headerHtml += `
                <i class="fa-solid fa-caret-right" style="font-size:10px; color:var(--text-secondary); margin:0 6px;"></i>
                <span class="parent-author" style="color:var(--text-secondary); font-size:13px;">${escapeHtml(comment.parent_author)}</span>
            `;
        }

        let html = `
        <div class="comment-item ${indentClass}" style="${replyStyle}" data-comment-id="${comment.id}">
            <img src="${avatarUrl}" 
                 alt="${comment.author.username}" 
                 class="comment-avatar"
                 onerror="this.src='../assets/default-avatar.png'">
            <div class="comment-content">
                <div class="comment-header" style="display:flex; align-items:center; flex-wrap:wrap; margin-bottom:4px;">
                    ${headerHtml}
                </div>
                <div class="comment-text">${escapeHtml(comment.comment)}</div>
                <div class="comment-actions">
                    <button class="comment-action-btn like-comment-btn ${comment.is_liked ? 'liked' : ''}" 
                            onclick="likeComment(${comment.id}, this)">
                        <i class="fa-solid fa-heart"></i>
                        <span>${comment.likes || 0}</span>
                    </button>
                    <button class="comment-action-btn" onclick="replyToComment(${comment.id}, '${escapeHtml(comment.author.username)}')">
                        <i class="fa-solid fa-reply"></i>
                        Reply
                    </button>
                    <span class="comment-date">${timeAgo(comment.created_at)}</span>
                    ${comment.can_delete ? `<button class="comment-action-btn delete-btn" onclick="deleteComment(${comment.id})" style="margin-left:auto;"><i class="fa-solid fa-trash"></i></button>` : ''}
                </div>
            </div>
        </div>
        `;

        // Handle Replies
        if (comment.replies && comment.replies.length > 0) {
            html += `<div class="replies-container" id="replies-${comment.id}" style="display:none;">`;

            const flattenReplies = (nodes) => {
                let flat = [];
                nodes.forEach(node => {
                    flat.push(node);
                    if (node.replies && node.replies.length > 0) {
                        flat = flat.concat(flattenReplies(node.replies));
                    }
                });
                return flat;
            };

            const flatReplies = flattenReplies(comment.replies);
            html += flatReplies.map(r => renderCommentNode(r, true)).join('');
            html += `</div>`;

            html += `
            <button class="show-replies-btn" onclick="toggleReplies(${comment.id})" style="margin-left: 52px; background:none; border:none; color:var(--text-secondary); font-size:12px; font-weight:600; cursor:pointer; padding:8px 0; display:flex; align-items:center; gap:6px;">
                <div style="width:20px; height:1px; background:var(--text-secondary); opacity:0.5;"></div>
                View ${flatReplies.length} replies
                <i class="fa-solid fa-chevron-down" style="font-size:10px;"></i>
            </button>
            `;
        }

        if (isReply) {
            return `
            <div class="comment-item reply-item" style="margin-left: 40px; border-left: 2px solid var(--border-color);" data-comment-id="${comment.id}">
                <img src="${avatarUrl}" 
                     alt="${comment.author.username}" 
                     class="comment-avatar"
                     onerror="this.src='../assets/default-avatar.png'">
                <div class="comment-content">
                    <div class="comment-header" style="display:flex; align-items:center; flex-wrap:wrap; margin-bottom:4px;">
                        ${headerHtml}
                    </div>
                    <div class="comment-text">${escapeHtml(comment.comment)}</div>
                    <div class="comment-actions">
                        <button class="comment-action-btn like-comment-btn ${comment.is_liked ? 'liked' : ''}" 
                                onclick="likeComment(${comment.id}, this)">
                            <i class="fa-solid fa-heart"></i>
                            <span>${comment.likes || 0}</span>
                        </button>
                        <button class="comment-action-btn" onclick="replyToComment(${comment.id}, '${escapeHtml(comment.author.username)}')">
                            <i class="fa-solid fa-reply"></i>
                            Reply
                        </button>
                        <span>${timeAgo(comment.created_at)}</span>
                        ${comment.can_delete ? `<button class="comment-action-btn delete-btn" onclick="deleteComment(${comment.id})" style="margin-left:auto;"><i class="fa-solid fa-trash"></i></button>` : ''}
                    </div>
                </div>
            </div>`;
        }

        return html;
    };

    commentsList.innerHTML = comments.map(c => renderCommentNode(c)).join('');
}

function toggleReplies(commentId) {
    const container = document.getElementById(`replies-${commentId}`);
    if (container) {
        const isHidden = container.style.display === 'none';
        container.style.display = isHidden ? 'block' : 'none';

        const btn = document.querySelector(`button[onclick="toggleReplies(${commentId})"]`);
        if (btn) {
            const count = container.children.length;
            if (isHidden) {
                btn.innerHTML = `<div style="width:20px; height:1px; background:var(--text-secondary); opacity:0.5;"></div> Hide replies <i class="fa-solid fa-chevron-up" style="font-size:10px;"></i>`;
            } else {
                btn.innerHTML = `<div style="width:20px; height:1px; background:var(--text-secondary); opacity:0.5;"></div> View ${count} replies <i class="fa-solid fa-chevron-down" style="font-size:10px;"></i>`;
            }
        }
    }
}

async function postComment() {
    const input = document.getElementById('commentInput');
    const sendBtn = document.getElementById('commentSendBtn');

    // Force read value
    const commentText = input.value.trim();
    console.log('Posting comment:', commentText);

    if (!commentText || !currentVideoId) return;

    sendBtn.disabled = true;

    const endpoint = replyingToId ? '../backend/addReply.php' : '../backend/addComment.php';
    const payload = {
        video_id: currentVideoId,
        comment: commentText
    };

    if (replyingToId) {
        payload.parent_id = replyingToId;
    }

    try {
        const response = await fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        const data = await response.json();

        if (data.success) {
            // Clear input immediately and thoroughly
            input.value = '';
            input.setAttribute('value', '');

            resetCommentInput();
            loadComments(currentVideoId);
            showToast(replyingToId ? 'Reply posted!' : 'Comment posted!');
        } else {
            showToast(data.message || 'Failed to post', 'error');
        }
    } catch (error) {
        console.error('Error posting:', error);
        showToast('Failed to post. Please try again.', 'error');
    } finally {
        sendBtn.disabled = false;
    }
}

async function likeComment(commentId, btn) {
    try {
        const response = await fetch('../backend/toggleCommentLike.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ comment_id: commentId })
        });

        const data = await response.json();

        if (data.success) {
            btn.classList.toggle('liked');
            const countSpan = btn.querySelector('span');
            if (countSpan) {
                countSpan.textContent = data.likes;
            }
        }
    } catch (error) {
        console.error('Error liking comment:', error);
    }
}

async function toggleCommentDislike(commentId, btn) {
    try {
        const response = await fetch('../backend/toggleCommentDislike.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ comment_id: commentId })
        });
        const data = await response.json();
        if (data.success) {
            btn.classList.toggle('disliked');
        }
    } catch (error) {
        console.error('Error disliking comment:', error);
    }
}

function renderEmojiReactions(reactions, userReaction, commentId) {
    if (!reactions || reactions.length === 0) return '';
    const sorted = [...reactions].sort((a, b) => b.count - a.count);
    const top3 = sorted.slice(0, 3);

    return top3.map(r => `
        <div class="emoji-reaction-pill ${userReaction === r.emoji ? 'active' : ''}" data-emoji="${r.emoji}" data-id="${commentId}">
            <span class="emoji">${r.emoji}</span>
            <span class="count">${r.count}</span>
        </div>
    `).join('');
}

async function toggleEmojiReaction(commentId, emoji, onSuccess) {
    try {
        const response = await fetch('../backend/toggleCommentReaction.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ comment_id: commentId, emoji: emoji })
        });
        const data = await response.json();
        if (data.success && onSuccess) onSuccess();
    } catch (e) {
        console.error('Reaction error:', e);
    }
}

function openNativeEmojiPicker(commentId, buttonElement, onSuccess) {
    const existing = document.getElementById('nativeEmojiInput');
    if (existing) existing.remove();

    const input = document.createElement('input');
    input.type = 'text';
    input.id = 'nativeEmojiInput';
    input.style.cssText = `position: absolute; opacity: 0; width: 1px; height: 1px; border: none; font-size: 16px;`;
    input.setAttribute('inputmode', 'none');

    const rect = buttonElement.getBoundingClientRect();
    input.style.left = rect.left + 'px';
    input.style.top = rect.top + 'px';
    document.body.appendChild(input);

    input.addEventListener('input', async (e) => {
        const emoji = e.target.value;
        if (emoji && emoji.trim()) {
            await toggleEmojiReaction(commentId, emoji, onSuccess);
            input.remove();
        }
    });

    // Fallback simple grid if input fails or for desktop
    openFallbackEmojiPicker(commentId, buttonElement, onSuccess);
    input.focus();
}

function openFallbackEmojiPicker(commentId, buttonElement, onSuccess) {
    const existing = document.getElementById('fallbackEmojiPicker');
    if (existing) existing.remove();

    const picker = document.createElement('div');
    picker.id = 'fallbackEmojiPicker';
    picker.className = 'emoji-fallback-picker';

    const emojis = ['❤️', '😂', '😮', '😢', '👍', '🔥', '💯', '✨', '🙌', '👏', '😍', '🥳', '🤔', '😎', '🥺', '💀', '😭', '🤣', '😊', '🙏'];

    picker.innerHTML = `
        <div class="picker-grid">
            ${emojis.map(e => `<span class="picker-emoji" data-emoji="${e}">${e}</span>`).join('')}
        </div>
    `;

    const rect = buttonElement.getBoundingClientRect();
    picker.style.cssText = `
        position: fixed;
        left: ${Math.max(10, rect.left - 50)}px;
        top: ${rect.top - 120}px;
        z-index: 99999;
    `;

    document.body.appendChild(picker);

    picker.querySelectorAll('.picker-emoji').forEach(emojiEl => {
        emojiEl.addEventListener('click', async () => {
            const emoji = emojiEl.dataset.emoji;
            await toggleEmojiReaction(commentId, emoji, onSuccess);
            picker.remove();
            document.getElementById('nativeEmojiInput')?.remove();
        });
    });

    setTimeout(() => {
        const closeHandler = (e) => {
            if (!picker.contains(e.target) && !buttonElement.contains(e.target)) {
                picker.remove();
                document.removeEventListener('click', closeHandler);
            }
        };
        document.addEventListener('click', closeHandler);
    }, 100);
}

function replyToComment(commentId, username) {
    replyingToId = commentId;
    const input = document.getElementById('commentInput');
    const wrapper = document.querySelector('.comment-input-wrapper');

    input.focus();
    input.placeholder = `Replying to @${username}...`;

    // Add cancel button if not exists
    if (!document.getElementById('cancelReplyBtn')) {
        const cancelBtn = document.createElement('button');
        cancelBtn.id = 'cancelReplyBtn';
        cancelBtn.innerHTML = '<i class="fa-solid fa-xmark"></i>';
        cancelBtn.className = 'comment-action-btn';
        cancelBtn.style.marginLeft = '10px';
        cancelBtn.onclick = resetCommentInput;
        wrapper.insertBefore(cancelBtn, input);
    }
}

async function deleteComment(commentId) {

    try {
        const response = await fetch('../backend/deleteComment.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ comment_id: commentId })
        });

        const data = await response.json();
        if (data.success) {
            loadComments(currentVideoId);
            showToast('Comment deleted');
        } else {
            showToast(data.message || 'Failed to delete', 'error');
        }
    } catch (error) {
        console.error('Error deleting comment:', error);
    }
}

function showHeartBurst(x, y) {
    const heart = document.createElement('div');
    heart.className = 'heart-burst';
    heart.style.left = x + 'px';
    heart.style.top = y + 'px';
    heart.innerHTML = '<i class="fa-solid fa-heart"></i>';
    document.body.appendChild(heart);

    setTimeout(() => {
        heart.remove();
    }, 1000);
}

function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.style.cssText = `
        position: fixed;
        top: 80px;
        left: 50%;
        transform: translateX(-50%);
        background: ${type === 'error' ? 'rgba(255, 68, 68, 0.8)' : 'rgba(20, 20, 20, 0.8)'};
        color: white;
        padding: 12px 24px;
        border-radius: 50px;
        backdrop-filter: blur(25px);
        -webkit-backdrop-filter: blur(25px);
        border: 1px solid var(--glass-border);
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.4);
        z-index: 100000;
        font-weight: 600;
        font-size: 14px;
        letter-spacing: 0.5px;
        animation: toastSlide 0.4s cubic-bezier(0.18, 0.89, 0.32, 1.28) forwards;
    `;
    toast.textContent = message;
    document.body.appendChild(toast);

    setTimeout(() => {
        toast.style.animation = 'toastSlide 0.3s ease-out reverse';
        setTimeout(() => toast.remove(), 300);
    }, 2000);
}

function timeAgo(dateString) {
    const seconds = Math.floor((new Date() - new Date(dateString)) / 1000);

    if (seconds < 60) return 'just now';
    if (seconds < 3600) return Math.floor(seconds / 60) + 'm ago';
    if (seconds < 86400) return Math.floor(seconds / 3600) + 'h ago';
    if (seconds < 604800) return Math.floor(seconds / 86400) + 'd ago';
    return Math.floor(seconds / 604800) + 'w ago';
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Setup drawer event listeners
document.addEventListener('DOMContentLoaded', () => {
    const drawerClose = document.getElementById('drawerClose');
    const drawerOverlay = document.getElementById('drawerOverlay');
    const commentSendBtn = document.getElementById('commentSendBtn');
    const commentInput = document.getElementById('commentInput');

    if (drawerClose) {
        drawerClose.addEventListener('click', closeCommentDrawer);
    }

    if (drawerOverlay) {
        drawerOverlay.addEventListener('click', closeCommentDrawer);
    }

    if (commentSendBtn) {
        commentSendBtn.addEventListener('click', postComment);
    }

    if (commentInput) {
        commentInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                postComment();
            }
        });
    }

    const navUp = document.getElementById('navUp');
    const navDown = document.getElementById('navDown');

    if (navUp) {
        navUp.addEventListener('click', () => scrollToClip(currentClipIndex - 1));
    }
    if (navDown) {
        navDown.addEventListener('click', () => scrollToClip(currentClipIndex + 1));
    }
});

function formatCount(num) {
    if (num >= 1000000) {
        return (num / 1000000).toFixed(1) + 'M';
    }
    if (num >= 1000) {
        return (num / 1000).toFixed(1) + 'K';
    }
    return num;
}

/**
 * AMBIENT GLOW COMPONENT - Cinematic Ambilight Engine
 * gpu-accelerated color sampling with temporal blending
 */
class AmbientGlow {
    constructor(video, canvas) {
        this.video = video;
        this.canvas = canvas;
        if (!this.video || !this.canvas) return;

        this.ctx = this.canvas.getContext('2d', { alpha: true });
        this.fieldWidth = 64;
        this.fieldHeight = 36;

        this.canvas.width = this.fieldWidth;
        this.canvas.height = this.fieldHeight;

        this.isActive = false;
        this.interval = null;
        this.init();
    }

    init() {
        const updateState = () => {
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

        const loop = () => {
            if (!this.isActive) return;
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
        this.ctx.globalAlpha = 0.05; // Temporal blending
        this.ctx.drawImage(this.video, 0, 0, this.fieldWidth, this.fieldHeight);
        this.ctx.globalAlpha = 1.0;
    }
}
