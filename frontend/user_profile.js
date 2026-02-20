// ============================================
// USER PROFILE PAGE FUNCTIONALITY
// ============================================
let profileUser = null;
let profileVideos = [];

document.addEventListener('DOMContentLoaded', () => {
    loadUserProfile();
    setupEventListeners();
});

async function loadUserProfile() {
    const loadingSpinner = document.getElementById('loadingSpinner');
    const profileContent = document.getElementById('profileContent');

    try {
        if (loadingSpinner) loadingSpinner.style.display = 'block';
        if (profileContent) profileContent.style.display = 'none';

        const url = profileUserId > 0
            ? `../backend/getUserProfile.php?user_id=${profileUserId}`
            : `../backend/getUserProfile.php?username=${encodeURIComponent(profileUsername)}`;

        const response = await fetch(url);
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);

        const data = await response.json();
        if (data.success) {
            profileUser = data.user;
            profileVideos = data.videos || [];
            displayProfile(data.user, data.videos);
        } else {
            throw new Error(data.message || 'User not found');
        }
    } catch (error) {
        console.error('Error loading profile:', error);
        if (loadingSpinner) {
            loadingSpinner.innerHTML = `
                <div style="text-align: center; padding: 40px;">
                    <p style="color: var(--error-color, #ff4444); margin-bottom: 16px;">Error loading profile: ${error.message}</p>
                    <a href="home.php" style="color: var(--accent-color, #3ea6ff); text-decoration: none; font-weight: 500;">Go back to home</a>
                </div>
            `;
        }
    } finally {
        if (loadingSpinner) loadingSpinner.style.display = 'none';
        if (profileContent) profileContent.style.display = 'block';
    }
}

