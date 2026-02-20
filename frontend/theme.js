/**
 * Loop Theme Manager
 * Handles light/dark mode switching with smooth expanding circle animation
 */

(function () {
    'use strict';

    const STORAGE_KEY = 'floxwatch_theme';

    // Get saved theme or default to dark
    function getSavedTheme() {
        const saved = localStorage.getItem(STORAGE_KEY);
        if (saved === 'light' || saved === 'dark') {
            return saved;
        }
        // Check system preference
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches) {
            return 'light';
        }
        return 'dark';
    }

    // Apply theme without animation (for initial load)
    function applyTheme(theme) {
        if (theme === 'light') {
            document.documentElement.setAttribute('data-theme', 'light');
        } else {
            document.documentElement.removeAttribute('data-theme');
        }
        localStorage.setItem(STORAGE_KEY, theme);
        updateThemeButtons(theme);
    }

    // Update theme option buttons in settings
    function updateThemeButtons(theme) {
        document.querySelectorAll('.theme-option').forEach(opt => {
            opt.classList.toggle('active', opt.dataset.theme === theme);
        });
    }

    // Switch theme with expanding circle animation
    function switchThemeWithAnimation(newTheme, clickX, clickY) {
        const currentTheme = document.documentElement.hasAttribute('data-theme') ? 'light' : 'dark';

        if (newTheme === currentTheme) return;
        if (newTheme === 'system') {
            // For system, check preference
            newTheme = window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark';
            if (newTheme === currentTheme) return;
        }

        // Create the expanding circle
        const circle = document.createElement('div');
        circle.className = 'theme-transition-circle';
        circle.style.left = clickX + 'px';
        circle.style.top = clickY + 'px';
        circle.style.backgroundColor = newTheme === 'light' ? '#ffffff' : '#0f0f0f';

        document.body.appendChild(circle);

        // Apply the new theme after a short delay (when circle starts expanding)
        setTimeout(() => {
            applyTheme(newTheme);
        }, 150);

        // Remove circle after animation completes
        setTimeout(() => {
            circle.classList.add('fade-out');
            setTimeout(() => {
                circle.remove();
            }, 300);
        }, 700);
    }

    // Initialize on page load
    function init() {
        // Apply saved theme immediately
        const savedTheme = getSavedTheme();
        applyTheme(savedTheme);

        // Apply saved font size
        const savedFontSize = getSavedFontSize();
        applyFontSize(savedFontSize);

        // Apply saved layout
        const savedLayout = getSavedLayout();
        applyLayout(savedLayout);

        // Apply saved gradient
        const savedGrad = getSavedGradient();
        applyGradient(savedGrad);

        // Listen for theme and gradient clicks
        document.addEventListener('click', (e) => {
            const themeOption = e.target.closest('.theme-option:not(.gradient-option)');
            if (themeOption) {
                const newTheme = themeOption.dataset.theme;
                const rect = themeOption.getBoundingClientRect();
                const clickX = rect.left + rect.width / 2;
                const clickY = rect.top + rect.height / 2;
                switchThemeWithAnimation(newTheme, clickX, clickY);
            }

            const gradOption = e.target.closest('.gradient-option');
            if (gradOption) {
                applyGradient(gradOption.dataset.gradient);
            }

            // Create Dropdown Toggle
            const createBtn = document.getElementById('createBtn');
            const createDropdown = document.getElementById('createDropdown');
            if (createBtn && createDropdown) {
                if (createBtn.contains(e.target)) {
                    e.preventDefault();
                    createDropdown.classList.toggle('active');

                    // Close other potential dropdowns
                    const accountDropdown = document.getElementById('accountDropdown');
                    const notificationsDropdown = document.getElementById('notificationsDropdown');
                    if (accountDropdown) accountDropdown.classList.remove('active');
                    if (notificationsDropdown) notificationsDropdown.classList.remove('active');
                } else if (!createDropdown.contains(e.target)) {
                    createDropdown.classList.remove('active');
                }
            }
        });

        // Listen for system theme changes
        if (window.matchMedia) {
            window.matchMedia('(prefers-color-scheme: light)').addEventListener('change', (e) => {
                const settings = JSON.parse(localStorage.getItem('floxwatch_settings') || '{}');
                if (settings.theme === 'system') {
                    applyTheme(e.matches ? 'light' : 'dark');
                }
            });
        }
    }

    // Gradient Theme Manager
    const GRADIENT_STORAGE_KEY = 'floxwatch_gradient';
    const CUSTOM_GRADIENT_COLORS_KEY = 'floxwatch_custom_gradient_colors';
    const gradients = {
        'default': '#121212',
        'midnight': 'linear-gradient(135deg, #121212 0%, #2d1b4d 100%)',
        'ocean': 'linear-gradient(135deg, #121212 0%, #1b3d4d 100%)',
        'aurora': 'linear-gradient(135deg, #121212 0%, #1b3d1b 100%)',
        'sunset': 'linear-gradient(135deg, #121212 0%, #4d1b1b 100%)',
        'gold': 'linear-gradient(135deg, #121212 0%, #4d3d1b 100%)'
    };

    function getSavedGradient() {
        return localStorage.getItem(GRADIENT_STORAGE_KEY) || 'default';
    }

    function getSavedCustomColors() {
        try {
            const saved = localStorage.getItem(CUSTOM_GRADIENT_COLORS_KEY);
            if (saved) return JSON.parse(saved);
        } catch (e) { }
        return { color1: '#121212', color2: '#1a1a2e' };
    }

    function saveCustomColors(color1, color2) {
        localStorage.setItem(CUSTOM_GRADIENT_COLORS_KEY, JSON.stringify({ color1, color2 }));
    }

    function applyGradient(name) {
        console.log("[FloxTheme] Applying Gradient:", name);
        let grad;
        if (name === 'custom') {
            const colors = getSavedCustomColors();
            grad = `linear-gradient(135deg, ${colors.color1} 0%, ${colors.color2} 100%)`;
        } else {
            grad = gradients[name] || gradients['default'];
        }
        // Set CSS variable for other elements that might use it
        document.documentElement.style.setProperty('--page-bg', grad);
        // Directly apply to body's background style for immediate effect
        document.body.style.background = grad;
        document.body.style.backgroundAttachment = 'fixed';
        document.body.style.backgroundSize = 'cover';
        localStorage.setItem(GRADIENT_STORAGE_KEY, name);
        updateGradientUI(name);
    }

    function applyCustomGradient(color1, color2) {
        console.log("[FloxTheme] Applying Custom Gradient:", color1, "->", color2);
        saveCustomColors(color1, color2);
        const grad = `linear-gradient(135deg, ${color1} 0%, ${color2} 100%)`;
        document.documentElement.style.setProperty('--page-bg', grad);
        document.body.style.background = grad;
        document.body.style.backgroundAttachment = 'fixed';
        document.body.style.backgroundSize = 'cover';
        localStorage.setItem(GRADIENT_STORAGE_KEY, 'custom');
        updateGradientUI('custom');
        // Update preview swatch
        const preview = document.getElementById('customGradientPreview');
        if (preview) {
            preview.style.background = grad;
        }
    }

    function updateGradientUI(name) {
        document.querySelectorAll('.gradient-option').forEach(opt => {
            if (opt.dataset.gradient === name) {
                opt.classList.add('active');
            } else {
                opt.classList.remove('active');
            }
        });
        // Show/hide custom controls
        const customControls = document.getElementById('customGradientControls');
        if (customControls) {
            customControls.style.display = name === 'custom' ? 'flex' : 'none';
        }
        // Update custom preview swatch if custom is selected
        if (name === 'custom') {
            const colors = getSavedCustomColors();
            const preview = document.getElementById('customGradientPreview');
            if (preview) {
                preview.style.background = `linear-gradient(135deg, ${colors.color1} 0%, ${colors.color2} 100%)`;
            }
            // Update color inputs
            const input1 = document.getElementById('gradientColor1');
            const input2 = document.getElementById('gradientColor2');
            if (input1) input1.value = colors.color1;
            if (input2) input2.value = colors.color2;
        }
    }

    // Font Size Manager
    const FONT_STORAGE_KEY = 'floxwatch_fontsize';

    function getSavedFontSize() {
        let saved = localStorage.getItem(FONT_STORAGE_KEY);
        if (saved) return saved;
        try {
            const settings = JSON.parse(localStorage.getItem('floxwatch_settings') || '{}');
            if (settings.fontSize) return settings.fontSize;
        } catch (e) { }
        return 'medium';
    }

    function applyFontSize(size) {
        document.documentElement.setAttribute('data-font-size', size);
        localStorage.setItem(FONT_STORAGE_KEY, size);
        updateFontSizeUI(size);
    }

    function updateFontSizeUI(size) {
        const dropdown = document.getElementById('fontSizeSelect');
        if (dropdown) {
            dropdown.setAttribute('data-value', size);
            const triggerText = dropdown.querySelector('.custom-dropdown-trigger span');
            if (triggerText) {
                triggerText.textContent = size.charAt(0).toUpperCase() + size.slice(1);
            }
            dropdown.querySelectorAll('.custom-dropdown-item').forEach(item => {
                item.classList.toggle('selected', item.dataset.value === size);
            });
        }
    }

    // Layout Manager
    const LAYOUT_STORAGE_KEY = 'floxwatch_layout';

    function getSavedLayout() {
        let saved = localStorage.getItem(LAYOUT_STORAGE_KEY);
        if (saved) return saved;
        try {
            const settings = JSON.parse(localStorage.getItem('floxwatch_settings') || '{}');
            if (settings.layout) return settings.layout;
        } catch (e) { }
        return 'grid';
    }

    function applyLayout(layout) {
        const container = document.getElementById('videosGrid') || document.querySelector('.videos-grid');
        if (container) {
            if (layout === 'list') {
                container.classList.add('list-view');
            } else {
                container.classList.remove('list-view');
            }
        }
        localStorage.setItem(LAYOUT_STORAGE_KEY, layout);
        updateLayoutUI(layout);
    }

    function updateLayoutUI(layout) {
        document.querySelectorAll('.layout-option').forEach(opt => {
            opt.classList.toggle('active', opt.dataset.layout === layout);
        });
    }

    // Standardized API Fetch Wrapper
    window.floxFetch = async function (url, options = {}) {
        const defaultOptions = {
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json'
            }
        };

        const mergedOptions = { ...defaultOptions, ...options };
        if (options.body && typeof options.body === 'object' && !(options.body instanceof FormData)) {
            mergedOptions.body = JSON.stringify(options.body);
        }

        try {
            const response = await fetch(url, mergedOptions);

            // Check for HTTP errors (4xx, 5xx)
            if (!response.ok) {
                let errorData;
                try {
                    errorData = await response.json();
                } catch (e) {
                    errorData = { message: `HTTP Error ${response.status}`, status: response.status };
                }
                throw errorData;
            }

            const data = await response.json();

            // Check for Application errors (success: false)
            if (data && data.success === false) {
                throw data;
            }

            return data;
        } catch (error) {
            // Log for debugging if needed, then re-throw for the caller to .catch()
            if (error.code === 403 || error.status === 403) {
                console.warn("[FloxFetch] Permission Denied:", error.message || error.error);
            }
            throw error;
        }
    };

    // Expose API for external use
    window.FloxTheme = {
        get: getSavedTheme,
        set: applyTheme,
        switchWithAnimation: switchThemeWithAnimation,
        getFontSize: getSavedFontSize,
        setFontSize: applyFontSize,
        getLayout: getSavedLayout,
        setLayout: applyLayout,
        applyGradient: applyGradient,
        applyCustomGradient: applyCustomGradient,
        getSavedCustomColors: getSavedCustomColors
    };

    // Interest Tracking System
    window.FloxInterests = {
        track: function (video) {
            if (!video) return;
            const keywords = [];
            if (video.hashtags && Array.isArray(video.hashtags)) {
                video.hashtags.forEach(tag => keywords.push({ type: 'hashtag', value: tag.toLowerCase() }));
            }
            const textToScan = (video.title || '') + ' ' + (video.description || '');
            const hashtagMatch = textToScan.match(/#(\w+)/g);
            if (hashtagMatch) {
                hashtagMatch.forEach(tag => {
                    const val = tag.substring(1).toLowerCase();
                    if (!keywords.find(k => k.type === 'hashtag' && k.value === val)) {
                        keywords.push({ type: 'hashtag', value: val });
                    }
                });
            }
            const titleWords = (video.title || '').toLowerCase()
                .replace(/[^\w\s]/g, '')
                .split(/\s+/)
                .filter(w => w.length > 2 && !this.isStopWord(w));
            titleWords.forEach(word => keywords.push({ type: 'keyword', value: word }));
            this.updateCookie(keywords);
        },
        updateCookie: function (newKeywords) {
            let interests = this.getCookie();
            newKeywords.forEach(nk => {
                const existing = interests.find(i => i.type === nk.type && i.value === nk.value);
                if (existing) {
                    existing.score = (existing.score || 1) + 1;
                } else {
                    interests.push({ ...nk, score: 1 });
                }
            });
            interests.sort((a, b) => (b.score || 1) - (a.score || 1));
            interests = interests.slice(0, 100);
            this.setCookie('flixwatch_interests', JSON.stringify(interests), 30);
        },
        getCookie: function () {
            const name = "flixwatch_interests=";
            const decodedCookie = decodeURIComponent(document.cookie);
            const ca = decodedCookie.split(';');
            for (let i = 0; i < ca.length; i++) {
                let c = ca[i].trim();
                if (c.indexOf(name) === 0) {
                    try { return JSON.parse(c.substring(name.length)); } catch (e) { return []; }
                }
            }
            return [];
        },
        setCookie: function (name, value, days) {
            const d = new Date();
            d.setTime(d.getTime() + (days * 24 * 60 * 60 * 1000));
            const expires = "expires=" + d.toUTCString();
            document.cookie = name + "=" + encodeURIComponent(value) + ";" + expires + ";path=/;SameSite=Lax";
        },
        isStopWord: function (word) {
            const stopWords = ['the', 'and', 'a', 'to', 'of', 'in', 'is', 'it', 'you', 'that', 'this', 'for', 'on', 'with', 'as', 'are', 'was', 'at', 'be', 'by', 'an', 'from', 'or', 'but', 'what', 'how', 'when', 'where', 'who', 'which', 'if', 'then', 'else', 'will', 'can', 'may', 'should', 'would', 'could', 'must', 'have', 'has', 'had', 'do', 'does', 'did', 'doing', 'done', 'get', 'got', 'getting', 'make', 'made', 'making', 'video', 'watch', 'official', 'clip'];
            return stopWords.includes(word);
        }
    };

    window.FloxThumbnails = {
        generate: async function (videoUrl, imgElement) {
            if (!videoUrl) return;
            imgElement.style.opacity = '0.5';
            const video = document.createElement('video');
            video.muted = true;
            video.preload = "auto";
            video.playsInline = true;
            if (videoUrl.startsWith('http')) video.crossOrigin = "anonymous";
            video.src = videoUrl;
            const captureFrame = () => {
                try {
                    const canvas = document.createElement('canvas');
                    canvas.width = video.videoWidth;
                    canvas.height = video.videoHeight;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                    imgElement.src = canvas.toDataURL('image/jpeg', 0.8);
                    imgElement.style.opacity = '1';
                    imgElement.classList.add('generated');
                    canvas.remove();
                } catch (e) {
                    imgElement.src = 'assets/placeholder.jpg';
                    imgElement.style.opacity = '1';
                }
                video.remove();
            };
            video.onloadedmetadata = () => {
                const duration = video.duration;
                if (!duration || isNaN(duration)) video.currentTime = 0;
                else video.currentTime = Math.random() * (duration * 0.8) + (duration * 0.1);
            };
            video.onseeked = captureFrame;
            video.onerror = () => {
                imgElement.src = 'assets/placeholder.jpg';
                imgElement.style.opacity = '1';
            };
            setTimeout(() => {
                if (imgElement.style.opacity === '0.5' && !imgElement.classList.contains('generated')) {
                    imgElement.src = 'assets/placeholder.jpg';
                    imgElement.style.opacity = '1';
                    video.remove();
                }
            }, 10000);
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
