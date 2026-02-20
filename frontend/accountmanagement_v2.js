// ============================================
// FLOXWATCH STUDIO & ACCOUNT MANAGEMENT - v2.1
// ============================================
let currentUser = null;
let allContent = [];
let allPosts = [];
let allPolls = [];

// --- NAVIGATION & TABS ---

window.setupNavigation = function () {
    // Main Navbar Navigation
    const navTabs = document.querySelectorAll('.nav-tab[data-sector]');
    navTabs.forEach(btn => {
        btn.addEventListener('click', () => {
            const sectorId = btn.dataset.sector;
            navTabs.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            document.querySelectorAll('.content-sector').forEach(s => {
                s.style.display = 'none';
                s.classList.remove('active');
            });

            const targetSector = document.getElementById('sector-' + sectorId);
            if (targetSector) {
                targetSector.style.display = 'block';
                setTimeout(() => targetSector.classList.add('active'), 10);
            }
        });
    });

    // Search Logic
    const searchInput = document.getElementById('studioSearchInput');
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            filterState.query = e.target.value.toLowerCase().trim();
            applyFilters();
        });
    }

    // Filter Dropdown Logic
    const filterToggle = document.getElementById('studioFilterToggle');
    const filterMenu = document.getElementById('studioFilterMenu');

    if (filterToggle && filterMenu) {
        filterToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            const isVisible = filterMenu.style.display === 'block';
            filterMenu.style.display = isVisible ? 'none' : 'block';
            filterToggle.classList.toggle('active', !isVisible);
        });

        // Close when clicking outside
        const closeMenu = (e) => {
            if (!filterToggle.contains(e.target) && !filterMenu.contains(e.target)) {
                filterMenu.style.display = 'none';
                filterToggle.classList.remove('active');
            }
        };
        document.addEventListener('click', closeMenu);

        // Filter Options
        filterMenu.querySelectorAll('.dropdown-item').forEach(item => {
            item.addEventListener('click', () => {
                const sortType = item.dataset.sort;

                // Update active state
                filterMenu.querySelectorAll('.dropdown-item').forEach(i => i.classList.remove('active'));
                item.classList.add('active');

                // Update State and Apply
                filterState.sort = sortType;
                applyFilters();
            });
        });
    }

    // Subscription Tabs (Legacy/Internal)
    const subTabs = document.querySelectorAll('.subs-tab-btn');
    if (subTabs.length > 0) {
        subTabs.forEach(btn => {
            btn.addEventListener('click', () => {
                const tab = btn.dataset.tab;
                subTabs.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');

                document.querySelectorAll('.subs-view').forEach(v => v.style.display = 'none');
                const target = document.getElementById(tab + 'TabContent');
                if (target) target.style.display = 'block';
            });
        });
    }
}

// Global Filter State
let filterState = {
    query: '',
    sort: 'newest'
};

window.applyFilters = function () {
    // Filter Videos/Clips
    let filteredVideos = [...allContent];
    if (filterState.query) {
        filteredVideos = filteredVideos.filter(item =>
            (item.title && item.title.toLowerCase().includes(filterState.query)) ||
            (item.description && item.description.toLowerCase().includes(filterState.query))
        );
    }
    sortItems(filteredVideos);
    processAndRenderContent(filteredVideos);

    // Filter Posts
    let filteredPosts = [...allPosts];
    if (filterState.query) {
        filteredPosts = filteredPosts.filter(post =>
            (post.content && post.content.toLowerCase().includes(filterState.query))
        );
    }
    sortItems(filteredPosts);
    renderPostList(filteredPosts, 'postsGrid', 'postsEmpty');
}

function sortItems(items) {
    items.sort((a, b) => {
        switch (filterState.sort) {
            case 'newest':
                return new Date(b.created_at || 0) - new Date(a.created_at || 0);
            case 'oldest':
                return new Date(a.created_at || 0) - new Date(b.created_at || 0);
            case 'views_desc':
                return (parseInt(b.views || b.likes || 0)) - (parseInt(a.views || a.likes || 0));
            case 'views_asc':
                return (parseInt(a.views || a.likes || 0)) - (parseInt(b.views || b.likes || 0));
            case 'likes_desc':
                return (parseInt(b.likes || 0)) - (parseInt(a.likes || 0));
            case 'likes_asc':
                return (parseInt(a.likes || 0)) - (parseInt(b.likes || 0));
            default:
                return 0;
        }
    });
}

