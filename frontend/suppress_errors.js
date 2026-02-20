/**
 * FloxWatch Error Suppressor v2.9 (Nuclear Silence)
 * Intercepts requests and rejections at the source to prevent error generation.
 */
(function () {
    const isNoise = (val) => {
        if (!val) return false;
        if (typeof val === 'object') {
            const v = val;
            if ((v.name === 'i' || v.name === 'IncompleteError') && v.code === 403) return true;
            if (v.code === 403 && (v.httpStatus === 200 || v.status === 200)) return true;
            if (v.reqInfo && (v.reqInfo.path === '/template_list' || v.reqInfo.pathPrefix === '/site_integration')) return true;
            if (v.message === 'permission error' || (v.data && v.data.msg === 'permission error')) return true;
        }
        try {
            const s = (val.stack || val.message || JSON.stringify(val) || "").toLowerCase();
            return ['grammarly', 'jiifimnepkibjfjbppnjble', 'extension', 'isolated-world', 'chrome-extension://'].some(p => s.includes(p));
        } catch (e) { return false; }
    };

    // --- 1. PROACTIVE INTERCEPTION (Fetch/XHR) ---
    // Divert any extension-driven requests before they can even fail
    const originalFetch = window.fetch;
    window.fetch = function (...args) {
        const url = args[0] ? args[0].toString() : '';
        if (url.includes('/site_integration') || url.includes('/template_list')) {
            // Return a fake success or a never-resolving promise to prevent noise
            return new Promise(() => { });
        }
        return originalFetch.apply(this, args).catch(err => {
            if (isNoise(err)) return new Promise(() => { }); // Sink the error
            throw err;
        });
    };

    const originalXHR = window.XMLHttpRequest.prototype.open;
    window.XMLHttpRequest.prototype.open = function (method, url) {
        if (typeof url === 'string' && (url.includes('/site_integration') || url.includes('/template_list'))) {
            this._isNoise = true;
        }
        return originalXHR.apply(this, arguments);
    };

    const originalXHRSend = window.XMLHttpRequest.prototype.send;
    window.XMLHttpRequest.prototype.send = function () {
        if (this._isNoise) {
            // Silent abort
            return;
        }
        return originalXHRSend.apply(this, arguments);
    };

    // --- 2. GLOBAL HANDLERS ---
    const silence = (e) => {
        if (isNoise(e.reason || e.error || e)) {
            if (e.preventDefault) e.preventDefault();
            if (e.stopImmediatePropagation) e.stopImmediatePropagation();
            return true;
        }
        return false;
    };

    window.addEventListener('unhandledrejection', silence, true);
    window.addEventListener('error', silence, true);
    window.onunhandledrejection = silence;
    window.onerror = (m, u, l, c, e) => { if (silence(e || m)) return true; };

    // --- 3. CONSOLE OVERRIDE ---
    const originalError = console.error;
    console.error = function (...args) {
        if (args.some(arg => isNoise(arg))) return;
        originalError.apply(console, args);
    };

    console.log("%c [FloxSuppression] Nuclear Silence v2.9 active. ", "color:#00ff00; font-weight:bold;");
})();
