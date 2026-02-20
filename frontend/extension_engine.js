/**
 * Loop Extension Runtime Engine v2.0
 * ----------------------------------------
 * Provides platform capability APIs for marketplace extensions.
 * Extensions use ctx.chat, ctx.ui, ctx.template - never raw fetch/DOM.
 */

const FloxExtensionEngine = {
    installed: [],
    chatSubscriptions: {},
    chatLastId: {},
    pollIntervals: {},

    async init() {
        console.log('%c [FloxEngine] Initializing extension layer... ', 'background: #9333ea; color: #fff; font-weight: 800;');

        try {
            const r = await fetch('../backend/get_installed_extensions.php');
            const d = await r.json();

            if (d.success && d.extensions) {
                this.installed = d.extensions;
                this.loadAll();
            }
        } catch (e) {
            console.error('[FloxEngine] Failed to load extensions:', e);
        }
    },

    loadAll() {
        this.installed.forEach(ext => {
            console.log(`%c [FloxEngine] Mounting: ${ext.name} `, 'color: #a855f7; font-weight: bold;');
            this.mountExtension(ext);
        });
    },

    mountExtension(ext) {
        const files = ext.files || {};
        const extId = ext.id;

        // 1. Inject CSS
        if (files['styles.css']) {
            const styleId = 'ext-styles-' + extId;
            if (!document.getElementById(styleId)) {
                const style = document.createElement('style');
                style.id = styleId;
                style.textContent = files['styles.css'].replace(/\[data-flox=/g, `[data-flox-ext="${extId}"] [data-flox=`);
                document.head.appendChild(style);
            }
        }

        // 2. Mount Slots (HTML)
        if (files['slots.html']) {
            this.mountSlots(extId, files['slots.html']);
        }

        // 3. Run Scripts with full ctx
        if (files['scripts.js']) {
            this.runScript(extId, files['scripts.js'], ext.name);
        }
    },

    mountSlots(extId, htmlStr) {
        const parser = new DOMParser();
        let raw = htmlStr.trim();
        if (!raw) return;

        raw = raw.replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, "")
            .replace(/on\w+="[^"]*"/gi, "")
            .replace(/on\w+='[^']*'/gi, "");

        const fragment = parser.parseFromString(raw, 'text/html').body;

        fragment.querySelectorAll('[data-slot]').forEach(slot => {
            this.injectToTarget(slot.dataset.slot, slot.innerHTML, extId);
            slot.remove();
        });

        const remaining = fragment.innerHTML.trim();
        if (remaining) {
            const defaultTarget = document.querySelector('[data-flox="home.main"]') ? 'home.main' : 'header';
            this.injectToTarget(defaultTarget, remaining, extId);
        }
    },

    injectToTarget(targetName, content, extId) {
        let container = document.querySelector(`[data-flox="${targetName}"]`);

        if (!container && targetName.includes('.')) container = document.querySelector(`[data-flox="${targetName.split('.')[0]}"]`);
        if (!container && targetName.startsWith('chat.')) container = document.querySelector('[data-flox="chat.panel"]');
        if (!container && targetName.includes('sidebar')) container = document.querySelector('[data-flox="sidebar"]');

        if (container) {
            container.querySelectorAll(`[data-flox-extension="${extId}"]`).forEach(el => el.remove());

            const wrapper = document.createElement('div');
            wrapper.setAttribute('data-flox-extension', extId);
            wrapper.style.cssText = 'display:block; width:100%; position:relative; z-index:999; pointer-events:auto;';
            wrapper.innerHTML = content;
            container.appendChild(wrapper);

            document.body.setAttribute('data-flox-ext', extId);
        }
    },

    /**
     * Build the full ctx object with platform capabilities
     */
    buildContext(extId, name) {
        const engine = this;

        return {
            id: extId,
            name: name,
            template: window.FLOX_CTX?.template || { user: { name: "Guest", username: "@guest", isPro: false } },

            // ========== ctx.ui ==========
            ui: {
                log: (msg, type) => {
                    const style = type === 'error' ? 'color:#ef4444' : type === 'success' ? 'color:#22c55e' : 'color:#3b82f6';
                    console.log(`%c[${name}] ${msg}`, style);
                },

                append: (selector, html) => {
                    const container = document.querySelector(`[data-flox-extension="${extId}"] ${selector}`);
                    if (container) {
                        container.insertAdjacentHTML('beforeend', html);
                    }
                },

                scrollToBottom: (selector) => {
                    const container = document.querySelector(`[data-flox-extension="${extId}"] ${selector}`);
                    if (container) {
                        container.scrollTop = container.scrollHeight;
                    }
                },

                onSubmit: (selector, callback) => {
                    const input = document.querySelector(`[data-flox-extension="${extId}"] ${selector}`);
                    if (input) {
                        input.addEventListener('keydown', (e) => {
                            if (e.key === 'Enter' && !e.shiftKey) {
                                e.preventDefault();
                                const text = input.value;
                                input.value = '';
                                callback(text);
                            }
                        });
                    }
                }
            },

            // ========== ctx.chat ==========
            chat: {
                subscribe: (room, callback) => {
                    engine.chatSubscriptions[room] = callback;
                    engine.chatLastId[room] = 0;

                    // Load history first
                    fetch(`../backend/extension_chat_api.php?action=history&room=${room}&limit=20`)
                        .then(r => r.json())
                        .then(d => {
                            if (d.success && d.messages) {
                                d.messages.forEach(msg => {
                                    callback(msg);
                                    if (msg.id > engine.chatLastId[room]) {
                                        engine.chatLastId[room] = msg.id;
                                    }
                                });
                            }
                        });

                    // Start polling for new messages
                    engine.pollIntervals[room] = setInterval(() => {
                        fetch(`../backend/extension_chat_api.php?action=poll&room=${room}&since=${engine.chatLastId[room]}`)
                            .then(r => r.json())
                            .then(d => {
                                if (d.success && d.messages) {
                                    d.messages.forEach(msg => {
                                        callback(msg);
                                        if (msg.id > engine.chatLastId[room]) {
                                            engine.chatLastId[room] = msg.id;
                                        }
                                    });
                                }
                            })
                            .catch(() => { });
                    }, 2000);
                },

                send: (room, text) => {
                    fetch('../backend/extension_chat_api.php?action=send&room=' + room, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ text: text })
                    }).catch(() => { });
                }
            }
        };
    },

    runScript(extId, code, name) {
        try {
            const ctx = this.buildContext(extId, name);

            // Inject ctx into the function scope
            const runner = new Function('ctx', `
                "use strict";
                try {
                    ${code}
                } catch(e) {
                    ctx.ui.log("Runtime: " + e.message, "error");
                }
            `);
            runner(ctx);
        } catch (e) {
            console.error(`[Extension:${name}] Parsing Error:`, e);
        }
    }
};

// Auto-boot on load
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => FloxExtensionEngine.init());
} else {
    FloxExtensionEngine.init();
}