window.loadPosts = async function () {
    const postsLoading = document.getElementById('postsLoading');
    try {
        const data = await floxFetch('../backend/getMyPosts.php');
        allPosts = data.posts || [];
        renderPostList(allPosts, 'postsGrid', 'postsEmpty');
    } catch (error) {
        console.warn('Posts could not be loaded:', error.message || error);
    } finally {
        if (postsLoading) postsLoading.style.display = 'none';
    }
}

function renderPostList(posts, containerId, emptyId) {
    const container = document.getElementById(containerId);
    const emptyState = document.getElementById(emptyId);
    if (!container) return;

    container.innerHTML = '';
    if (posts.length === 0) {
        if (emptyState) emptyState.style.display = 'flex';
        return;
    }

    if (emptyState) emptyState.style.display = 'none';

    container.innerHTML = posts.map((post, index) => `
        <div class="my-post-card" style="animation-delay: ${index * 0.05}s; cursor:pointer;" onclick="if(!event.target.closest('.meta-item') && !event.target.closest('.my-post-actions')) window.location.href='view_post.php?id=${post.id}'">
            <div class="my-post-content">${escapeHtml(post.content)}</div>
            <div class="my-post-meta">
                <div class="meta-item like-btn" onclick="likePost(${post.id}, this)" style="cursor:pointer;">
                    <i class="fa-solid fa-heart ${post.is_liked ? 'active' : ''}"></i> <span class="like-count">${formatMetric(post.likes)}</span>
                </div>
                <div class="meta-item"><i class="fa-solid fa-comment"></i> ${formatMetric(post.comments)}</div>
                <div class="meta-item"><i class="fa-solid fa-calendar"></i> ${formatTimeAgo(post.created_at)}</div>
            </div>
            <div class="my-post-actions">
                <button class="action-btn delete-btn" onclick="deletePost(${post.id})">
                    <i class="fa-solid fa-trash"></i> Delete
                </button>
            </div>
        </div>
    `).join('');
}

window.likePost = async function (postId, el) {
    const icon = el.querySelector('i');
    const label = el.querySelector('.like-count');

    // Optimistic UI
    icon.classList.toggle('active');
    icon.style.transform = 'scale(1.3)';
    setTimeout(() => icon.style.transform = 'scale(1)', 200);

    try {
        const data = await floxFetch('../backend/likePost.php', {
            method: 'POST',
            body: { post_id: postId }
        });
        label.textContent = formatMetric(data.likes);
        if (data.status === 'liked') icon.classList.add('active');
        else icon.classList.remove('active');
    } catch (e) {
        console.warn('Like failed:', e.message || e);
        // Revert UI on failure
        icon.classList.toggle('active');
    }
};

window.deletePost = async function (id) {
    if (confirm("Delete this post permanently?")) {
        try {
            await floxFetch('../backend/deletePost.php', {
                method: 'POST',
                body: { post_id: id }
            });
            loadPosts();
        } catch (e) {
            console.error('Delete failed:', e.message || e);
        }
    }
};

window.loadPolls = async function () {
    const pollsLoading = document.getElementById('pollsLoading');
    try {
        const data = await floxFetch('../backend/getMyPolls.php');
        allPolls = data.polls || [];
        renderPollList(allPolls, 'pollsGrid', 'pollsEmpty');
    } catch (error) {
        console.warn('Polls could not be loaded:', error.message || error);
    } finally {
        if (pollsLoading) pollsLoading.style.display = 'none';
    }
}

