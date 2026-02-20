
// Starfield
(function () {
    const canvas = document.getElementById('postBgCanvas');
    const ctx = canvas.getContext('2d');
    let stars = [];
    const starCount = 200;
    let speedFactor = 1;
    let lastTime = 0;

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
                speed: (Math.random() * 0.015) + 0.005, // Much slower base speed
                opacity: Math.random(),
                driftX: (Math.random() - 0.5) * 0.01
            });
        }
    }

    function draw(timestamp) {
        if (!lastTime) lastTime = timestamp;
        const elapsed = timestamp - lastTime;

        // Gradually increase speed much more slowly (max 2x speed)
        if (speedFactor < 2) {
            speedFactor += 0.0001 * (elapsed / 16);
        }

        ctx.clearRect(0, 0, canvas.width, canvas.height);
        stars.forEach(star => {
            ctx.fillStyle = `rgba(255, 255, 255, ${star.opacity})`;
            ctx.beginPath();
            ctx.arc(star.x, star.y, star.size, 0, Math.PI * 2);
            ctx.fill();

            star.y -= star.speed * speedFactor;
            star.x += star.driftX * speedFactor;

            if (star.y < 0) {
                star.y = canvas.height;
                star.x = Math.random() * canvas.width;
            }
        });

        lastTime = timestamp;
        requestAnimationFrame(draw);
    }

    window.addEventListener('resize', resize);
    resize();
    requestAnimationFrame(draw);
})();

const postId = <? php echo $post_id;?>;
const postAuthorId = <? php echo (int)$post['user_id']; ?>;
const currentUserId = <? php echo $_SESSION['user_id'] ?? 0; ?>;
let newlySubmittedIds = [];
let currentSortMode = 'newest';

window.setCommentSort = function (mode, el) {
    currentSortMode = mode;
    document.querySelectorAll('.sort-option').forEach(opt => opt.classList.remove('active'));
    el.classList.add('active');
    loadComments();
};

function getBadgeHtml(author) {
    if (!author.is_pro || !author.comment_badge) return '';

    if (author.comment_badge === 'pro') {
        return `<span class="comment-author-badge pro-svg" style="width: 22px; height: 16px; display: inline-flex;" title="Pro Member"><svg width="100%" height="100%" viewBox="0 0 340 96" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M87.5524 2.5625H121.74C128.865 2.5625 134.886 3.72917 139.802 6.0625C144.761 8.39583 148.532 11.6667 151.115 15.875C153.698 20.0833 154.99 25 154.99 30.625C154.99 38.2917 153.282 44.9375 149.865 50.5625C146.49 56.1458 141.657 60.4792 135.365 63.5625C129.073 66.6042 121.615 68.125 112.99 68.125H100.177L94.9274 92.75H68.4899L87.5524 2.5625ZM109.802 22.5625L104.24 48.5H113.177C116.261 48.5 118.948 47.8333 121.24 46.5C123.532 45.125 125.302 43.2708 126.552 40.9375C127.802 38.5625 128.427 35.9167 128.427 33C128.427 29.4167 127.427 26.7917 125.427 25.125C123.427 23.4167 120.532 22.5625 116.74 22.5625H109.802ZM153.24 92.75L172.427 2.5625H212.99C219.323 2.5625 224.698 3.75 229.115 6.125C233.573 8.45833 236.969 11.6458 239.302 15.6875C241.636 19.7292 242.802 24.3333 242.802 29.5C242.802 33.75 241.99 37.875 240.365 41.875C238.74 45.8333 236.344 49.3958 233.177 52.5625C230.052 55.7292 226.198 58.2083 221.615 60L231.615 92.75H203.99L195.49 62.5H196.115H186.177L179.74 92.75H153.24ZM194.802 22.125L189.99 44.375H201.802C204.761 44.375 207.323 43.8333 209.49 42.75C211.657 41.6667 213.323 40.1667 214.49 38.25C215.657 36.3333 216.24 34.1458 216.24 31.6875C216.24 28.4792 215.261 26.0833 213.302 24.5C211.386 22.9167 208.594 22.125 204.927 22.125H194.802ZM296.427 22.125C293.386 22.125 290.511 22.9583 287.802 24.625C285.136 26.2917 282.761 28.6042 280.677 31.5625C278.594 34.5208 276.948 37.9375 275.74 41.8125C274.573 45.6875 273.99 49.8542 273.99 54.3125C273.99 58.1042 274.615 61.4167 275.865 64.25C277.115 67.0833 278.865 69.2917 281.115 70.875C283.365 72.4167 286.011 73.1875 289.052 73.1875C292.094 73.1875 294.969 72.3542 297.677 70.6875C300.386 69.0208 302.782 66.7083 304.865 63.75C306.948 60.7917 308.573 57.375 309.74 53.5C310.948 49.5833 311.552 45.3958 311.552 40.9375C311.552 37.1042 310.927 33.7917 309.677 31C308.427 28.1667 306.657 25.9792 304.365 24.4375C302.115 22.8958 299.469 22.125 296.427 22.125ZM288.24 94.3125C279.657 94.3125 272.282 92.6458 266.115 89.3125C259.99 85.9375 255.282 81.3542 251.99 75.5625C248.74 69.7708 247.115 63.2292 247.115 55.9375C247.115 47.6875 248.407 40.1875 250.99 33.4375C253.573 26.6875 257.136 20.8958 261.677 16.0625C266.261 11.2292 271.573 7.52083 277.615 4.9375C283.657 2.3125 290.136 1 297.052 1C305.886 1 313.365 2.6875 319.49 6.0625C325.657 9.4375 330.344 14.0208 333.552 19.8125C336.802 25.5625 338.427 32.0833 338.427 39.375C338.427 47.6667 337.115 55.1875 334.49 61.9375C331.907 68.6875 328.302 74.4792 323.677 79.3125C319.094 84.1458 313.761 87.8542 307.677 90.4375C301.636 93.0208 295.157 94.3125 288.24 94.3125Z" fill="currentColor"/></svg></span>`;
    } else if (author.comment_badge === 'crown') {
        return `<span class="comment-author-badge crown" title="Crown"><i class="fa-solid fa-crown"></i></span>`;
    } else if (author.comment_badge === 'bolt') {
        return `<span class="comment-author-badge bolt" title="Electricity"><i class="fa-solid fa-bolt"></i></span>`;
    } else if (author.comment_badge === 'verified') {
        return `<span class="comment-author-badge verified" title="Verified"><i class="fa-solid fa-check-double"></i></span>`;
    }
    return '';
}

