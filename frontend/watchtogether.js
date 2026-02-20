/**
 * ============================================
 * WATCHTOGETHER - Synchronized Video Watching
 * Core JavaScript Module
 * ============================================
 */

(function () {
    'use strict';

    // State Management
    let wtState = {
        isActive: false,
        isAdmin: false,
        sessionId: null,
        groupId: null,
        currentVideo: null,
        participants: new Map(),
        ws: null,
        videoPlayer: null,
        isChatMode: false,
        localStream: null,
        syncTolerance: 1.5,
        lastSyncTime: 0,
        lastUIUpdate: 0,
        currentTab: 'server',
        waitingAudio: null,
        waitingSoundMuted: false,
        replyTo: null,
        game: {
            active: false,
            type: null,
            opponentId: null,
            opponentName: null,
            mySymbol: null, // 'X' or 'O'
            board: Array(9).fill(null),
            turn: null, // userId whose turn it is
            canvas: null,
            ctx: null
        },
        pendingInvites: new Map() // user_id -> name
    };

    // Active parties tracking (for "Party ongoing" display)
    let activeParties = new Map(); // groupId -> sessionInfo

    // Helper: Resolve profile picture URL - FIXED
    function resolveProfilePic(pic) {
        if (!pic) return 'https://www.gravatar.com/avatar/00?d=mp';
        if (pic.startsWith('http') || pic.startsWith('data:')) return pic;
        // Handle different path formats
        pic = pic.replace(/^\.\//, '').replace(/^\//, '');
        if (pic.startsWith('uploads/')) {
            return '../' + pic;
        }
        if (!pic.startsWith('../')) {
            return '../' + pic;
        }
        return pic;
    }

    // Helper: Escape HTML
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Helper: Format time (seconds -> mm:ss or hh:mm:ss)
    function formatTime(seconds) {
        if (isNaN(seconds) || !isFinite(seconds)) return '0:00';
        const h = Math.floor(seconds / 3600);
        const m = Math.floor((seconds % 3600) / 60);
        const s = Math.floor(seconds % 60);
        if (h > 0) {
            return `${h}:${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`;
        }
        return `${m}:${s.toString().padStart(2, '0')}`;
    }

    // Helper: Format view count
    function formatViews(views) {
        if (views >= 1000000) return (views / 1000000).toFixed(1) + 'M';
        if (views >= 1000) return (views / 1000).toFixed(1) + 'K';
        return views.toString();
    }

    // Initialize WatchTogether
    function initWatchTogether(groupId, isCreator = false) {
        wtState.groupId = groupId;
        wtState.isAdmin = isCreator;
        wtState.sessionId = `wt_${groupId}_${Date.now()}`;

        createWatchTogetherUI();
        connectToSession();

        if (isCreator) {
            addSelfAsParticipant();
            // Broadcast party started
            broadcastPartyStatus(groupId, true);
        }

        wtState.isActive = true;
        window.wtIsActive = true; // Global flag for other scripts
        document.body.style.overflow = 'hidden';
        document.body.classList.add('wt-active');

        // Start waiting sound (very quiet, looped)
        startWaitingSound();

        // HEAVY-DUTY LAG FIX: Request high-priority rendering
        requestWakeLock();
        setupMediaSession();
        ensurePriorityProcess();
    }

    // Keep the renderer alive in windowed mode
    function ensurePriorityProcess() {
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (AudioContext) {
                const ctx = new AudioContext();
                const oscillator = ctx.createOscillator();
                const gain = ctx.createGain();
                gain.gain.value = 0.0001; // Silent
                oscillator.connect(gain);
                gain.connect(ctx.destination);
                oscillator.start();
                wtState.priorityCtx = ctx;
            }
        } catch (e) { }
    }

    // Broadcast party status to chat
    function broadcastPartyStatus(groupId, started) {
        if (typeof ws !== 'undefined' && ws && ws.readyState === WebSocket.OPEN) {
            ws.send(JSON.stringify({
                type: started ? 'WATCHTOGETHER_PARTY_STARTED' : 'WATCHTOGETHER_PARTY_ENDED',
                group_id: groupId,
                session_id: wtState.sessionId,
                admin_id: typeof myUserId !== 'undefined' ? myUserId : 0,
                admin_name: typeof myUsername !== 'undefined' ? myUsername : 'Host'
            }));
        }
    }

    // Get my profile pic safely
    function getMyProfilePic() {
        if (typeof myProfilePic !== 'undefined' && myProfilePic) {
            return resolveProfilePic(myProfilePic);
        }
        return 'https://www.gravatar.com/avatar/00?d=mp';
    }

    // Get my username safely
    function getMyUsername() {
        return typeof myUsername !== 'undefined' ? myUsername : 'You';
    }

    // Create the main UI
    function createWatchTogetherUI() {
        const existingOverlay = document.getElementById('watchTogetherOverlay');
        if (existingOverlay) existingOverlay.remove();

        const myPic = getMyProfilePic();
        const myName = getMyUsername();

        const overlay = document.createElement('div');
        overlay.id = 'watchTogetherOverlay';
        overlay.className = 'watchtogether-overlay';
        overlay.innerHTML = `
            <!-- Main Video Area -->
            <div class="wt-video-area">
                <!-- Header -->
                <div class="wt-header visible" id="wtHeader">
                    <div class="wt-session-info">
                        <div class="wt-session-badge">
                            <div class="live-dot"></div>
                            <span>WATCHING TOGETHER</span>
                        </div>
                        <div class="wt-session-title" id="wtSessionTitle">Waiting for video...</div>
                    </div>
                    <div class="wt-header-actions">
                        <button class="wt-header-btn" id="wtMuteWaitingBtn" title="Toggle Waiting Music">
                            <i class="fa-solid fa-music" id="wtWaitingVolumeIcon"></i>
                        </button>
                        <button class="wt-header-btn" id="wtToggleMicBtn" title="Toggle Microphone">
                            <i class="fa-solid fa-microphone-slash"></i>
                        </button>
                        <button class="wt-header-btn" id="wtToggleCamBtn" title="Toggle Camera">
                            <i class="fa-solid fa-video-slash"></i>
                        </button>
                        <button class="wt-header-btn" id="wtInviteBtn" title="Invite Friends">
                            <i class="fa-solid fa-user-plus"></i>
                        </button>
                        <button class="wt-header-btn" id="wtGamesBtn" title="Play Games">
                            <i class="fa-solid fa-gamepad"></i>
                        </button>
                        <button class="wt-header-btn" id="wtSettingsBtn" title="Settings">
                            <i class="fa-solid fa-gear"></i>
                        </button>
                        <button class="wt-header-btn danger" id="wtLeaveBtn" title="Leave Session">
                            <i class="fa-solid fa-door-open"></i>
                        </button>
                    </div>
                </div>

                <!-- Video Player Container -->
                <div class="wt-player-container" id="wtPlayerContainer">
                    <!-- Starfield Background for Waiting -->
                    <canvas class="wt-stars-canvas" id="wtStarsCanvas"></canvas>
                    
                    <video class="wt-video-player" id="wtVideoPlayer" playsinline></video>
                    
                    <!-- No Video State - Enhanced -->
                    <div class="wt-no-video" id="wtNoVideo">
                        <div class="wt-waiting-content">
                            <i class="fa-solid fa-popcorn"></i>
                            <h3>Ready to watch together?</h3>
                            <p>${wtState.isAdmin ? 'Pick something to watch with your friends' : 'Waiting for the host to pick a video...'}</p>
                            ${wtState.isAdmin ? `
                                <button class="wt-select-video-btn" id="wtSelectVideoBtn">
                                    <i class="fa-solid fa-play"></i>
                                    Browse Videos
                                </button>
                            ` : `
                                <div class="wt-waiting-animation">
                                    <div class="wt-waiting-dots">
                                        <span></span><span></span><span></span>
                                    </div>
                                    <button class="wt-game-quick-btn" id="wtQuickGameBtn">
                                        <i class="fa-solid fa-gamepad"></i>
                                        Play Tic-Tac-Toe
                                    </button>
                                </div>
                            `}
                        </div>
                        
                        <!-- Chat Preview Section -->
                        <div class="wt-waiting-chat-preview">
                            <div class="wt-chat-preview-header">
                                <i class="fa-solid fa-comments"></i>
                                <span>Check out the server chat while ${wtState.isAdmin ? 'you pick' : 'the host picks'} a video</span>
                            </div>
                            <div class="wt-chat-preview-messages" id="wtWaitingChatPreview">
                                <div class="wt-chat-preview-empty">
                                    <i class="fa-regular fa-message"></i>
                                    <span>No messages yet. Start the conversation!</span>
                                </div>
                            </div>
                            <button class="wt-open-chat-btn" id="wtOpenChatFromWaiting">
                                <i class="fa-solid fa-arrow-right"></i>
                                Open Chat
                            </button>
                        </div>
                    </div>
                </div>

                <div class="wt-bottom-controls" id="wtBottomControls" style="display: none;">
                    <div class="wt-progress-bar" id="wtProgressBar">
                        <div class="wt-progress-fill" id="wtProgressFill"></div>
                    </div>
                    <div class="wt-controls-row">
                        <div class="wt-controls-left">
                            <button class="wt-control-btn" id="wtSkipBackBtn" title="Rewind 10s">
                                <i class="fa-solid fa-rotate-left"></i>
                            </button>
                            <button class="wt-control-btn play-pause" id="wtPlayPauseBtn" title="Play/Pause">
                                <i class="fa-solid fa-play" id="wtPlayIcon"></i>
                                <i class="fa-solid fa-pause" id="wtPauseIcon" style="display: none;"></i>
                            </button>
                            <button class="wt-control-btn" id="wtSkipForwardBtn" title="Forward 10s">
                                <i class="fa-solid fa-rotate-right"></i>
                            </button>
                            <div class="wt-volume-wrapper">
                                <button class="wt-control-btn" id="wtVolumeBtn" title="Mute/Unmute">
                                    <i class="fa-solid fa-volume-high" id="wtVolumeIcon"></i>
                                </button>
                                <div class="wt-volume-slider" id="wtVolumeSlider">
                                    <div class="wt-volume-fill" id="wtVolumeFill"></div>
                                </div>
                            </div>
                            <div class="wt-time-display">
                                <span id="wtCurrentTime">0:00</span>
                                <span class="sep">/</span>
                                <span id="wtDuration">0:00</span>
                            </div>
                        </div>
                        <div class="wt-controls-right">
                            ${wtState.isAdmin ? `
                                <button class="wt-control-btn" id="wtChangeVideoBtn" title="Change Video">
                                    <i class="fa-solid fa-film"></i>
                                </button>
                            ` : ''}
                            <button class="wt-control-btn" id="wtTogglePanelBtn" title="Toggle Panel">
                                <i class="fa-solid fa-users"></i>
                            </button>
                            <button class="wt-control-btn" id="wtFullscreenBtn" title="Fullscreen">
                                <i class="fa-solid fa-expand"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Participants Panel -->
            <div class="wt-participants-panel" id="wtParticipantsPanel">
                <div class="wt-panel-header">
                    <div class="wt-panel-title">
                        <i class="fa-solid fa-users"></i>
                        Watching
                        <span class="count" id="wtParticipantCount">1</span>
                    </div>
                    <button class="wt-toggle-chat-btn" id="wtToggleChatBtn" title="Toggle Chat">
                        <i class="fa-solid fa-comments"></i>
                        <div class="wt-chat-badge" id="wtChatBadge">0</div>
                    </button>
                </div>

                <div class="wt-participants-content" id="wtParticipantsContent">
                    <!-- Participants (Visible by default) -->
                    <div class="wt-participants-list" id="wtParticipantsList">
                        <!-- Participants List will be rendered here -->
                    </div>

                    <!-- Tabs (Only in chat mode) -->
                    <div class="wt-panel-tabs">
                        <div class="wt-panel-tab active" id="tabServer">
                            <i class="fa-solid fa-server"></i>
                            <span>Server</span>
                        </div>
                        <div class="wt-panel-tab" id="tabPrivate">
                            <i class="fa-solid fa-user-lock"></i>
                            <span>Private</span>
                        </div>
                    </div>

                    <!-- Server Content (Session Chat) -->
                    <div id="panelServerContent" class="wt-chat-panel active">
                        <div class="wt-chat-messages" id="wtChatMessages"></div>
                        <div class="wt-chat-input-wrapper" id="wtServerInputArea">
                            <div class="wt-reply-preview" id="wtReplyPreview" style="display: none;">
                                <div class="wt-reply-line"></div>
                                <div class="wt-reply-preview-content">
                                    <span class="name" id="wtReplyName"></span>
                                    <span class="text" id="wtReplyText"></span>
                                </div>
                                <button class="wt-reply-cancel" id="wtReplyCancel"><i class="fa-solid fa-xmark"></i></button>
                            </div>
                            <div class="wt-input-row">
                                <input type="text" class="wt-chat-input" id="wtChatInput" placeholder="Message session..." autocomplete="off">
                                <button class="wt-chat-send-btn" id="wtChatSendBtn">
                                    <i class="fa-solid fa-paper-plane"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Private/Groups Content -->
                    <div id="panelPrivateContent" class="wt-chat-panel">
                        <div id="wtBackToList" class="wt-back-btn" style="display: none;">
                            <i class="fa-solid fa-chevron-left"></i>
                        </div>
                        
                        <!-- Inbox (List of chats) -->
                        <div class="wt-chat-messages" id="wtPrivateInbox">
                            <div class="wt-private-chats-list" id="wtPrivateChatsList"></div>
                        </div>

                        <!-- Chat Messages (The actual conversation) -->
                        <div class="wt-chat-messages" id="wtPrivateChatMessages" style="display: none;"></div>

                        <div class="wt-chat-input-wrapper" id="wtPrivateInputArea" style="display: none;">
                            <input type="text" class="wt-chat-input" id="wtPrivateChatInput" placeholder="Send message..." autocomplete="off">
                            <button class="wt-chat-send-btn" id="wtPrivateChatSendBtn">
                                <i class="fa-solid fa-paper-plane"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Game Overlay (Tic-Tac-Toe) -->
            <div id="wtGameOverlay" class="wt-game-overlay" style="display: none;">
                <div class="wt-game-container">
                    <div class="wt-game-header">
                        <div class="wt-game-title">Tic-Tac-Toe</div>
                        <div class="wt-game-status" id="wtGameStatus">Waiting for turn...</div>
                        <button class="wt-game-close" id="wtCloseGameBtn"><i class="fa-solid fa-xmark"></i></button>
                    </div>
                    <div class="wt-game-body">
                        <div class="wt-game-players">
                            <div class="wt-game-pbox" id="pbox-me">
                                <img src="${myPic}" class="p-avatar">
                                <span class="p-symbol">X</span>
                            </div>
                            <div class="vs">VS</div>
                            <div class="wt-game-pbox" id="pbox-them">
                                <img src="" class="p-avatar">
                                <span class="p-symbol">O</span>
                            </div>
                        </div>
                        <canvas id="wtGameCanvas" width="300" height="300"></canvas>
                        <div id="wtGameEndActions" class="wt-game-end-actions" style="display: none;">
                             <button class="wt-game-replay-btn" id="wtReplayBtn"><i class="fa-solid fa-rotate-right"></i> Play Again</button>
                             <button class="wt-game-exit-btn" onclick="quitGame()">Exit</button>
                        </div>
                    </div>
                </div>
                </div>
            </div>

            <!-- Tug Game Overlay -->
            <div id="wtTugOverlay" class="wt-game-overlay tug-overlay" style="display: none;">
                <div class="wt-game-container tug-container">
                    <div class="wt-game-header">
                        <div class="wt-game-title">Three-Way Tug</div>
                        <div class="wt-game-status" id="wtTugStatus">STAY CENTERED!</div>
                        <button class="wt-game-close" onclick="quitGame()"><i class="fa-solid fa-xmark"></i></button>
                    </div>
                    <div class="wt-tug-instruction" id="wtTugInstruction">Hold Mouse, 'A', or 'L' to PULL</div>
                    <div class="wt-game-body">
                        <canvas id="wtTugCanvas" width="400" height="400"></canvas>
                        <div id="wtTugEndActions" class="wt-game-end-actions" style="display: none;">
                             <button class="wt-game-replay-btn" id="wtTugReplayBtn"><i class="fa-solid fa-rotate-right"></i> Play Again</button>
                             <button class="wt-game-exit-btn" onclick="quitGame()">Exit</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Game Selection Modal (Tiered) -->
            <div id="wtGameSelector" class="wt-game-modal" style="display: none;">
                <div class="wt-modal-content">
                    <div class="wt-modal-header">
                        <h3 id="wtGameModalTitle">Select Game</h3>
                        <button onclick="document.getElementById('wtGameSelector').style.display='none'"><i class="fa-solid fa-xmark"></i></button>
                    </div>
                    <div class="wt-modal-body">
                        <!-- Step 1: Game List -->
                        <div id="wtGameTypeList" class="wt-game-list">
                            <div class="wt-game-item" onclick="selectGameType('tic-tac-toe')">
                                <img src="./ttt_thumb.png" class="game-thumbnail">
                                <div class="game-info">
                                    <span class="game-name">Tic-Tac-Toe</span>
                                    <span class="game-desc">Classic 3x3 strategy</span>
                                    <div class="game-players-count"><i class="fa-solid fa-user-group"></i> 2 Players</div>
                                </div>
                            </div>
                            <div class="wt-game-item" onclick="selectGameType('three-way-tug')">
                                <img src="./tug_thumb.png" class="game-thumbnail">
                                <div class="game-info">
                                    <span class="game-name">Three-Way Tug</span>
                                    <span class="game-desc">Physics struggle for dominance</span>
                                    <div class="game-players-count"><i class="fa-solid fa-users"></i> 3 Players</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Step 2: Opponent List -->
                        <div id="wtGameOpponentList" class="wt-game-friends-list" style="display: none;">
                            <div class="wt-modal-back" onclick="showGameList()"><i class="fa-solid fa-chevron-left"></i> Back to Games</div>
                            <div id="wtGameSelectionStatus" class="wt-selection-status">Select 1 player</div>
                            <div id="wtGameFriendsList"></div>
                            <div id="wtGameConfirmSelection" class="wt-confirm-selection" style="display: none;">
                                <button class="wt-send-invite-btn" id="wtConfirmGameBtn">Send Invites (0/0)</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.body.appendChild(overlay);

        // Initialize video player reference
        wtState.videoPlayer = document.getElementById('wtVideoPlayer');

        // Bind Event Listeners
        bindWatchTogetherEvents();

        // Activate overlay with animation
        requestAnimationFrame(() => {
            overlay.classList.add('active');
        });
    }

    // Bind all event listeners
    function bindWatchTogetherEvents() {
        // Removed `if (wtState.listenersAttached) return;` because we recreate the DOM every time


        // Leave Button
        document.getElementById('wtLeaveBtn')?.addEventListener('click', leaveWatchTogether);

        // Media Buttons
        document.getElementById('wtToggleMicBtn')?.addEventListener('click', toggleMic);
        document.getElementById('wtToggleCamBtn')?.addEventListener('click', toggleCam);

        // Select Video Button (Admin only)
        document.getElementById('wtSelectVideoBtn')?.addEventListener('click', openVideoSelector);
        document.getElementById('wtChangeVideoBtn')?.addEventListener('click', openVideoSelector);

        // Play/Pause
        document.getElementById('wtPlayPauseBtn')?.addEventListener('click', togglePlayPause);

        // Skip buttons
        document.getElementById('wtSkipBackBtn')?.addEventListener('click', () => seek(-10));
        document.getElementById('wtSkipForwardBtn')?.addEventListener('click', () => seek(10));

        // Volume
        document.getElementById('wtVolumeBtn')?.addEventListener('click', toggleMute);
        document.getElementById('wtVolumeSlider')?.addEventListener('click', handleVolumeClick);

        // Progress bar
        document.getElementById('wtProgressBar')?.addEventListener('click', handleProgressClick);

        // Fullscreen
        document.getElementById('wtFullscreenBtn')?.addEventListener('click', toggleFullscreen);

        // Toggle Chat
        document.getElementById('wtToggleChatBtn')?.addEventListener('click', toggleChatMode);

        // Toggle Panel
        document.getElementById('wtTogglePanelBtn')?.addEventListener('click', (e) => {
            e.stopPropagation();
            togglePanel();
        });

        // Tabs
        document.getElementById('tabServer')?.addEventListener('click', () => setWtTab('server'));
        document.getElementById('tabPrivate')?.addEventListener('click', () => setWtTab('private'));

        // Chat Input
        document.getElementById('wtChatInput')?.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') sendChatMessage();
        });
        document.getElementById('wtChatSendBtn')?.addEventListener('click', sendChatMessage);

        // Private Chat Back Button
        document.getElementById('wtBackToList')?.addEventListener('click', goBackToList);

        // Reply Cancel Button
        document.getElementById('wtReplyCancel')?.addEventListener('click', cancelReply);

        // Mute Waiting Sound Button
        document.getElementById('wtMuteWaitingBtn')?.addEventListener('click', toggleWaitingSound);

        // Games Button
        document.getElementById('wtGamesBtn')?.addEventListener('click', openGameSelector);
        document.getElementById('wtQuickGameBtn')?.addEventListener('click', openGameSelector);
        document.getElementById('wtCloseGameBtn')?.addEventListener('click', quitGame);
        document.getElementById('wtReplayBtn')?.addEventListener('click', requestReplay);
        document.getElementById('wtTugReplayBtn')?.addEventListener('click', requestReplay);
        document.getElementById('wtConfirmGameBtn')?.addEventListener('click', () => sendSelectedGameInvites());

        // Open Chat from Waiting Screen
        document.getElementById('wtOpenChatFromWaiting')?.addEventListener('click', () => {
            if (!wtState.isChatMode) toggleChatMode();
            setWtTab('server');
        });

        // Video Player Events
        if (wtState.videoPlayer) {
            wtState.videoPlayer.addEventListener('timeupdate', handleTimeUpdate);
            wtState.videoPlayer.addEventListener('play', () => syncPlayState(true));
            wtState.videoPlayer.addEventListener('pause', () => syncPlayState(false));
            wtState.videoPlayer.addEventListener('loadedmetadata', handleVideoLoaded);
            wtState.videoPlayer.addEventListener('ended', handleVideoEnded);
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', handleKeyboardShortcuts);

        // Header/Controls visibility - OPTIMIZED
        const videoArea = document.querySelector('.wt-video-area');
        const wtHeader = document.getElementById('wtHeader');
        const wtBottomControls = document.getElementById('wtBottomControls');
        let hideTimeout;
        let isMoving = false;

        videoArea?.addEventListener('mousemove', () => {
            if (isMoving) return;
            isMoving = true;

            requestAnimationFrame(() => {
                wtHeader?.classList.add('visible');
                wtBottomControls?.classList.add('visible');
                isMoving = false;
            });

            clearTimeout(hideTimeout);
            hideTimeout = setTimeout(() => {
                if (!wtState.videoPlayer?.paused) {
                    requestAnimationFrame(() => {
                        wtHeader?.classList.remove('visible');
                        wtBottomControls?.classList.remove('visible');
                    });
                }
            }, 3000);
        });

        // Initialize starfield for waiting screen
        initWaitingStarfield();
    }

    // Connect to WebSocket session
    async function connectToSession() {
        // Use existing WS connection if available
        if (typeof ws !== 'undefined' && ws && ws.readyState === WebSocket.OPEN) {
            wtState.ws = ws;
        } else {
            console.warn('[WatchTogether] Global WebSocket not found, initializing private connection...');
            let serverIp = window.FLOX_CTX?.wsHost || window.location.hostname;
            if (serverIp === 'localhost') serverIp = '127.0.0.1';

            try {
                wtState.ws = new WebSocket(`ws://${serverIp}:8080`);
                // Wait for it to open
                await new Promise((resolve, reject) => {
                    wtState.ws.onopen = resolve;
                    wtState.ws.onerror = reject;
                    setTimeout(() => reject(new Error("WS Connection Timeout")), 5000);
                });
            } catch (e) {
                console.error('[WatchTogether] External WebSocket failed:', e);
                return;
            }
        }

        if (wtState.ws && wtState.ws.readyState === WebSocket.OPEN) {
            setupWatchTogetherMessageHandler();

            // Announce joining
            wtState.ws.send(JSON.stringify({
                type: 'WATCHTOGETHER_JOIN',
                session_id: wtState.sessionId,
                group_id: wtState.groupId,
                user_id: typeof myUserId !== 'undefined' ? myUserId : 0,
                user_name: getMyUsername(),
                user_pic: typeof myProfilePic !== 'undefined' ? myProfilePic : '',
                is_admin: wtState.isAdmin
            }));
        } else {
            console.error('[WatchTogether] WebSocket still not available after attempt');
        }
    }

    // Setup message handler for WatchTogether events
    function setupWatchTogetherMessageHandler() {
        if (wtState.attachedSocket === wtState.ws) return;

        console.log('[WatchTogether] Attaching event handlers to socket');
        const originalOnMessage = wtState.ws.onmessage;

        wtState.ws.onmessage = function (event) {
            try {
                const data = JSON.parse(event.data);
                // Handle WatchTogether specific messages
                if (data.type && data.type.startsWith('WATCHTOGETHER_')) {
                    handleWatchTogetherMessage(data);
                }
            } catch (e) {
                console.error('[WT] JSON Parse Error:', e);
            }

            // Also call original handler (from chat.php)
            if (originalOnMessage) {
                originalOnMessage.call(wtState.ws, event);
            }
        };

        wtState.attachedSocket = wtState.ws;
    }

    // Background poller to handle socket reconnections
    setInterval(() => {
        if (typeof ws !== 'undefined' && ws && ws !== wtState.attachedSocket && ws.readyState === WebSocket.OPEN) {
            console.warn('[WatchTogether] Socket change detected! Restoring session...');
            wtState.ws = ws;
            setupWatchTogetherMessageHandler();

            // Re-announce existence if in a session
            if (wtState.isActive && wtState.sessionId) {
                wtState.ws.send(JSON.stringify({
                    type: 'WATCHTOGETHER_JOIN',
                    session_id: wtState.sessionId,
                    group_id: wtState.groupId,
                    user_id: typeof myUserId !== 'undefined' ? myUserId : 0,
                    user_name: getMyUsername(),
                    user_pic: typeof myProfilePic !== 'undefined' ? myProfilePic : '',
                    is_admin: wtState.isAdmin
                }));
            }
        }
    }, 3000);

    // Handle incoming WatchTogether messages
    function handleWatchTogetherMessage(data) {
        // Isolation Check: Ensure we only process messages for our active session
        // Global events like PARTY_STARTED/ENDED are allowed to update the map
        const isGlobalEvent = data.type === 'WATCHTOGETHER_PARTY_STARTED' || data.type === 'WATCHTOGETHER_PARTY_ENDED';

        if (!isGlobalEvent) {
            if (!wtState.isActive) return; // Don't process session events if not in one
            if (data.group_id && String(data.group_id) !== String(wtState.groupId)) {
                return; // Not our group
            }
        }

        switch (data.type) {
            case 'WATCHTOGETHER_USER_JOINED':
                addParticipant(data);
                showSyncToast(`${data.user_name} joined the watch party!`);
                break;

            case 'WATCHTOGETHER_USER_LEFT':
                removeParticipant(data.user_id);
                showSyncToast(`${data.user_name} left the watch party`);
                break;

            case 'WATCHTOGETHER_VIDEO_SELECTED':
                // Load video for everyone (including admin confirmation)
                loadVideo(data.video);
                if (!wtState.isAdmin) {
                    showSyncToast(`Now playing: ${data.video.title}`);
                }
                break;

            case 'WATCHTOGETHER_SYNC_STATE':
                console.log('[WATCHTOGETHER] Received SYNC STATE (Late Join)');
                if (data.video) {
                    loadVideo(data.video);

                    const onReadySync = () => {
                        if (wtState.videoPlayer) {
                            if (typeof data.video.currentTime === 'number') {
                                wtState.videoPlayer.currentTime = data.video.currentTime;
                            }
                            if (data.video.isPlaying) {
                                wtState.videoPlayer.play().catch(e => console.warn('Sync autoplay blocked', e));
                            } else {
                                wtState.videoPlayer.pause();
                            }
                            showSyncToast('Synced to party!');
                        }
                    };

                    if (wtState.videoPlayer.readyState >= 1) {
                        onReadySync();
                    } else {
                        wtState.videoPlayer.addEventListener('loadedmetadata', onReadySync, { once: true });
                    }
                }
                break;

            case 'WATCHTOGETHER_SYNC':
                if (!wtState.isAdmin) {
                    syncToHost(data);
                }
                break;

            case 'WATCHTOGETHER_PLAY':
                if (!wtState.isAdmin && wtState.videoPlayer) {
                    wtState.videoPlayer.play().catch(e => console.warn(e));
                }
                break;

            case 'WATCHTOGETHER_PAUSE':
                if (!wtState.isAdmin && wtState.videoPlayer) {
                    wtState.videoPlayer.pause();
                }
                break;

            case 'WATCHTOGETHER_SEEK':
                if (!wtState.isAdmin && wtState.videoPlayer) {
                    const oldTime = wtState.videoPlayer.currentTime;
                    wtState.videoPlayer.currentTime = data.time;

                    const jump = data.jump || (data.time - oldTime);
                    if (Math.abs(jump) > 0.5) { // Only show if it's a significant jump
                        const direction = jump > 0 ? 'forward' : 'backward';
                        const absJump = Math.abs(jump);
                        let timeStr = '';

                        if (absJump < 1) {
                            timeStr = '<1 second';
                        } else {
                            timeStr = `${Math.round(absJump)} seconds`;
                        }
                        showSyncToast(`The host skipped ${timeStr} ${direction}`);
                    }
                }
                break;

            case 'WATCHTOGETHER_CHAT':
                // Only display if NOT from me (to avoid duplication with local display)
                if (String(data.user_id) !== String(typeof myUserId !== 'undefined' ? myUserId : 0)) {
                    displayChatMessage(data);
                }
                break;

            case 'WATCHTOGETHER_KICK':
                if (String(data.user_id) === String(typeof myUserId !== 'undefined' ? myUserId : 0)) {
                    showSyncToast('You have been removed from the watch party');
                    setTimeout(() => closeWatchTogether(), 2000);
                } else {
                    removeParticipant(data.user_id);
                }
                break;

            case 'WATCHTOGETHER_MUTE':
                if (String(data.user_id) === String(typeof myUserId !== 'undefined' ? myUserId : 0)) {
                    showSyncToast('You have been muted by the host');
                }
                break;

            case 'WATCHTOGETHER_PARTY_STARTED':
                activeParties.set(data.group_id, {
                    session_id: data.session_id,
                    admin_name: data.admin_name
                });
                break;

            case 'WATCHTOGETHER_PARTY_ENDED':
                activeParties.delete(data.group_id);
                break;

            case 'WATCHTOGETHER_GAME_INVITE':
                handleGameInvite(data);
                break;

            case 'WATCHTOGETHER_GAME_RESPONSE':
                handleGameResponse(data);
                break;

            case 'WATCHTOGETHER_GAME_MOVE':
                handleGameMove(data);
                break;

            case 'WATCHTOGETHER_GAME_QUIT':
                handleGameQuit(data);
                break;
        }
    }

    // Add participant to the list
    function addParticipant(data) {
        wtState.participants.set(String(data.user_id), data);
        renderParticipantsList();
    }

    // Remove participant from the list
    function removeParticipant(userId) {
        wtState.participants.delete(String(userId));
        renderParticipantsList();
    }

    // Add self as participant
    function addSelfAsParticipant() {
        const myId = typeof myUserId !== 'undefined' ? myUserId : 0;
        wtState.participants.set(String(myId), {
            user_id: myId,
            user_name: getMyUsername(),
            user_pic: typeof myProfilePic !== 'undefined' ? myProfilePic : '',
            is_admin: wtState.isAdmin
        });
    }

    // Render participants list
    function renderParticipantsList() {
        const list = document.getElementById('wtParticipantsList');
        if (!list) return;

        const myPic = getMyProfilePic();
        const myName = getMyUsername();
        const myId = typeof myUserId !== 'undefined' ? myUserId : 0;

        let html = `
            <!-- Self (Admin if creator) -->
            <div class="wt-participant-card" data-user-id="${myId}">
                <div class="wt-participant-avatar">
                    <video class="wt-peer-video" id="wtLocalVideo" autoplay playsinline muted></video>
                    <img src="${myPic}" alt="${escapeHtml(myName)}" onerror="this.src='https://www.gravatar.com/avatar/00?d=mp'">
                    <div class="status-dot"></div>
                    ${wtState.isAdmin ? '<div class="admin-crown"><i class="fa-solid fa-crown"></i></div>' : ''}
                </div>
                <div class="wt-participant-info">
                    <div class="wt-participant-name">${escapeHtml(myName)} (You)</div>
                    <div class="wt-participant-role">
                        ${wtState.isAdmin ? '<span style="color: #f59e0b;">Host</span>' : '<span>Viewer</span>'}
                        <span class="mic-status"><i class="fa-solid fa-microphone"></i></span>
                    </div>
                </div>
            </div>
        `;

        wtState.participants.forEach((p, id) => {
            if (String(id) !== String(myId)) {
                html += createParticipantCard(p);
            }
        });

        list.innerHTML = html;

        // Update count
        const countEl = document.getElementById('wtParticipantCount');
        if (countEl) countEl.textContent = wtState.participants.size;

        // Rebind admin action buttons
        // Rebind admin action buttons
        bindAdminActions();

        // Re-attach streams after render
        reattachMediaStreams();
    }

    function reattachMediaStreams() {
        if (wtState.localStream) {
            const localVid = document.getElementById('wtLocalVideo');
            if (localVid) {
                localVid.srcObject = wtState.localStream;
                localVid.classList.add('active');
                localVid.play().catch(e => console.warn(e));
            }
        }
    }

    // Toggle Microphone
    async function toggleMic() {
        const btn = document.getElementById('wtToggleMicBtn');
        if (!wtState.localStream) {
            await initLocalMedia({ audio: true });
            if (btn) btn.innerHTML = '<i class="fa-solid fa-microphone"></i>';
        } else {
            const track = wtState.localStream.getAudioTracks()[0];
            if (track) {
                track.enabled = !track.enabled;
                if (btn) {
                    btn.innerHTML = track.enabled ?
                        '<i class="fa-solid fa-microphone"></i>' :
                        '<i class="fa-solid fa-microphone-slash"></i>';
                    btn.classList.toggle('media-active', track.enabled);
                    btn.classList.toggle('media-off', !track.enabled);
                }
            } else {
                // If we have video but no audio, request audio
                // For simplicity, just restart stream
            }
        }
    }

    // Toggle Camera
    async function toggleCam() {
        const btn = document.getElementById('wtToggleCamBtn');
        if (!wtState.localStream || !wtState.localStream.getVideoTracks().length) {
            await initLocalMedia({ audio: true, video: true }); // Always ask for both if starting fresh
            // If already had audio, merge? Simplest is get new stream.
        } else {
            const track = wtState.localStream.getVideoTracks()[0];
            track.enabled = !track.enabled;

            const localVid = document.getElementById('wtLocalVideo');
            if (localVid) localVid.classList.toggle('active', track.enabled);

            if (btn) {
                btn.innerHTML = track.enabled ?
                    '<i class="fa-solid fa-video"></i>' :
                    '<i class="fa-solid fa-video-slash"></i>';
                btn.classList.toggle('media-active', track.enabled);
                btn.classList.toggle('media-off', !track.enabled);
            }
        }
    }

    // Initialize Local Media
    async function initLocalMedia(constraints = { audio: true, video: true }) {
        try {
            const stream = await navigator.mediaDevices.getUserMedia(constraints);
            wtState.localStream = stream;
            reattachMediaStreams();

            // Update buttons state
            const micBtn = document.getElementById('wtToggleMicBtn');
            const camBtn = document.getElementById('wtToggleCamBtn');

            if (micBtn) {
                micBtn.classList.add('media-active');
                micBtn.innerHTML = '<i class="fa-solid fa-microphone"></i>';
            }
            if (camBtn && constraints.video) {
                camBtn.classList.add('media-active');
                camBtn.innerHTML = '<i class="fa-solid fa-video"></i>';
            }
        } catch (err) {
            console.error('Media Error', err);
            showSyncToast('Could not access Camera/Mic');
        }
    }

    // Create participant card HTML
    function createParticipantCard(p) {
        const pic = resolveProfilePic(p.user_pic);
        return `
            <div class="wt-participant-card" data-user-id="${p.user_id}">
                <div class="wt-participant-avatar">
                    <video class="wt-peer-video" id="wtVideo-${p.user_id}" autoplay playsinline></video>
                    <img src="${pic}" alt="${escapeHtml(p.user_name)}" onerror="this.src='https://www.gravatar.com/avatar/00?d=mp'">
                    <div class="status-dot"></div>
                    ${p.is_admin ? '<div class="admin-crown"><i class="fa-solid fa-crown"></i></div>' : ''}
                </div>
                <div class="wt-participant-info">
                    <div class="wt-participant-name">${escapeHtml(p.user_name)}</div>
                    <div class="wt-participant-role">
                        ${p.is_admin ? '<span style="color: #f59e0b;">Host</span>' : '<span>Viewer</span>'}
                        <span class="mic-status"><i class="fa-solid fa-microphone"></i></span>
                    </div>
                </div>
                ${wtState.isAdmin && !p.is_admin ? `
                    <div class="wt-admin-actions">
                        <button class="wt-admin-action-btn kick" data-action="kick" data-user="${p.user_id}" title="Kick User">
                            <i class="fa-solid fa-user-xmark"></i>
                        </button>
                        <button class="wt-admin-action-btn mute" data-action="mute-mic" data-user="${p.user_id}" title="Mute Mic">
                            <i class="fa-solid fa-microphone-slash"></i>
                        </button>
                        <button class="wt-admin-action-btn block" data-action="block" data-user="${p.user_id}" title="Block User">
                            <i class="fa-solid fa-ban"></i>
                        </button>
                        <button class="wt-admin-action-btn camera" data-action="block-camera" data-user="${p.user_id}" title="Block Camera">
                            <i class="fa-solid fa-video-slash"></i>
                        </button>
                        <button class="wt-admin-action-btn chat" data-action="chat" data-user="${p.user_id}" title="Direct Message">
                            <i class="fa-solid fa-message"></i>
                        </button>
                    </div>
                ` : ''}
            </div>
        `;
    }

    // Bind admin action buttons
    function bindAdminActions() {
        document.querySelectorAll('.wt-admin-action-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const action = btn.dataset.action;
                const userId = btn.dataset.user;
                handleAdminAction(action, userId);
            });
        });
    }

    // Handle admin actions
    function handleAdminAction(action, userId) {
        const participant = wtState.participants.get(String(userId));
        if (!participant) return;

        switch (action) {
            case 'kick':
                wtState.ws.send(JSON.stringify({
                    type: 'WATCHTOGETHER_KICK',
                    session_id: wtState.sessionId,
                    group_id: wtState.groupId,
                    user_id: userId,
                    user_name: participant.user_name
                }));
                removeParticipant(userId);
                showSyncToast(`${participant.user_name} was removed`);
                break;

            case 'mute-mic':
                wtState.ws.send(JSON.stringify({
                    type: 'WATCHTOGETHER_MUTE',
                    session_id: wtState.sessionId,
                    group_id: wtState.groupId,
                    user_id: userId,
                    mute_type: 'mic'
                }));
                showSyncToast(`${participant.user_name}'s mic muted`);
                break;

            case 'block':
                wtState.ws.send(JSON.stringify({
                    type: 'WATCHTOGETHER_BLOCK',
                    session_id: wtState.sessionId,
                    group_id: wtState.groupId,
                    user_id: userId

                }));
                removeParticipant(userId);
                showSyncToast(`${participant.user_name} was blocked`);
                break;

            case 'block-camera':
                wtState.ws.send(JSON.stringify({
                    type: 'WATCHTOGETHER_MUTE',
                    session_id: wtState.sessionId,
                    group_id: wtState.groupId,
                    user_id: userId,
                    mute_type: 'camera'
                }));
                showSyncToast(`${participant.user_name}'s camera disabled`);
                break;

            case 'chat':
                // Switch to chat mode if not already
                if (!wtState.isChatMode) toggleChatMode();

                // Switch to session chat tab
                setWtTab('server');

                const chatInput = document.getElementById('wtChatInput');
                if (chatInput) {
                    chatInput.value = `@${participant.user_name} `;
                    chatInput.focus();
                }
                break;

        }
    }

    // Open video selector modal
    function openVideoSelector() {
        createVideoSelectorModal();
    }

    // Create video selector modal
    async function createVideoSelectorModal() {
        const existingModal = document.getElementById('wtVideoModal');
        if (existingModal) existingModal.remove();

        const modalOverlay = document.createElement('div');
        modalOverlay.id = 'wtVideoModal';
        modalOverlay.className = 'wt-video-modal-overlay';
        modalOverlay.innerHTML = `
            <div class="wt-video-modal">
                <div class="wt-modal-header">
                    <h2><i class="fa-solid fa-clapperboard"></i> Pick a Video</h2>
                    <button class="wt-modal-close" id="wtModalClose">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                <div class="wt-modal-search">
                    <input type="text" placeholder="Search videos..." id="wtVideoSearch">
                </div>
                <div class="wt-video-grid" id="wtVideoGrid">
                    <div class="wt-loading" style="grid-column: 1/-1; text-align: center; padding: 40px; color: var(--wt-text-secondary);">
                        <i class="fa-solid fa-spinner fa-spin" style="font-size: 24px;"></i>
                        <p style="margin-top: 12px;">Loading videos...</p>
                    </div>
                </div>
            </div>
        `;

        const container = document.getElementById('watchTogetherOverlay') || document.body;
        container.appendChild(modalOverlay);

        // Bind close button
        document.getElementById('wtModalClose')?.addEventListener('click', () => {
            modalOverlay.classList.remove('active');
            setTimeout(() => modalOverlay.remove(), 300);
        });

        // Close on backdrop click
        modalOverlay.addEventListener('click', (e) => {
            if (e.target === modalOverlay) {
                modalOverlay.classList.remove('active');
                setTimeout(() => modalOverlay.remove(), 300);
            }
        });

        // Search functionality
        document.getElementById('wtVideoSearch')?.addEventListener('input', (e) => {
            filterVideos(e.target.value);
        });

        // Show modal
        requestAnimationFrame(() => {
            modalOverlay.classList.add('active');
        });

        // Load videos
        await loadVideosForModal();
    }

    // Load videos for the modal (using same endpoint as home)
    async function loadVideosForModal() {
        try {
            const response = await fetch('../backend/getVideos.php?limit=50');
            const result = await response.json();

            if (result.success && result.videos && result.videos.length > 0) {
                renderVideoGrid(result.videos);
            } else {
                document.getElementById('wtVideoGrid').innerHTML = `
                    <div style="grid-column: 1/-1; text-align: center; padding: 40px; color: var(--wt-text-secondary);">
                        <i class="fa-solid fa-film" style="font-size: 40px; margin-bottom: 12px; opacity: 0.5;"></i>
                        <p>No videos available</p>
                    </div>
                `;
            }
        } catch (error) {
            console.error('[WatchTogether] Error loading videos:', error);
            document.getElementById('wtVideoGrid').innerHTML = `
                <div style="grid-column: 1/-1; text-align: center; padding: 40px; color: var(--wt-danger);">
                    <i class="fa-solid fa-exclamation-triangle" style="font-size: 40px; margin-bottom: 12px;"></i>
                    <p>Failed to load videos</p>
                </div>
            `;
        }
    }

    // Render video grid
    function renderVideoGrid(videos) {
        const grid = document.getElementById('wtVideoGrid');
        if (!grid) return;

        grid.innerHTML = videos.map(video => {
            const thumbnailUrl = video.thumbnail_url || '../uploads/thumbnails/default.jpg';
            const authorName = video.author?.username || video.username || 'Unknown';
            const videoData = JSON.stringify(video).replace(/'/g, "&#39;").replace(/"/g, "&quot;");

            return `
                <div class="wt-video-card" data-video-id="${video.id}" data-video='${videoData}'>
                    <img class="thumbnail" src="${thumbnailUrl}" alt="${escapeHtml(video.title)}" loading="lazy" onerror="this.src='../uploads/thumbnails/default.jpg'">
                    <div class="info">
                        <div class="title">${escapeHtml(video.title)}</div>
                        <div class="meta">${escapeHtml(authorName)} • ${formatViews(video.views || 0)} views</div>
                    </div>
                </div>
            `;
        }).join('');

        // Bind click handlers
        grid.querySelectorAll('.wt-video-card').forEach(card => {
            card.addEventListener('click', () => {
                try {
                    const videoData = card.dataset.video.replace(/&quot;/g, '"').replace(/&#39;/g, "'");
                    const video = JSON.parse(videoData);
                    selectVideo(video);
                } catch (e) {
                    console.error('[WatchTogether] Error parsing video data:', e);
                }
            });
        });
    }

    // Filter videos in the modal
    function filterVideos(query) {
        const cards = document.querySelectorAll('.wt-video-card');
        const q = query.toLowerCase();

        cards.forEach(card => {
            const title = card.querySelector('.title')?.textContent.toLowerCase() || '';
            const meta = card.querySelector('.meta')?.textContent.toLowerCase() || '';
            card.style.display = (title.includes(q) || meta.includes(q)) ? '' : 'none';
        });
    }

    // Select a video to play
    function selectVideo(video) {
        wtState.currentVideo = video;

        // Close modal
        const modal = document.getElementById('wtVideoModal');
        if (modal) {
            modal.classList.remove('active');
            setTimeout(() => modal.remove(), 300);
        }

        // Load video locally
        loadVideo(video);

        // Broadcast to ALL participants (including self for consistency)
        if (wtState.ws && wtState.ws.readyState === WebSocket.OPEN) {
            wtState.ws.send(JSON.stringify({
                type: 'WATCHTOGETHER_VIDEO_SELECTED',
                session_id: wtState.sessionId,
                group_id: wtState.groupId,
                video: video
            }));
        }

        showSyncToast(`Now playing: ${video.title}`);
    }

    // Load video into player
    function loadVideo(video) {
        wtState.currentVideo = video;

        // Update title
        const titleEl = document.getElementById('wtSessionTitle');
        if (titleEl) titleEl.textContent = video.title;

        // Hide no-video state
        const noVideoEl = document.getElementById('wtNoVideo');
        const controlsEl = document.getElementById('wtBottomControls');
        if (noVideoEl) noVideoEl.style.display = 'none';
        if (controlsEl) controlsEl.style.display = 'block';

        // Stop waiting sound when video starts
        stopWaitingSound();

        // Cleanup starfield background
        if (wtState.starfieldCleanup) {
            wtState.starfieldCleanup();
            wtState.starfieldCleanup = null;

            const canvas = document.getElementById('wtStarsCanvas');
            if (canvas) canvas.style.display = 'none';
        }

        // Set video source
        if (wtState.videoPlayer) {
            let videoUrl = video.video_url || video.url || '';

            // Fix URL path
            if (videoUrl && !videoUrl.startsWith('http')) {
                videoUrl = videoUrl.replace(/^\.\//, '').replace(/^\//, '');
                if (!videoUrl.startsWith('../')) {
                    videoUrl = '../' + videoUrl;
                }
            }

            console.log('[WatchTogether] Loading video:', videoUrl);
            wtState.videoPlayer.src = videoUrl;

            // Optimization properties
            wtState.videoPlayer.preload = "auto";
            wtState.videoPlayer.autoplay = true;
            wtState.videoPlayer.setAttribute('crossorigin', 'anonymous');

            wtState.videoPlayer.load();

            // Auto-play when loaded
            wtState.videoPlayer.addEventListener('canplay', function playOnLoad() {
                wtState.videoPlayer.play().catch(e => console.warn('[WatchTogether] Autoplay blocked:', e));
                wtState.videoPlayer.removeEventListener('canplay', playOnLoad);

                // Update media session metadata
                updateMediaSessionMetadata();
            }, { once: true });
        }
    }

    // Toggle Play/Pause
    function togglePlayPause() {
        if (!wtState.videoPlayer) return;

        if (wtState.videoPlayer.paused) {
            wtState.videoPlayer.play();
        } else {
            wtState.videoPlayer.pause();
        }
    }

    // Sync play state with other participants
    function syncPlayState(isPlaying) {
        updatePlayPauseIcon(isPlaying);

        if (wtState.isAdmin && wtState.ws && wtState.ws.readyState === WebSocket.OPEN) {
            wtState.ws.send(JSON.stringify({
                type: isPlaying ? 'WATCHTOGETHER_PLAY' : 'WATCHTOGETHER_PAUSE',
                session_id: wtState.sessionId,
                group_id: wtState.groupId,
                time: wtState.videoPlayer?.currentTime || 0
            }));
        }
    }

    // Update play/pause button icon
    function updatePlayPauseIcon(isPlaying) {
        const playIcon = document.getElementById('wtPlayIcon');
        const pauseIcon = document.getElementById('wtPauseIcon');

        if (playIcon && pauseIcon) {
            playIcon.style.display = isPlaying ? 'none' : '';
            pauseIcon.style.display = isPlaying ? '' : 'none';
        }
    }

    // Seek forward/backward
    function seek(seconds) {
        if (!wtState.videoPlayer) return;

        const newTime = Math.max(0, Math.min(wtState.videoPlayer.duration || 0, wtState.videoPlayer.currentTime + seconds));
        wtState.videoPlayer.currentTime = newTime;

        if (wtState.isAdmin && wtState.ws && wtState.ws.readyState === WebSocket.OPEN) {
            wtState.ws.send(JSON.stringify({
                type: 'WATCHTOGETHER_SEEK',
                session_id: wtState.sessionId,
                group_id: wtState.groupId,
                time: newTime,
                jump: seconds
            }));
        }
    }

    // Sync to host's position - ULTRA FAST NO-LAG VERSION
    function syncToHost(data) {
        if (!wtState.videoPlayer || wtState.isAdmin) return;

        // Skip sync if already loading/seeking to prevent stutter
        if (wtState.videoPlayer.seeking || wtState.videoPlayer.readyState < 2) return;

        const hostTime = data.time;
        const myTime = wtState.videoPlayer.currentTime;
        const diff = hostTime - myTime;
        const absDiff = Math.abs(diff);

        // 1. HARD SYNC (Seek) - Only for massive desyncs (> 10s)
        if (absDiff > 10) {
            wtState.videoPlayer.currentTime = hostTime;
            wtState.videoPlayer.playbackRate = 1.0;
            showSyncToast('Hard-syncing to host...');
            return;
        }

        // 2. SOFT SYNC (PlaybackRate) - "The Catch-up Logic"
        // If we are between 0.5s and 10s apart, we adjust the playback speed
        // instead of seeking. This avoids the "buffer/lag" circle.
        if (absDiff > 0.5) {
            if (diff > 0) {
                // I am behind - Speed up to catch up
                // The further behind, the faster we go (max 1.25x)
                const rate = Math.min(1.25, 1.0 + (absDiff / 20));
                wtState.videoPlayer.playbackRate = rate;
            } else {
                // I am ahead - Slow down subtly
                const rate = Math.max(0.75, 1.0 - (absDiff / 20));
                wtState.videoPlayer.playbackRate = rate;
            }
            // Perfectly synced - Reset to normal speed
            // Only update if current rate is not 1.0 to avoid redundant property writes
            if (Math.abs(wtState.videoPlayer.playbackRate - 1.0) > 0.01) {
                wtState.videoPlayer.playbackRate = 1.0;
            }
        }
    }

    // Handle time update
    function handleTimeUpdate() {
        if (!wtState.videoPlayer) return;

        const now = Date.now();
        const current = wtState.videoPlayer.currentTime;

        // HIGH PERFORMANCE UI UPDATES
        // Use 200ms throttle for labels (~5fps) which is plenty for humans
        // and significantly reduces layout/text recalculation overhead.
        if (now - (wtState.lastUIUpdate || 0) > 200) {
            wtState.lastUIUpdate = now;

            const duration = wtState.videoPlayer.duration || 0;
            const percent = duration > 0 ? (current / duration) : 0;

            // Update progress bar using TRANSFORM (No Reflow / GPU path)
            const fill = document.getElementById('wtProgressFill');
            if (fill) {
                fill.style.transform = `scaleX(${percent})`;
            }

            // Update time display
            const currentTimeEl = document.getElementById('wtCurrentTime');
            if (currentTimeEl) currentTimeEl.textContent = formatTime(current);
        }

        // Periodic sync broadcast (admin only, every 2s for tighter stability)
        if (wtState.isAdmin && wtState.ws && wtState.ws.readyState === WebSocket.OPEN && now - wtState.lastSyncTime > 2000) {
            wtState.lastSyncTime = now;
            wtState.ws.send(JSON.stringify({
                type: 'WATCHTOGETHER_SYNC',
                session_id: wtState.sessionId,
                group_id: wtState.groupId,
                time: current,
                isPlaying: !wtState.videoPlayer.paused
            }));
        }
    }

    // Handle video loaded
    function handleVideoLoaded() {
        const durationEl = document.getElementById('wtDuration');
        if (durationEl) durationEl.textContent = formatTime(wtState.videoPlayer?.duration || 0);
    }

    // Handle video ended
    function handleVideoEnded() {
        showSyncToast('Video ended');
    }

    // Handle progress bar click
    function handleProgressClick(e) {
        if (!wtState.videoPlayer || !wtState.isAdmin) return;

        const bar = e.currentTarget;
        const rect = bar.getBoundingClientRect();
        const percent = (e.clientX - rect.left) / rect.width;
        const newTime = percent * (wtState.videoPlayer.duration || 0);

        const oldTime = wtState.videoPlayer.currentTime;
        wtState.videoPlayer.currentTime = newTime;

        if (wtState.ws && wtState.ws.readyState === WebSocket.OPEN) {
            wtState.ws.send(JSON.stringify({
                type: 'WATCHTOGETHER_SEEK',
                session_id: wtState.sessionId,
                group_id: wtState.groupId,
                time: newTime,
                jump: newTime - oldTime
            }));
        }
    }

    // Toggle mute
    function toggleMute() {
        if (!wtState.videoPlayer) return;

        wtState.videoPlayer.muted = !wtState.videoPlayer.muted;
        updateVolumeIcon();
    }

    // Handle volume slider click
    function handleVolumeClick(e) {
        if (!wtState.videoPlayer) return;

        const slider = e.currentTarget;
        const rect = slider.getBoundingClientRect();
        const percent = (e.clientX - rect.left) / rect.width;

        wtState.videoPlayer.volume = Math.max(0, Math.min(1, percent));
        wtState.videoPlayer.muted = false;

        updateVolumeUI();
    }

    // Update volume UI
    function updateVolumeUI() {
        const fill = document.getElementById('wtVolumeFill');
        if (fill && wtState.videoPlayer) {
            fill.style.width = `${wtState.videoPlayer.volume * 100}%`;
        }
        updateVolumeIcon();
    }

    // Update volume icon
    function updateVolumeIcon() {
        const icon = document.getElementById('wtVolumeIcon');
        if (!icon || !wtState.videoPlayer) return;

        if (wtState.videoPlayer.muted || wtState.videoPlayer.volume === 0) {
            icon.className = 'fa-solid fa-volume-xmark';
        } else if (wtState.videoPlayer.volume < 0.5) {
            icon.className = 'fa-solid fa-volume-low';
        } else {
            icon.className = 'fa-solid fa-volume-high';
        }
    }

    // Toggle fullscreen
    function toggleFullscreen() {
        const overlay = document.getElementById('watchTogetherOverlay');
        if (!overlay) return;

        if (document.fullscreenElement) {
            document.exitFullscreen();
        } else {
            overlay.requestFullscreen();
        }
    }

    // Toggle panel (mini mode on desktop, slide on mobile)

    function togglePanel() {
        const panel = document.getElementById('wtParticipantsPanel');
        if (!panel) return;

        if (window.innerWidth <= 1024) {
            panel.classList.toggle('open');
        } else {
            panel.classList.toggle('mini');
        }
    }

    // Send chat message
    function sendChatMessage() {
        const input = document.getElementById('wtChatInput');
        const message = input?.value.trim();

        if (!message || !wtState.ws || wtState.ws.readyState !== WebSocket.OPEN) return;

        wtState.ws.send(JSON.stringify({
            type: 'WATCHTOGETHER_CHAT',
            session_id: wtState.sessionId,
            group_id: wtState.groupId,
            user_id: typeof myUserId !== 'undefined' ? myUserId : 0,
            user_name: getMyUsername(),
            user_pic: typeof myProfilePic !== 'undefined' ? myProfilePic : '',
            message: message,
            reply_to: wtState.replyTo,
            timestamp: new Date().toISOString()
        }));

        // Display locally
        displayChatMessage({
            user_id: typeof myUserId !== 'undefined' ? myUserId : 0,
            user_name: getMyUsername(),
            user_pic: typeof myProfilePic !== 'undefined' ? myProfilePic : '',
            message: message,
            reply_to: wtState.replyTo
        });

        cancelReply();
        input.value = '';
    }

    // Display chat message
    function displayChatMessage(data) {
        const container = document.getElementById('wtChatMessages');
        if (!container) return;

        // Remove placeholder if exists
        const placeholder = container.querySelector('.system');
        if (placeholder) placeholder.remove();

        const pic = resolveProfilePic(data.user_pic);
        const isMe = String(data.user_id) === String(typeof myUserId !== 'undefined' ? myUserId : 0);
        const messageDiv = document.createElement('div');
        messageDiv.className = `wt-chat-message ${isMe ? 'own' : ''}`;

        let replyHtml = '';
        if (data.reply_to) {
            replyHtml = `
                <div class="wt-message-reply-to">
                    <div class="line"></div>
                    <div class="reply-content">
                        <span class="user">${escapeHtml(data.reply_to.user_name)}</span>
                        <span class="text">${escapeHtml(data.reply_to.message)}</span>
                    </div>
                </div>
            `;
        }

        messageDiv.innerHTML = `
            ${!isMe ? `<img src="${pic}" class="avatar" alt="" onerror="this.src='https://www.gravatar.com/avatar/00?d=mp'">` : ''}
            <div class="content">
                ${!isMe ? `<div class="name">${escapeHtml(data.user_name)}</div>` : ''}
                ${replyHtml}
                <div class="text">
                    ${escapeHtml(data.message)}
                    <button class="wt-msg-reply-btn" title="Reply">
                        <i class="fa-solid fa-reply"></i>
                    </button>
                </div>
            </div>
        `;

        // Bind reply button
        messageDiv.querySelector('.wt-msg-reply-btn').addEventListener('click', () => {
            initiateReply(data);
        });

        container.appendChild(messageDiv);
        container.scrollTop = container.scrollHeight;

        // Message Notification Badge logic
        if (!wtState.isChatMode || wtState.currentTab !== 'server') {
            const badge = document.getElementById('wtChatBadge');
            if (badge) {
                let count = parseInt(badge.textContent) + 1;
                badge.textContent = count;
                badge.style.display = 'flex';
            }
        }

        // Also update waiting screen chat preview
        if (!wtState.currentVideo) {
            updateWaitingChatPreview(data);
        }
    }

    // Update the waiting screen chat preview with new messages
    function updateWaitingChatPreview(data) {
        const previewContainer = document.getElementById('wtWaitingChatPreview');
        if (!previewContainer) return;

        // Remove "No messages yet" placeholder if it exists
        const emptyState = previewContainer.querySelector('.wt-chat-preview-empty');
        if (emptyState) {
            previewContainer.innerHTML = '';
        }

        const pic = resolveProfilePic(data.user_pic);
        const name = escapeHtml(data.user_name);
        const text = escapeHtml(data.message);

        const msgDiv = document.createElement('div');
        msgDiv.className = 'wt-chat-preview-message';
        msgDiv.innerHTML = `
            <img src="${pic}" onerror="this.src='https://www.gravatar.com/avatar/00?d=mp'">
            <div class="wt-chat-preview-bubble">
                <div class="name">${name}</div>
                <div class="text">${text}</div>
            </div>
        `;

        previewContainer.appendChild(msgDiv);

        // Auto-scroll to bottom of preview
        previewContainer.scrollTop = previewContainer.scrollHeight;

        // Keep only the last 10 messages for performance
        const items = previewContainer.querySelectorAll('.wt-chat-preview-message');
        if (items.length > 10) {
            items[0].remove();
        }
    }

    // ==========================================
    // CHAT REPLY HELPERS
    // ==========================================

    function initiateReply(data) {
        wtState.replyTo = {
            user_id: data.user_id,
            user_name: data.user_name,
            message: data.message
        };

        const preview = document.getElementById('wtReplyPreview');
        const nameEl = document.getElementById('wtReplyName');
        const textEl = document.getElementById('wtReplyText');
        const input = document.getElementById('wtChatInput');

        if (preview && nameEl && textEl) {
            nameEl.textContent = data.user_name;
            textEl.textContent = data.message;
            preview.style.display = 'flex';
            input?.focus();
        }
    }

    function cancelReply() {
        wtState.replyTo = null;
        const preview = document.getElementById('wtReplyPreview');
        if (preview) preview.style.display = 'none';
    }

    function toggleChatMode() {
        wtState.isChatMode = !wtState.isChatMode;
        const panel = document.getElementById('wtParticipantsPanel');
        if (panel) {
            panel.classList.toggle('chat-open', wtState.isChatMode);
            // Ensure panel is not in mini mode if opening chat
            if (wtState.isChatMode) panel.classList.remove('mini');
        }

        const chatIcon = document.getElementById('wtToggleChatBtn')?.querySelector('i');
        if (chatIcon) {
            chatIcon.className = wtState.isChatMode ? 'fa-solid fa-users' : 'fa-solid fa-comments';
        }

        // Reset badge when opening chat
        if (wtState.isChatMode) {
            const badge = document.getElementById('wtChatBadge');
            if (badge) {
                badge.textContent = '0';
                badge.style.display = 'none';
            }
        }
    }

    // Keyboard shortcuts
    function handleKeyboardShortcuts(e) {
        if (!wtState.isActive) return;
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;

        switch (e.key.toLowerCase()) {
            case ' ':
            case 'k':
                e.preventDefault();
                togglePlayPause();
                break;
            case 'f':
                e.preventDefault();
                toggleFullscreen();
                break;
            case 'm':
                e.preventDefault();
                toggleMute();
                break;
            case 'arrowleft':
                e.preventDefault();
                seek(-10);
                break;
            case 'arrowright':
                e.preventDefault();
                seek(10);
                break;
            case 'escape':
                if (document.fullscreenElement) {
                    document.exitFullscreen();
                }
                break;
        }
    }

    // Leave WatchTogether session
    function leaveWatchTogether() {
        wtState.isActive = false;
        window.wtIsActive = false;

        // Release resources
        releaseWakeLock();
        if (wtState.priorityCtx) {
            wtState.priorityCtx.close().catch(() => { });
            wtState.priorityCtx = null;
        }
        if ('mediaSession' in navigator) {
            navigator.mediaSession.playbackState = 'none';
        }

        // Notify server
        if (wtState.ws && wtState.ws.readyState === WebSocket.OPEN) {
            wtState.ws.send(JSON.stringify({
                type: 'WATCHTOGETHER_LEAVE',
                session_id: wtState.sessionId,
                group_id: wtState.groupId,
                user_id: typeof myUserId !== 'undefined' ? myUserId : 0,
                user_name: getMyUsername()
            }));

            // If admin, also broadcast party ended
            if (wtState.isAdmin) {
                broadcastPartyStatus(wtState.groupId, false);
            }
        }

        // Cleanup
        closeWatchTogether();
    }

    // Close WatchTogether UI
    function closeWatchTogether() {
        const overlay = document.getElementById('watchTogetherOverlay');
        if (overlay) {
            overlay.classList.remove('active');
            setTimeout(() => overlay.remove(), 300);
        }

        // Stop video
        if (wtState.videoPlayer) {
            wtState.videoPlayer.pause();
            wtState.videoPlayer.src = '';
        }

        // Reset state
        wtState.isActive = false;
        wtState.currentVideo = null;
        wtState.participants.clear();
        document.body.style.overflow = '';
        document.body.classList.remove('wt-active');

        // Stop waiting sound
        stopWaitingSound();

        // Cleanup starfield background
        if (wtState.starfieldCleanup) {
            wtState.starfieldCleanup();
            wtState.starfieldCleanup = null;
        }

        // Remove keyboard listener
        document.removeEventListener('keydown', handleKeyboardShortcuts);
    }

    // Show sync toast notification
    function showSyncToast(message) {
        let toast = document.getElementById('wtSyncToast');

        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'wtSyncToast';
            toast.className = 'wt-sync-toast';
            const container = document.getElementById('watchTogetherOverlay') || document.body;
            container.appendChild(toast);
        }

        toast.innerHTML = `<i class="fa-solid fa-circle-play"></i> ${escapeHtml(message)}`;
        toast.classList.add('visible');

        clearTimeout(toast.hideTimer);
        toast.hideTimer = setTimeout(() => {
            toast.classList.remove('visible');
        }, 3000);
    }

    // Check if there's an active party for a group
    function hasActiveParty(groupId) {
        return activeParties.has(String(groupId));
    }

    // Get active party info
    function getActiveParty(groupId) {
        return activeParties.get(String(groupId));
    }

    // Export functions to global scope
    window.WatchTogether = {
        init: initWatchTogether,
        close: closeWatchTogether,
        isActive: () => wtState.isActive,
        hasActiveParty: hasActiveParty,
        getActiveParty: getActiveParty,
        activeParties: activeParties
    };

    // Auto-init if triggered from chat button
    window.startWatchTogether = function (groupId) {
        initWatchTogether(groupId, true);
    };

    window.joinWatchTogether = function (groupId, sessionId) {
        wtState.sessionId = sessionId;
        initWatchTogether(groupId, false);
    };

    function setWtTab(tab) {
        if (tab === 'private' && wtState.currentTab === 'private' && wtActivePrivatePeer) {
            goBackToList();
            return;
        }

        wtState.currentTab = tab;

        document.querySelectorAll('.wt-panel-tab').forEach(t => t.classList.remove('active'));
        document.getElementById(`tab${tab.charAt(0).toUpperCase() + tab.slice(1)}`)?.classList.add('active');

        document.getElementById('panelServerContent')?.classList.toggle('active', tab === 'server');
        document.getElementById('panelPrivateContent')?.classList.toggle('active', tab === 'private');

        const tabs = document.querySelector('.wt-panel-tabs');
        if (tabs) tabs.style.display = ''; // Reset to CSS default

        if (tab === 'private' && !wtActivePrivatePeer) {
            loadWtPrivateChats();
        }
    }

    window.setWtTab = setWtTab;

    function goBackToList() {
        wtActivePrivatePeer = null;
        const inbox = document.getElementById('wtPrivateInbox');
        const msgs = document.getElementById('wtPrivateChatMessages');
        const backBtn = document.getElementById('wtBackToList');
        const inputArea = document.getElementById('wtPrivateInputArea');

        if (inbox) inbox.style.display = 'flex';
        if (msgs) msgs.style.display = 'none';
        if (backBtn) backBtn.style.display = 'none';
        if (inputArea) inputArea.style.display = 'none';

        const tabs = document.querySelector('.wt-panel-tabs');
        if (tabs) tabs.style.setProperty('display', 'flex', 'important');

        loadWtPrivateChats();
    }

    window.goBackToList = goBackToList;

    async function loadWtPrivateChats() {
        const list = document.getElementById('wtPrivateChatsList');
        if (!list) return;

        list.innerHTML = '<div style="text-align: center; padding: 20px; color: var(--wt-text-secondary);"><i class="fa-solid fa-spinner fa-spin"></i> Loading...</div>';

        try {
            const res = await fetch(`../backend/get_conversations.php?_t=${Date.now()}`);
            const data = await res.json();

            if (data.success) {
                let items = [];
                if (data.conversations) items.push(...data.conversations);
                if (data.groups) items.push(...data.groups.map(g => ({ ...g, isGroup: true, username: g.name, profile_picture: g.picture })));

                if (items.length === 0) {
                    list.innerHTML = '<div style="text-align: center; padding: 20px; color: var(--wt-text-secondary);">No messages found</div>';
                    return;
                }

                items.sort((a, b) => new Date(b.last_time || 0) - new Date(a.last_time || 0));

                list.innerHTML = items.map(c => `
                    <div class="wt-private-chat-item" onclick="openWtPrivateChat('${c.id}', '${escapeHtml(c.username)}', '${resolveProfilePic(c.profile_picture)}', ${!!c.isGroup})">
                        <img src="${resolveProfilePic(c.profile_picture)}" onerror="this.src='https://www.gravatar.com/avatar/00?d=mp'">
                        <div class="info">
                            <div class="name">${escapeHtml(c.username)} ${c.isGroup ? '<i class="fa-solid fa-users" style="font-size: 10px; margin-left: 5px; opacity: 0.5;"></i>' : ''}</div>
                            <div class="preview">${escapeHtml(c.last_message || 'Start chatting')}</div>
                        </div>
                    </div>
                `).join('');
            }
        } catch (e) {
            console.error('Failed to load private chats in WT:', e);
            list.innerHTML = '<div style="text-align: center; padding: 20px; color: var(--wt-danger);">Error loading chats</div>';
        }
    }

    let wtActivePrivatePeer = null;


    window.openWtPrivateChat = async function (peerId, name, pic, isGroup = false) {
        wtActivePrivatePeer = { id: peerId, name: name, pic: pic, isGroup: isGroup };

        // UI transitions
        document.getElementById('wtPrivateInbox').style.display = 'none';
        document.getElementById('wtPrivateChatMessages').style.display = 'flex';
        document.getElementById('wtBackToList').style.display = 'flex';
        document.getElementById('wtPrivateInputArea').style.display = 'flex';

        const tabs = document.querySelector('.wt-panel-tabs');
        if (tabs) tabs.style.setProperty('display', 'none', 'important');

        const msgContainer = document.getElementById('wtPrivateChatMessages');

        msgContainer.innerHTML = '<div style="text-align: center; padding: 20px; color: var(--wt-text-secondary);"><i class="fa-solid fa-spinner fa-spin"></i> Loading...</div>';

        try {
            const url = isGroup
                ? `../backend/get_private_messages.php?group_id=${peerId}&_t=${Date.now()}`
                : `../backend/get_private_messages.php?other_id=${peerId}&_t=${Date.now()}`;

            const res = await fetch(url);
            const data = await res.json();

            if (data.success) {
                msgContainer.innerHTML = '';
                const messages = data.messages || [];
                if (messages.length === 0) {
                    msgContainer.innerHTML = '<div style="text-align: center; padding: 20px; color: var(--wt-text-secondary);">No messages yet</div>';
                }
                messages.forEach(m => {
                    const isMe = String(m.sender_id) === String(typeof myUserId !== 'undefined' ? myUserId : 0);
                    const msgDiv = document.createElement('div');
                    msgDiv.className = `wt-chat-message ${isMe ? 'own' : ''}`;
                    msgDiv.innerHTML = `
                        ${!isMe ? `<img src="${resolveProfilePic(m.sender_pic || m.profile_picture)}" class="avatar">` : ''}
                        <div class="content">
                            ${!isMe ? `<div class="name">${escapeHtml(m.sender_name || m.username)}</div>` : ''}
                            <div class="msg-content">${escapeHtml(m.message)}</div>
                        </div>
                    `;
                    msgContainer.appendChild(msgDiv);
                });
                msgContainer.scrollTop = msgContainer.scrollHeight;
            }
        } catch (e) {
            msgContainer.innerHTML = '<div style="text-align: center; padding: 20px; color: var(--wt-danger);">Error loading history</div>';
        }
    };

    // Remove old event listener for wtBackToList as it's being removed
    // document.getElementById('wtBackToList')?.addEventListener('click', ...);

    function sendWtPrivateMessage() {
        const input = document.getElementById('wtPrivateChatInput');
        const text = input?.value.trim();
        if (!text || !wtActivePrivatePeer) return;

        // Optimistic append
        const msgContainer = document.getElementById('wtPrivateChatMessages');
        const msgDiv = document.createElement('div');
        msgDiv.className = 'wt-chat-message own';
        msgDiv.innerHTML = `
            <div class="content">
                <div class="msg-content">${escapeHtml(text)}</div>
            </div>
        `;
        msgContainer.appendChild(msgDiv);
        msgContainer.scrollTop = msgContainer.scrollHeight;
        input.value = '';

        if (wtActivePrivatePeer.isGroup) {
            // Group message logic
            if (window.ws && window.ws.readyState === WebSocket.OPEN) {
                window.ws.send(JSON.stringify({
                    type: 'GROUP_MESSAGE',
                    group_id: wtActivePrivatePeer.id,
                    sender_id: typeof myUserId !== 'undefined' ? myUserId : 0,
                    user: typeof myUsername !== 'undefined' ? myUsername : 'User',
                    text: text,
                    profilePic: typeof myProfilePic !== 'undefined' ? myProfilePic : ''
                }));
            }

            fetch('../backend/send_private_message.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    group_id: wtActivePrivatePeer.id,
                    message: text
                })
            });
        } else {
            // Handled by main chat logic usually, but here we need to call the backend
            fetch('../backend/send_private_message.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    receiver_id: wtActivePrivatePeer.id,
                    message: text
                })
            }).then(res => res.json()).then(data => {
                if (data.success) {
                    // Also signal via WS if available
                    if (window.ws && window.ws.readyState === WebSocket.OPEN) {
                        window.ws.send(JSON.stringify({
                            type: 'PRIVATE_MESSAGE',
                            sender_id: typeof myUserId !== 'undefined' ? myUserId : 0,
                            receiver_id: wtActivePrivatePeer.id,
                            user: typeof myUsername !== 'undefined' ? myUsername : 'User',
                            text: text,
                            profilePic: typeof myProfilePic !== 'undefined' ? myProfilePic : ''
                        }));
                    }
                }
            });
        }
    }

    document.getElementById('wtPrivateChatSendBtn')?.addEventListener('click', sendWtPrivateMessage);
    document.getElementById('wtPrivateChatInput')?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') sendWtPrivateMessage();
    });

    // Expose functions to global scope
    window.startWatchTogether = (groupId) => initWatchTogether(groupId, true);
    window.joinWatchTogether = (groupId, sessionId) => {
        // Init logic for joiner
        wtState.groupId = groupId;
        wtState.isAdmin = false;
        wtState.sessionId = sessionId;

        createWatchTogetherUI();
        connectToSession();

        wtState.isActive = true;
        document.body.style.overflow = 'hidden';

        // Start waiting sound for joiners too
        startWaitingSound();
    };

    // ==========================================
    // WAITING SOUND FUNCTIONS
    // ==========================================

    // Start the ambient waiting sound (very quiet, looped)
    function startWaitingSound() {
        if (wtState.waitingAudio) return; // Already playing

        try {
            wtState.waitingAudio = new Audio('./waiting_song.m4a');
            wtState.waitingAudio.loop = true;
            wtState.waitingAudio.volume = 0.08; // Very quiet - barely noticeable
            wtState.waitingAudio.play().catch(e => {
                console.warn('[WatchTogether] Waiting sound autoplay blocked:', e);
            });
        } catch (e) {
            console.warn('[WatchTogether] Could not load waiting sound:', e);
        }
    }

    // Stop the waiting sound
    function stopWaitingSound() {
        if (wtState.waitingAudio) {
            wtState.waitingAudio.pause();
            wtState.waitingAudio.currentTime = 0;
            wtState.waitingAudio = null;
        }
    }

    // Toggle waiting sound on/off
    function toggleWaitingSound() {
        const btn = document.getElementById('wtMuteWaitingBtn');
        const icon = document.getElementById('wtWaitingVolumeIcon');

        if (!wtState.waitingAudio) return;

        wtState.waitingSoundMuted = !wtState.waitingSoundMuted;

        if (wtState.waitingSoundMuted) {
            wtState.waitingAudio.volume = 0;
            if (icon) icon.className = 'fa-solid fa-volume-xmark';
            if (btn) btn.classList.add('muted');
            btn.title = 'Unmute Waiting Music';
        } else {
            wtState.waitingAudio.volume = 0.08;
            if (icon) icon.className = 'fa-solid fa-music';
            if (btn) btn.classList.remove('muted');
            btn.title = 'Mute Waiting Music';
        }
    }

    // ==========================================
    // STARFIELD FOR WAITING SCREEN
    // ==========================================

    function initWaitingStarfield() {
        const canvas = document.getElementById('wtStarsCanvas');
        if (!canvas) return;

        const ctx = canvas.getContext('2d');
        let stars = [];
        const starCount = 120;
        let animationId;

        function resize() {
            canvas.width = canvas.parentElement?.offsetWidth || window.innerWidth;
            canvas.height = canvas.parentElement?.offsetHeight || window.innerHeight;
            initStars();
        }

        function initStars() {
            stars = [];
            for (let i = 0; i < starCount; i++) {
                stars.push({
                    x: Math.random() * canvas.width,
                    y: Math.random() * canvas.height,
                    size: Math.random() * 1.5 + 0.5,
                    speed: Math.random() * 0.3 + 0.1,
                    opacity: Math.random() * 0.6 + 0.2,
                    twinkle: Math.random() * 2
                });
            }
        }

        function draw() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);

            stars.forEach(star => {
                // Subtle twinkling
                const opacity = star.opacity + Math.sin(Date.now() * 0.001 + star.twinkle) * 0.15;

                ctx.fillStyle = `rgba(255, 255, 255, ${Math.max(0.1, Math.min(0.8, opacity))})`;
                ctx.beginPath();
                ctx.arc(star.x, star.y, star.size, 0, Math.PI * 2);
                ctx.fill();

                // Slow upward drift
                star.y -= star.speed;
                if (star.y < -5) {
                    star.y = canvas.height + 5;
                    star.x = Math.random() * canvas.width;
                }
            });

            animationId = requestAnimationFrame(draw);
        }

        window.addEventListener('resize', resize);
        resize();
        draw();

        // Store cancel function for cleanup
        wtState.starfieldCleanup = () => {
            cancelAnimationFrame(animationId);
            window.removeEventListener('resize', resize);
        };
    }

    // ==========================================
    // GAMES MODULE LOGIC
    // ==========================================

    let selectedGameType = null;
    let selectedPlayers = new Set();
    let tugGameLoop = null;

    function openGameSelector() {
        const modal = document.getElementById('wtGameSelector');
        if (!modal) return;
        showGameList();
        modal.style.display = 'flex';
    }

    window.showGameList = function () {
        document.getElementById('wtGameModalTitle').textContent = 'Select Game';
        document.getElementById('wtGameTypeList').style.display = 'block';
        document.getElementById('wtGameOpponentList').style.display = 'none';
        selectedGameType = null;
        selectedPlayers.clear();
    }

    window.selectGameType = function (type) {
        selectedGameType = type;
        const required = type === 'three-way-tug' ? 2 : 1;
        document.getElementById('wtGameModalTitle').textContent = 'Choose Opponent' + (required > 1 ? 's' : '');
        document.getElementById('wtGameTypeList').style.display = 'none';
        document.getElementById('wtGameOpponentList').style.display = 'block';
        document.getElementById('wtGameSelectionStatus').textContent = `Select ${required} participant${required > 1 ? 's' : ''}`;

        const confirmArea = document.getElementById('wtGameConfirmSelection');
        if (confirmArea) confirmArea.style.display = required > 1 ? 'block' : 'none';

        renderOpponentList();
    }

    function renderOpponentList() {
        const list = document.getElementById('wtGameFriendsList');
        if (!list) return;

        list.innerHTML = '';
        const myId = typeof myUserId !== 'undefined' ? myUserId : 0;
        const required = selectedGameType === 'three-way-tug' ? 2 : 1;

        if (wtState.participants.size <= 1) {
            list.innerHTML = '<div style="text-align: center; padding: 20px; color: var(--wt-text-secondary);">Nobody else is in the room right now.</div>';
        } else {
            wtState.participants.forEach((p, id) => {
                if (String(id) !== String(myId)) {
                    const isSelected = selectedPlayers.has(String(id));
                    const card = document.createElement('div');
                    card.className = `wt-opponent-card ${isSelected ? 'selected' : ''}`;
                    card.innerHTML = `
                        <img src="${resolveProfilePic(p.user_pic)}" onerror="this.src='https://www.gravatar.com/avatar/00?d=mp'">
                        <div class="name">${escapeHtml(p.user_name)}</div>
                        <i class="fa-solid ${isSelected ? 'fa-check' : 'fa-play'}" style="color: var(--wt-accent); font-size: 14px;"></i>
                    `;
                    card.onclick = () => togglePlayerSelection(id, p.user_name, required);
                    list.appendChild(card);
                }
            });
        }

        updateConfirmBtn();
    }

    function togglePlayerSelection(id, name, required) {
        const sid = String(id);
        if (selectedPlayers.has(sid)) {
            selectedPlayers.delete(sid);
        } else {
            if (required === 1) {
                selectedPlayers.clear();
                selectedPlayers.add(sid);
                sendSelectedGameInvites(); // Auto send if only 1 required
                return;
            }
            if (selectedPlayers.size < required) {
                selectedPlayers.add(sid);
            }
        }
        renderOpponentList();
    }

    function updateConfirmBtn() {
        const btn = document.getElementById('wtConfirmGameBtn');
        if (!btn) return;
        const required = selectedGameType === 'three-way-tug' ? 2 : 1;
        btn.textContent = `Send Invites (${selectedPlayers.size}/${required})`;
        btn.disabled = selectedPlayers.size !== required;
    }

    function sendSelectedGameInvites() {
        const required = selectedGameType === 'three-way-tug' ? 2 : 1;
        if (selectedPlayers.size !== required) return;

        document.getElementById('wtGameSelector').style.display = 'none';
        showSyncToast(`Sending invites...`);

        const playerList = Array.from(selectedPlayers);
        playerList.push(String(typeof myUserId !== 'undefined' ? myUserId : 0));

        if (wtState.ws && wtState.ws.readyState === WebSocket.OPEN) {
            // Join all into a match
            wtState.ws.send(JSON.stringify({
                type: 'WATCHTOGETHER_GAME_INVITE',
                group_id: wtState.groupId,
                session_id: wtState.sessionId,
                sender_id: typeof myUserId !== 'undefined' ? myUserId : 0,
                sender_name: getMyUsername(),
                receivers: Array.from(selectedPlayers),
                game_type: selectedGameType,
                all_players: playerList
            }));

            // Track pending
            wtState.pendingInvites.clear();
            selectedPlayers.forEach(id => {
                const name = wtState.participants.get(String(id))?.user_name || 'User';
                wtState.pendingInvites.set(String(id), name);
            });
            updateWaitingStatus();
        }
    }

    function updateWaitingStatus() {
        if (wtState.pendingInvites.size === 0) return;

        const names = Array.from(wtState.pendingInvites.values());
        const statusText = `Waiting for ${names.join(' and ')} to respond...`;

        // Update Replay buttons if visible
        ['wtReplayBtn', 'wtTugReplayBtn'].forEach(id => {
            const btn = document.getElementById(id);
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> ${statusText}`;
            }
        });

        showSyncToast(statusText);
    }

    function handleGameInvite(data) {
        const myId = String(typeof myUserId !== 'undefined' ? myUserId : 0);
        // Robust check: Ensure receivers list exists and contains current user (loose conversion to string)
        if (!data.receivers || !data.receivers.map(id => String(id)).includes(myId)) return;

        const existing = document.getElementById('wtGameInviteBanner');
        if (existing) existing.remove();

        const banner = document.createElement('div');
        banner.id = 'wtGameInviteBanner';
        banner.className = 'wt-game-invite-banner';
        const isReplay = data.is_replay === true;
        const gameName = data.game_type === 'three-way-tug' ? 'Three-Way Tug' : 'Tic-Tac-Toe';

        banner.innerHTML = `
            <div class="wt-invite-text"><b>${escapeHtml(data.sender_name)}</b> ${isReplay ? 'wants a REMATCH!' : `wants to play ${gameName}!`}</div>
            <div class="wt-invite-actions">
                <button class="wt-invite-btn accept" onclick='respondToMultiPlayerInvite(${JSON.stringify(data)}, true)'>${isReplay ? 'Accept' : 'Play'}</button>
                <button class="wt-invite-btn deny" onclick='respondToMultiPlayerInvite(${JSON.stringify(data)}, false)'>${isReplay ? 'No thanks' : 'Deny'}</button>
            </div>
        `;
        document.body.appendChild(banner);
        setTimeout(() => banner.remove(), 15000);
    }

    window.respondToMultiPlayerInvite = function (data, accepted) {
        document.getElementById('wtGameInviteBanner')?.remove();

        if (wtState.ws && wtState.ws.readyState === WebSocket.OPEN) {
            wtState.ws.send(JSON.stringify({
                type: 'WATCHTOGETHER_GAME_RESPONSE',
                group_id: wtState.groupId,
                session_id: wtState.sessionId,
                sender_id: String(typeof myUserId !== 'undefined' ? myUserId : 0),
                sender_name: getMyUsername(),
                receiver_id: String(data.sender_id), // Respond back to host
                accepted: accepted,
                game_type: data.game_type,
                all_players: data.all_players
            }));
        }

        if (accepted) {
            if (data.game_type === 'three-way-tug') {
                startThreeWayTug(data.all_players);
            } else {
                startTicTacToe(data.sender_id, data.sender_name, 'O');
            }
        }
    }

    function handleGameResponse(data) {
        const myId = String(typeof myUserId !== 'undefined' ? myUserId : 0);
        if (String(data.receiver_id) !== myId) return;

        if (data.accepted) {
            showSyncToast(`${data.sender_name} accepted!`);

            if (data.game_type === 'three-way-tug') {
                // Remove from pending
                wtState.pendingInvites.delete(String(data.sender_id));

                if (wtState.pendingInvites.size > 0) {
                    updateWaitingStatus();
                } else {
                    startThreeWayTug(data.all_players);
                }
            } else {
                wtState.pendingInvites.clear();
                startTicTacToe(data.sender_id, data.sender_name, 'X');
            }
        } else {
            wtState.pendingInvites.delete(String(data.sender_id));
            showSyncToast(`${data.sender_name} declined.`);

            // Reset buttons if decline received
            ['wtReplayBtn', 'wtTugReplayBtn'].forEach(id => {
                const btn = document.getElementById(id);
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-solid fa-rotate-right"></i> Play Again';
                }
            });
        }
    }

    // ==========================================
    // THREE-WAY TUG ENGINE
    // ==========================================

    function startThreeWayTug(playerIds) {
        if (tugGameLoop) cancelAnimationFrame(tugGameLoop);

        const myId = String(typeof myUserId !== 'undefined' ? myUserId : 0);
        const myIdx = playerIds.indexOf(myId);

        // Define controls based on player index in invitation
        // P1: Mouse, P2: A, P3: L
        let inputMethod = 'mouse';
        if (myIdx === 1) inputMethod = 'A';
        if (myIdx === 2) inputMethod = 'L';

        wtState.game = {
            active: true,
            type: 'three-way-tug',
            playerIds: playerIds,
            myIndex: myIdx,
            inputMethod: inputMethod,
            pulling: [false, false, false],
            pos: { x: 200, y: 200 },
            vel: { x: 0, y: 0 },
            winners: null,
            canvas: document.getElementById('wtTugCanvas'),
            ctx: document.getElementById('wtTugCanvas')?.getContext('2d'),
            instruction: inputMethod === 'mouse' ? 'LEFT CLICK TO PULL' : `HOLD '${inputMethod}' TO PULL`
        };

        const overlay = document.getElementById('wtTugOverlay');
        if (overlay) overlay.style.display = 'block';

        const replayBtn = document.getElementById('wtTugReplayBtn');
        if (replayBtn) {
            replayBtn.disabled = false;
            replayBtn.innerHTML = '<i class="fa-solid fa-rotate-right"></i> Play Again';
        }

        document.getElementById('wtTugInstruction').textContent = wtState.game.instruction;
        document.getElementById('wtTugStatus').textContent = 'STAY CENTERED!';
        document.getElementById('wtTugStatus').style.color = '#fff';
        document.getElementById('wtTugEndActions').style.display = 'none';

        initTugEvents();
        runTugLoop();
    }

    function initTugEvents() {
        const g = wtState.game;
        window.onmousedown = () => { if (g.inputMethod === 'mouse') sendTugPull(true); };
        window.onmouseup = () => { if (g.inputMethod === 'mouse') sendTugPull(false); };
        window.onkeydown = (e) => {
            if (e.key.toUpperCase() === g.inputMethod) sendTugPull(true);
        };
        window.onkeyup = (e) => {
            if (e.key.toUpperCase() === g.inputMethod) sendTugPull(false);
        };
    }

    function sendTugPull(state) {
        if (!wtState.game.active) return;
        if (wtState.game.pulling[wtState.game.myIndex] === state) return;

        wtState.game.pulling[wtState.game.myIndex] = state;

        if (wtState.ws && wtState.ws.readyState === WebSocket.OPEN) {
            wtState.ws.send(JSON.stringify({
                type: 'WATCHTOGETHER_GAME_MOVE',
                game_type: 'three-way-tug',
                group_id: wtState.groupId,
                sender_id: typeof myUserId !== 'undefined' ? myUserId : 0,
                receivers: wtState.game.playerIds.filter(id => id !== String(typeof myUserId !== 'undefined' ? myUserId : 0)),
                pull_index: wtState.game.myIndex,
                pull_state: state
            }));
        }
    }

    function handleTugMove(data) {
        if (!wtState.game.active || wtState.game.type !== 'three-way-tug') return;
        if (typeof data.pull_index === 'number') {
            wtState.game.pulling[data.pull_index] = data.pull_state;
        }
    }

    function runTugLoop() {
        if (!wtState.game.active && !wtState.game.winners) return;

        updateTugPhysics();
        drawTugArena();

        if (wtState.game.active) {
            tugGameLoop = requestAnimationFrame(runTugLoop);
        }
    }

    function updateTugPhysics() {
        const g = wtState.game;
        if (!g.active) return;

        const centerX = 200, centerY = 200;
        const radius = 140;
        const forceStrength = 0.35;
        const friction = 0.96;
        const winDist = 160;

        // Player Vertex positions (Equilateral)
        const vertices = [
            { x: centerX, y: centerY - radius }, // P0 (Top)
            { x: centerX - radius * 0.866, y: centerY + radius * 0.5 }, // P1 (Bottom Left)
            { x: centerX + radius * 0.866, y: centerY + radius * 0.5 }  // P2 (Bottom Right)
        ];

        let tx = 0, ty = 0;
        g.pulling.forEach((isPulling, i) => {
            if (isPulling) {
                const dx = vertices[i].x - g.pos.x;
                const dy = vertices[i].y - g.pos.y;
                const dist = Math.sqrt(dx * dx + dy * dy) || 1;
                tx += (dx / dist) * forceStrength;
                ty += (dy / dist) * forceStrength;
            }
        });

        // Add noise
        tx += (Math.random() - 0.5) * 0.05;
        ty += (Math.random() - 0.5) * 0.05;

        g.vel.x += tx;
        g.vel.y += ty;
        g.vel.x *= friction;
        g.vel.y *= friction;
        g.pos.x += g.vel.x;
        g.pos.y += g.vel.y;

        // Win check
        vertices.forEach((v, i) => {
            const dx = v.x - g.pos.x;
            const dy = v.y - g.pos.y;
            const dist = Math.sqrt(dx * dx + dy * dy);
            if (dist < 40) {
                triggerTugWin(i);
            }
        });

        // Out of bounds safety
        const distFromCenter = Math.sqrt((g.pos.x - centerX) ** 2 + (g.pos.y - centerY) ** 2);
        if (distFromCenter > 180) triggerTugWin(-1);
    }

    function triggerTugWin(index) {
        wtState.game.active = false;
        const status = document.getElementById('wtTugStatus');

        if (index === -1) {
            status.textContent = 'STALEMATE! ⚔️';
        } else {
            const isMe = index === wtState.game.myIndex;
            const winnerName = wtState.participants.get(wtState.game.playerIds[index])?.user_name || 'Player';
            status.textContent = isMe ? 'YOU PULLED THROUGH! 🏆' : `${winnerName.toUpperCase()} WON!`;
            status.style.color = isMe ? 'var(--wt-success)' : 'var(--wt-danger)';
        }

        document.getElementById('wtTugEndActions').style.display = 'flex';
    }

    function drawTugArena() {
        const g = wtState.game;
        const ctx = g.ctx;
        if (!ctx) return;
        const w = 400, h = 400;
        const cx = 200, cy = 200;
        const radius = 140;

        ctx.clearRect(0, 0, w, h);

        // Shake logic
        const distFromCenter = Math.sqrt((g.pos.x - cx) ** 2 + (g.pos.y - cy) ** 2);
        const shake = Math.max(0, (distFromCenter - 80) * 0.1);
        if (shake > 0) {
            ctx.translate((Math.random() - 0.5) * shake, (Math.random() - 0.5) * shake);
        }

        const vertices = [
            { x: cx, y: cy - radius, color: '#3ea6ff' },
            { x: cx - radius * 0.866, y: cy + radius * 0.5, color: '#ff3e3e' },
            { x: cx + radius * 0.866, y: cy + radius * 0.5, color: '#3eff3e' }
        ];

        // Draw Arena Triangle
        ctx.strokeStyle = 'rgba(255,255,255,0.05)';
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.moveTo(vertices[0].x, vertices[0].y);
        ctx.lineTo(vertices[1].x, vertices[1].y);
        ctx.lineTo(vertices[2].x, vertices[2].y);
        ctx.closePath();
        ctx.stroke();

        // Pull Lines
        vertices.forEach((v, i) => {
            if (g.pulling[i]) {
                ctx.strokeStyle = v.color;
                ctx.lineWidth = 3 + Math.sin(Date.now() * 0.02) * 1;
                ctx.beginPath();
                ctx.moveTo(v.x, v.y);
                ctx.lineTo(g.pos.x, g.pos.y);
                ctx.stroke();

                // Glow
                ctx.shadowBlur = 15;
                ctx.shadowColor = v.color;
                ctx.stroke();
                ctx.shadowBlur = 0;
            }
        });

        // Player Nodes
        vertices.forEach((v, i) => {
            ctx.fillStyle = v.color;
            ctx.beginPath();
            ctx.arc(v.x, v.y, 10, 0, Math.PI * 2);
            ctx.fill();
            if (g.pulling[i]) {
                ctx.strokeStyle = '#fff';
                ctx.lineWidth = 3;
                ctx.stroke();
            }
        });

        // The Orb
        const grad = ctx.createRadialGradient(g.pos.x, g.pos.y, 5, g.pos.x, g.pos.y, 25);
        grad.addColorStop(0, '#fff');
        grad.addColorStop(1, 'rgba(255,255,255,0)');
        ctx.fillStyle = grad;
        ctx.beginPath();
        ctx.arc(g.pos.x, g.pos.y, 25 + Math.sin(Date.now() * 0.01) * 3, 0, Math.PI * 2);
        ctx.fill();

        // Wobble core
        ctx.fillStyle = '#fff';
        ctx.beginPath();
        ctx.arc(g.pos.x + Math.sin(Date.now() * 0.05) * 2, g.pos.y + Math.cos(Date.now() * 0.05) * 2, 10, 0, Math.PI * 2);
        ctx.fill();

        if (shake > 0) ctx.setTransform(1, 0, 0, 1, 0, 0);
    }

    // Unified game handlers are defined above (lines 2271-2337)


    function startTicTacToe(opponentId, opponentName, mySymbol) {
        // Reset replay button if visible
        const btn = document.getElementById('wtReplayBtn');
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-rotate-right"></i> Play Again';
        }
        const actions = document.getElementById('wtGameEndActions');
        if (actions) actions.style.display = 'none';

        wtState.game = {
            active: true,
            type: 'tic-tac-toe',
            opponentId: opponentId,
            opponentName: opponentName,
            mySymbol: mySymbol,
            board: Array(9).fill(null),
            turn: 'X', // 'X' always starts
            canvas: document.getElementById('wtGameCanvas'),
            ctx: document.getElementById('wtGameCanvas')?.getContext('2d')
        };

        const overlay = document.getElementById('wtGameOverlay');
        const status = document.getElementById('wtGameStatus');
        const themBox = document.getElementById('pbox-them');
        const meBox = document.getElementById('pbox-me');

        if (overlay) overlay.style.display = 'block';
        if (status) status.style.color = '#fff'; // Reset color from win/loss
        if (themBox) {
            const opp = wtState.participants.get(String(opponentId));
            themBox.querySelector('img').src = resolveProfilePic(opp?.user_pic);
            themBox.querySelector('.p-symbol').textContent = mySymbol === 'X' ? 'O' : 'X';
        }
        if (meBox) {
            const myPic = getMyProfilePic();
            const myAvatar = meBox.querySelector('img');
            if (myAvatar) myAvatar.src = myPic;
            meBox.querySelector('.p-symbol').textContent = mySymbol;
        }

        updateTTCHUI();
        initTTCEvents();
        drawTTTBoard();
    }

    function updateTTCHUI() {
        const isMyTurn = wtState.game.mySymbol === wtState.game.turn;
        const status = document.getElementById('wtGameStatus');
        const meBox = document.getElementById('pbox-me');
        const themBox = document.getElementById('pbox-them');

        if (status) status.textContent = isMyTurn ? 'YOUR TURN' : `${wtState.game.opponentName.toUpperCase()}'S TURN`;
        if (meBox) meBox.classList.toggle('active', isMyTurn);
        if (themBox) themBox.classList.toggle('active', !isMyTurn);
    }

    function initTTCEvents() {
        const canvas = wtState.game.canvas;
        if (!canvas) return;

        canvas.onclick = (e) => {
            if (!wtState.game.active || wtState.game.turn !== wtState.game.mySymbol) return;

            const rect = canvas.getBoundingClientRect();
            // Scaling coordinates for CSS width/height vs canvas internal width/height
            const scaleX = canvas.width / rect.width;
            const scaleY = canvas.height / rect.height;

            const x = (e.clientX - rect.left) * scaleX;
            const y = (e.clientY - rect.top) * scaleY;

            const cellW = canvas.width / 3;
            const cellH = canvas.height / 3;

            const col = Math.floor(x / cellW);
            const row = Math.floor(y / cellH);
            const index = row * 3 + col;

            if (wtState.game.board[index] === null) {
                makeGameMove(index);
            }
        };
    }

    function makeGameMove(index) {
        wtState.game.board[index] = wtState.game.turn;
        wtState.game.turn = wtState.game.turn === 'X' ? 'O' : 'X';

        drawTTTBoard();
        updateTTCHUI();

        // Send move
        if (wtState.ws && wtState.ws.readyState === WebSocket.OPEN) {
            wtState.ws.send(JSON.stringify({
                type: 'WATCHTOGETHER_GAME_MOVE',
                group_id: wtState.groupId,
                session_id: wtState.sessionId,
                sender_id: typeof myUserId !== 'undefined' ? myUserId : 0,
                receiver_id: wtState.game.opponentId,
                index: index,
                symbol: wtState.game.mySymbol
            }));
        }

        checkGameOver();
    }

    function handleGameMove(data) {
        if (!wtState.game.active) return;
        const myId = String(typeof myUserId !== 'undefined' ? myUserId : 0);
        if (String(data.sender_id) === myId) return;

        if (data.game_type === 'three-way-tug') {
            handleTugMove(data);
            return;
        }

        wtState.game.board[data.index] = data.symbol;
        wtState.game.turn = data.symbol === 'X' ? 'O' : 'X';

        drawTTTBoard();
        updateTTCHUI();
        checkGameOver();
    }

    function checkGameOver() {
        const winner = checkTTTWin(wtState.game.board);
        if (winner || !wtState.game.board.includes(null)) {
            wtState.game.active = false;
            const status = document.getElementById('wtGameStatus');
            if (winner) {
                const isIWin = winner === wtState.game.mySymbol;
                status.textContent = isIWin ? 'YOU WON! 🏆' : `${wtState.game.opponentName} WON!`;
                status.style.color = isIWin ? 'var(--wt-success)' : 'var(--wt-danger)';
            } else {
                status.textContent = "IT'S A DRAW! 🤝";
                status.style.color = '#fff';
            }

            // Show actions
            const actions = document.getElementById('wtGameEndActions');
            if (actions) actions.style.display = 'flex';
        }
    }

    function requestReplay() {
        const isTug = wtState.game.type === 'three-way-tug';
        const btnId = isTug ? 'wtTugReplayBtn' : 'wtReplayBtn';
        const btn = document.getElementById(btnId);

        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Asking...';
        }

        if (wtState.ws && wtState.ws.readyState === WebSocket.OPEN) {
            const myId = String(typeof myUserId !== 'undefined' ? myUserId : 0);

            // Get all participants from the previous game
            // We use the original lists without filtering by 'wtState.participants' just in case
            // the participant map is briefly out of sync.
            const prevList = isTug ? (wtState.game.playerIds || []) : (wtState.game.opponentId ? [wtState.game.opponentId, myId] : [myId]);
            const originalPlayers = prevList.map(id => String(id));

            const payload = {
                type: 'WATCHTOGETHER_GAME_INVITE',
                group_id: wtState.groupId,
                session_id: wtState.sessionId,
                sender_id: myId,
                sender_name: getMyUsername(),
                game_type: wtState.game.type,
                is_replay: true,
                all_players: originalPlayers,
                receivers: originalPlayers.filter(id => id !== myId)
            };

            console.log('[WT] Sending Replay Request to:', payload.receivers);
            wtState.ws.send(JSON.stringify(payload));

            // Track pending for Replay
            wtState.pendingInvites.clear();
            originalPlayers.filter(id => id !== myId).forEach(id => {
                const name = wtState.participants.get(id)?.user_name || 'Opponent';
                wtState.pendingInvites.set(id, name);
            });
            updateWaitingStatus();

            // Safety timeout: If no one responds in 10 seconds, reset the button
            setTimeout(() => {
                if (wtState.pendingInvites.size > 0) {
                    wtState.pendingInvites.clear();
                    const btn = document.getElementById(isTug ? 'wtTugReplayBtn' : 'wtReplayBtn');
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fa-solid fa-rotate-right"></i> Play Again';
                    }
                    showSyncToast('Invite timed out.');
                }
            }, 10000);
        }
    }

    function drawTTTBoard() {
        const ctx = wtState.game.ctx;
        if (!ctx) return;
        const canvas = wtState.game.canvas;
        const w = canvas.width;
        const h = canvas.height;

        ctx.clearRect(0, 0, w, h);

        // Lines
        ctx.strokeStyle = 'rgba(255,255,255,0.1)';
        ctx.lineWidth = 4;
        ctx.lineCap = 'round';

        // Vertical & Horizontal Grid
        for (let i = 1; i < 3; i++) {
            ctx.beginPath(); ctx.moveTo(i * (w / 3), 10); ctx.lineTo(i * (w / 3), h - 10); ctx.stroke();
            ctx.beginPath(); ctx.moveTo(10, i * (h / 3)); ctx.lineTo(w - 10, i * (h / 3)); ctx.stroke();
        }

        // Symbols
        wtState.game.board.forEach((val, i) => {
            if (!val) return;
            const row = Math.floor(i / 3);
            const col = i % 3;
            const cx = col * (w / 3) + (w / 6);
            const cy = row * (h / 3) + (h / 6);
            const size = (w / 10);

            if (val === 'X') {
                ctx.strokeStyle = '#3ea6ff';
                ctx.lineWidth = 10;
                ctx.beginPath();
                ctx.moveTo(cx - size, cy - size); ctx.lineTo(cx + size, cy + size);
                ctx.moveTo(cx + size, cy - size); ctx.lineTo(cx - size, cy + size);
                ctx.stroke();
            } else {
                ctx.strokeStyle = '#ef4444';
                ctx.lineWidth = 10;
                ctx.beginPath();
                ctx.arc(cx, cy, size, 0, Math.PI * 2);
                ctx.stroke();
            }
        });
    }

    function checkTTTWin(b) {
        const lines = [[0, 1, 2], [3, 4, 5], [6, 7, 8], [0, 3, 6], [1, 4, 7], [2, 5, 8], [0, 4, 8], [2, 4, 6]];
        for (let l of lines) { if (b[l[0]] && b[l[0]] === b[l[1]] && b[l[0]] === b[l[2]]) return b[l[0]]; }
        return null;
    }

    window.quitGame = function () {
        if (wtState.game.active && wtState.ws && wtState.ws.readyState === WebSocket.OPEN) {
            wtState.ws.send(JSON.stringify({
                type: 'WATCHTOGETHER_GAME_QUIT',
                group_id: wtState.groupId,
                session_id: wtState.sessionId,
                sender_id: typeof myUserId !== 'undefined' ? myUserId : 0,
                receivers: (wtState.game.type === 'three-way-tug' ? wtState.game.playerIds : [wtState.game.opponentId]).filter(id => id !== String(typeof myUserId !== 'undefined' ? myUserId : 0))
            }));
        }

        wtState.game.active = false;
        if (tugGameLoop) cancelAnimationFrame(tugGameLoop);
        document.getElementById('wtGameOverlay').style.display = 'none';
        document.getElementById('wtTugOverlay').style.display = 'none';
        showSyncToast('Game ended.');
    }

    function handleGameQuit(data) {
        if (!wtState.game.active) return;
        wtState.game.active = false;
        if (tugGameLoop) cancelAnimationFrame(tugGameLoop);

        const status = document.getElementById(wtState.game.type === 'three-way-tug' ? 'wtTugStatus' : 'wtGameStatus');
        if (status) status.textContent = 'Opponent left the game.';
        setTimeout(() => {
            document.getElementById('wtGameOverlay').style.display = 'none';
            document.getElementById('wtTugOverlay').style.display = 'none';
        }, 3000);
    }


    // ==========================================
    // PRIORITY & POWER MANAGEMENT (LAG FIX)
    // ==========================================

    let wakeLock = null;

    async function requestWakeLock() {
        if (!('wakeLock' in navigator)) return;
        try {
            wakeLock = await navigator.wakeLock.request('screen');
            console.log('[WATCHTOGETHER] Wake Lock active');
            wakeLock.addEventListener('release', () => {
                console.log('[WATCHTOGETHER] Wake Lock released');
            });
        } catch (err) {
            console.warn('[WATCHTOGETHER] Wake Lock failed:', err);
        }
    }

    function releaseWakeLock() {
        if (wakeLock) {
            wakeLock.release().then(() => wakeLock = null);
        }
    }

    function setupMediaSession() {
        if (!('mediaSession' in navigator)) return;

        navigator.mediaSession.setActionHandler('play', () => togglePlayPause());
        navigator.mediaSession.setActionHandler('pause', () => togglePlayPause());
        navigator.mediaSession.setActionHandler('seekbackward', () => seek(-10));
        navigator.mediaSession.setActionHandler('seekforward', () => seek(10));
        navigator.mediaSession.setActionHandler('previoustrack', () => seek(-30));
        navigator.mediaSession.setActionHandler('nexttrack', () => seek(30));
    }

    function updateMediaSessionMetadata() {
        if (!('mediaSession' in navigator) || !wtState.currentVideo) return;

        navigator.mediaSession.metadata = new MediaMetadata({
            title: wtState.currentVideo.title || 'Loop Video',
            artist: 'Loop Together',
            album: 'Watch Party',
            artwork: [
                { src: wtState.currentVideo.thumbnail_url || '', sizes: '512x512', type: 'image/jpeg' }
            ]
        });

        navigator.mediaSession.playbackState = wtState.videoPlayer?.paused ? 'paused' : 'playing';
    }

    // Handle visibility changes to re-request priority
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible' && wtState.isActive) {
            requestWakeLock();
        }
    });

})();