function renderPollList(polls, containerId, emptyId) {
    const container = document.getElementById(containerId);
    const emptyState = document.getElementById(emptyId);
    if (!container) return;

    container.innerHTML = '';
    if (polls.length === 0) {
        if (emptyState) emptyState.style.display = 'flex';
        return;
    }

    if (emptyState) emptyState.style.display = 'none';

    container.innerHTML = polls.map((poll, index) => {
        const total = parseInt(poll.total_votes) || 0;
        return `
            <div class="my-poll-card" style="animation-delay: ${index * 0.05}s">
                <div class="my-poll-question">${escapeHtml(poll.question)}</div>
                <div class="my-poll-options">
                    ${poll.options.map(opt => {
            const count = parseInt(opt.votes) || 0;
            const pct = total > 0 ? Math.round((count / total) * 100) : 0;
            return `
                            <div class="my-poll-opt">
                                <div class="opt-label">
                                    <span>${escapeHtml(opt.option_text)}</span>
                                    <span>${pct}%</span>
                                </div>
                                <div class="opt-bar-bg">
                                    <div class="opt-bar-fill" style="width: ${pct}%"></div>
                                </div>
                            </div>
                        `;
        }).join('')}
                </div>
                <div class="my-post-meta">
                    <div class="meta-item"><i class="fa-solid fa-check-to-slot"></i> ${formatMetric(total)} votes</div>
                    <div class="meta-item"><i class="fa-solid fa-calendar"></i> ${formatTimeAgo(poll.created_at)}</div>
                </div>
                <div class="my-post-actions">
                    <button class="action-btn delete-btn" onclick="deletePoll(${poll.id})">
                        <i class="fa-solid fa-trash"></i> Delete
                    </button>
                </div>
            </div>
        `;
    }).join('');
}

window.deletePoll = async function (id) {
    if (confirm("Delete this poll permanently?")) {
        try {
            await floxFetch('../backend/deletePoll.php', {
                method: 'POST',
                body: { poll_id: id }
            });
            loadPolls();
        } catch (e) {
            console.error('Delete failed:', e.message || e);
        }
    }
};

window.switchSettingsTab = function (tabName) {
    // update buttons
    document.querySelectorAll('.settings-subnav .nav-tab').forEach(btn => {
        btn.classList.remove('active');
        if (btn.getAttribute('onclick').includes(tabName)) {
            btn.classList.add('active');
        }
    });

    // hide all setting tabs
    document.querySelectorAll('[id^="setting-tab-"]').forEach(el => {
        el.style.display = 'none';
    });

    // show target
    const target = document.getElementById(`setting-tab-${tabName}`);
    if (target) {
        target.style.display = 'block';
        target.style.animation = 'fadeInUp 0.4s ease';
    }
}

// --- CONTENT LIBRARY (VIDEOS & CLIPS) ---

window.loadContentLibrary = async function () {
    const vidLoading = document.getElementById('vidLoading');
    const clipLoading = document.getElementById('clipLoading');

    try {
        const data = await floxFetch('../backend/getMyVideos.php');
        allContent = data.videos || [];
        processAndRenderContent(allContent);
    } catch (error) {
        console.warn('Library could not be loaded:', error.message || error);
    } finally {
        if (vidLoading) vidLoading.style.display = 'none';
        if (clipLoading) clipLoading.style.display = 'none';
    }
}

function processAndRenderContent(videos) {
    const longForm = videos.filter(v => !v.is_clip);
    const clips = videos.filter(v => v.is_clip);

    // Render Lists
    renderVideoList(longForm, 'videosGrid', 'vidEmpty');
    renderClipList(clips, 'clipsGrid', 'clipEmpty');

    // Calculate Stats
    updateStudioStats(videos);
}