function getAuthorDisplay(c) {
    const author = {
        id: c.user_id,
        username: c.username,
        is_pro: parseInt(c.is_pro),
        comment_badge: c.comment_badge,
        name_badge: c.name_badge
    };
    const badgeHtml = getBadgeHtml(author);
    const isOP = parseInt(author.id) === postAuthorId;
    const opHtml = isOP ? `<span class="op-badge" title="Post Author"><i class="fa-solid fa-user-pen"></i> Author</span>` : '';

    if (author.is_pro && author.name_badge === 'on') {
        return `
                    <a href="user_profile.php?user_id=${author.id}" class="premium-nickname-box" style="text-decoration:none; display:inline-flex; align-items:center; gap:6px; background:linear-gradient(90deg, #ffeb3b, #fbc02d); -webkit-background-clip:text; -webkit-text-fill-color:transparent; font-weight:800;">
                        <i class="fa-solid fa-bolt mini-icon" style="color: #ffeb3b; -webkit-text-fill-color: #ffeb3b;"></i>
                        <span>${escapeHtml(author.username)}</span>
                        <i class="fa-solid fa-crown mini-icon" style="color: #ffd700; -webkit-text-fill-color: #ffd700;"></i>
                        ${badgeHtml} ${opHtml}
                    </a>`;
    } else {
        return `
                    <div style="display:inline-flex; align-items:center; gap:6px;">
                        <span class="comment-user-lg">${escapeHtml(author.username)}</span>
                        ${badgeHtml} ${opHtml}
                    </div>`;
    }
}

function renderCommentMenu(c) {
    const isOwner = parseInt(c.user_id) === currentUserId;
    return `
                <div class="comment-menu-container">
                    <button class="comment-menu-btn" onclick="toggleCommentDropdown(event, ${c.id})">
                        <i class="fa-solid fa-ellipsis-vertical"></i>
                    </button>
                    <div id="dropdown-${c.id}" class="comment-dropdown-menu">
                        <div class="dropdown-item" onclick="reportComment(${c.id})"><i class="fa-solid fa-flag"></i> Report</div>
                        <div class="dropdown-item" onclick="copyCommentText('${c.id}')"><i class="fa-solid fa-copy"></i> Copy Text</div>
                        <div class="dropdown-item" onclick="hideComment(${c.id}, this)"><i class="fa-solid fa-eye-slash"></i> Hide</div>
                        ${isOwner ? `
                            <div class="dropdown-item" onclick="editComment(${c.id})"><i class="fa-solid fa-pen"></i> Edit</div>
                            <div class="dropdown-item danger" onclick="deleteComment(${c.id})"><i class="fa-solid fa-trash"></i> Delete</div>
                        ` : ''}
                    </div>
                </div>
            `;
}

