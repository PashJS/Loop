// ============================================
// NOTIFICATIONS SYSTEM
// ============================================
let notifications = [];
let unreadCount = 0;

// Safety helper
const safeCharAt = (str, index = 0) => {
    if (typeof str !== 'string' || !str) return '';
    return str.charAt(index);
};

document.addEventListener('DOMContentLoaded', () => {
    const notificationsBtn = document.getElementById('notificationsBtn');
    const notificationsDropdown = document.getElementById('notificationsDropdown');

    if (notificationsBtn && notificationsDropdown) {
        // Request notification permission
        if ("Notification" in window && Notification.permission === "default") {
            Notification.requestPermission();
        }

        // Load notifications
        loadNotifications();

        // Refresh notifications every 30 seconds
        setInterval(loadNotifications, 30000);

        // Check for Pro Gifts
        checkProGifts();
        setInterval(checkProGifts, 60000);

        // Toggle dropdown
        notificationsBtn.addEventListener('click', (e) => {
            if (window.innerWidth <= 768) {
                window.location.href = 'notifications.php';
                return;
            }
            e.stopPropagation();
            notificationsDropdown.classList.toggle('active');
            if (notificationsDropdown.classList.contains('active')) {
                // Immediately hide badge
                const badge = document.getElementById('notificationBadge');
                if (badge) badge.style.display = 'none';
                unreadCount = 0;

                // Mark all as read in backend
                markAllNotificationsRead();

                // Reload list (optional but good to show updated state)
                loadNotifications();
            }
        });

        // Close dropdown when clicking outside

        // Notification Settings Menu Logic
        const settingsBtn = document.getElementById('notificationSettingsBtn');
        const settingsMenu = document.getElementById('notificationSettingsMenu');

        if (settingsBtn && settingsMenu) {
            settingsBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                // Close main dropdown if open? No, it's inside main dropdown
                settingsMenu.classList.toggle('active');
            });
        }

        // DND Logic
        const dndBtn = document.getElementById('dndToggleBtn');
        const dndIcon = document.getElementById('dndIcon');

        // Initial DND state
        const isDnd = localStorage.getItem('floxwatch_dnd') === 'true';
        if (isDnd && dndIcon) {
            dndIcon.className = 'fa-solid fa-toggle-on';
            dndIcon.style.color = 'var(--accent-color)';
        }

        if (dndBtn && dndIcon) {
            dndBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                const current = localStorage.getItem('floxwatch_dnd') === 'true';
                const newState = !current;
                localStorage.setItem('floxwatch_dnd', newState);

                if (newState) {
                    dndIcon.className = 'fa-solid fa-toggle-on';
                    dndIcon.style.color = 'var(--accent-color)';
                    // Ensure we don't play sounds if DND is on
                } else {
                    dndIcon.className = 'fa-solid fa-toggle-off';
                    dndIcon.style.color = '';
                }
            });
        }

        // Close dropdowns when clicking outside
        document.addEventListener('click', (e) => {
            // Close settings menu if open
            if (settingsBtn && settingsMenu && !settingsBtn.contains(e.target) && !settingsMenu.contains(e.target)) {
                settingsMenu.classList.remove('active');
            }

            if (!notificationsBtn.contains(e.target) && !notificationsDropdown.contains(e.target)) {
                notificationsDropdown.classList.remove('active');
                // Reset view when closing
                setTimeout(() => {
                    const mainView = document.getElementById('notificationsMainView');
                    const commentView = document.getElementById('notificationCommentView');
                    if (mainView && commentView) {
                        mainView.style.display = 'flex';
                        commentView.style.display = 'none';
                    }
                }, 200);
            }
        });

        const backToNotifications = document.getElementById('backToNotifications');
        const notificationsMainView = document.getElementById('notificationsMainView');
        const notificationCommentView = document.getElementById('notificationCommentView');

        if (backToNotifications) {
            backToNotifications.addEventListener('click', (e) => {
                e.stopPropagation();
                notificationCommentView.style.display = 'none';
                notificationsMainView.style.display = 'flex';
            });
        }

        // Mark all as read
        const markAllReadBtn = document.getElementById('markAllReadBtn');
        if (markAllReadBtn) {
            markAllReadBtn.addEventListener('click', async () => {
                await markAllNotificationsRead();
                // Visual feedback
                markAllReadBtn.innerHTML = '<i class="fa-solid fa-check"></i> Done';
                setTimeout(() => {
                    markAllReadBtn.innerHTML = '<i class="fa-solid fa-check-double"></i> <span>Mark all read</span>';
                }, 2000);
            });
        }

        // Handle context menu clicks
        document.addEventListener('click', () => {
            const contextMenu = document.getElementById('notificationContextMenu');
            if (contextMenu) contextMenu.style.display = 'none';
        });
    }
});