function displayProfile(user, videos) {
    const profileHeaderAvatar = document.getElementById('profileHeaderAvatar');
    const profileDisplayName = document.getElementById('profileDisplayName');
    const profileBio = document.getElementById('profileBio');
    const profileBanner = document.getElementById('profileBanner');
    const profileContent = document.getElementById('profileContent');

    // Banner
    if (user.banner_url) {
        profileBanner.style.backgroundImage = `url('${user.banner_url}')`;
        profileBanner.classList.add('has-banner');
        profileContent.classList.add('with-banner');
    } else {
        profileBanner.style.backgroundImage = 'none';
        profileBanner.classList.remove('has-banner');
        profileContent.classList.remove('with-banner');
    }

    // Avatar
    if (user.profile_picture) {
        profileHeaderAvatar.innerHTML = `
            <img src="${escapeHtml(user.profile_picture)}" alt="${escapeHtml(user.username)}" 
                 onload="this.style.opacity='1'"
                 onerror="this.style.display='none'; document.getElementById('mainAvatarFallback').style.display='flex';">
            <div class="avatar-placeholder" id="mainAvatarFallback" style="display: none;">
                ${user.username.charAt(0).toUpperCase()}
            </div>
        `;
    } else {
        profileHeaderAvatar.innerHTML = `<div class="avatar-placeholder">${user.username.charAt(0).toUpperCase()}</div>`;
    }

    // Badge Logic
    let badgeHtml = '';
    if (user.is_pro && user.comment_badge) {
        if (user.comment_badge === 'pro') {
            badgeHtml = `<span class="comment-author-badge pro-svg" style="margin-left: 10px; width: 26px; height: 20px; display: inline-flex;" title="Pro Member"><svg width="100%" height="100%" viewBox="0 0 340 96" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M87.5524 2.5625H121.74C128.865 2.5625 134.886 3.72917 139.802 6.0625C144.761 8.39583 148.532 11.6667 151.115 15.875C153.698 20.0833 154.99 25 154.99 30.625C154.99 38.2917 153.282 44.9375 149.865 50.5625C146.49 56.1458 141.657 60.4792 135.365 63.5625C129.073 66.6042 121.615 68.125 112.99 68.125H100.177L94.9274 92.75H68.4899L87.5524 2.5625ZM109.802 22.5625L104.24 48.5H113.177C116.261 48.5 118.948 47.8333 121.24 46.5C123.532 45.125 125.302 43.2708 126.552 40.9375C127.802 38.5625 128.427 35.9167 128.427 33C128.427 29.4167 127.427 26.7917 125.427 25.125C123.427 23.4167 120.532 22.5625 116.74 22.5625H109.802ZM153.24 92.75L172.427 2.5625H212.99C219.323 2.5625 224.698 3.75 229.115 6.125C233.573 8.45833 236.969 11.6458 239.302 15.6875C241.636 19.7292 242.802 24.3333 242.802 29.5C242.802 33.75 241.99 37.875 240.365 41.875C238.74 45.8333 236.344 49.3958 233.177 52.5625C230.052 55.7292 226.198 58.2083 221.615 60L231.615 92.75H203.99L195.49 62.5H196.115H186.177L179.74 92.75H153.24ZM194.802 22.125L189.99 44.375H201.802C204.761 44.375 207.323 43.8333 209.49 42.75C211.657 41.6667 213.323 40.1667 214.49 38.25C215.657 36.3333 216.24 34.1458 216.24 31.6875C216.24 28.4792 215.261 26.0833 213.302 24.5C211.386 22.9167 208.594 22.125 204.927 22.125H194.802ZM296.427 22.125C293.386 22.125 290.511 22.9583 287.802 24.625C285.136 26.2917 282.761 28.6042 280.677 31.5625C278.594 34.5208 276.948 37.9375 275.74 41.8125C274.573 45.6875 273.99 54.3125C273.99 58.1042 274.615 61.4167 275.865 64.25C277.115 67.0833 278.865 69.2917 281.115 70.875C283.365 72.4167 286.011 73.1875 289.052 73.1875C292.094 73.1875 294.969 72.3542 297.677 70.6875C300.386 69.0208 302.782 66.7083 304.865 63.75C306.948 60.7917 308.573 57.375 309.74 53.5C310.948 49.5833 311.552 45.3958 311.552 40.9375C311.552 37.1042 310.927 33.7917 309.677 31C308.427 28.1667 306.657 25.9792 304.365 24.4375C302.115 22.8958 299.469 22.125 296.427 22.125ZM288.24 94.3125C279.657 94.3125 272.282 92.6458 266.115 89.3125C259.99 85.9375 255.282 81.3542 251.99 75.5625C248.74 69.7708 247.115 63.2292 247.115 55.9375C247.115 47.6875 248.407 40.1875 250.99 33.4375C253.573 26.6875 257.136 20.8958 261.677 16.0625C266.261 11.2292 271.573 7.52083 277.615 4.9375C283.657 2.3125 290.136 1 297.052 1C305.886 1 313.365 2.6875 319.49 6.0625C325.657 9.4375 330.344 14.0208 333.552 19.8125C336.802 25.5625 338.427 32.0833 338.427 39.375C338.427 47.6667 337.115 55.1875 334.49 61.9375C331.907 68.6875 328.302 74.4792 323.677 79.3125C319.094 84.1458 313.761 87.8542 307.677 90.4375C301.636 93.0208 295.157 94.3125 288.24 94.3125Z" fill="white"/></svg></span>`;
        } else if (user.comment_badge === 'crown') {
            badgeHtml = `<span class="comment-author-badge crown" style="margin-left: 10px;" title="Crown"><i class="fa-solid fa-crown" style="color: #ffd700; font-size: 18px;"></i></span>`;
        } else if (user.comment_badge === 'bolt') {
            badgeHtml = `<span class="comment-author-badge bolt" style="margin-left: 10px;" title="Electricity"><i class="fa-solid fa-bolt" style="color: #ffeb3b; font-size: 18px;"></i></span>`;
        } else if (user.comment_badge === 'verified') {
            badgeHtml = `<span class="comment-author-badge verified" style="margin-left: 10px;" title="Verified"><i class="fa-solid fa-check-double" style="color: #3ea6ff; font-size: 18px;"></i></span>`;
        }
    }

    profileDisplayName.innerHTML = escapeHtml(user.username) + badgeHtml;

    // Sub Info
    const profileHandle = document.getElementById('profileHandle');
    const profileSubCount = document.getElementById('profileSubCount');
    const profileVideoCount = document.getElementById('profileVideoCount');

    if (profileHandle) profileHandle.textContent = '@' + user.username;
    if (profileSubCount) profileSubCount.textContent = formatSubscribers(user.subscriber_count || 0) + ' subscribers';
    if (profileVideoCount) profileVideoCount.textContent = (videos.length || 0) + ' videos';

    // Bio
    profileBio.textContent = user.bio || (user.is_private ? 'This account is private.' : 'No bio yet.');

    // About Section
    const abJoin = document.getElementById('aboutJoinDate');
    const abViews = document.getElementById('aboutTotalViews');
    const abBio = document.getElementById('aboutFullBio');
    const joinDate = new Date(user.created_at);
    const joinStr = joinDate.toLocaleDateString('en-US', { year: 'numeric', month: 'long' });

    if (abJoin) abJoin.textContent = joinStr;
    if (abViews) abViews.textContent = formatViews(videos.reduce((acc, v) => acc + (v.views || 0), 0)) + ' views';
    if (abBio) abBio.textContent = user.bio || 'No channel description provided.';

    // Action Button
    const headerActions = document.getElementById('headerActions');
    if (headerActions) {
        headerActions.innerHTML = '';
        const btn = document.createElement('button');
        if (user.is_owner) {
            btn.className = 'edit-profile-btn';
            btn.textContent = 'Edit Profile';
            btn.onclick = () => window.location.href = 'settings.php';
        } else {
            btn.className = user.is_subscribed ? 'unsubscribe-btn' : 'subscribe-btn';
            btn.textContent = user.is_subscribed ? 'Subscribed' : 'Subscribe';
            btn.onclick = () => toggleSubscription(user.id);
        }
        headerActions.appendChild(btn);
    }

    // Tabs
    const tabBtns = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.profile-tab-content');
    tabBtns.forEach(btn => {
        btn.onclick = () => {
            const target = btn.dataset.tab;
            tabBtns.forEach(b => b.classList.remove('active'));
            tabContents.forEach(c => c.classList.remove('active'));
            btn.classList.add('active');
            const targetEl = document.getElementById(target + 'Tab');
            if (targetEl) targetEl.classList.add('active');
            if (target === 'clips') loadProfileClips(user.id);
        };
    });

    // Chips
    const chips = document.querySelectorAll('.chip-btn');
    chips.forEach(c => {
        c.onclick = () => {
            chips.forEach(x => x.classList.remove('active'));
            c.classList.add('active');
            const sort = c.dataset.sort;
            let sorted = [...videos];
            if (sort === 'popular') sorted.sort((a, b) => b.views - a.views);
            else if (sort === 'oldest') sorted.sort((a, b) => new Date(a.created_at) - new Date(b.created_at));
            else sorted.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
            renderVideos(sorted);
        };
    });

    // Initial Video Load
    if (user.is_private) {
        const grid = document.getElementById('profileVideosGrid');
        if (grid) grid.innerHTML = `<div class="private-profile-message">This account is private</div>`;
    } else {
        renderVideos(videos);
    }
}

