/* Loop Stories System */
class StorySystem {
    constructor() {
        this.currentStories = [];
        this.currentIndex = 0;
        this.progressInterval = null;
        this.progress = 0;
        this.storyDuration = 5000;

        this.init();
    }

    async init() {
        console.log("[StorySystem] Initializing...");

        // Ensure myUserId is available
        if (!window.myUserId) {
            try {
                const res = await fetch('../backend/getUser.php');
                const data = await res.json();
                if (data.success) window.myUserId = data.user.id;
            } catch (e) { }
        }

        // Create Viewer HTML if it doesn't exist
        if (!document.getElementById('storyViewer')) {
            const viewer = document.createElement('div');
            viewer.id = 'storyViewer';
            viewer.innerHTML = `
                <div class="story-progress" id="storyProgress"></div>
                <div class="story-viewer-header">
                    <img class="story-viewer-avatar" id="storyAvatar">
                    <span class="story-viewer-name" id="storyUsername"></span>
                    <button class="icon-btn" style="margin-left: auto; background: none; border: none; color: #fff; font-size: 28px; cursor: pointer; padding: 10px; z-index: 1001;" id="storyCloseBtn">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                <div class="story-content" id="storyContent"></div>
                <div class="story-nav-area story-nav-prev" id="storyPrev"></div>
                <div class="story-nav-area story-nav-next" id="storyNext"></div>
                <div class="story-footer">
                    <div class="story-action"><i class="fa-regular fa-heart"></i></div>
                    <div class="story-action"><i class="fa-regular fa-message"></i></div>
                </div>
            `;
            document.body.appendChild(viewer);

            document.getElementById('storyPrev').addEventListener('click', (e) => { e.stopPropagation(); this.prev(); });
            document.getElementById('storyNext').addEventListener('click', (e) => { e.stopPropagation(); this.next(); });
            document.getElementById('storyCloseBtn').addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.close();
            });
        }

        this.applyStoryRings();

        // Capture phase click listener
        document.addEventListener('click', (e) => {
            const storyTarget = e.target.closest('.has-story');
            if (storyTarget) {
                const userId = storyTarget.getAttribute('data-user-id');
                console.log("[StorySystem] Ring clicked for user:", userId);

                // Block other listeners (like dropdown)
                e.preventDefault();
                e.stopImmediatePropagation();

                if (userId) {
                    // Redirect to the new view_story page
                    window.location.href = 'view_story.php?user_id=' + userId;
                }
            }
        }, true);

        setInterval(() => this.applyStoryRings(), 3000);
    }

    async applyStoryRings() {
        try {
            const res = await fetch('../backend/check_all_stories.php');
            const data = await res.json();

            if (data.success && data.user_ids) {
                // Clear state
                document.querySelectorAll('.has-story').forEach(el => {
                    el.classList.remove('has-story');
                    el.removeAttribute('data-user-id');
                });

                data.user_ids.forEach(userId => {
                    const isMe = String(userId) === String(window.myUserId);

                    const selectors = [
                        `.video-avatar[data-user-id="${userId}"]`,
                        `.video-author-avatar[data-id="${userId}"]`,
                        `.conv-card[data-id="${userId}"] .conv-avatar`,
                        `.chat-sidebar img[src*="id=${userId}"]`,
                        `img[src*="user_id=${userId}"]`,
                        `img[src*="id=${userId}"]`
                    ];

                    if (isMe) {
                        selectors.push(`.account-avatar`, `#accountBtn`, `.account-btn`);
                    }

                    selectors.forEach(sel => {
                        document.querySelectorAll(sel).forEach(el => {
                            let shouldApply = false;
                            const tid = el.getAttribute('data-user-id') || el.getAttribute('data-id');

                            if (isMe && (sel.includes('account') || sel === '#accountBtn')) {
                                shouldApply = true;
                            } else if (tid == userId || sel.includes(userId)) {
                                shouldApply = true;
                            }

                            if (shouldApply) {
                                el.classList.add('has-story');
                                el.setAttribute('data-user-id', userId);
                            }
                        });
                    });
                });
            }
        } catch (e) {
            // Only log if it's not a standard fetch failure (to avoid spamming suspended mobile logs)
            if (e.name !== 'TypeError') console.error("[StorySystem] Ring calculation error:", e);
        }
    }

    async open(userId) {
        if (!userId) return;
        console.log("[StorySystem] Fetching stories for user:", userId);
        try {
            const res = await fetch(`../backend/get_user_stories.php?user_id=${userId}`);
            const data = await res.json();
            if (data.success && data.stories && data.stories.length > 0) {
                this.currentStories = data.stories;
                this.currentIndex = 0;
                this.showStory();
                document.getElementById('storyViewer').style.display = 'flex';
                console.log("[StorySystem] Viewer displayed with", data.stories.length, "stories");
            } else {
                console.warn("[StorySystem] No stories found for user:", userId, data);
            }
        } catch (e) { console.error("[StorySystem] Open error:", e); }
    }

    showStory() {
        const story = this.currentStories[this.currentIndex];
        if (!story) return;

        const container = document.getElementById('storyContent');
        const progressContainer = document.getElementById('storyProgress');

        progressContainer.innerHTML = '';
        this.currentStories.forEach((_, i) => {
            const bar = document.createElement('div');
            bar.className = 'story-bar';
            const fill = document.createElement('div');
            fill.className = 'story-bar-fill';
            if (i < this.currentIndex) fill.style.width = '100%';
            bar.appendChild(fill);
            progressContainer.appendChild(bar);
        });

        document.getElementById('storyUsername').innerText = story.username || 'User';
        document.getElementById('storyAvatar').src = this.resolvePath(story.profile_picture);

        // Add time ago
        if (story.created_at) {
            const timeAgo = this.formatTimeAgo(story.created_at);
            document.getElementById('storyUsername').innerHTML = `${story.username || 'User'} <span style="opacity: 0.6; font-weight: 400; font-size: 13px; margin-left: 8px;">${timeAgo}</span>`;
        }

        container.innerHTML = '';
        container.style.opacity = '0';
        container.style.transition = 'opacity 0.3s ease';

        if (story.type === 'image') {
            const img = document.createElement('img');
            img.src = '../uploads/stories/' + story.content_path;
            img.onload = () => {
                container.style.opacity = '1';
                this.storyDuration = 5000;
                this.startProgress();
            };
            container.appendChild(img);
        } else {
            const video = document.createElement('video');
            video.src = '../uploads/stories/' + story.content_path;
            video.autoplay = true;
            video.playsinline = true;
            video.muted = false;
            container.appendChild(video);

            video.onloadedmetadata = () => {
                container.style.opacity = '1';
                this.storyDuration = (video.duration * 1000) || 5000;
                this.startProgress();
            };
            video.onerror = () => this.next();
        }
    }

    startProgress() {
        clearInterval(this.progressInterval);
        this.progress = 0;
        const bars = document.querySelectorAll('.story-bar-fill');
        const currentBar = bars[this.currentIndex];
        if (!currentBar) return;

        const step = 30;
        const increment = (step / this.storyDuration) * 100;

        this.progressInterval = setInterval(() => {
            this.progress += increment;
            if (currentBar) currentBar.style.width = Math.min(this.progress, 100) + '%';
            if (this.progress >= 100) this.next();
        }, step);
    }

    next() {
        if (this.currentIndex < this.currentStories.length - 1) {
            this.currentIndex++;
            this.showStory();
        } else {
            this.close();
        }
    }

    prev() {
        if (this.currentIndex > 0) {
            this.currentIndex--;
            this.showStory();
        } else {
            this.currentIndex = 0;
            this.showStory();
        }
    }

    close() {
        clearInterval(this.progressInterval);
        document.getElementById('storyViewer').style.display = 'none';
        const video = document.querySelector('#storyContent video');
        if (video) video.pause();

        // Only redirect if explicitly on the standalone page
        if (window.location.pathname.includes('view_story.php')) {
            if (window.history.length > 1) {
                window.history.back();
            } else {
                window.location.href = 'home.php';
            }
        }
    }

    resolvePath(path) {
        if (!path) return 'default-avatar.png';
        if (path.startsWith('http')) return path;
        return '../' + path.replace(/^\.\//, '');
    }

    formatTimeAgo(dateString) {
        const now = new Date();
        const past = new Date(dateString);
        const diffInMs = now - past;
        const diffInHours = Math.floor(diffInMs / (1000 * 60 * 60));

        if (diffInHours < 1) {
            const diffInMins = Math.floor(diffInMs / (1000 * 60));
            return diffInMins + 'm';
        }
        return diffInHours + 'h';
    }
}

const storySystem = new StorySystem();
window.storySystem = storySystem;