async function loadNotifications() {
    try {
        console.log('Fetching notifications from:', '../backend/getNotifications.php?limit=50');
        const response = await fetch('../backend/getNotifications.php?limit=50');

        console.log('Response status:', response.status);
        console.log('Response ok:', response.ok);

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();
        console.log('Notifications data:', data);

        if (data.success) {
            const newNotifications = data.notifications || [];

            // Trigger browser notifications for new unread items
            if (notifications.length > 0) { // Don't notify on first load
                const existingIds = new Set(notifications.map(n => n.id));
                newNotifications.forEach(notif => {
                    if (!notif.is_read && !existingIds.has(notif.id)) {
                        showBrowserNotification(notif);
                    }
                });
            }

            notifications = newNotifications;
            unreadCount = data.unread_count || 0;
            updateNotificationsUI();
        } else {
            console.error('Failed to load notifications:', data.message);
            showNotificationError(data.message || 'Failed to load');
        }
    } catch (error) {
        console.error('Error loading notifications:', error);
        console.error('Error details:', error.message);
        showNotificationError(`Failed to load notifications: ${error.message}`);
    }
}

function showBrowserNotification(notif) {
    if ("Notification" in window && Notification.permission === "granted") {
        const n = new Notification("Loop", {
            body: notif.message,
            icon: notif.actor && notif.actor.profile_picture ? notif.actor.profile_picture : '../assets/logo.png', // Fallback to logo
            badge: '../assets/logo.png',
            tag: 'floxwatch-notif-' + notif.id
        });

        n.onclick = () => {
            window.focus();
            if (notif.target_type === 'message') {
                window.location.href = 'chat.php';
            } else if (notif.target_type === 'video' && notif.target_id) {
                window.location.href = 'videoid.php?id=' + notif.target_id;
            }
            n.close();
        };
    }
}

function showNotificationError(message) {
    const lists = document.querySelectorAll('#notificationsList, #fullNotificationsList');
    const empties = document.querySelectorAll('#notificationsEmpty, #fullNotificationsEmpty');

    lists.forEach(list => {
        list.innerHTML = `<div style="padding: 20px; text-align: center; color: var(--text-secondary); font-size: 14px;">${message}</div>`;
        list.style.display = 'block';
    });

    empties.forEach(empty => {
        empty.style.display = 'none';
    });
}