function renderVideoList(videos, containerId, emptyId) {
    const container = document.getElementById(containerId);
    const emptyState = document.getElementById(emptyId);

    if (!container) return;

    container.innerHTML = '';

    if (videos.length === 0) {
        if (emptyState) emptyState.style.display = 'flex';
        return;
    }

    if (emptyState) emptyState.style.display = 'none';

    container.innerHTML = videos.map((video, index) => `
        <div class="my-video-card" style="animation-delay: ${index * 0.05}s">
            <div class="my-video-thumbnail">
                <img src="${video.thumbnail_url || 'assets/default-thumb.jpg'}" loading="lazy" alt="Thumb">
                <div class="status-badge ${video.status || 'published'}">${video.status || 'Public'}</div>
            </div>
            <div class="my-video-info">
                <div class="my-video-title">${escapeHtml(video.title)}</div>
                <div class="my-video-meta">
                    <div class="meta-item"><i class="fa-solid fa-eye"></i> ${formatMetric(video.views)}</div>
                    <div class="meta-item"><i class="fa-solid fa-heart"></i> ${formatMetric(video.likes)}</div>
                    <div class="meta-item"><i class="fa-solid fa-calendar"></i> ${formatTimeAgo(video.created_at)}</div>
                </div>
                <div class="my-video-actions">
                    <button class="action-btn edit-btn" onclick="openEditor(${video.id})">
                        <i class="fa-solid fa-pen"></i> Edit
                    </button>
                    <button class="action-btn delete-btn" onclick="deleteContent(${video.id})">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    `).join('');
}

function renderClipList(clips, containerId, emptyId) {
    const container = document.getElementById(containerId);
    const emptyState = document.getElementById(emptyId);

    if (!container) return;

    container.innerHTML = '';

    if (clips.length === 0) {
        if (emptyState) emptyState.style.display = 'flex';
        return;
    }

    if (emptyState) emptyState.style.display = 'none';

    container.innerHTML = clips.map((clip, index) => `
        <div class="my-clip-card" style="animation-delay: ${index * 0.05}s">
            <div class="my-clip-thumbnail">
                <img src="${clip.thumbnail_url || 'assets/default-thumb.jpg'}" loading="lazy" alt="Thumb">
                <div class="status-badge ${clip.status || 'published'}">${clip.status || 'Public'}</div>
            </div>
            <div class="my-video-info">
                <div class="my-video-title" style="font-size:14px;">${escapeHtml(clip.title)}</div>
                <div class="my-video-meta" style="font-size:12px; gap:10px;">
                    <div class="meta-item"><i class="fa-solid fa-play"></i> ${formatMetric(clip.views)}</div>
                    <div class="meta-item"><i class="fa-solid fa-heart"></i> ${formatMetric(clip.likes)}</div>
                </div>
                <div class="my-video-actions">
                    <button class="action-btn edit-btn" onclick="openEditor(${clip.id})"><i class="fa-solid fa-pen"></i></button>
                    <button class="action-btn delete-btn" onclick="deleteContent(${clip.id})"><i class="fa-solid fa-trash"></i></button>
                </div>
            </div>
        </div>
    `).join('');
}

function updateStudioStats(videos) {
    const totalViews = videos.reduce((sum, v) => sum + (parseInt(v.views) || 0), 0);
    const totalLikes = videos.reduce((sum, v) => sum + (parseInt(v.likes) || 0), 0);
    const totalComments = videos.reduce((sum, v) => sum + (parseInt(v.comments) || 0), 0);

    const viewEl = document.getElementById('vidTotalViews');
    const engEl = document.getElementById('vidTotalEngagement');

    if (viewEl) viewEl.textContent = formatMetric(totalViews);
    if (engEl) engEl.textContent = formatMetric(totalLikes + totalComments);
}

// --- USER MANAGEMENT ---

window.loadUserData = async function () {
    try {
        const data = await floxFetch('../backend/getUser.php');
        currentUser = data.user;
        updateProfileUI(data.user);
        loadSubscriptions();
        loadSubscribers();
    } catch (error) {
        console.warn('User session invalid or data missing:', error.message || error);
    }
}