async function loadComments() {
    const list = document.getElementById('fullCommentsList');

    if (!list.querySelector('.full-comment-item')) {
        list.innerHTML = `
            <div style="display:flex; flex-direction:column; justify-content:center; align-items:center; padding:60px 0; gap:16px;">
                <svg class="premium-spinner-svg" width="40" height="40" viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
                    <defs>
                        <linearGradient id="spinner-gradient-temp" x1="0%" y1="0%" x2="100%" y2="0%">
                            <stop offset="0%" stop-color="#3ea6ff" />
                            <stop offset="100%" stop-color="#9d4edd" />
                        </linearGradient>
                    </defs>
                    <circle cx="20" cy="20" r="18" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="4"></circle>
                    <circle cx="20" cy="20" r="18" fill="none" stroke="url(#spinner-gradient-temp)" stroke-width="4" stroke-dasharray="20 100" stroke-linecap="round"></circle>
                </svg>
                <div style="color:rgba(255,255,255,0.4); font-size:11px; font-weight:700; letter-spacing:1.5px; text-transform:uppercase;">Loading Comments...</div>
            </div>
        `;
    }

    try {
        const res = await fetch(`../backend/getPostComments.php?post_id=${postId}`);
        const data = await res.json();

        if (data.success) {
            if (!data.comments || data.comments.length === 0) {
                list.innerHTML = '<div style="text-align:center; padding:40px; opacity:0.5; color:#fff;">No comments yet. Start the conversation!</div>';
            } else {
                // Priority Sorting: Newly submitted in this session come first, then Pinned, then User Selected
                const sortedComments = data.comments.sort((a, b) => {
                    const aNew = newlySubmittedIds.includes(parseInt(a.id));
                    const bNew = newlySubmittedIds.includes(parseInt(b.id));
                    if (aNew && !bNew) return -1;
                    if (!aNew && bNew) return 1;

                    // Primary sort: Pinned status
                    const aPinned = parseInt(a.is_pinned) || 0;
                    const bPinned = parseInt(b.is_pinned) || 0;
                    if (aPinned > bPinned) return -1;
                    if (aPinned < bPinned) return 1;

                    // Secondary sort: Mode-based
                    if (currentSortMode === 'top') {
                        const aLikes = parseInt(a.likes_count) || 0;
                        const bLikes = parseInt(b.likes_count) || 0;
                        if (aLikes !== bLikes) return bLikes - aLikes;
                    }

                    // Default to newest
                    return b.id - a.id;
                });

                // Separate roots from replies
                const roots = sortedComments.filter(c => !c.parent_id);
                const replies = sortedComments.filter(c => c.parent_id);

                list.innerHTML = roots.map(c => {
                    // Find all replies and prioritize new ones
                    const threadReplies = findDescendants(c.id, sortedComments);

                    // Re-sort threadReplies to ensure 'new' ones in this thread are at the top of the reply list
                    threadReplies.sort((a, b) => {
                        const aNew = newlySubmittedIds.includes(parseInt(a.id));
                        const bNew = newlySubmittedIds.includes(parseInt(b.id));
                        if (aNew && !bNew) return -1;
                        if (!aNew && bNew) return 1;
                        return 0;
                    });

                    return `
                                <div class="full-comment-item">
                                    <img src="${c.profile_picture || 'assets/default-avatar.png'}" class="comment-avatar-lg">
                                    <div class="comment-main-lg">
                                        ${parseInt(c.is_pinned) ? `<div class="pinned-label"><i class="fa-solid fa-thumbtack"></i> Pinned</div>` : ''}
                                        <div class="comment-header-row">
                                            ${getAuthorDisplay(c)}
                                            ${renderCommentMenu(c)}
                                        </div>
                                        <p class="comment-text-lg" id="comment-body-${c.id}">${escapeHtml(c.comment)}</p>

                                        <div class="comment-footer-actions">
                                            <div class="comment-action-link ${parseInt(c.is_liked) ? 'active-like' : ''}" onclick="toggleCommentLike(${c.id}, this)">
                                                <i class="fa-solid fa-thumbs-up"></i>
                                                <span class="count">${c.likes_count || 0}</span>
                                            </div>
                                            <div class="comment-action-link ${parseInt(c.is_disliked) ? 'active-dislike' : ''}" onclick="toggleCommentDislike(${c.id}, this)">
                                                <i class="fa-solid fa-thumbs-down"></i>
                                                <span class="count">${c.dislikes_count || 0}</span>
                                            </div>
                                            <div class="comment-action-link" onclick="toggleReplyInput(${c.id}, this)">
                                                <i class="fa-solid fa-reply"></i> Reply
                                            </div>
                                            <div style="position:relative;">
                                                <button class="add-reaction-trigger" onclick="openCommentEmojiPicker(event, ${c.id}, this)">
                                                    <i class="fa-solid fa-plus"></i>
                                                </button>
                                                <div id="commentPicker-${c.id}" class="emoji-picker-panel" style="display:none; left:0;">
                                                    <div class="emoji-picker-header">
                                                        <input type="text" placeholder="Search..." oninput="filterEmojis(this, 'comment', ${c.id})">
                                                    </div>
                                                    <div class="emoji-grid"></div>
                                                </div>
                                            </div>
                                            <div class="comment-reactions-display" id="comment-reactions-${c.id}">
                                                ${(c.reactions || []).map(r => `
                                                    <div class="reaction-pill ${r.reaction_type === c.user_reaction ? 'active' : ''}" onclick="handleEmojiReaction('${r.reaction_type}', 'comment', ${c.id})">
                                                        <span>${r.reaction_type}</span>
                                                        <span class="count">${r.count}</span>
                                                    </div>
                                                `).join('')}
                                            </div>
                                        </div>

                                        <div id="replyArea-${c.id}" class="reply-input-area" style="display:none; margin-top:10px; margin-bottom: 20px;">
                                            <div class="full-comment-input-wrapper">
                                                <input type="text" placeholder="Write a reply..." class="reply-input-field">
                                                <button class="full-comment-submit" onclick="submitReply(${c.id}, this)"><i class="fa-solid fa-paper-plane"></i></button>
                                            </div>
                                        </div>

                                        <div class="comment-replies-container">
                                            ${threadReplies.map(r => `
                                                <div class="reply-item">
                                                    <img src="${r.profile_picture || 'assets/default-avatar.png'}" class="reply-avatar">
                                                    <div class="reply-main">
                                                        <div class="comment-header-row">
                                                            <div class="reply-user-header">
                                                                ${getAuthorDisplay(r)}
                                                                <span class="reply-target-indicator">
                                                                    <i class="fa-solid fa-play" style="font-size: 8px;"></i>
                                                                    <span class="reply-target-name">${escapeHtml(r.parent_username)}</span>
                                                                </span>
                                                            </div>
                                                            ${renderCommentMenu(r)}
                                                        </div>
                                                        <p class="reply-text" id="comment-body-${r.id}">${escapeHtml(r.comment)}</p>
                                                        <div class="comment-footer-actions" style="margin-top: 5px;">
                                                            <div class="comment-action-link ${parseInt(r.is_liked) ? 'active-like' : ''}" onclick="toggleCommentLike(${r.id}, this)">
                                                                <i class="fa-solid fa-thumbs-up"></i>
                                                                <span class="count">${r.likes_count || 0}</span>
                                                            </div>
                                                            <div class="comment-action-link ${parseInt(r.is_disliked) ? 'active-dislike' : ''}" onclick="toggleCommentDislike(${r.id}, this)">
                                                                <i class="fa-solid fa-thumbs-down"></i>
                                                                <span class="count">${r.dislikes_count || 0}</span>
                                                            </div>
                                                            <div class="comment-action-link" style="font-size: 11px;" onclick="toggleReplyInput(${r.id}, this)">
                                                                <i class="fa-solid fa-reply"></i> Reply
                                                            </div>
                                                            <div style="position:relative;">
                                                                <button class="add-reaction-trigger" style="padding: 2px 8px;" onclick="openCommentEmojiPicker(event, ${r.id}, this)">
                                                                    <i class="fa-solid fa-plus"></i>
                                                                </button>
                                                                <div id="commentPicker-${r.id}" class="emoji-picker-panel" style="display:none; left:0;">
                                                                    <div class="emoji-picker-header">
                                                                        <input type="text" placeholder="Search..." oninput="filterEmojis(this, 'comment', ${r.id})">
                                                                    </div>
                                                                    <div class="emoji-grid"></div>
                                                                </div>
                                                            </div>
                                                            <div class="comment-reactions-display" id="comment-reactions-${r.id}">
                                                                ${(r.reactions || []).map(re => `
                                                                    <div class="reaction-pill ${re.reaction_type === r.user_reaction ? 'active' : ''}" onclick="handleEmojiReaction('${re.reaction_type}', 'comment', ${r.id})">
                                                                        <span>${re.reaction_type}</span>
                                                                        <span class="count">${re.count}</span>
                                                                    </div>
                                                                `).join('')}
                                                            </div>
                                                        </div>
                                                        <div id="replyArea-${r.id}" class="reply-input-area" style="display:none; margin-top:10px;">
                                                            <div class="full-comment-input-wrapper">
                                                                <input type="text" placeholder="Reply to ${escapeHtml(r.username)}..." class="reply-input-field">
                                                                <button class="full-comment-submit" onclick="submitReply(${r.id}, this)"><i class="fa-solid fa-paper-plane"></i></button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            `).join('')}
                                        </div>
                                    </div>
                                </div>
                            `;
                }).join('');
            }
        }
    } catch (e) {
        // Silently fails as per cleanup request
    }
}

// Helper to find all descendants for infinite flattening
function findDescendants(parentId, allComments) {
    let descendants = [];
    const direct = allComments.filter(c => c.parent_id == parentId);
    for (const d of direct) {
        descendants.push(d);
        descendants = descendants.concat(findDescendants(d.id, allComments));
    }
    return descendants;
}

async function submitMainComment() {
    const input = document.getElementById('mainCommentInput');
    const comment = input.value.trim();
    if (!comment) return;

    const btn = document.querySelector('.full-comment-submit');
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
            if (data.id) newlySubmittedIds.push(parseInt(data.id));
            loadComments();
        }
    } catch (e) {
    } finally {
        btn.disabled = false;
    }
}

window.likePost = async function (id, el) {
    const icon = el.querySelector('i');
    const label = document.getElementById('likeCount');
    const wasActive = icon.classList.contains('active');

    icon.classList.toggle('active');
    try {
        const res = await fetch('../backend/likePost.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ post_id: id })
        });
        const data = await res.json();
        if (data.success) {
            label.textContent = data.likes.toLocaleString();
            if (data.status === 'liked') {
                icon.classList.add('active');
                createSparkles(el);
            } else {
                icon.classList.remove('active');
            }
        }
    } catch (e) { }
};