function updateNotificationsUI() {
    const badge = document.getElementById('notificationBadge');
    const dropdownList = document.getElementById('notificationsList');
    const dropdownEmpty = document.getElementById('notificationsEmpty');
    const fullList = document.getElementById('fullNotificationsList');
    const fullEmpty = document.getElementById('fullNotificationsEmpty');

    // Update badge
    if (badge) {
        if (unreadCount > 0) {
            badge.textContent = unreadCount > 99 ? '99+' : unreadCount;
            badge.style.display = 'block';
        } else {
            badge.style.display = 'none';
        }
    }

    // Prepare content with categorization
    const hasNotifications = notifications.length > 0;
    const important = notifications.filter(n => !n.is_read);
    const earlier = notifications.filter(n => n.is_read);

    function renderNotificationItem(notif, idx) {
        const delay = (idx % 15) * 0.05;
        const getActionIcon = (type) => {
            switch (type) {
                case 'video_like':
                case 'comment_like':
                    return '<div class="notification-type-badge like"><i class="fa-solid fa-thumbs-up"></i></div>';
                case 'video_comment':
                case 'comment_reply':
                    return '<div class="notification-type-badge comment"><i class="fa-solid fa-comment"></i></div>';
                case 'video_love':
                    return '<div class="notification-type-badge love"><i class="fa-solid fa-heart"></i></div>';
                case 'video_save':
                    return '<div class="notification-type-badge save"><i class="fa-solid fa-bookmark"></i></div>';
                case 'subscription':
                    return '<div class="notification-type-badge sub"><i class="fa-solid fa-user-plus"></i></div>';
                case 'comment_reaction':
                    return '<div class="notification-type-badge reaction"><i class="fa-solid fa-face-smile"></i></div>';
                case 'security_alert':
                    return '<div class="notification-type-badge security"><i class="fa-solid fa-shield-halved" style="background:#ef4444;"></i></div>';
                case 'message_request':
                    return '<div class="notification-type-badge message"><i class="fa-solid fa-message"></i></div>';
                default:
                    return '';
            }
        };

        const actionIcon = getActionIcon(notif.type);
        let actorAvatar = '';
        let actorInitial = '?';

        if (notif.type === 'security_alert') {
            actorInitial = '<i class="fa-solid fa-envelope"></i>';
        } else {
            actorAvatar = notif.actor && notif.actor.profile_picture
                ? `<img src="${escapeHtml(notif.actor.profile_picture)}" alt="${escapeHtml(notif.actor.username)}" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">`
                : '';
            actorInitial = (notif.actor && notif.actor.username) ? safeCharAt(notif.actor.username, 0).toUpperCase() : '?';
        }

        const videoThumbnail = notif.video_thumbnail
            ? `<div class="notification-video-preview">
                <img src="${escapeHtml(notif.video_thumbnail)}" alt="Preview">
               </div>`
            : '';

        const unreadIndicator = !notif.is_read ? '<div class="unread-dot"></div>' : '';

        return `
            <div class="notification-item ${!notif.is_read ? 'unread' : ''}" data-notification-id="${notif.id}" style="animation-delay: ${delay}s">
                ${unreadIndicator}
                <div class="notification-avatar">
                    ${actorAvatar}
                    <div class="avatar-fallback" style="display: ${notif.actor && notif.actor.profile_picture ? 'none' : 'flex'};">
                        ${actorInitial}
                    </div>
                    ${notif.group_count > 1 ? '<div class="group-badge">+' + (notif.group_count - 1) + '</div>' : actionIcon}
                </div>
                <div class="notification-content">
                    <div class="notification-message">${escapeHtml(notif.message)}</div>
                    <div class="notification-time">${formatTimeAgo(notif.created_at)}</div>
                </div>
                ${videoThumbnail}
                <div class="notification-menu-btn">
                    <i class="fa-solid fa-ellipsis-vertical"></i>
                </div>
            </div>
        `;
    }

    let content = '';
    if (hasNotifications) {
        if (important.length > 0) {
            content += `<div class="notif-section-title">Important</div>`;
            content += important.map((n, i) => renderNotificationItem(n, i)).join('');
        }

        if (earlier.length > 0) {
            content += `<div class="notif-section-title">More notifications</div>`;
            content += earlier.map((n, i) => renderNotificationItem(n, i + important.length)).join('');
        }
    }

    // Update Dropdown UI
    if (dropdownList && dropdownEmpty) {
        if (!hasNotifications) {
            dropdownList.innerHTML = '';
            dropdownList.style.display = 'none';
            dropdownEmpty.style.display = 'block';
        } else {
            dropdownEmpty.style.display = 'none';
            dropdownList.style.display = 'block';
            dropdownList.innerHTML = content;
        }
    }

    // Update Full Page UI
    if (fullList && fullEmpty) {
        if (!hasNotifications) {
            fullList.innerHTML = '';
            fullList.style.display = 'none';
            fullEmpty.style.display = 'block';
        } else {
            fullEmpty.style.display = 'none';
            fullList.style.display = 'block';
            fullList.innerHTML = content;
        }
    }

    // Re-add click handlers for all items
    document.querySelectorAll('.notification-item').forEach(item => {
        const notificationId = parseInt(item.dataset.notificationId);
        const contentArea = item.querySelector('.notification-content');
        const avatarArea = item.querySelector('.notification-avatar');

        const handleAction = async (e) => {
            if (notificationId) {
                const notif = notifications.find(n => n.id === notificationId);
                if (notif && !notif.is_read) {
                    await markNotificationRead(notificationId);
                }

                if (notif) {
                    if (notif.type === 'message_request') {
                        window.location.href = 'chat.php';
                    } else if (notif.target_type === 'comment' && notif.target_id) {
                        showCommentContext(notif.target_id);
                    } else if (notif.target_type === 'video' && notif.target_id) {
                        window.location.href = 'videoid.php?id=' + notif.target_id;
                    }
                }
            }
        };

        if (contentArea) contentArea.addEventListener('click', handleAction);
        if (avatarArea) avatarArea.addEventListener('click', handleAction);

        const menuBtn = item.querySelector('.notification-menu-btn');
        if (menuBtn) {
            menuBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                showNotificationContextMenu(e, notificationId);
            });
        }
    });
}

