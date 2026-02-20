/**
 * NOVA EXTENSION LOADER (v1.0.0)
 * A sandboxed, declarative UI extension engine for Loop.
 * 
 * RULES:
 * - Only touches elements with data-flox="..." attributes
 * - No access to document.body, auth, storage, or internal IDs
 * - Fully reversible (stores original styles for rollback)
 * - Uses Anime.js for smooth animations
 */

class NovaExtensionLoader {
    constructor() {
        this.originalStyles = new Map();
        this.boundEvents = [];
        this.isActive = false;
        console.log("[Nova] Extension Loader Initialized");
    }

    /**
     * Load and apply an extension manifest
     * @param {Object|string} manifest - The extension JSON or parsed object
     */
    load(manifest) {
        try {
            const config = typeof manifest === 'string' ? JSON.parse(manifest) : manifest;

            if (!config.extension_name || !config.patches) {
                throw new Error("Invalid manifest: missing extension_name or patches");
            }

            console.log(`[Nova] Loading "${config.extension_name}" v${config.version || '1.0.0'}`);

            // Apply each patch
            config.patches.forEach(patch => this._applyPatch(patch));

            // Bind events if defined
            if (config.events) {
                config.events.forEach(event => this._bindEvent(event));
            }

            this.isActive = true;
            return { success: true, name: config.extension_name };

        } catch (e) {
            console.error("[Nova] Load Error:", e.message);
            return { success: false, error: e.message };
        }
    }

    /**
     * Apply a single UI patch
     * @private
     */
    _applyPatch(patch) {
        if (!patch.target) return;

        // SAFETY: Only select elements with public contract selector
        const selector = `[data-flox="${patch.target}"]`;
        const elements = document.querySelectorAll(selector);

        if (elements.length === 0) {
            console.warn(`[Nova] Target "${patch.target}" not found`);
            return;
        }

        elements.forEach(el => {
            // Store original styles for rollback
            if (!this.originalStyles.has(el)) {
                this.originalStyles.set(el, el.getAttribute('style') || '');
            }

            // Apply CSS styles safely
            if (patch.style && typeof patch.style === 'object') {
                Object.keys(patch.style).forEach(prop => {
                    el.style[prop] = patch.style[prop];
                });
            }

            // Execute animation via Anime.js
            if (patch.animation && window.anime) {
                const animConfig = {
                    targets: el,
                    duration: patch.animation.duration || 800,
                    easing: this._getEasing(patch.animation.easing)
                };

                // Map animation properties
                ['translateX', 'translateY', 'scale', 'opacity', 'rotate'].forEach(prop => {
                    if (patch.animation[prop] !== undefined) {
                        animConfig[prop] = patch.animation[prop];
                    }
                });

                anime(animConfig);
            }
        });

        console.log(`[Nova] Patch applied to "${patch.target}"`);
    }

    /**
     * Bind a declarative event
     * @private
     */
    _bindEvent(event) {
        if (!event.target || !event.trigger || !event.action) return;

        const selector = `[data-flox="${event.target}"]`;
        const elements = document.querySelectorAll(selector);

        elements.forEach(el => {
            const handler = () => this._executeAction(el, event.action, event.params);
            el.addEventListener(event.trigger, handler);

            // Store for cleanup
            this.boundEvents.push({ el, trigger: event.trigger, handler });
        });
    }

    /**
     * Execute an allowed API action
     * @private
     */
    _executeAction(el, action, params) {
        switch (action) {
            case 'animate':
                if (window.anime && params) {
                    anime({
                        targets: el,
                        ...params,
                        easing: this._getEasing(params.easing)
                    });
                }
                break;
            case 'toggle':
                el.style.display = el.style.display === 'none' ? '' : 'none';
                break;
            case 'show':
                el.style.display = '';
                el.style.opacity = '1';
                break;
            case 'hide':
                el.style.display = 'none';
                break;
            default:
                console.warn(`[Nova] Unknown action: ${action}`);
        }
    }

    /**
     * Get Anime.js easing function
     * @private
     */
    _getEasing(type) {
        const easings = {
            'spring': 'easeOutElastic(1, .6)',
            'bounce': 'easeOutBounce',
            'smooth': 'easeInOutQuad',
            'snap': 'easeOutExpo'
        };
        return easings[type] || 'easeOutQuad';
    }

    /**
     * Disable extension and rollback all changes
     */
    disable() {
        // Restore original styles
        this.originalStyles.forEach((originalStyle, el) => {
            el.setAttribute('style', originalStyle);
        });
        this.originalStyles.clear();

        // Remove event listeners
        this.boundEvents.forEach(({ el, trigger, handler }) => {
            el.removeEventListener(trigger, handler);
        });
        this.boundEvents = [];

        this.isActive = false;
        console.log("[Nova] Extension disabled and rolled back");
    }
}

// Global instance
window.Nova = new NovaExtensionLoader();