function createSparkles(el) {
    const colors = ['#ff4f4f', '#ff8a8a', '#ffb3b3', '#ffffff'];
    for (let i = 0; i < 12; i++) {
        const sparkle = document.createElement('div');
        sparkle.className = 'sparkle';

        // Random position around the element center
        const tx = (Math.random() - 0.5) * 60;
        const ty = (Math.random() - 0.5) * 60;

        sparkle.style.setProperty('--tx', `${tx}px`);
        sparkle.style.setProperty('--ty', `${ty}px`);
        sparkle.style.background = colors[Math.floor(Math.random() * colors.length)];

        // Center the sparkle on the icon
        sparkle.style.left = '50%';
        sparkle.style.top = '50%';
        sparkle.style.transform = 'translate(-50%, -50%)';

        el.appendChild(sparkle);

        setTimeout(() => sparkle.remove(), 700);
    }
}

window.toggleCommentLike = async function (commentId, el) {
    try {
        const res = await fetch('../backend/togglePostCommentLike.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ comment_id: commentId })
        });
        const data = await res.json();
        if (data.success) {
            const parent = el.closest('.comment-footer-actions');
            const likeBtn = parent.querySelector('.active-like') || el;
            const dislikeBtn = parent.querySelector('.active-dislike') || parent.querySelectorAll('.comment-action-link')[1];

            likeBtn.querySelector('.count').textContent = data.likes;
            dislikeBtn.querySelector('.count').textContent = data.dislikes;

            if (data.is_liked) likeBtn.classList.add('active-like');
            else likeBtn.classList.remove('active-like');

            if (data.is_disliked) dislikeBtn.classList.add('active-dislike');
            else dislikeBtn.classList.remove('active-dislike');
        }
    } catch (e) {
    }
};

window.toggleCommentDislike = async function (commentId, el) {
    try {
        const res = await fetch('../backend/togglePostCommentDislike.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ comment_id: commentId })
        });
        const data = await res.json();
        if (data.success) {
            const parent = el.closest('.comment-footer-actions');
            const likeBtn = parent.querySelector('.active-like') || parent.querySelectorAll('.comment-action-link')[0];
            const dislikeBtn = parent.querySelector('.active-dislike') || el;

            likeBtn.querySelector('.count').textContent = data.likes;
            dislikeBtn.querySelector('.count').textContent = data.dislikes;

            if (data.is_liked) likeBtn.classList.add('active-like');
            else likeBtn.classList.remove('active-like');

            if (data.is_disliked) dislikeBtn.classList.add('active-dislike');
            else dislikeBtn.classList.remove('active-dislike');
        }
    } catch (e) {
    }
};

window.toggleReplyInput = function (id, el) {
    const area = document.getElementById(`replyArea-${id}`);
    area.style.display = area.style.display === 'none' ? 'block' : 'none';
    if (area.style.display === 'block') {
        area.querySelector('input').focus();
    }
};

window.submitReply = async function (parentId, btn) {
    const area = document.getElementById(`replyArea-${parentId}`);
    const input = area.querySelector('input');
    const comment = input.value.trim();
    if (!comment) return;

    btn.disabled = true;
    try {
        const res = await fetch('../backend/addPostComment.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ post_id: postId, comment: comment, parent_id: parentId })
        });
        const data = await res.json();
        if (data.success) {
            input.value = '';
            area.style.display = 'none';
            if (data.id) newlySubmittedIds.push(parseInt(data.id));
            loadComments(); // Refresh list to show new reply
        }
    } catch (e) {
    } finally {
        btn.disabled = false;
    }
};

window.reactToPost = async function (type) {
    console.log("Reacting to post:", type, "Post ID:", postId);
    try {
        const res = await fetch('../backend/reactToPost.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ post_id: postId, reaction: type })
        });
        const data = await res.json();
        if (data.success) {
            renderPostReactionPills(data.reactions, data.user_reaction);
        }
    } catch (e) {
    }
};

function renderPostReactionPills(reactions, userReaction) {
    const display = document.getElementById('postReactionsDisplay');
    const totalLabel = document.getElementById('totalReactionsCount');

    let total = 0;
    if (reactions) reactions.forEach(r => total += parseInt(r.count));

    if (total > 0) {
        totalLabel.innerHTML = `${total.toLocaleString()} reactions`;
        totalLabel.style.display = 'block';
    } else {
        totalLabel.style.display = 'none';
    }

    display.innerHTML = '';
    display.appendChild(totalLabel); // Keep label first

    (reactions || []).forEach(r => {
        const pill = document.createElement('div');
        pill.className = `reaction-pill ${r.reaction_type === userReaction ? 'active' : ''}`;
        pill.onclick = () => handleEmojiReaction(r.reaction_type, 'post');
        pill.innerHTML = `<span>${r.reaction_type}</span><span class="count">${r.count}</span>`;
        display.appendChild(pill);
    });
}

window.reactToComment = async function (commentId, type) {
    try {
        const res = await fetch('../backend/reactToComment.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ comment_id: commentId, reaction: type })
        });
        const data = await res.json();
        if (data.success) {
            const display = document.getElementById(`comment-reactions-${commentId}`);
            if (display) {
                display.innerHTML = (data.reactions || []).map(r => `
                            <div class="reaction-pill ${r.reaction_type === data.user_reaction ? 'active' : ''}" 
                                 onclick="handleEmojiReaction('${r.reaction_type}', 'comment', ${commentId})">
                                <span>${r.reaction_type}</span>
                                <span class="count">${r.count}</span>
                            </div>
                        `).join('');
            }
        }
    } catch (e) {
    }
};

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