// function showNotificationContextMenu moved below to global scope

// Make available globally
window.handleNotificationHide = async function (notifId, action) {
    try {
        const response = await fetch('../backend/hideNotification.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ notif_id: notifId, action: action })
        });
        const data = await response.json();
        if (data.success) {
            // Refresh list
            loadNotifications();
        } else {
            console.error('Hide error:', data.message);
        }
    } catch (error) {
        console.error('Failed to hide notification:', error);
    }
    // Close menu
    const menu = document.getElementById('notificationContextMenu');
    if (menu) menu.style.display = 'none';
};

function showNotificationContextMenu(e, notificationId) {
    const menu = document.getElementById('notificationContextMenu');
    if (!menu) return;

    // Move to body to avoid overflow/z-index issues
    if (menu.parentElement !== document.body) {
        document.body.appendChild(menu);
    }

    const notif = notifications.find(n => n.id === notificationId);
    if (!notif) return;

    const type = notif.type || '';
    const typeLabel = type.includes('like') ? 'like' : (type.includes('comment') ? 'comment' : 'this');
    // For grouped notifications, we might want to hide all from the latest actor or just hide type
    const username = (notif.actor && notif.actor.username) ? notif.actor.username : 'this user';

    menu.innerHTML = `
        <div class="context-menu-item" onclick="window.handleNotificationHide('${notificationId}', 'hide_type')">
            <i class="fa-solid fa-eye-slash"></i>
            <span>Hide ${typeLabel} activity</span>
        </div>
        <div class="context-menu-item" onclick="window.handleNotificationHide('${notificationId}', 'hide_user')">
            <i class="fa-solid fa-user-slash"></i>
            <span>Hide notifications from @${username}</span>
        </div>
        <div class="context-menu-item" onclick="window.handleNotificationHide('${notificationId}', 'hide_this')">
            <i class="fa-solid fa-trash-can"></i>
            <span>Hide this notification</span>
        </div>
    `;

    // Position menu
    menu.style.display = 'flex';

    // Adjust position
    let x = e.clientX - 220;
    let y = e.clientY + 10;

    // Check bounds
    if (x < 10) x = 10;
    if (y + 150 > window.innerHeight) y = window.innerHeight - 160;

    menu.style.left = `${x}px`;
    menu.style.top = `${y}px`;

    // Add close listener
    const closeMenu = (ev) => {
        if (!menu.contains(ev.target) && ev.target !== e.target) {
            menu.style.display = 'none';
            document.removeEventListener('click', closeMenu);
        }
    };
    // Delay adding listener to avoid immediate close
    setTimeout(() => {
        document.addEventListener('click', closeMenu);
    }, 0);
}

async function markNotificationRead(notificationId) {
    try {
        const response = await fetch('../backend/markNotificationRead.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ notification_id: notificationId })
        });

        const data = await response.json();
        if (data.success) {
            // Update local state
            const notif = notifications.find(n => n.id === notificationId);
            if (notif) {
                notif.is_read = true;
                if (unreadCount > 0) unreadCount--;
            }
            updateNotificationsUI();
        }
    } catch (error) {
        console.error('Error marking notification as read:', error);
    }
}

async function markAllNotificationsRead() {
    try {
        const response = await fetch('../backend/markNotificationRead.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ mark_all: true })
        });

        const data = await response.json();
        if (data.success) {
            notifications.forEach(n => n.is_read = true);
            unreadCount = 0;
            updateNotificationsUI();
        }
    } catch (error) {
        console.error('Error marking all as read:', error);
    }
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatTimeAgo(dateString) {
    const now = new Date();
    const date = new Date(dateString);
    const diffInSeconds = Math.floor((now - date) / 1000);

    if (diffInSeconds < 60) {
        return 'just now';
    } else if (diffInSeconds < 3600) {
        const minutes = Math.floor(diffInSeconds / 60);
        return `${minutes}m ago`;
    } else if (diffInSeconds < 86400) {
        const hours = Math.floor(diffInSeconds / 3600);
        return `${hours}h ago`;
    } else if (diffInSeconds < 2592000) {
        const days = Math.floor(diffInSeconds / 86400);
        return `${days}d ago`;
    } else {
        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    }
}

