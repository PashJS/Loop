/* history.js - Premium History Logic */
let activeTab = 'watch';
let rawData = [];
let filteredData = [];

document.addEventListener('DOMContentLoaded', () => {
    loadHistoryData();
});

async function loadHistoryData() {
    const grid = document.getElementById('historyGrid');
    grid.innerHTML = `<div style="text-align:center; padding: 100px; opacity: 0.5;">Wait a moment...</div>`;

    let endpoint = activeTab === 'watch' ? '../backend/getAllWatchHistory.php' : '../backend/getAllSearchHistory.php';

    try {
        const response = await fetch(endpoint);
        const data = await response.json();

        if (data.success) {
            rawData = data.history || [];
            filteredData = [...rawData];
            renderHistoryGrid();
        } else {
            showEmptyState();
        }
    } catch (err) {
        console.error('History Error:', err);
        showEmptyState();
    }
}

function switchHistoryTab(tab) {
    if (activeTab === tab) return;
    activeTab = tab;

    // UI Update
    document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));
    document.getElementById(tab + 'Tab').classList.add('active');

    loadHistoryData();
}

function renderHistoryGrid() {
    const grid = document.getElementById('historyGrid');
    if (filteredData.length === 0) {
        showEmptyState();
        return;
    }

    // Grouping
    const groups = groupItemsByDate(filteredData);
    let html = '';

    for (const [title, items] of Object.entries(groups)) {
        html += `
            <div class="history-segment">
                <h2 class="history-section-title">${title}</h2>
                <div class="items-list">
                    ${items.map(item => activeTab === 'watch' ? renderWatchItem(item) : renderSearchItem(item)).join('')}
                </div>
            </div>
        `;
    }

    grid.innerHTML = html;
}

function renderWatchItem(item) {
    const time = new Date(item.updated_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    return `
        <div class="history-item" onclick="location.href='videoid.php?id=${item.video_id}'">
            <img src="${item.thumbnail_path}" class="item-thumb" alt="thumb">
            <div class="item-info">
                <span class="item-title">${item.title}</span>
                <div class="item-meta">
                    <span>${item.channel_name}</span>
                    <span>•</span>
                    <span>Watched at ${time}</span>
                </div>
            </div>
            <div class="item-actions">
                <button class="delete-item-btn" onclick="deleteHistoryItem(${item.video_id}, event)">
                    <i class="fa-solid fa-trash"></i>
                </button>
            </div>
        </div>
    `;
}

function renderSearchItem(item) {
    const time = new Date(item.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    return `
        <div class="history-item" onclick="location.href='search.php?q=${encodeURIComponent(item.query)}'">
            <div class="item-icon">
                <i class="fa-solid fa-magnifying-glass"></i>
            </div>
            <div class="item-info">
                <span class="item-title">${item.query}</span>
                <div class="item-meta">
                    <span>Search query</span>
                    <span>•</span>
                    <span>at ${time}</span>
                </div>
            </div>
            <div class="item-actions">
                <button class="delete-item-btn" onclick="deleteHistoryItem(${item.id}, event)">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
        </div>
    `;
}

function groupItemsByDate(items) {
    const groups = {};
    items.forEach(item => {
        const dateStr = item.updated_at || item.created_at;
        const date = new Date(dateStr);
        const today = new Date();
        const yesterday = new Date();
        yesterday.setDate(yesterday.getDate() - 1);

        let label = date.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
        if (date.toDateString() === today.toDateString()) label = 'Today';
        else if (date.toDateString() === yesterday.toDateString()) label = 'Yesterday';

        if (!groups[label]) groups[label] = [];
        groups[label].push(item);
    });
    return groups;
}

function filterHistoryItems() {
    const term = document.getElementById('historyFilter').value.toLowerCase();
    filteredData = rawData.filter(item => {
        const text = activeTab === 'watch' ? item.title : item.query;
        return text.toLowerCase().includes(term);
    });
    renderHistoryGrid();
}

function showEmptyState() {
    document.getElementById('historyGrid').innerHTML = `
        <div style="text-align:center; padding: 100px; background: rgba(255,255,255,0.02); border: 1px dashed rgba(255,255,255,0.1); border-radius: 40px; backdrop-filter: blur(20px);">
            <i class="fa-solid fa-clock-rotate-left" style="font-size: 64px; margin-bottom: 24px; background: linear-gradient(135deg, #fff 0%, #444 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; opacity: 0.8;"></i>
            <h3 style="font-size: 24px; font-weight: 700; margin-bottom: 12px;">No History Records</h3>
            <p style="color: var(--text-secondary); max-width: 400px; margin: 0 auto; line-height: 1.6;">Your history is currently empty in this category.</p>
        </div>
    `;
}

async function deleteHistoryItem(id, event) {
    event.stopPropagation();
    // Simplified deletion logic for demonstration, should call backend endpoints
    const endpoint = activeTab === 'watch' ? '../backend/deleteWatchHistory.php' : '../backend/deleteSearchHistoryItem.php';

    try {
        const response = await fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        });
        const res = await response.json();
        if (res.success) {
            loadHistoryData();
        }
    } catch (e) { }
}

async function clearFullHistory() {
    const confirmed = await Popup.confirm('Are you sure you want to purge your history? This action is irreversible.');
    if (!confirmed) return;

    const endpoint = activeTab === 'watch' ? '../backend/clearWatchHistory.php' : '../backend/clearSearchHistory.php';
    try {
        const response = await fetch(endpoint, { method: 'POST' });
        const res = await response.json();
        if (res.success) {
            loadHistoryData();
            Popup.show('History purged successfully', 'success');
        }
    } catch (e) { }
}