loadComments();
// Comprehensive Emoji List - All Major Categories
const allEmojis = [
    // Smileys & Emotion
    { char: '😀', name: 'grinning face' }, { char: '😃', name: 'grinning face big eyes' }, { char: '😄', name: 'grinning squinting' }, { char: '😁', name: 'beaming face' },
    { char: '😆', name: 'grinning squinting face' }, { char: '😅', name: 'grinning sweat' }, { char: '🤣', name: 'rolling floor laughing' }, { char: '😂', name: 'face tears joy' },
    { char: '🙂', name: 'slightly smiling' }, { char: '🙃', name: 'upside down' }, { char: '🫠', name: 'melting face' }, { char: '😉', name: 'winking face' },
    { char: '😊', name: 'smiling blushing' }, { char: '😇', name: 'smiling halo angel' }, { char: '🥰', name: 'smiling hearts love' }, { char: '😍', name: 'heart eyes' },
    { char: '🤩', name: 'star struck' }, { char: '😘', name: 'face blowing kiss' }, { char: '😗', name: 'kissing face' }, { char: '☺️', name: 'smiling face' },
    { char: '😚', name: 'kissing closed eyes' }, { char: '😙', name: 'kissing smiling' }, { char: '🥲', name: 'smiling tear' }, { char: '😋', name: 'face savoring' },
    { char: '😛', name: 'face tongue' }, { char: '😜', name: 'winking tongue' }, { char: '🤪', name: 'zany face crazy' }, { char: '😝', name: 'squinting tongue' },
    { char: '🤑', name: 'money mouth' }, { char: '🤗', name: 'hugging face' }, { char: '🤭', name: 'face hand over mouth' }, { char: '🫢', name: 'face open eyes hand' },
    { char: '🫣', name: 'face peeking' }, { char: '🤫', name: 'shushing face' }, { char: '🤔', name: 'thinking face' }, { char: '🫡', name: 'saluting face' },
    { char: '🤐', name: 'zipper mouth' }, { char: '🤨', name: 'face raised eyebrow' }, { char: '😐', name: 'neutral face' }, { char: '😑', name: 'expressionless' },
    { char: '😶', name: 'face without mouth' }, { char: '🫥', name: 'dotted line face' }, { char: '😶‍🌫️', name: 'face clouds' }, { char: '😏', name: 'smirking' },
    { char: '😒', name: 'unamused face' }, { char: '🙄', name: 'rolling eyes' }, { char: '😬', name: 'grimacing' }, { char: '😮‍💨', name: 'exhaling' },
    { char: '🤥', name: 'lying face' }, { char: '🫨', name: 'shaking face' }, { char: '😌', name: 'relieved' }, { char: '😔', name: 'pensive' },
    { char: '😪', name: 'sleepy face' }, { char: '🤤', name: 'drooling' }, { char: '😴', name: 'sleeping face' }, { char: '😷', name: 'face medical mask' },
    { char: '🤒', name: 'face thermometer' }, { char: '🤕', name: 'face bandage' }, { char: '🤢', name: 'nauseated' }, { char: '🤮', name: 'vomiting' },
    { char: '🤧', name: 'sneezing' }, { char: '🥵', name: 'hot face' }, { char: '🥶', name: 'cold face' }, { char: '🥴', name: 'woozy' },
    { char: '😵', name: 'face spiral eyes' }, { char: '😵‍💫', name: 'face spiral eyes dizzy' }, { char: '🤯', name: 'exploding head' }, { char: '🤠', name: 'cowboy hat' },
    { char: '🥳', name: 'partying face' }, { char: '🥸', name: 'disguised face' }, { char: '😎', name: 'sunglasses cool' }, { char: '🤓', name: 'nerd face' },
    { char: '🧐', name: 'monocle' }, { char: '😕', name: 'confused' }, { char: '🫤', name: 'face diagonal mouth' }, { char: '😟', name: 'worried' },
    { char: '🙁', name: 'slightly frowning' }, { char: '☹️', name: 'frowning face' }, { char: '😮', name: 'face open mouth' }, { char: '😯', name: 'hushed' },
    { char: '😲', name: 'astonished' }, { char: '😳', name: 'flushed' }, { char: '🥺', name: 'pleading face' }, { char: '🥹', name: 'holding back tears' },
    { char: '😦', name: 'frowning open mouth' }, { char: '😧', name: 'anguished' }, { char: '😨', name: 'fearful' }, { char: '😰', name: 'anxious sweat' },
    { char: '😥', name: 'sad relieved' }, { char: '😢', name: 'crying face' }, { char: '😭', name: 'loudly crying' }, { char: '😱', name: 'screaming fear' },
    { char: '😖', name: 'confounded' }, { char: '😣', name: 'persevering' }, { char: '😞', name: 'disappointed' }, { char: '😓', name: 'downcast sweat' },
    { char: '😩', name: 'weary' }, { char: '😫', name: 'tired' }, { char: '🥱', name: 'yawning' }, { char: '😤', name: 'huffing triumph' },
    { char: '😡', name: 'enraged pouting' }, { char: '😠', name: 'angry' }, { char: '🤬', name: 'cursing symbols' }, { char: '😈', name: 'smiling horns devil' },
    { char: '👿', name: 'angry horns' }, { char: '💀', name: 'skull' }, { char: '☠️', name: 'skull crossbones' }, { char: '💩', name: 'pile poo' },
    { char: '🤡', name: 'clown' }, { char: '👹', name: 'ogre' }, { char: '👺', name: 'goblin' }, { char: '👻', name: 'ghost' },
    { char: '👽', name: 'alien' }, { char: '👾', name: 'alien monster' }, { char: '🤖', name: 'robot' }, { char: '😺', name: 'grinning cat' },
    { char: '😸', name: 'grinning cat smiling' }, { char: '😹', name: 'cat tears joy' }, { char: '😻', name: 'cat heart eyes' }, { char: '😼', name: 'cat wry smile' },
    { char: '😽', name: 'kissing cat' }, { char: '🙀', name: 'weary cat' }, { char: '😿', name: 'crying cat' }, { char: '😾', name: 'pouting cat' },
    { char: '🙈', name: 'see no evil monkey' }, { char: '🙉', name: 'hear no evil' }, { char: '🙊', name: 'speak no evil' },
    // Hearts & Love
    { char: '💌', name: 'love letter' }, { char: '💘', name: 'heart arrow' }, { char: '💝', name: 'heart ribbon' }, { char: '💖', name: 'sparkling heart' },
    { char: '💗', name: 'growing heart' }, { char: '💓', name: 'beating heart' }, { char: '💞', name: 'revolving hearts' }, { char: '💕', name: 'two hearts' },
    { char: '💟', name: 'heart decoration' }, { char: '❣️', name: 'heart exclamation' }, { char: '💔', name: 'broken heart' }, { char: '❤️‍🔥', name: 'heart fire' },
    { char: '❤️‍🩹', name: 'mending heart' }, { char: '❤️', name: 'red heart love' }, { char: '🩷', name: 'pink heart' }, { char: '🧡', name: 'orange heart' },
    { char: '💛', name: 'yellow heart' }, { char: '💚', name: 'green heart' }, { char: '💙', name: 'blue heart' }, { char: '🩵', name: 'light blue heart' },
    { char: '💜', name: 'purple heart' }, { char: '🤎', name: 'brown heart' }, { char: '🖤', name: 'black heart' }, { char: '🩶', name: 'grey heart' },
    { char: '🤍', name: 'white heart' }, { char: '💋', name: 'kiss mark' },
    // Gestures & Body
    { char: '👋', name: 'waving hand' }, { char: '🤚', name: 'raised back hand' }, { char: '🖐️', name: 'hand fingers splayed' }, { char: '✋', name: 'raised hand' },
    { char: '🖖', name: 'vulcan salute' }, { char: '🫱', name: 'rightwards hand' }, { char: '🫲', name: 'leftwards hand' }, { char: '🫳', name: 'palm down' },
    { char: '🫴', name: 'palm up' }, { char: '🫷', name: 'leftwards pushing' }, { char: '🫸', name: 'rightwards pushing' }, { char: '👌', name: 'ok hand' },
    { char: '🤌', name: 'pinched fingers' }, { char: '🤏', name: 'pinching hand' }, { char: '✌️', name: 'victory hand peace' }, { char: '🤞', name: 'crossed fingers' },
    { char: '🫰', name: 'hand index thumb crossed' }, { char: '🤟', name: 'love you gesture' }, { char: '🤘', name: 'sign horns rock' }, { char: '🤙', name: 'call me hand' },
    { char: '👈', name: 'backhand pointing left' }, { char: '👉', name: 'backhand pointing right' }, { char: '👆', name: 'backhand pointing up' }, { char: '🖕', name: 'middle finger' },
    { char: '👇', name: 'backhand pointing down' }, { char: '☝️', name: 'index pointing up' }, { char: '🫵', name: 'index pointing viewer' }, { char: '👍', name: 'thumbs up' },
    { char: '👎', name: 'thumbs down' }, { char: '✊', name: 'raised fist' }, { char: '👊', name: 'oncoming fist' }, { char: '🤛', name: 'left facing fist' },
    { char: '🤜', name: 'right facing fist' }, { char: '👏', name: 'clapping hands' }, { char: '🙌', name: 'raising hands' }, { char: '🫶', name: 'heart hands' },
    { char: '👐', name: 'open hands' }, { char: '🤲', name: 'palms up' }, { char: '🤝', name: 'handshake' }, { char: '🙏', name: 'folded hands pray' },
    { char: '✍️', name: 'writing hand' }, { char: '💅', name: 'nail polish' }, { char: '🤳', name: 'selfie' }, { char: '💪', name: 'flexed biceps muscle' },
    { char: '🦾', name: 'mechanical arm' }, { char: '🦿', name: 'mechanical leg' }, { char: '🦵', name: 'leg' }, { char: '🦶', name: 'foot' },
    { char: '👂', name: 'ear' }, { char: '🦻', name: 'ear hearing aid' }, { char: '👃', name: 'nose' }, { char: '🧠', name: 'brain' },
    { char: '🫀', name: 'anatomical heart' }, { char: '🫁', name: 'lungs' }, { char: '🦷', name: 'tooth' }, { char: '🦴', name: 'bone' },
    { char: '👀', name: 'eyes' }, { char: '👁️', name: 'eye' }, { char: '👅', name: 'tongue' }, { char: '👄', name: 'mouth' }, { char: '🫦', name: 'biting lip' },
    // Symbols & Objects
    { char: '💯', name: 'hundred points' }, { char: '💢', name: 'anger symbol' }, { char: '💥', name: 'collision' }, { char: '💫', name: 'dizzy' },
    { char: '💦', name: 'sweat droplets' }, { char: '💨', name: 'dashing away' }, { char: '🕳️', name: 'hole' }, { char: '💬', name: 'speech balloon' },
    { char: '👁️‍🗨️', name: 'eye speech bubble' }, { char: '🗨️', name: 'left speech bubble' }, { char: '🗯️', name: 'right anger bubble' }, { char: '💭', name: 'thought balloon' },
    { char: '💤', name: 'zzz sleeping' }, { char: '🔥', name: 'fire lit' }, { char: '✨', name: 'sparkles' }, { char: '⭐', name: 'star' },
    { char: '🌟', name: 'glowing star' }, { char: '💫', name: 'shooting star' }, { char: '⚡', name: 'high voltage lightning' }, { char: '💡', name: 'light bulb idea' },
    { char: '🎉', name: 'party popper' }, { char: '🎊', name: 'confetti ball' }, { char: '🎈', name: 'balloon' }, { char: '🏆', name: 'trophy' },
    { char: '🥇', name: 'first place medal' }, { char: '🥈', name: 'second place medal' }, { char: '🥉', name: 'third place medal' }, { char: '🏅', name: 'sports medal' },
    { char: '🎯', name: 'direct hit target' }, { char: '🎮', name: 'video game' }, { char: '🎲', name: 'game die' }, { char: '🎵', name: 'musical note' },
    { char: '🎶', name: 'musical notes' }, { char: '🎤', name: 'microphone' }, { char: '🎬', name: 'clapper board' }, { char: '📸', name: 'camera flash' },
    { char: '💎', name: 'gem stone diamond' }, { char: '💰', name: 'money bag' }, { char: '💵', name: 'dollar banknote' }, { char: '💳', name: 'credit card' },
    { char: '✅', name: 'check mark button' }, { char: '❌', name: 'cross mark' }, { char: '❓', name: 'question mark' }, { char: '❗', name: 'exclamation mark' },
    { char: '⚠️', name: 'warning' }, { char: '🚫', name: 'prohibited' }, { char: '🔴', name: 'red circle' }, { char: '🟢', name: 'green circle' },
    { char: '🔵', name: 'blue circle' }, { char: '🟡', name: 'yellow circle' }, { char: '🟠', name: 'orange circle' }, { char: '🟣', name: 'purple circle' },
    // Nature & Animals
    { char: '🐶', name: 'dog face' }, { char: '🐱', name: 'cat face' }, { char: '🐭', name: 'mouse face' }, { char: '🐹', name: 'hamster' },
    { char: '🐰', name: 'rabbit face' }, { char: '🦊', name: 'fox' }, { char: '🐻', name: 'bear' }, { char: '🐼', name: 'panda' },
    { char: '🐨', name: 'koala' }, { char: '🐯', name: 'tiger face' }, { char: '🦁', name: 'lion' }, { char: '🐮', name: 'cow face' },
    { char: '🐷', name: 'pig face' }, { char: '🐸', name: 'frog' }, { char: '🐵', name: 'monkey face' }, { char: '🦄', name: 'unicorn' },
    { char: '🐔', name: 'chicken' }, { char: '🐧', name: 'penguin' }, { char: '🐦', name: 'bird' }, { char: '🦅', name: 'eagle' },
    { char: '🦆', name: 'duck' }, { char: '🦉', name: 'owl' }, { char: '🦇', name: 'bat' }, { char: '🐺', name: 'wolf' },
    { char: '🐗', name: 'boar' }, { char: '🐴', name: 'horse face' }, { char: '🦋', name: 'butterfly' }, { char: '🐛', name: 'bug' },
    { char: '🐌', name: 'snail' }, { char: '🐝', name: 'honeybee' }, { char: '🐞', name: 'lady beetle' }, { char: '🦂', name: 'scorpion' },
    { char: '🐢', name: 'turtle' }, { char: '🐍', name: 'snake' }, { char: '🦎', name: 'lizard' }, { char: '🐙', name: 'octopus' },
    { char: '🦑', name: 'squid' }, { char: '🦐', name: 'shrimp' }, { char: '🦀', name: 'crab' }, { char: '🐠', name: 'tropical fish' },
    { char: '🐟', name: 'fish' }, { char: '🐬', name: 'dolphin' }, { char: '🐳', name: 'whale' }, { char: '🦈', name: 'shark' },
    { char: '🌸', name: 'cherry blossom' }, { char: '🌹', name: 'rose' }, { char: '🌺', name: 'hibiscus' }, { char: '🌻', name: 'sunflower' },
    { char: '🌼', name: 'blossom' }, { char: '🌷', name: 'tulip' }, { char: '🌱', name: 'seedling' }, { char: '🌲', name: 'evergreen tree' },
    { char: '🌳', name: 'deciduous tree' }, { char: '🌴', name: 'palm tree' }, { char: '🌵', name: 'cactus' }, { char: '🍀', name: 'four leaf clover' },
    { char: '🍁', name: 'maple leaf' }, { char: '🍂', name: 'fallen leaf' }, { char: '🍃', name: 'leaf fluttering' },
    // Food & Drink
    { char: '🍎', name: 'red apple' }, { char: '🍊', name: 'tangerine' }, { char: '🍋', name: 'lemon' }, { char: '🍌', name: 'banana' },
    { char: '🍉', name: 'watermelon' }, { char: '🍇', name: 'grapes' }, { char: '🍓', name: 'strawberry' }, { char: '🍒', name: 'cherries' },
    { char: '🍑', name: 'peach' }, { char: '🥭', name: 'mango' }, { char: '🍍', name: 'pineapple' }, { char: '🥥', name: 'coconut' },
    { char: '🥝', name: 'kiwi' }, { char: '🍅', name: 'tomato' }, { char: '🥑', name: 'avocado' }, { char: '🥕', name: 'carrot' },
    { char: '🌽', name: 'corn' }, { char: '🥔', name: 'potato' }, { char: '🍕', name: 'pizza' }, { char: '🍔', name: 'hamburger' },
    { char: '🍟', name: 'french fries' }, { char: '🌭', name: 'hot dog' }, { char: '🥪', name: 'sandwich' }, { char: '🌮', name: 'taco' },
    { char: '🌯', name: 'burrito' }, { char: '🍿', name: 'popcorn' }, { char: '🧁', name: 'cupcake' }, { char: '🎂', name: 'birthday cake' },
    { char: '🍰', name: 'cake' }, { char: '🍫', name: 'chocolate bar' }, { char: '🍬', name: 'candy' }, { char: '🍭', name: 'lollipop' },
    { char: '🍩', name: 'doughnut' }, { char: '🍪', name: 'cookie' }, { char: '🥤', name: 'cup straw' }, { char: '☕', name: 'coffee' },
    { char: '🫖', name: 'teapot' }, { char: '🍵', name: 'teacup' }, { char: '🍺', name: 'beer mug' }, { char: '🍻', name: 'clinking beer' },
    { char: '🥂', name: 'clinking glasses' }, { char: '🍷', name: 'wine glass' }, { char: '🥃', name: 'tumbler glass' }, { char: '🍸', name: 'cocktail glass' },
    // Travel & Places
    { char: '🚀', name: 'rocket' }, { char: '✈️', name: 'airplane' }, { char: '🚁', name: 'helicopter' }, { char: '🚂', name: 'locomotive' },
    { char: '🚗', name: 'automobile car' }, { char: '🚕', name: 'taxi' }, { char: '🚌', name: 'bus' }, { char: '🏎️', name: 'racing car' },
    { char: '🚲', name: 'bicycle' }, { char: '🛵', name: 'motor scooter' }, { char: '🚤', name: 'speedboat' }, { char: '⛵', name: 'sailboat' },
    { char: '🏠', name: 'house' }, { char: '🏢', name: 'office building' }, { char: '🏰', name: 'castle' }, { char: '🗼', name: 'tokyo tower' },
    { char: '🗽', name: 'statue liberty' }, { char: '⛰️', name: 'mountain' }, { char: '🏔️', name: 'snow capped mountain' }, { char: '🌋', name: 'volcano' },
    { char: '🏝️', name: 'desert island' }, { char: '🏖️', name: 'beach umbrella' }, { char: '🌅', name: 'sunrise' }, { char: '🌄', name: 'sunrise mountains' },
    { char: '🌃', name: 'night stars' }, { char: '🌉', name: 'bridge night' }, { char: '🌌', name: 'milky way' },
    // Weather
    { char: '☀️', name: 'sun' }, { char: '🌤️', name: 'sun small cloud' }, { char: '⛅', name: 'sun behind cloud' }, { char: '🌥️', name: 'sun behind large cloud' },
    { char: '🌦️', name: 'sun behind rain cloud' }, { char: '🌧️', name: 'cloud rain' }, { char: '⛈️', name: 'cloud lightning rain' }, { char: '🌩️', name: 'cloud lightning' },
    { char: '🌨️', name: 'cloud snow' }, { char: '❄️', name: 'snowflake' }, { char: '☃️', name: 'snowman' }, { char: '⛄', name: 'snowman without snow' },
    { char: '🌬️', name: 'wind face' }, { char: '💨', name: 'dash' }, { char: '🌀', name: 'cyclone' }, { char: '🌈', name: 'rainbow' },
    { char: '🌙', name: 'crescent moon' }, { char: '🌛', name: 'first quarter moon face' }, { char: '🌜', name: 'last quarter moon face' }, { char: '⭐', name: 'star' },
    { char: '🌟', name: 'glowing star' }, { char: '💫', name: 'shooting star dizzy' }, { char: '☄️', name: 'comet' },
    // Flags (Popular)
    { char: '🏳️', name: 'white flag' }, { char: '🏴', name: 'black flag' }, { char: '🚩', name: 'triangular flag' }, { char: '🏁', name: 'chequered flag' },
    { char: '🇺🇸', name: 'usa flag' }, { char: '🇬🇧', name: 'uk flag' }, { char: '🇫🇷', name: 'france flag' }, { char: '🇩🇪', name: 'germany flag' },
    { char: '🇯🇵', name: 'japan flag' }, { char: '🇨🇳', name: 'china flag' }, { char: '🇮🇳', name: 'india flag' }, { char: '🇧🇷', name: 'brazil flag' },
    { char: '🇷🇺', name: 'russia flag' }, { char: '🇰🇷', name: 'south korea flag' }, { char: '🇪🇸', name: 'spain flag' }, { char: '🇮🇹', name: 'italy flag' }
];

