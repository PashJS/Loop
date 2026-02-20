// ============================================
// POPUP MODAL SYSTEM
// ============================================
class Popup {
    static show(message, type = 'info', duration = 3000) {
        // Remove existing popups
        const existing = document.querySelectorAll('.popup-container');
        existing.forEach(p => p.remove());

        const popup = document.createElement('div');
        popup.className = 'popup-container';
        popup.innerHTML = `
            <div class="popup-content popup-${type}">
                <div class="popup-icon">
                    ${this.getIcon(type)}
                </div>
                <div class="popup-message">${message}</div>
                <button class="popup-close" onclick="this.parentElement.parentElement.remove()">×</button>
            </div>
        `;

        document.body.appendChild(popup);

        // Auto remove after duration
        if (duration > 0) {
            setTimeout(() => {
                if (popup.parentElement) {
                    popup.remove();
                }
            }, duration);
        }

        // Animate in
        setTimeout(() => popup.classList.add('show'), 10);

        return popup;
    }

    static getIcon(type) {
        const icons = {
            success: '<i class="fa-solid fa-circle-check"></i>',
            error: '<i class="fa-solid fa-circle-xmark"></i>',
            warning: '<i class="fa-solid fa-triangle-exclamation"></i>',
            info: '<i class="fa-solid fa-circle-info"></i>'
        };
        return icons[type] || icons.info;
    }

    static confirm(message, options = {}) {
        const { header = 'Confirm', onConfirm, onCancel, confirmText = 'Confirm', cancelText = 'Cancel' } = options;
        return new Promise((resolve) => {
            const overlay = document.createElement('div');
            overlay.className = 'popup-overlay';
            overlay.innerHTML = `
                <div class="popup-dialog">
                    <div class="popup-dialog-header">
                        <h3>${header}</h3>
                    </div>
                    <div class="popup-dialog-body">
                        <p>${message}</p>
                    </div>
                    <div class="popup-dialog-footer">
                        <button class="btn-cancel">${cancelText}</button>
                        <button class="btn-confirm">${confirmText}</button>
                    </div>
                </div>
            `;

            document.body.appendChild(overlay);

            const confirmBtn = overlay.querySelector('.btn-confirm');
            const cancelBtn = overlay.querySelector('.btn-cancel');

            confirmBtn.onclick = () => {
                overlay.remove();
                resolve(true);
                if (onConfirm) onConfirm();
            };

            cancelBtn.onclick = () => {
                overlay.remove();
                resolve(false);
                if (onCancel) onCancel();
            };

            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) {
                    overlay.remove();
                    resolve(false);
                    if (onCancel) onCancel();
                }
            });
        });
    }
}

// Add popup styles
const popupStyles = document.createElement('style');
popupStyles.textContent = `
    .popup-container {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 10000;
        opacity: 0;
        transform: translateX(400px);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    .popup-container.show {
        opacity: 1;
        transform: translateX(0);
    }
    
    .popup-content {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 16px 20px;
        border-radius: 12px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
        min-width: 300px;
        max-width: 500px;
        position: relative;
        backdrop-filter: blur(10px);
    }
    
    .popup-success {
        background: rgba(76, 175, 80, 0.15);
        border: 1px solid rgba(76, 175, 80, 0.3);
        color: #4caf50;
    }
    
    .popup-error {
        background: rgba(255, 68, 68, 0.15);
        border: 1px solid rgba(255, 68, 68, 0.3);
        color: #ff4444;
    }
    
    .popup-warning {
        background: rgba(255, 193, 7, 0.15);
        border: 1px solid rgba(255, 193, 7, 0.3);
        color: #ffc107;
    }
    
    .popup-info {
        background: rgba(62, 166, 255, 0.15);
        border: 1px solid rgba(62, 166, 255, 0.3);
        color: #3ea6ff;
    }
    
    .popup-icon {
        font-size: 20px;
        flex-shrink: 0;
    }
    
    .popup-message {
        flex: 1;
        font-size: 14px;
        line-height: 1.5;
    }
    
    .popup-close {
        background: none;
        border: none;
        color: inherit;
        font-size: 24px;
        cursor: pointer;
        padding: 0;
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0.7;
        transition: opacity 0.2s;
    }
    
    .popup-close:hover {
        opacity: 1;
    }
    
    .popup-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.7);
        backdrop-filter: blur(4px);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10001;
        animation: fadeIn 0.2s ease;
    }
    
    .popup-dialog {
        background: var(--secondary-color, #1a1a1a);
        border-radius: 16px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
        max-width: 400px;
        width: 90%;
        overflow: hidden;
        animation: scaleIn 0.2s ease;
    }
    
    .popup-dialog-header {
        padding: 20px 24px;
        border-bottom: 1px solid var(--border-color, #303030);
    }
    
    .popup-dialog-header h3 {
        margin: 0;
        font-size: 18px;
        font-weight: 600;
        color: var(--text-primary, #ffffff);
    }
    
    .popup-dialog-body {
        padding: 24px;
        color: var(--text-primary, #ffffff);
        font-size: 14px;
        line-height: 1.6;
    }
    
    .popup-dialog-footer {
        padding: 16px 24px;
        display: flex;
        gap: 12px;
        justify-content: flex-end;
        border-top: 1px solid var(--border-color, #303030);
    }
    
    .popup-dialog-footer button {
        padding: 10px 20px;
        border: none;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .popup-dialog-footer .btn-cancel {
        background: var(--tertiary-color, #272727);
        color: var(--text-primary, #ffffff);
    }
    
    .popup-dialog-footer .btn-cancel:hover {
        background: var(--hover-bg, #2d2d2d);
    }
    
    .popup-dialog-footer .btn-confirm {
        background: var(--accent-color, #3ea6ff);
        color: white;
    }
    
    .popup-dialog-footer .btn-confirm:hover {
        background: var(--accent-hover, #2d8ce6);
    }
    
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    @keyframes scaleIn {
        from { transform: scale(0.9); opacity: 0; }
        to { transform: scale(1); opacity: 1; }
    }
`;
document.head.appendChild(popupStyles);

// Expose to window
window.Popup = Popup;

