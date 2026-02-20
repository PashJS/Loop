/* Mobile Footer Scroll Behavior Script - V4 (Force Chat internal scroll) */
(function () {
    const footer = document.querySelector('.mobile-footer');
    if (!footer) return;

    // EXCLUDE CLIPS PAGE
    if (document.querySelector('.clips-feed')) {
        footer.classList.remove('hidden');
        return;
    }

    let lastY = 0;

    function updateFooter(diff, currentY, target) {
        // PREVENT HIDING IF SCROLLING CHAT INPUT
        if (target && (target.closest('.chat-input-container') || target.classList.contains('chat-input-container'))) {
            return;
        }

        const threshold = 5;
        if (Math.abs(diff) < threshold) return;

        if (diff > 0 && currentY > 20) {
            footer.classList.add('hidden');
        } else if (diff < 0) {
            footer.classList.remove('hidden');
        }
    }

    // 1. GLOBAL WINDOW SCROLL (For Home page, etc)
    window.addEventListener('scroll', (e) => {
        const currentY = window.pageYOffset || document.documentElement.scrollTop;
        const diff = currentY - lastY;
        updateFooter(diff, currentY, e.target);
        lastY = currentY;
    }, { passive: true });

    // 2. INTERNAL ELEMENT SCROLL (CRITICAL FOR CHAT MESSAGES)
    // We use a capture listener to catch scrolls on any div like .messages-viewport
    let lastInternalY = 0;
    document.addEventListener('scroll', function (e) {
        // Ignore the window scroll event here as it's handled above
        if (e.target === document || e.target === window) return;

        const currentY = e.target.scrollTop;
        const diff = currentY - (e.target.lastStoredY || 0);

        // Debug: console.log('Internal Scroll on:', e.target.id, 'Y:', currentY, 'Diff:', diff);

        updateFooter(diff, currentY, e.target);
        e.target.lastStoredY = currentY;
    }, true); // The 'true' here is the magic - it's the "capture" phase

    // 3. AUTO-SHOW ON CHAT CHANGE
    // When you click a new contact, the #chatWindow content changes. 
    // We want the footer to show up so you aren't "lost" without navigation.
    const observer = new MutationObserver(() => {
        footer.classList.remove('hidden');
    });

    const chatWindow = document.getElementById('chatWindow');
    if (chatWindow) {
        observer.observe(chatWindow, { childList: true });
    }
})();