function renderVideos(vids) {
    const grid = document.getElementById('profileVideosGrid');
    const empty = document.getElementById('emptyVideos');
    if (!grid) return;

    if (vids.length === 0) {
        grid.innerHTML = '';
        if (empty) empty.style.display = 'block';
        return;
    }

    if (empty) empty.style.display = 'none';
    grid.innerHTML = vids.map(video => `
        <div class="video-card" onclick="window.location.href='videoid.php?id=${video.id}'">
            <div class="video-thumbnail">
                <img src="${video.thumbnail_url || 'data:image/svg+xml,%3Csvg xmlns=\"http://www.w3.org/2000/svg\" width=\"320\" height=\"180\"%3E%3Crect fill=\"%231a1a1a\" width=\"320\" height=\"180\"/%3E%3Ctext fill=\"%23ffffff\" font-family=\"Arial,sans-serif\" font-size=\"18\" x=\"50%25\" y=\"50%25\" text-anchor=\"middle\" dominant-baseline=\"middle\"%3ENo Thumbnail%3C/text%3E%3C/svg%3E'}" 
                     alt="${escapeHtml(video.title)}" loading="lazy">
            </div>
            <div class="video-info">
                <div class="video-details">
                    <div class="video-title" title="${escapeHtml(video.title)}">${escapeHtml(video.title)}</div>
                    <div class="video-stats">
                        <span>${formatViews(video.views)} views</span>
                        <span class="dot-separator">•</span>
                        <span>${formatTimeAgo(video.created_at)}</span>
                    </div>
                </div>
            </div>
        </div>
    `).join('');
}

