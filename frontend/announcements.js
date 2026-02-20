/**
 * Announcements / Poll Frontend Logic
 */
if (typeof FloxPoll === 'undefined') {
    const FloxPoll = {
        init() {
            this.container = document.createElement('div');
            this.container.className = 'poll-widget';
            this.container.id = 'floxPollWidget';
            document.body.appendChild(this.container);

            this.fetchActivePoll();
        },

        async fetchActivePoll() {
            try {
                const response = await fetch('../backend/get_active_announcement.php');
                const data = await response.json();

                if (data.success && data.announcement) {
                    // If the user has already voted on this specific poll, don't show it at all
                    if (data.announcement.has_voted) {
                        return;
                    }
                    this.renderPoll(data.announcement);
                }
            } catch (error) {
                console.error('Failed to fetch poll:', error);
            }
        },

        renderPoll(poll) {
            let content = `
                <div class="poll-header">
                    <div class="poll-title">
                        <i class="fa-solid fa-square-poll-vertical"></i>
                        Community Poll
                    </div>
                    <button class="poll-close" onclick="FloxPoll.hide()"><i class="fa-solid fa-times"></i></button>
                </div>
                <div id="pollBody">
                    <div class="poll-context">${poll.context}</div>
                    <div class="poll-options">
            `;

            poll.options.forEach(opt => {
                content += `
                    <button class="poll-option-btn" onclick="FloxPoll.vote(${poll.id}, ${opt.id})">
                        ${opt.label}
                    </button>
                `;
            });

            content += `
                    </div>
                </div>
            `;

            this.container.innerHTML = content;
            this.show();
        },

        async vote(announcementId, optionId) {
            try {
                const btn = event.currentTarget;
                const originalText = btn.innerHTML;
                btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Voting...';
                btn.style.pointerEvents = 'none';

                const response = await fetch('../backend/vote_announcement.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ announcement_id: announcementId, option_id: optionId })
                });

                const data = await response.json();

                if (data.success) {
                    // Success: Show thank you message and fade out
                    const pollBody = document.getElementById('pollBody');
                    pollBody.style.transition = 'opacity 0.3s ease';
                    pollBody.style.opacity = '0';

                    setTimeout(() => {
                        pollBody.innerHTML = `
                            <div style="text-align: center; padding: 20px 0;">
                                <i class="fa-solid fa-circle-check" style="font-size: 32px; color: #3ea6ff; margin-bottom: 15px;"></i>
                                <div style="font-size: 16px; font-weight: 600; color: #fff; line-height: 1.4;">
                                    Thank you for helping us improve Loop
                                </div>
                            </div>
                        `;
                        pollBody.style.opacity = '1';
                    }, 300);

                    // Hide completely after 3 seconds
                    setTimeout(() => {
                        this.container.style.transform = 'translateY(100px)';
                        this.container.style.opacity = '0';
                        setTimeout(() => this.hide(), 500);
                    }, 3000);
                } else {
                    btn.innerHTML = originalText;
                    btn.style.pointerEvents = 'auto';
                }
            } catch (error) {
                console.error('Vote error:', error);
            }
        },

        show() {
            this.container.style.display = 'block';
        },

        hide() {
            this.container.style.display = 'none';
            this.container.style.transform = '';
            this.container.style.opacity = '';
        }
    };

    document.addEventListener('DOMContentLoaded', () => FloxPoll.init());
}