window.openPostEmojiPicker = function (e) {
    e.stopPropagation();
    const picker = document.getElementById('postEmojiPicker');
    const isHidden = picker.style.display === 'none';
    closeAllPickers();
    if (isHidden) {
        picker.style.display = 'block';
        renderEmojiGrid(picker.querySelector('.emoji-grid'), 'post');
    }
};

window.openCommentEmojiPicker = function (e, commentId, btn) {
    e.stopPropagation();
    const picker = document.getElementById(`commentPicker-${commentId}`);
    const isHidden = picker.style.display === 'none';
    closeAllPickers();

    if (isHidden) {
        // Smart positioning: Check if there's space below (approx 350px for picker)
        const rect = btn.getBoundingClientRect();
        const spaceBelow = window.innerHeight - rect.bottom;

        if (spaceBelow < 300) {
            picker.style.bottom = '40px';
            picker.style.top = 'auto';
        } else {
            picker.style.top = '40px';
            picker.style.bottom = 'auto';
        }

        picker.style.display = 'block';
        renderEmojiGrid(picker.querySelector('.emoji-grid'), 'comment', commentId);
    }
};

function renderEmojiGrid(grid, context, id = null) {
    grid.innerHTML = allEmojis.map(e => `
                <div class="emoji-item" onclick="handleEmojiReaction('${e.char}', '${context}', ${id})">
                    ${e.char}
                </div>
            `).join('');
}

