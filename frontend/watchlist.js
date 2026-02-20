/* watchlist.js - Archive Management Logic */
let archiveData = null;
let currentTab = 'partial';
let currentSort = 'newest';
let filterTerm = '';

document.addEventListener('DOMContentLoaded', () => {
    initializeArchive();

    // Close dropdown on outside click
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.filter-wrapper')) {
            document.getElementById('filterDropdown').classList.remove('show');
        }
    });
});

async function initializeArchive() {
    const grid = document.getElementById('archiveGrid');
    grid.innerHTML = `<div style="text-align:center; padding: 100px; opacity: 0.5;">Loading your watchlists...</div>`;

    try {
        const response = await fetch('../backend/getWatchlists.php');
        const data = await response.json();
        if (data.success) {
            archiveData = data.lists;
            updateFilterOptionsVisibility();
            renderArchive(currentTab);
        } else {
            showNullState(currentTab);
        }
    } catch (err) {
        console.error('Watchlist Error:', err);
        showNullState(currentTab);
    }
}

function toggleFilterDropdown() {
    const dropdown = document.getElementById('filterDropdown');
    dropdown.classList.toggle('show');
}

function updateFilterOptionsVisibility() {
    const options = document.querySelectorAll('.filter-option');
    options.forEach(opt => {
        if (opt.dataset.watchOnly === 'true') {
            opt.style.display = (currentTab === 'partial' || currentTab === 'watched') ? 'block' : 'none';
        }
    });
}

function applySort(sort) {
    currentSort = sort;

    // Update Active UI
    document.querySelectorAll('.filter-option').forEach(opt => {
        const optSort = opt.getAttribute('onclick').match(/'([^']+)'/)[1];
        opt.classList.toggle('active', optSort === sort);
    });

    document.getElementById('filterDropdown').classList.remove('show');
    renderArchive(currentTab);
}

function switchArchive(tab) {
    if (currentTab === tab) return;
    currentTab = tab;

    // UI Feedback
    document.querySelectorAll('.archive-selector').forEach(el => el.classList.remove('active'));
    const activeBtn = document.querySelector(`.archive-selector[data-tab="${tab}"]`);
    if (activeBtn) activeBtn.classList.add('active');

    updateFilterOptionsVisibility();
    renderArchive(tab);
}

function renderArchive(tab) {
    const grid = document.getElementById('archiveGrid');
    let items = archiveData ? [...archiveData[tab]] : [];

    // Apply Sort
    items.sort((a, b) => {
        if (currentSort === 'newest') return new Date(b.created_at) - new Date(a.created_at);
        if (currentSort === 'oldest') return new Date(a.created_at) - new Date(b.created_at);
        if (currentSort === 'most-liked') return (b.likes_count || 0) - (a.likes_count || 0);
        if (currentSort === 'least-liked') return (a.likes_count || 0) - (b.likes_count || 0);
        if (currentSort === 'most-watched') return (b.views || 0) - (a.views || 0);
        if (currentSort === 'least-watched') return (a.views || 0) - (b.views || 0);
        return 0;
    });

    // Apply Filter
    if (filterTerm) {
        items = items.filter(item => item.title.toLowerCase().includes(filterTerm.toLowerCase()));
    }

    if (items.length === 0) {
        showNullState(tab);
        return;
    }

    grid.innerHTML = items.map((video, idx) => renderMediaCapsule(video, idx, tab)).join('');

    // Trigger thumbnail generation for placeholders
    grid.querySelectorAll('img.p-thumb').forEach(img => {
        if (img.dataset.url) {
            window.FloxThumbnails.generate(img.dataset.url, img);
        }
    });
}

function renderMediaCapsule(v, idx, tab) {
    const delay = idx * 0.05;
    const progress = v.progress_seconds > 0 ? (v.progress_seconds / v.duration_seconds) * 100 : 0;
    const hasThumb = v.thumbnail_url && !v.thumbnail_url.includes('placeholder.jpg');

    return `
        <a href="videoid.php?id=${v.id}&list=${tab}" class="media-capsule" style="animation-delay: ${delay}s">
            <div class="capsule-visual">
                <img src="${hasThumb ? v.thumbnail_url : 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'}" 
                     class="${!hasThumb ? 'p-thumb' : ''}" 
                     data-url="${v.video_url}" 
                     alt="visual">
                <div class="telemetry-overlay">
                    ${progress > 0 ? `
                        <div class="telemetry-progress-rail">
                            <div class="telemetry-progress-fill" style="width: ${progress}%"></div>
                        </div>
                    ` : ''}
                </div>
            </div>
            <div class="capsule-content">
                <span class="capsule-title">${v.title}</span>
                <div class="capsule-meta">
                    <div class="author-link">
                        <img src="${v.author.profile_picture || 'assets/default-avatar.png'}" class="author-avatar-mini" alt="v">
                        <span>${v.author.username}</span>
                    </div>
                </div>
            </div>
        </a>
    `;
}

function showNullState(tab) {
    const labels = {
        'partial': 'No videos found in your "Continue Watching" list.',
        'watched': 'You haven\'t finished any videos yet.',
        'liked': 'You haven\'t liked any videos yet.',
        'favorites': 'Your favorites list is currently empty.',
        'saved': 'You haven\'t saved any videos for later.'
    };

    const icons = {
        'partial': 'fa-solid fa-hourglass-half',
        'watched': 'fa-solid fa-check-double',
        'liked': 'fa-solid fa-thumbs-up',
        'favorites': 'fa-solid fa-heart',
        'saved': 'fa-solid fa-bookmark'
    };

    document.getElementById('archiveGrid').innerHTML = `
        <div class="null-archive">
            <i class="${icons[tab]}"></i>
            <h3>No Videos Found</h3>
            <p>${labels[tab]}</p>
        </div>
    `;
}

function filterArchive() {
    filterTerm = document.getElementById('archiveFilter').value;
    renderArchive(currentTab);
}
