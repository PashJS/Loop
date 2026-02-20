// ============================================
// SEARCH HISTORY FUNCTIONALITY
// ============================================
if (typeof SearchHistory === 'undefined') {
    class SearchHistory {
        static async getHistory() {
            try {
                const response = await fetch('../backend/getSearchHistory.php');
                const data = await response.json();
                return data.success ? data.history : [];
            } catch (e) {
                console.error('Failed to get search history:', e);
                return [];
            }
        }

        static async searchUsers(query) {
            if (!query) return [];
            try {
                const response = await fetch(`../backend/searchChannels.php?q=${encodeURIComponent(query)}`);
                const data = await response.json();
                return data.success ? data.users : [];
            } catch (e) {
                console.error('Failed to search users:', e);
                return [];
            }
        }

        static async addToHistory(query) {
            if (!query || !query.trim()) return;

            try {
                await fetch('../backend/saveSearch.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ query: query.trim() }),
                    keepalive: true
                });
            } catch (e) {
                console.error('Failed to save search history:', e);
            }
        }

        static async clearHistory() {
            try {
                await fetch('../backend/clearSearchHistory.php', {
                    method: 'POST'
                });
            } catch (e) {
                console.error('Failed to clear search history:', e);
            }
        }

        static async renderDropdown(searchInput, searchContainer) {
            const history = await this.getHistory();
            const currentVal = searchInput.value.trim().toLowerCase();

            let filteredHistory = history;

            if (currentVal) {
                filteredHistory = history.filter(item => item.toLowerCase().includes(currentVal));
            }

            // Remove existing dropdown
            const existing = searchContainer.querySelector('.search-history-dropdown');
            if (existing) existing.remove();

            // If history is empty and no input, show "No search history"
            if (history.length === 0 && !currentVal) {
                const dropdown = document.createElement('div');
                dropdown.className = 'search-history-dropdown';
                dropdown.innerHTML = `
                <div class="search-history-items">
                    <div class="search-history-item" style="cursor: default;">
                        <span style="color: var(--text-secondary);">No search history</span>
                    </div>
                </div>
             `;
                searchContainer.appendChild(dropdown);
                return;
            }

            if (filteredHistory.length === 0) return;

            // Create dropdown
            const dropdown = document.createElement('div');
            dropdown.className = 'search-history-dropdown';

            let html = '';

            // Render History
            if (filteredHistory.length > 0) {
                html += `
                <div class="search-history-header">
                    <span>Recent Searches</span>
                    <button class="search-history-clear" title="Clear history">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </div>
                <div class="search-history-items">
                    ${filteredHistory.map(item => `
                        <div class="search-history-item" data-query="${escapeHtml(item)}">
                            <i class="fa-solid fa-clock-rotate-left"></i>
                            <span>${escapeHtml(item)}</span>
                        </div>
                    `).join('')}
                </div>
            `;
            }

            dropdown.innerHTML = html;
            searchContainer.appendChild(dropdown);

            // Add event listeners
            dropdown.querySelectorAll('.search-history-item').forEach(item => {
                item.addEventListener('click', () => {
                    const query = item.dataset.query;
                    searchInput.value = query;
                    searchInput.focus();
                    dropdown.remove();

                    // If on search.php, use its performSearch function
                    if (window.location.pathname.includes('search.php')) {
                        if (typeof window.performSearch === 'function') {
                            window.performSearch(query);
                        } else {
                            // Fallback: redirect
                            window.location.href = `search.php?q=${encodeURIComponent(query)}`;
                        }
                    } else {
                        this.performSearch(query);
                    }
                });
            });

            const clearBtn = dropdown.querySelector('.search-history-clear');
            if (clearBtn) {
                clearBtn.addEventListener('click', async (e) => {
                    e.stopPropagation();

                    let confirmed = false;
                    if (window.Popup) {
                        confirmed = await window.Popup.confirm('Clear entire search history?');
                    } else {
                        confirmed = true;
                    }

                    if (confirmed) {
                        await this.clearHistory();
                        dropdown.remove();
                        if (window.Popup) window.Popup.show('Search history cleared', 'success');
                    }
                });
            }
        }

        static performSearch(query) {
            if (query && query.trim()) {
                // Track search as interest
                if (window.FloxInterests) {
                    window.FloxInterests.track({ title: query.trim(), description: '', hashtags: [] });
                }
                const q = encodeURIComponent(query.trim());

                // If on search.php, use its logic
                if (window.location.pathname.includes('search.php')) {
                    if (typeof window.performSearch === 'function') {
                        window.performSearch(query.trim());
                    } else if (typeof performSearch === 'function') {
                        performSearch(query.trim());
                    } else {
                        window.location.href = `search.php?q=${q}`;
                    }
                }
                // If on home.php, redirect to search.php
                else if (window.location.pathname.includes('home.php')) {
                    window.location.href = `search.php?q=${q}`;
                }
                // Default fallback
                else {
                    window.location.href = `search.php?q=${q}`;
                }
            }
        }
    }

    function escapeHtml(text) {
        if (text === null || text === undefined) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function formatSubscribers(count) {
        const n = Number(count) || 0;
        if (n >= 1000000) return (n / 1000000).toFixed(1).replace(/\.0$/, '') + 'M';
        if (n >= 1000) return (n / 1000).toFixed(1).replace(/\.0$/, '') + 'K';
        return n.toString();
    }

    // Add search history styles
    const searchHistoryStyles = document.createElement('style');
    searchHistoryStyles.textContent = `
    .search-container {
        position: relative;
    }
    
    .search-history-dropdown {
        position: absolute;
        top: calc(100% + 8px);
        left: 0;
        right: 0;
        background: var(--secondary-color, #1a1a1a);
        border: 1px solid var(--border-color, #303030);
        border-radius: 12px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
        z-index: 9999;
        max-height: 400px;
        overflow-y: auto;
        animation: slideDown 0.2s ease;
    }
    
    .search-history-header, .search-section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 16px;
        border-bottom: 1px solid var(--border-color, #303030);
        font-size: 12px;
        font-weight: 600;
        color: var(--text-secondary, #aaaaaa);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .search-history-clear {
        background: none;
        border: none;
        color: var(--text-secondary, #aaaaaa);
        cursor: pointer;
        padding: 4px 8px;
        border-radius: 4px;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .search-history-clear:hover {
        background: var(--hover-bg, #2d2d2d);
        color: var(--text-primary, #ffffff);
    }
    
    .search-history-items {
        padding: 4px;
    }
    
    .search-history-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 16px;
        cursor: pointer;
        border-radius: 8px;
        transition: all 0.2s;
        color: var(--text-primary, #ffffff);
        font-size: 14px;
    }
    
    .search-history-item:hover {
        background: var(--hover-bg, #2d2d2d);
    }
    
    .search-history-item i {
        color: var(--text-secondary, #aaaaaa);
        font-size: 14px;
        width: 16px;
    }
    
    .search-history-item:hover i {
        color: var(--accent-color, #3ea6ff);
    }

    /* User Search Items */
    .search-user-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 8px 16px;
        cursor: pointer;
        transition: background 0.2s;
    }
    
    .search-user-item:hover {
        background: var(--hover-bg, #2d2d2d);
    }

    .search-user-avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        overflow: hidden;
        background: var(--accent-color);
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .search-user-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .search-user-initial {
        color: white;
        font-weight: 600;
        font-size: 16px;
    }

    .search-user-info {
        flex: 1;
        min-width: 0;
    }

    .search-user-name {
        font-weight: 500;
        color: var(--text-primary, #ffffff);
        font-size: 14px;
        margin-bottom: 2px;
    }

    .search-user-meta {
        font-size: 12px;
        color: var(--text-secondary, #aaaaaa);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    .search-divider {
        height: 1px;
        background: var(--border-color, #303030);
        margin: 4px 0;
    }
    
    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
`;
    document.head.appendChild(searchHistoryStyles);

    // Initialize listeners when DOM is loaded
    document.addEventListener('DOMContentLoaded', () => {
        // Handle home page search (searchInput)
        const searchInput = document.getElementById('searchInput');
        const searchContainer = document.querySelector('.search-container') || document.getElementById('searchContainer');

        if (searchInput && searchContainer) {
            const showHistory = () => {
                SearchHistory.renderDropdown(searchInput, searchContainer);
            };

            const handleSearch = async () => {
                const query = searchInput.value.trim();
                if (query) {
                    await SearchHistory.addToHistory(query);
                    SearchHistory.performSearch(query);
                }
            };

            searchInput.addEventListener('focus', showHistory);
            searchInput.addEventListener('input', showHistory);
            searchInput.addEventListener('click', showHistory);

            // Handle Enter key
            searchInput.addEventListener('keypress', async (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault(); // Prevent default form submission or other handlers
                    await handleSearch();
                }
            });

            // Handle Search Button
            const searchBtn = document.getElementById('searchBtn');
            if (searchBtn) {
                searchBtn.addEventListener('click', async (e) => {
                    e.preventDefault();
                    await handleSearch();
                });
            }

            // Hide history when clicking outside
            document.addEventListener('click', (e) => {
                if (!searchContainer.contains(e.target)) {
                    const dropdown = searchContainer.querySelector('.search-history-dropdown');
                    if (dropdown) dropdown.remove();
                }
            });
        }

        // Handle search page search (searchPageInput) - only for history dropdown
        const searchPageInput = document.getElementById('searchPageInput');
        const searchPageContainer = document.querySelector('.search-page-input-container');

        if (searchPageInput && searchPageContainer) {
            const showHistory = () => {
                SearchHistory.renderDropdown(searchPageInput, searchPageContainer);
            };

            // Only add history dropdown listeners, not search execution
            // Search execution is handled by search.php itself
            searchPageInput.addEventListener('focus', showHistory);
            searchPageInput.addEventListener('input', showHistory);
            searchPageInput.addEventListener('click', showHistory);

            // Hide history when clicking outside
            document.addEventListener('click', (e) => {
                if (!searchPageContainer.contains(e.target)) {
                    const dropdown = searchPageContainer.querySelector('.search-history-dropdown');
                    if (dropdown) dropdown.remove();
                }
            });
        }
    });
}