window.handleEmojiReaction = function (char, context, id) {
    if (context === 'post') {
        reactToPost(char);
    } else {
        reactToComment(id, char);
    }
    closeAllPickers();
};

window.filterEmojis = function (input, context, id = null) {
    const query = input.value.toLowerCase();
    const filtered = allEmojis.filter(e => e.name.toLowerCase().includes(query));
    const grid = input.parentElement.nextElementSibling;
    grid.innerHTML = filtered.map(e => `
                <div class="emoji-item" onclick="handleEmojiReaction('${e.char}', '${context}', ${id})">
                    ${e.char}
                </div>
            `).join('');
};

window.toggleCommentDropdown = function (e, id) {
    e.stopPropagation();
    const dropdown = document.getElementById(`dropdown-${id}`);
    const isVisible = dropdown.classList.contains('active');

    // Close all other dropdowns
    document.querySelectorAll('.comment-dropdown-menu').forEach(d => d.classList.remove('active'));

    if (!isVisible) {
        dropdown.classList.add('active');
    }
};

window.reportComment = function (id) {
    document.querySelectorAll('.comment-dropdown-menu').forEach(d => d.classList.remove('active'));
};

window.copyCommentText = function (id) {
    const text = document.getElementById(`comment-body-${id}`).innerText;
    navigator.clipboard.writeText(text);
    document.querySelectorAll('.comment-dropdown-menu').forEach(d => d.classList.remove('active'));
};

