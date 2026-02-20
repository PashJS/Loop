// Icon replacement utility - replaces Bootstrap icons with Font Awesome
document.addEventListener('DOMContentLoaded', () => {
    // Icon mapping from Bootstrap to Font Awesome
    const iconMap = {
        'bi-search': 'fa-solid fa-magnifying-glass',
        'bi-plus-circle-fill': 'fa-solid fa-circle-plus',
        'bi-person-circle': 'fa-solid fa-circle-user',
        'bi-film': 'fa-solid fa-film',
        'bi-box-arrow-right': 'fa-solid fa-right-from-bracket',
        'bi-hand-thumbs-up': 'fa-solid fa-thumbs-up',
        'bi-hand-thumbs-down': 'fa-solid fa-thumbs-down',
        'bi-heart': 'fa-regular fa-heart',
        'bi-heart-fill': 'fa-solid fa-heart',
        'bi-chat-left-text': 'fa-solid fa-comments',
        'bi-chat-left': 'fa-solid fa-comment',
        'bi-reply': 'fa-solid fa-reply',
        'bi-eye': 'fa-solid fa-eye',
        'bi-cloud-upload': 'fa-solid fa-cloud-arrow-up',
        'bi-image': 'fa-solid fa-image',
        'bi-file-earmark-play': 'fa-solid fa-file-video',
        'bi-x': 'fa-solid fa-xmark',
        'bi-x-lg': 'fa-solid fa-xmark',
        'bi-check': 'fa-solid fa-check',
        'bi-check-lg': 'fa-solid fa-check',
        'bi-arrow-left': 'fa-solid fa-arrow-left',
        'bi-arrow-right': 'fa-solid fa-arrow-right',
        'bi-chevron-down': 'fa-solid fa-chevron-down',
        'bi-chevron-up': 'fa-solid fa-chevron-up',
        'bi-three-dots': 'fa-solid fa-ellipsis',
        'bi-three-dots-vertical': 'fa-solid fa-ellipsis-vertical',
        'bi-pencil': 'fa-solid fa-pencil',
        'bi-trash': 'fa-solid fa-trash',
        'bi-gear': 'fa-solid fa-gear',
        'bi-bell': 'fa-solid fa-bell',
        'bi-bell-fill': 'fa-solid fa-bell',
        'bi-play-fill': 'fa-solid fa-play',
        'bi-pause-fill': 'fa-solid fa-pause',
        'bi-volume-up': 'fa-solid fa-volume-high',
        'bi-volume-mute': 'fa-solid fa-volume-xmark',
        'bi-fullscreen': 'fa-solid fa-expand',
        'bi-fullscreen-exit': 'fa-solid fa-compress',
        'bi-share': 'fa-solid fa-share',
        'bi-download': 'fa-solid fa-download',
        'bi-bookmark': 'fa-regular fa-bookmark',
        'bi-bookmark-fill': 'fa-solid fa-bookmark',
        'bi-flag': 'fa-regular fa-flag',
        'bi-flag-fill': 'fa-solid fa-flag',
        'bi-exclamation-triangle': 'fa-solid fa-triangle-exclamation',
        'bi-info-circle': 'fa-solid fa-circle-info',
        'bi-check-circle': 'fa-solid fa-circle-check',
        'bi-x-circle': 'fa-solid fa-circle-xmark',
        'bi-cast': 'fa-brands fa-chromecast'
    };

    // Replace all Bootstrap icons with Font Awesome
    function replaceIcons() {
        Object.keys(iconMap).forEach(bootstrapClass => {
            const elements = document.querySelectorAll(`.${bootstrapClass}, i.${bootstrapClass}`);
            elements.forEach(el => {
                const faClass = iconMap[bootstrapClass];
                el.className = el.className.replace(bootstrapClass, faClass);
            });
        });
    }

    // Run replacement
    replaceIcons();

    // Watch for dynamically added elements
    const observer = new MutationObserver(() => {
        replaceIcons();
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
});