function updateProfileUI(user) {
    // Header Stats
    document.getElementById('profileUsername').textContent = user.username;
    document.getElementById('profileEmail').textContent = user.email;

    const statVideos = document.getElementById('statVideos');
    const statJoined = document.getElementById('statJoined');
    if (statVideos) statVideos.textContent = user.video_count || 0;
    if (statJoined) statJoined.textContent = formatDate(user.created_at);

    // Profile Pic
    const img = document.getElementById('profilePictureImg');
    const placeholder = document.getElementById('profilePicturePlaceholder');

    if (user.profile_picture) {
        img.src = user.profile_picture;
        img.style.display = 'block';
        placeholder.style.display = 'none';
        updateGlobalAvatars(user.profile_picture);
    } else {
        img.style.display = 'none';
        placeholder.style.display = 'flex';
    }

    // Form Fields
    const uInput = document.getElementById('accountUsername');
    const eInput = document.getElementById('accountEmail');
    const bInput = document.getElementById('accountBio');
    const eDisplay = document.getElementById('currentEmailDisplay');

    if (uInput) uInput.value = user.username;
    if (bInput) bInput.value = user.bio || '';
    if (eDisplay) eDisplay.textContent = user.email; // or maskEmail(user.email)
    if (eInput) eInput.value = ''; // Clean for new input

    updateBioCharCount();
}

function updateGlobalAvatars(url) {
    document.querySelectorAll('.account-avatar, .nav-avatar').forEach(el => {
        el.src = url;
    });
}

window.updateBioCharCount = function () {
    const el = document.getElementById('accountBio');
    const count = document.getElementById('bioCharCount');
    if (el && count) count.textContent = el.value.length;
}

// --- ACTIONS ---

window.openEditor = function (id) {
    const item = allContent.find(i => i.id == id);
    if (!item) return;

    document.getElementById('editVideoId').value = item.id;
    document.getElementById('editTitle').value = item.title;
    document.getElementById('editDescription').value = item.description || '';
    document.getElementById('editStatus').value = item.status || 'published';

    document.getElementById('editVideoModal').style.display = 'flex';
};

window.closeEditModal = function () {
    document.getElementById('editVideoModal').style.display = 'none';
};

// Setup Edit Form Submit
const editForm = document.getElementById('editVideoForm');
if (editForm) {
    editForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const id = document.getElementById('editVideoId').value;
        const title = document.getElementById('editTitle').value;
        const description = document.getElementById('editDescription').value;
        const status = document.getElementById('editStatus').value;

        try {
            await floxFetch('../backend/updateVideo.php', {
                method: 'POST',
                body: { id, title, description, status }
            });
            closeEditModal();
            loadContentLibrary();
            if (window.showPopup) showPopup('Success', 'Video updated!', 'success');
        } catch (err) {
            console.error('Update failed:', err);
            if (window.showPopup) showPopup('Error', err.message || 'Update failed', 'error');
        }
    });
}

window.deleteContent = async function (id) {
    if (confirm("Delete this content permanently?")) {
        try {
            await floxFetch('../backend/deleteVideo.php', {
                method: 'POST',
                body: { video_id: id }
            });
            loadContentLibrary();
        } catch (e) {
            console.error('Delete failed:', e.message || e);
        }
    }
};

// --- ANALYTICS ---

// Note: Add logic to open analytics modal if needed, currently manual only in mockup or can be added to buttons.
// Let's add an Analytics button to the action buttons in renderVideoList/renderClipList as well?
// I should probably add the button back to the render function if I want to use it.


// --- SUBSCRIPTIONS ---

async function loadSubscriptions() {
    if (!currentUser) return;
    fetchSubs('getSubscriptions.php?user_id=' + currentUser.id, 'subscribedList', 'subscribedEmpty', 'subscribedCount');
}

async function loadSubscribers() {
    if (!currentUser) return;
    fetchSubs('getSubscribers.php?channel_id=' + currentUser.id, 'subscribersList', 'subscribersEmpty', 'subscribersCount');
}