async function loadProfileClips(userId) {
    const grid = document.getElementById('profileClipsGrid');
    const empty = document.getElementById('emptyClips');
    try {
        const res = await fetch(`../backend/getClips.php?user_id=${userId}`);
        const data = await res.json();
        if (data.success && data.clips && data.clips.length > 0) {
            if (empty) empty.style.display = 'none';
            grid.innerHTML = data.clips.map(c => `
                <div class="clip-card" onclick="window.location.href='clips.php?id=${c.id}'">
                    <img src="${c.thumbnail_url || '../assets/clip-placeholder.jpg'}" style="width:100%; height:100%; object-fit:cover;">
                    <div class="clip-overlay">
                        <div class="clip-title">${escapeHtml(c.title)}</div>
                        <div class="clip-views">${formatViews(c.views)} views</div>
                    </div>
                </div>
            `).join('');
        } else {
            grid.innerHTML = '';
            if (empty) empty.style.display = 'block';
        }
    } catch (e) {
        console.error('Clips error:', e);
        if (empty) empty.style.display = 'block';
    }
}

async function toggleSubscription(cid) {
    try {
        const res = await fetch('../backend/subscribe.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ channel_id: cid })
        });
        const d = await res.json();
        if (d.success) loadUserProfile();
    } catch (e) {
        console.error('Sub error:', e);
    }
}

function setupEventListeners() {
    const searchBtn = document.getElementById('searchBtn');
    const searchInput = document.getElementById('searchInput');

    function performSearch() {
        const q = searchInput?.value.trim();
        if (q) window.location.href = `home.php?search=${encodeURIComponent(q)}`;
    }

    if (searchBtn) searchBtn.onclick = performSearch;
    if (searchInput) {
        searchInput.onkeypress = (e) => { if (e.key === 'Enter') performSearch(); };
    }

    const accountBtn = document.getElementById('accountBtn');
    const accountDropdown = document.getElementById('accountDropdown');
    if (accountBtn && accountDropdown) {
        accountBtn.onclick = (e) => {
            e.stopPropagation();
            accountDropdown.classList.toggle('active');
        };
        document.onclick = (e) => {
            if (!accountBtn.contains(e.target) && !accountDropdown.contains(e.target)) {
                accountDropdown.classList.remove('active');
            }
        };
    }
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatViews(count) {
    if (count >= 1000000) return (count / 1000000).toFixed(1) + 'M';
    if (count >= 1000) return (count / 1000).toFixed(1) + 'K';
    return count.toString();
}

function formatSubscribers(count) {
    return formatViews(count);
}

function formatTimeAgo(dateString) {
    const now = new Date();
    const date = new Date(dateString);
    const diff = Math.floor((now - date) / 1000);
    if (diff < 60) return 'just now';
    if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
    if (diff < 2592000) return Math.floor(diff / 86400) + 'd ago';
    if (diff < 31536000) return Math.floor(diff / 2592000) + 'mo ago';
    return Math.floor(diff / 31536000) + 'y ago';
}