window.hideComment = function (id, el) {
    const commentItem = el.closest('.full-comment-item') || el.closest('.reply-item');
    commentItem.style.opacity = '0.3';
    commentItem.style.filter = 'blur(2px)';
    commentItem.style.pointerEvents = 'none';
};

window.editComment = async function (id) {
    document.querySelectorAll('.comment-dropdown-menu').forEach(d => d.classList.remove('active'));

    const body = document.getElementById(`comment-body-${id}`);
    if (!body) return;

    const oldText = body.innerText;

    // Check if already editing
    if (body.querySelector('.edit-comment-input')) return;

    // Create inline edit UI
    body.innerHTML = `
        <div class="edit-comment-wrapper" style="display: flex; flex-direction: column; gap: 8px; width: 100%;">
            <textarea class="edit-comment-input" style="
                width: 100%;
                min-height: 60px;
                padding: 10px 12px;
                background: rgba(255,255,255,0.05);
                border: 1px solid rgba(255,255,255,0.15);
                border-radius: 8px;
                color: #fff;
                font-size: 14px;
                font-family: inherit;
                resize: none;
                outline: none;
            ">${escapeHtml(oldText)}</textarea>
            <div class="edit-comment-actions" style="display: flex; gap: 8px; justify-content: flex-end;">
                <button class="edit-cancel-btn" style="
                    padding: 6px 14px;
                    background: rgba(255,255,255,0.1);
                    border: none;
                    border-radius: 6px;
                    color: #aaa;
                    font-size: 12px;
                    cursor: pointer;
                ">Cancel</button>
                <button class="edit-save-btn" style="
                    padding: 6px 14px;
                    background: linear-gradient(135deg, #3ea6ff, #9d4edd);
                    border: none;
                    border-radius: 6px;
                    color: #fff;
                    font-size: 12px;
                    font-weight: 600;
                    cursor: pointer;
                ">Save</button>
            </div>
        </div>
    `;

    const textarea = body.querySelector('.edit-comment-input');
    const cancelBtn = body.querySelector('.edit-cancel-btn');
    const saveBtn = body.querySelector('.edit-save-btn');

    textarea.focus();
    textarea.setSelectionRange(textarea.value.length, textarea.value.length);

    // Cancel handler
    cancelBtn.onclick = () => {
        body.innerText = oldText;
    };

    // Save handler
    saveBtn.onclick = async () => {
        const newText = textarea.value.trim();
        if (!newText || newText === oldText) {
            body.innerText = oldText;
            return;
        }

        saveBtn.disabled = true;
        saveBtn.textContent = 'Saving...';

        try {
            const res = await fetch('../backend/editPostComment.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ comment_id: id, comment: newText })
            });
            const data = await res.json();
            if (data.success) {
                body.innerText = newText;
            } else {
                console.warn("Edit failed:", data.message);
                body.innerText = oldText;
            }
        } catch (e) {
            console.error("Edit error:", e);
            body.innerText = oldText;
        }
    };
};


window.deleteComment = async function (id) {
    document.querySelectorAll('.comment-dropdown-menu').forEach(d => d.classList.remove('active'));

    // Delete silently as requested - NO POPUPS
    try {
        // Optimistic UI update: Fade out immediately
        const commentEl = document.getElementById(`comment-body-${id}`)?.closest('.full-comment-item')
            || document.getElementById(`comment-body-${id}`)?.closest('.reply-item');
        if (commentEl) {
            commentEl.style.transition = 'opacity 0.3s, transform 0.3s';
            commentEl.style.opacity = '0';
            commentEl.style.transform = 'scale(0.95)';
            setTimeout(() => commentEl.style.display = 'none', 300);
        }

        const res = await fetch('../backend/deletePostComment.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ comment_id: id })
        });
        const data = await res.json();
        if (data.success) {
            loadComments();
        } else {
            console.warn("Delete failed:", data.message);
            // Revert if failed
            if (commentEl) {
                commentEl.style.display = '';
                setTimeout(() => {
                    commentEl.style.opacity = '1';
                    commentEl.style.transform = 'none';
                }, 10);
            }
        }
    } catch (e) {
        console.error("Delete error:", e);
    }
};

function closeAllPickers() {
    document.querySelectorAll('.emoji-picker-panel').forEach(p => p.style.display = 'none');
    document.querySelectorAll('.comment-dropdown-menu').forEach(d => d.classList.remove('active'));
}

document.addEventListener('click', (e) => {
    if (!e.target.closest('.emoji-picker-panel') && !e.target.closest('.add-reaction-trigger') && !e.target.closest('.comment-menu-container')) {
        closeAllPickers();
    }
});