async function checkProGifts() {
    try {
        const res = await fetch('../backend/check_pending_gifts.php');
        const data = await res.json();

        if (data.success && data.gift && !window.giftPopupShown) {
            window.giftPopupShown = true;

            const gift = data.gift;
            if (typeof Popup !== 'undefined') {
                Popup.confirm(`You got a gift from <b>${gift.sender_name}</b>: 1 Week of Loop Pro!`, {
                    header: '🎉 Special Gift!',
                    confirmText: 'Accept',
                    cancelText: 'Decline',
                    onConfirm: () => respondToGift(gift.id, 'accept'),
                    onCancel: () => respondToGift(gift.id, 'decline')
                });
            }
        }
    } catch (e) {
        console.error('Gift Check Error', e);
    }
}

async function respondToGift(giftId, action) {
    try {
        const res = await fetch('../backend/respond_to_gift.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ gift_id: giftId, action: action })
        });
        const data = await res.json();

        if (data.success) {
            Popup.show(`Gift ${action}ed!`, 'success');
            if (action === 'accept') {
                setTimeout(() => location.reload(), 2000);
            }
        } else {
            Popup.show(data.message, 'error');
        }
    } catch (e) {
        console.error('Gift Response Error', e);
    }
}

function renderNotifComment(comment, isReply = false, isHighlighted = false) {
    const avatar = comment.author.profile_picture
        ? `<img src="${escapeHtml(comment.author.profile_picture)}" alt="${escapeHtml(comment.author.username)}">`
        : `<div class="avatar-fallback">${safeCharAt((comment.author && comment.author.username ? comment.author.username : '?'), 0).toUpperCase()}</div>`;

    return `
        <div class="notif-comment-item ${isReply ? 'is-reply' : ''} ${isHighlighted ? 'highlighted' : ''}">
            <div class="notif-comment-avatar">
                ${avatar}
            </div>
            <div class="notif-comment-details">
                ${isHighlighted ? '<div class="notif-highlight-badge">Highlighted comment</div>' : ''}
                <div class="notif-comment-header">
                    <span class="notif-comment-author">${escapeHtml(comment.author && comment.author.username ? comment.author.username : 'Deleted User')}</span>
                    <span class="notif-comment-time">${formatTimeAgo(comment.created_at)}</span>
                </div>
                <div class="notif-comment-text">${escapeHtml(comment.comment)}</div>
                <div class="notif-comment-actions">
                    <div class="notif-comment-action ${comment.is_liked ? 'active' : ''}">
                        <i class="fa-regular fa-thumbs-up"></i>
                        <span>${comment.likes || ''}</span>
                    </div>
                    <div class="notif-comment-action">
                        <i class="fa-regular fa-thumbs-down"></i>
                    </div>
                    <div class="notif-comment-action">
                        <span>Reply</span>
                    </div>
                </div>
            </div>
        </div>
    `;
}

async function showCommentContext(commentId) {
    const mainView = document.getElementById('notificationsMainView');
    const commentView = document.getElementById('notificationCommentView');
    const content = document.getElementById('notificationCommentContent');

    if (!mainView || !commentView || !content) return;

    // Show loading or transition
    mainView.style.display = 'none';
    commentView.style.display = 'flex';
    content.innerHTML = '<div class="loading-notifications">Loading thread...</div>';

    try {
        const response = await fetch(`../backend/getCommentContext.php?comment_id=${commentId}`);
        const data = await response.json();

        if (data.success && data.comment) {
            const root = data.comment;
            let html = '<div class="notif-comment-thread">';

            // Render root comment
            html += renderNotifComment(root, false, root.id == commentId);

            // Render replies
            if (root.replies && root.replies.length > 0) {
                root.replies.forEach(reply => {
                    html += renderNotifComment(reply, true, reply.id == commentId);
                });
            }

            html += '</div>';
            content.innerHTML = html;
        } else {
            content.innerHTML = `<div class="notifications-empty"><p>${data.message || 'Comment not found'}</p></div>`;
        }
    } catch (error) {
        console.error('Error fetching comment context:', error);
        content.innerHTML = '<div class="notifications-empty"><p>Error loading comment thread</p></div>';
    }
}