async function fetchSubs(endpoint, listId, emptyId, countId) {
    const list = document.getElementById(listId);
    const empty = document.getElementById(emptyId);
    const count = document.getElementById(countId);

    try {
        const data = await floxFetch('../backend/' + endpoint);
        const items = data.subscriptions || data.subscribers || [];

        if (count) count.textContent = items.length;

        if (items.length === 0) {
            list.style.display = 'none';
            empty.style.display = 'block';
        } else {
            empty.style.display = 'none';
            list.style.display = 'grid';

            list.innerHTML = items.map(u => `
                <div class="subs-item" onclick="location.href='user_profile.php?user_id=${u.id}'" style="cursor:pointer; background:rgba(255,255,255,0.05); padding:10px; border-radius:10px; display:flex; align-items:center; gap:10px;">
                    <img src="${u.profile_picture || 'assets/default-avatar.png'}" style="width:40px; height:40px; border-radius:50%; object-fit:cover;">
                    <div style="overflow:hidden;">
                        <div style="font-weight:bold; color:white; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${escapeHtml(u.username)}</div>
                        <div style="font-size:11px; color:var(--text-secondary);">User</div>
                    </div>
                </div>
            `).join('');
        }
    } catch (e) {
        console.warn('Failed to load subscriptions:', e.message || e);
    }
}

// --- HELPERS ---

function formatMetric(num) {
    num = parseInt(num) || 0;
    if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
    if (num >= 1000) return (num / 1000).toFixed(1) + 'K';
    return num;
}

function formatDate(str) {
    if (!str) return '';
    return new Date(str).toLocaleDateString();
}

function formatTimeAgo(dateString) {
    const diff = (new Date() - new Date(dateString)) / 1000;
    if (diff < 60) return 'Just now';
    if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
    return Math.floor(diff / 86400) + 'd ago';
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

window.setupEventListeners = function () {
    // Bio Character Count
    const bio = document.getElementById('accountBio');
    if (bio) bio.addEventListener('input', updateBioCharCount);

    // Profile Pic Upload
    const ppBtn = document.getElementById('profilePictureBtn');
    const ppInput = document.getElementById('profilePictureInput');
    if (ppBtn && ppInput) {
        ppBtn.addEventListener('click', () => ppInput.click());
        ppInput.addEventListener('change', uploadProfilePic);
    }

    // Account Form Submit
    const accForm = document.getElementById('accountForm');
    if (accForm) {
        accForm.addEventListener('submit', handleProfileUpdate);
    }
}

async function uploadProfilePic(e) {
    const file = e.target.files[0];
    if (!file) return;

    const formData = new FormData();
    formData.append('profile_picture', file);

    try {
        await floxFetch('../backend/uploadProfilePicture.php', {
            method: 'POST',
            body: formData
        });
        loadUserData(); // Refresh
        if (window.showPopup) showPopup('Success', 'Profile picture updated!', 'success');
    } catch (err) {
        console.error('Upload failed:', err);
        if (window.showPopup) showPopup('Error', err.message || 'Upload failed', 'error');
    }
}

async function handleProfileUpdate(e) {
    e.preventDefault();
    const btn = document.getElementById('submitAccountBtn');
    btn.textContent = 'Saving...';
    btn.disabled = true;

    const formData = {
        username: document.getElementById('accountUsername').value,
        bio: document.getElementById('accountBio').value,
        email: document.getElementById('accountEmail').value
    };

    try {
        await floxFetch('../backend/updateUser.php', {
            method: 'POST',
            body: formData
        });
        loadUserData();
        if (window.showPopup) showPopup('Success', 'Profile updated!', 'success');
    } catch (err) {
        if (window.showPopup) showPopup('Error', err.message || 'Update failed', 'error');
    } finally {
        btn.textContent = 'Save Profile';
        btn.disabled = false;
    }
}

// Initial data load - Wrapped in DOMContentLoaded and explicitly calling global functions
document.addEventListener('DOMContentLoaded', async () => {
    console.log('Account Management Init v2.1');
    try {
        if (typeof window.loadUserData === 'function') {
            // Run independent loads in parallel
            await Promise.allSettled([
                window.loadUserData(),
                window.loadContentLibrary(),
                window.loadPosts(),
                window.loadPolls()
            ]);

            window.setupNavigation();
            window.setupEventListeners();
        } else {
            console.error('loadUserData is still not a function?');
        }
    } catch (e) {
        console.warn("[FloxStudio] Init error:", e);
    }
});