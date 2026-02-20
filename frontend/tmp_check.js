
        const myUserId = null;
        const myUsername = null;
        const myProfilePic = null;
        let activePeer = null;
        let activeTypers = new Map(); // Store who is typing: sender_id -> sender_name
        let myGroupIds = []; // Track group IDs we belong to for WS re-joins

        function resolveProfilePic(path) {
            if (!path || path === 'null') return 'https://www.gravatar.com/avatar/00?d=mp';
            if (path.startsWith('http') || path.startsWith('data:')) return path;
            let clean = path.replace(/^\.?\//, ''); 
            if (!clean.startsWith('../')) clean = '../' + clean;
            return clean;
        }
        let ws;

        // Initialize WebSocket
        function connectWS() {
            // Using current hostname so it works on LAN (e.g. 192.168.x.x) or localhost
            const wsUrl = `ws://${window.location.hostname}:8080`;
            console.log(`📡 [CHAT ENGINE] Connecting to: ${wsUrl}`);
            const connId = Math.random().toString(36).substring(7).toUpperCase();
            ws = new WebSocket(wsUrl);
            ws.onopen = () => {
                console.log(`%c[WS] Connected | User: ${myUsername} (${myUserId}) | ConnID: ${connId}`, 'color: #22c55e; font-weight: bold;');
                
                const statusEl = document.getElementById('connectionStatus');
                const statusText = document.getElementById('connText');
                if (statusEl) {
                    statusEl.className = 'connection-pill online';
                    if(statusText) statusText.textContent = 'Live';
                }
                
                ws.send(JSON.stringify({ 
                    type: 'JOIN_STREAM', 
                    streamId: `user_${myUserId}`,
                    userId: myUserId,
                    connId: connId
                }));

                // HEARTBEAT to keep connection alive
                if (window.wsHeartbeat) clearInterval(window.wsHeartbeat);
                window.wsHeartbeat = setInterval(() => {
                    if (ws && ws.readyState === WebSocket.OPEN) {
                        ws.send(JSON.stringify({ type: 'HEARTBEAT' }));
                    }
                }, 25000); // Send PING every 25s

                joinAllRooms();
            };

            ws.onmessage = (e) => {
                try {
                    const data = JSON.parse(e.data);
                    console.info(`%c📡 [TRACE] Received Signal: ${data.type}`, 'background: #333; color: #fff; padding: 2px 5px;', data);
                
                    if (data.type === 'NEW_PRIVATE_MESSAGE') {
                        console.log(`[WS] Handling NEW_$ PM from ${data.sender_id} to ${data.receiver_id}. Group: ${data.isGroup}`);

                        // IGNORE messages from ourselves - we already handle them locally
                        if (String(data.sender_id) === String(myUserId)) {
                            console.log('%c[WS] Ignoring message from self to prevent duplication.', 'color: #ef4444; font-weight: bold;');
                            return;
                        }
                        
                        const isGroup = data.isGroup || false;
                        const matchId = isGroup ? String(data.group_id || data.receiver_id) : String(data.sender_id);
                        
                        // Reload conversations to get the latest state and badges
                        loadConversations();

                        // 1. Visual Toast & Badge
                        const toastTitle = isGroup ? `Group: ${data.username}` : `Message from ${data.username || 'Someone'}`;
                        // If we are NOT in this chat, show toast and badge
                        if (!activePeer || String(activePeer.id) !== String(matchId)) {
                            showMsgToast(toastTitle, data.text);
                            
                            // Add red badge to card (this will be handled by loadConversations now, but we can force a visual update if needed)
                            // For now, rely on loadConversations to fetch unread counts and render badges.
                        }
                        
                        // 3. Append to Chat Viewport if active
                        // Match condition: If activePeer.id matches the context ID (group ID or sender ID)
                        if (activePeer && (String(matchId) === String(activePeer.id))) {
                            console.log('%c[WS] Matching current chat! Appending...', 'color: #22c55e;');
                            appendMessage({
                                id: data.id, // Ensure ID is passed if available
                                sender_id: data.sender_id,
                                sender_name: data.sender_name || data.username,
                                sender_pic: data.sender_pic || data.profile_picture, // For group avatars
                                text: data.text || data.message,
                                timestamp: data.timestamp
                            });
                            
                            // IMMEDIATELY MARK READ
                            // Send backend request so it persists as read
                            // We can use get_private_messages with limit 1 or a specific endpoint, but usually reading history marks all as read.
                            // Let's create a specific mark_read fetch or just rely on 'PEER_STATUS' if backend handled it? 
                            // Actually, backend needs to know messages are read. 'get_private_messages' does that.
                            // We can re-trigger a background fetch to 'get_private_messages' implicitly marks them read.
                            fetch(`../backend/get_private_messages.php?other_id=${activePeer.id}&mark_read_only=1`);

                            // Send WS Signal so sender sees blue checks instantly
                             if (ws && ws.readyState === WebSocket.OPEN) {
                                ws.send(JSON.stringify({
                                    type: 'PEER_STATUS',
                                    user_id: myUserId, // Who read it (me)
                                    receiver_id: data.sender_id, // Who sent it (them)
                                    status: 'READ'
                                }));
                            }
                        } 
                        
                        // ALWAYS Mark as Delivered (since we received it via WS)
                        // Send to backend to persist
                        fetch('../backend/mark_delivered.php', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({ message_id: data.message_id })
                        });
                        
                        // Send WS signal back to sender
                        if (ws && ws.readyState === WebSocket.OPEN) {
                             ws.send(JSON.stringify({
                                 type: 'MESSAGE_DELIVERED',
                                 sender_id: myUserId, // I am receiving it, so I am the sender of the receipt
                                 receiver_id: data.sender_id, // The original sender is the receiver of the receipt
                                 message_id: data.message_id
                             }));
                        }
                    } else if (data.type === 'GROUP_CREATED') {
                        console.log('[WS] Added to group!', data);
                        loadConversations();
                        showMsgToast(`Added to Group`, `You were added to the group "${data.group_name || 'New Group'}"`);
                    } else if (data.type === 'MEMBERS_ADDED') {
                         console.log('[WS] Members added to group!', data);
                         loadConversations(); // Sync sidebar (member counts)
                         
                         // If we are looking at this group, append the system message
                         if (activePeer && String(activePeer.id) == String(data.group_id)) {
                             const names = data.member_names || [];
                             const text = `${data.sender_name || 'Someone'} added ${names.join(', ')} to the group`;
                             
                             appendMessage({
                                 id: 'sys-' + Date.now(),
                                 sender_id: 0,
                                 message: text,
                                 created_at: new Date().toISOString()
                             });
                             
                             // Update member count in header immediately if possible
                             const statusText = document.getElementById('peerStatusText');
                             if(statusText) {
                                 // We don't know exact count without fetch, but we can guess or just wait for sidebar sync?
                                 // Sidebar sync (loadConversations) won't update the header of OPEN chat activePeer object.
                                 // Let's force a background refresh of the header metadata
                                 // Or just rely on visual message for now.
                             }
                         }
                    } else if (data.type === 'GROUP_LEAVE') {
                        console.log('[WS] Group Leave Signal:', data);
                        loadConversations();
                        
                        if (activePeer && String(activePeer.id) == String(data.group_id)) {
                             const person = data.username || 'Someone';
                             appendMessage({
                                 id: 'sys-' + Date.now(),
                                 sender_id: 0,
                                 message: `${person} left the group`,
                                 created_at: new Date().toISOString()
                             });
                        }

                    } else if (data.type === 'REQUEST_APPROVED') {
                    loadConversations();
                    if (activePeer && (data.sender_id == activePeer.id || data.receiver_id == activePeer.id)) {
                        openChat(activePeer);
                    }
                } else if (data.type === 'MESSAGE_DELETED') {
                    const row = document.getElementById(`msg-${data.message_id}`);
                    if (row) {
                        row.style.opacity = '0';
                        row.style.transform = 'scale(0.9)';
                        row.style.transition = 'all 0.3s ease';
                        setTimeout(() => row.remove(), 300);
                    }
                } else if (data.type === 'MESSAGE_REACTED') {
                    updateMessageReactions(data.message_id, data.reactions);
                } else if (data.type === 'WATCHTOGETHER_PARTY_STARTED' || data.type === 'WATCHTOGETHER_PARTY_ENDED') {
                     // Forward to specific WT handler
                     if (typeof handleWatchTogetherPartyMessage === 'function') {
                         handleWatchTogetherPartyMessage(data);
                     }
                } else if (data.type === 'MESSAGE_EDITED') {
                    const row = document.getElementById(`msg-${data.message_id}`);
                    if (row) {
                        const bubble = row.querySelector('.msg-bubble');
                        if (bubble) {
                            // Find the reaction container if it exists
                            const reactionContainer = bubble.querySelector('.reaction-container');
                            let reactionHtml = '';
                            if (reactionContainer) {
                                reactionHtml = reactionContainer.outerHTML;
                            } else {
                                const emptyDiv = document.createElement('div');
                                emptyDiv.className = 'reaction-container';
                                reactionHtml = emptyDiv.outerHTML;
                            }
                            bubble.innerHTML = escapeHtml(data.new_text) + ' ' + reactionHtml;
                        }
                    }
                } else if (data.type === 'MESSAGE_DELIVERED') {
                    // Update specific message or all unread sent messages to delivered status
                    const updateDeliveredIcon = (retryCount = 0) => {
                        const row = document.getElementById(`msg-${data.message_id}`);
                        if (row && row.classList.contains('sent')) {
                            const info = row.querySelector('.msg-info');
                            // Only update if not already read (blue)
                            if (info && !info.innerHTML.includes('var(--azure-blue)')) {
                                // Find existing icon and replace or append
                                const existingIcon = info.querySelector('i');
                                if (existingIcon) {
                                    existingIcon.className = 'fa-solid fa-check-double';
                                    existingIcon.style.color = 'rgba(255,255,255,0.3)';
                                }
                            }
                        } else if (retryCount < 5) {
                            // Retry in case of race condition (API response vs WS event)
                            setTimeout(() => updateDeliveredIcon(retryCount + 1), 500);
                        }
                    };
                    updateDeliveredIcon();
                } else if (data.type === 'PEER_STATUS') {
                    if (data.status === 'READ' && activePeer && data.user_id == activePeer.id) {
                        // Peer read our messages - turn all our checks blue
                        document.querySelectorAll('.msg-row.sent .msg-info i').forEach(icon => {
                            icon.className = 'fa-solid fa-check-double';
                            icon.style.color = 'var(--azure-blue)';
                        });
                    }
                } else if (data.type === 'TYPING') {
                    // Logic: matched if (private and sender is peer) OR (group and group matches)
                    const isMatch = activePeer && (
                        (!activePeer.isGroup && data.sender_id == activePeer.id) ||
                        (activePeer.isGroup && data.group_id == activePeer.id)
                    );

                    if (isMatch) {
                        const statusText = document.getElementById('peerStatusText');
                        const statusDot  = document.getElementById('peerStatusDot');
                        
                        if (data.isTyping) {
                            activeTypers.set(data.sender_id, data.sender_name || 'Someone');
                        } else {
                            activeTypers.delete(data.sender_id);
                        }

                        if (statusText) {
                            if (activeTypers.size > 0) {
                                let typingMsg = '';
                                const names = Array.from(activeTypers.values());
                                
                                if (names.length === 1) {
                                    typingMsg = `${names[0]} is typing`;
                                } else if (names.length === 2) {
                                    typingMsg = `${names[0]} and ${names[1]} are typing`;
                                } else {
                                    typingMsg = `${names.length} people are typing`;
                                }

                                statusText.innerHTML = `<span class="typing-dots">${typingMsg}<span>.</span><span>.</span><span>.</span></span>`;
                                statusText.style.color = 'var(--azure-blue)';
                                if(statusDot) {
                                    statusDot.style.background = 'var(--azure-blue)';
                                    statusDot.style.display = 'block';
                                }
                            } else {
                                // Revert to last active status
                                let statusStr = '';
                                if (activePeer.isGroup) {
                                    statusStr = `${activePeer.member_count || '...'} participants`;
                                } else {
                                    statusStr = formatActiveStatus(activePeer.last_active_at);
                                }

                                if (statusStr) {
                                    statusText.textContent = statusStr;
                                    statusText.style.color = (statusStr === 'Active Now' || activePeer.isGroup) ? '#22c55e' : 'var(--text-dim)';
                                    if(statusDot) {
                                        statusDot.style.background = (statusStr === 'Active Now' || activePeer.isGroup) ? '#22c55e' : 'var(--text-dim)';
                                        statusDot.style.display = activePeer.isGroup ? 'none' : 'block';
                                    }
                                } else {
                                    statusText.textContent = '';
                                    if(statusDot) statusDot.style.display = 'none';
                                }
                            }
                        }
                    }
                } else if (data.type === 'INCOMING_CALL') {
                    console.info('[CALL] Received INCOMING_CALL signal');
                    showIncomingCall(data.caller_id, data.caller_name, data.caller_pic, data.call_type);
                } else if (data.type === 'GROUP_CALL_START') {
                    console.info(`[CALL] Received GROUP_CALL_START signal for group: ${data.group_id}`);
                    // Only show if we are NOT already in a call
                    if (!activeCallOverlay) {
                        showIncomingCall(data.caller_id, data.caller_name, data.caller_pic, data.call_type, data.group_id, data.group_name);
                    } else if (activeGroupCallId && String(activeGroupCallId) === String(data.group_id)) {
                        updateParticipantCount(data.participant_count);
                    } else {
                        console.warn('[CALL] Ignoring group call signal: Already in an active call.');
                    }
                } else if (data.type === 'GROUP_CALL_JOIN') {
                    console.info(`[CALL] User ${data.user_id} joined group call ${data.group_id}`);
                    if (activeCallOverlay && activeGroupCallId && String(activeGroupCallId) === String(data.group_id) && data.user_id != myUserId) {
                        updateParticipantCount(data.participant_count);
                        initiateGroupPeerConnection(data.user_id, data.user_name, data.user_pic);
                    }
                } else if (data.type === 'GROUP_CALL_LEAVE') {
                    console.info(`[CALL] User ${data.user_id} left group call`);
                    if (activeCallOverlay && activeGroupCallId && groupConnections[data.user_id]) {
                        updateParticipantCount(data.participant_count);
                        removeGroupParticipant(data.user_id);
                    }
                } else if (data.type === 'CALL_ACCEPTED') {
                    // Start WebRTC Negotiation as Caller
                    const statusText = document.getElementById('callStatusText');
                    if (statusText) statusText.textContent = 'Connecting...';
                    
                    (async () => {
                        const pc = await createPeerConnection(data.callee_id);
                        
                        // Ensure localStream tracks are added before creating offer
                        if (localStream) {
                            console.log('[RTC] Adding local tracks for initial offer');
                        } else {
                            console.warn('[RTC] Caller localStream not ready, re-initializing...');
                            await initMedia(activePeer && activePeer.call_type ? activePeer.call_type : 'audio');
                        }

                        const offer = await pc.createOffer();
                        await pc.setLocalDescription(offer);
                        ws.send(JSON.stringify({
                            type: 'CALL_SIGNAL',
                            from_id: myUserId,
                            to_id: data.callee_id,
                            signal: { offer: offer }
                        }));
                    })();
                    
                    startCallTimer();
                } else if (data.type === 'CALL_SIGNAL') {
                    console.log(`📡 [WS] Received WebRTC Signal from: ${data.from_id}`, data.signal);
                    handleCallSignal(data);
                } else if (data.type === 'CALL_ERROR') {
                    const statusText = document.getElementById('callStatusText');
                    if (statusText) statusText.textContent = 'User is offline';
                    setTimeout(() => { if (activeCallOverlay) endCall(); }, 3000);
                } else if (data.type === 'CHAT_DELETED') {
                    console.log('[WS] Chat Deleted signal received');
                    loadConversations();
                    // If we are looking at this chat, clear it
                    if (activePeer && (data.sender_id == activePeer.id || data.receiver_id == activePeer.id)) {
                         activePeer = null;
                         document.getElementById('chatWindow').innerHTML = `
                            <div class="empty-view">
                                <i class="fa-solid fa-trash-can"></i>
                                <h3>Conversation Deleted</h3>
                                <p>This chat was deleted by the other user.</p>
                            </div>
                         `;
                    }
                } else if (data.type === 'CALL_ENDED') {
                    // Stop ringtone if still ringing
                    if (incomingCallAudio) { incomingCallAudio.pause(); incomingCallAudio = null; }
                    const incomingOverlay = document.getElementById('incomingCallOverlay');
                    if (incomingOverlay) incomingOverlay.remove();
                    
                    // Remove active call if exists
                    if (activeCallOverlay) {
                        activeCallOverlay.remove();
                        activeCallOverlay = null;
                        if (callTimerInterval) clearInterval(callTimerInterval);
                    }
                } else if (data.type === 'GROUP_CREATED') {
                    console.log(`[WS] New group created: ${data.group_name}`);
                    loadConversations();
                } else if (data.type === 'WATCHTOGETHER_PARTY_STARTED' || data.type === 'WATCHTOGETHER_PARTY_ENDED') {
                    if (typeof handleWatchTogetherPartyMessage === 'function') {
                        handleWatchTogetherPartyMessage(data);
                    }
                }
                } catch (err) {
                    console.error('📡 WS Signal Error:', err, e.data);
                }
            };
            ws.onclose = () => {
                console.warn('Chat Engine Disconnected. Retrying in 3s...');
                
                const statusEl = document.getElementById('connectionStatus');
                const statusText = document.getElementById('connText');
                if (statusEl) {
                    statusEl.className = 'connection-pill offline';
                    if(statusText) statusText.textContent = 'Reconnecting...';
                }

                setTimeout(connectWS, 3000);
            };
            ws.onerror = (err) => {
                console.error('WS Error:', err);
            };
        }

        function joinAllRooms() {
            if (!ws || ws.readyState !== WebSocket.OPEN) return;
            
            console.log('[WS] Joining signaling rooms...');
            
            // Join personal room
            ws.send(JSON.stringify({ 
                type: 'JOIN_STREAM', 
                streamId: `user_${myUserId}`,
                userId: myUserId
            }));

            // Join all group rooms
            if (typeof myGroupIds !== 'undefined' && Array.isArray(myGroupIds)) {
                myGroupIds.forEach(gid => {
                    console.log(`[WS] Joining group room: group_${gid}`);
                    ws.send(JSON.stringify({ type: 'JOIN_STREAM', streamId: `group_${gid}` }));
                });
            }
        }

        let currentChatTab = 'all';

        function switchChatTab(tab) {
            currentChatTab = tab;
            document.querySelectorAll('.chat-tab').forEach(t => {
                t.classList.toggle('active', t.getAttribute('data-tab') === tab);
            });
            renderConversationList();
        }

        let cachedConversations = {
            requests: [],
            groups: [],
            conversations: [],
            pending_sent: []
        };

        function renderConversationList() {
            const list = document.getElementById('convList');
            if (!list) return;
            
            let html = '';
            const data = cachedConversations;

            // Render logic with filtering
            const showPrivate = (currentChatTab === 'all' || currentChatTab === 'private');
            const showGlobal = (currentChatTab === 'all' || currentChatTab === 'global');

            // 1. MESSAGE REQUESTS
            if (showPrivate && data.requests && data.requests.length > 0) {
                html += `
                    <div class="section-header" style="padding: 15px 20px; color: var(--azure-blue); font-weight: 700; font-size: 13px; text-transform: uppercase; letter-spacing: 1px; display: flex; align-items: center; gap: 8px;">
                        <i class="fa-solid fa-inbox"></i>
                        Chat Requests (${data.requests.length})
                    </div>
                `;
                data.requests.forEach(r => {
                    const time = formatShortTime(r.last_time);
                    html += `
                        <div class="conv-card request-card" onclick='openChat(${JSON.stringify({...r, isRequest: true}).replace(/'/g, "&#39;")})'>
                            <div class="avatar-wrapper">
                                <img src="${resolveProfilePic(r.profile_picture)}" class="avatar-img">
                            </div>
                            <div class="conv-details">
                                <div class="conv-top">
                                    <span class="conv-name">${escapeHtml(r.username)}</span>
                                    <span class="conv-time">Now</span>
                                </div>
                                <div class="conv-preview" style="color: var(--azure-blue); font-weight: 700;">Sent a message request</div>
                            </div>
                        </div>
                    `;
                });
            }

            // 2. GROUPS
            if (showGlobal && data.groups && data.groups.length > 0) {
                html += `
                    <div class="section-header" style="padding: 15px 20px; color: var(--text-dim); font-weight: 700; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; margin-top: 10px;">
                        Groups
                    </div>
                `;
                data.groups.forEach(g => {
                    const time = g.last_time ? formatShortTime(g.last_time) : '';
                    const isActive = activePeer && activePeer.id == g.id && activePeer.isGroup;
                    html += `
                        <div class="conv-card ${isActive ? 'active' : ''}" data-id="${g.id}" onclick='openChat(${JSON.stringify({...g, isGroup: true, username: g.name, profile_picture: g.picture || null, member_count: g.member_count}).replace(/'/g, "&#39;")})'>
                            <div class="avatar-wrapper">
                                <div class="avatar-img" style="background: var(--tertiary-color); display: flex; align-items: center; justify-content: center; font-size: 20px; color: var(--text-dim);">
                                    <i class="fa-solid fa-users"></i>
                                </div>
                            </div>
                            <div class="conv-details">
                                <div class="conv-top">
                                    <span class="conv-name">${escapeHtml(g.name)}</span>
                                    <span class="conv-time">${time}</span>
                                </div>
                                <div class="conv-preview" style="color: var(--azure-blue); font-weight: 600;">${g.member_count} participants</div>
                                <div class="conv-preview">${escapeHtml(g.last_message || 'No messages yet')}</div>
                            </div>
                        </div>
                    `;
                });
            }

            // 3. DIRECT MESSAGES
            if (showPrivate && data.conversations && data.conversations.length > 0) {
                html += `
                    <div class="section-header" style="padding: 15px 20px; color: var(--text-dim); font-weight: 700; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; margin-top: 10px;">
                        Direct Messages
                    </div>
                `;
                data.conversations.forEach(c => {
                    const time = formatShortTime(c.last_time);
                    const isActive = activePeer && activePeer.id == c.id && !activePeer.isGroup;
                    const unreadCount = c.unread_count || 0;
                    
                    html += `
                        <div class="conv-card ${isActive ? 'active' : ''}" data-id="${c.id}" onclick='openChat(${JSON.stringify(c).replace(/'/g, "&#39;")})'>
                            <div class="avatar-wrapper">
                                <img src="${resolveProfilePic(c.profile_picture)}" class="avatar-img">
                                ${unreadCount > 0 ? `<div class="conv-badge" data-count="${unreadCount}">${unreadCount}</div>` : ''}
                            </div>
                            <div class="conv-details">
                                <div class="conv-top">
                                    <span class="conv-name">${escapeHtml(c.username)}</span>
                                    <span class="conv-time">${time}</span>
                                </div>
                                <div class="conv-preview">${escapeHtml(c.last_message || 'No messages yet')}</div>
                            </div>
                        </div>
                    `;
                });
            }

            // 4. PENDING SENT section (your requests waiting for approval)
            if (showPrivate && data.pending_sent && data.pending_sent.length > 0) {
                html += `
                    <div class="section-header" style="padding: 15px 20px; color: var(--text-dim); font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: 1px;">
                        <i class="fa-solid fa-clock"></i> Awaiting Response
                    </div>
                `;
                html += data.pending_sent.map(c => `
                    <div class="conv-card pending-card ${activePeer && activePeer.id == c.id ? 'active' : ''}" data-id="${c.id}" onclick="openChat(${JSON.stringify({...c, isPendingSent: true}).replace(/"/g, '&quot;')})" style="opacity: 0.7;">
                        <div class="avatar-wrapper">
                            <img src="${resolveProfilePic(c.profile_picture)}" class="avatar-img">
                        </div>
                        <div class="conv-details">
                            <div class="conv-top">
                                <span class="conv-name">${escapeHtml(c.username)}</span>
                                <span class="conv-time">${formatShortTime(c.last_time)}</span>
                            </div>
                            <div class="conv-preview" style="font-style: italic;">Request pending...</div>
                        </div>
                    </div>
                `).join('');
            }

            if (!html) {
                html = `<div style="padding: 40px; text-align: center; color: var(--text-dim);">No chats found in this category.</div>`;
            }

            list.innerHTML = html;
        }

        async function loadConversations() {
            try {
                const res = await fetch(`../backend/get_conversations.php?_t=${Date.now()}`);
                const data = await res.json();
                if (data.success) {
                    cachedConversations = data;
                    if (data.groups && data.groups.length > 0) {
                        myGroupIds = data.groups.map(g => g.id); // Store group IDs globally
                        joinAllRooms(); // Trigger join now that we have IDs
                    }
                    renderConversationList();
                } else {
                    const list = document.getElementById('convList');
                    if(list) list.innerHTML = '<div style="padding: 40px; text-align: center; color: #ef4444;">Failed to load chats.</div>';
                }
            } catch (err) {
                console.error('Critical Error in loadConversations:', err);
                const list = document.getElementById('convList');
                if(list) list.innerHTML = '<div style="padding: 40px; text-align: center; color: #ef4444;">Connection Error.</div>';
            }
        }

        async function openChat(peer) {
            activePeer = peer;
            activeTypers.clear();
            
            // Mobile Transition
            if (window.innerWidth <= 768) {
                document.getElementById('sidebarContainer').classList.add('mobile-hide');
                document.getElementById('chatWindow').classList.add('mobile-show');
            }
            
            // Update UI Active State
            document.querySelectorAll('.conv-card').forEach(card => card.classList.remove('active'));
            const activeCard = document.querySelector(`.conv-card[data-id="${peer.id}"]`);
            if (activeCard) activeCard.classList.add('active');

            // Load History FIRST to check status
            let historyUrl = `../backend/get_private_messages.php?_t=${Date.now()}`;
            if (peer.isGroup) {
                historyUrl += `&group_id=${peer.id}`;
            } else {
                historyUrl += `&other_id=${peer.id}`;
            }
            
            const res = await fetch(historyUrl);
            const data = await res.json();
            
            // Update peer active status with fresh data
            if (data.last_active_at) {
                activePeer.last_active_at = data.last_active_at;
            }

            // History results
            const messages = (data.success && Array.isArray(data.messages)) ? data.messages : [];

            // Check if thread is approved (at least one approved message exists)
            const hasApprovedMessage = messages.some(m => m.is_approved == 1);
            
            // Check if I have a pending request TO this peer (unapproved messages FROM me TO them)
            const myPendingToPeer = messages.some(m => m.sender_id == myUserId && m.is_approved != 1);
            
            // Check if peer has a pending request TO me (unapproved messages FROM them TO me)
            const hasPendingFromPeer = messages.some(m => m.sender_id == peer.id && m.is_approved != 1);
            
            // Can I send messages?
            // ONLY if it's already approved OR there are zero messages and no pending outgoing request
            // Groups ALWAYS allow sending messages
            const canSendMessages = peer.isGroup || hasApprovedMessage || (messages.length === 0 && !myPendingToPeer);
            
            chatWindow.innerHTML = `
                <style>
                    .header-icon-btn {
                        background: transparent;
                        border: none;
                        color: rgba(255,255,255,0.7);
                        width: 38px;
                        height: 38px;
                        border-radius: 50%;
                        cursor: pointer;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        font-size: 16px;
                        transition: all 0.2s;
                    }
                    .header-icon-btn:hover { background: rgba(255,255,255,0.1); color: white; }
                    
                    .header-dropdown {
                        display: none;
                        position: absolute;
                        top: 100%;
                        right: 0;
                        margin-top: 8px;
                        background: rgba(20,20,25,0.98);
                        border: 1px solid rgba(255,255,255,0.1);
                        border-radius: 12px;
                        padding: 6px;
                        min-width: 170px;
                        z-index: 100;
                        backdrop-filter: blur(15px);
                        box-shadow: 0 8px 30px rgba(0,0,0,0.6);
                    }
                    .dropdown-item {
                        padding: 10px 14px;
                        display: flex;
                        align-items: center;
                        gap: 10px;
                        color: rgba(255,255,255,0.9);
                        font-size: 13px;
                        cursor: pointer;
                        border-radius: 8px;
                        transition: background 0.2s;
                    }
                    .dropdown-item:hover { background: rgba(255,255,255,0.08); }
                    .dropdown-item.danger { color: #ef4444; }
                    .dropdown-item.danger:hover { background: rgba(239, 68, 68, 0.15); }
                </style>
                <canvas id="chatStars" class="chat-stars"></canvas>
                <header class="chat-view-header">
                    <div class="back-btn-mobile" style="display: none;" onclick="closeChatMobile()">
                        <i class="fa-solid fa-chevron-left"></i>
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px; cursor: pointer;" onclick="window.location.href='profile.php?id=${peer.id}'">
                        <img src="${resolveProfilePic(peer.profile_picture)}" style="width: 40px; height: 40px; border-radius: 50%; border: 2px solid var(--azure-blue); padding: 2px;">
                        <div>
                            <div style="font-weight: 800; font-size: 15px;">${escapeHtml(peer.username)}</div>
                            <div style="font-size: 11px; color: #22c55e; display: flex; align-items: center; gap: 4px;">
                                <div id="peerStatusDot" style="width: 6px; height: 6px; background: #22c55e; border-radius: 50%; display: none;"></div>
                                <span id="peerStatusText"></span>
                            </div>
                        </div>
                    </div>
                    <div class="header-actions-mobile">
                        <button class="header-icon-btn" onclick="startCall('audio')" title="Voice Call">
                            <i class="fa-solid fa-phone"></i>
                        </button>
                        <button class="header-icon-btn" onclick="startCall('video')" title="Video Call">
                            <i class="fa-solid fa-video"></i>
                        </button>
                        ${peer.isGroup ? `
                        <div id="wtButtonContainer">
                            <button class="wt-start-btn" id="wtStartBtn" onclick="handleWatchTogetherClick(${peer.id})" title="Watch Together">
                                <i class="fa-solid fa-tv"></i>
                                <span id="wtBtnText">Watch Together</span>
                            </button>
                        </div>
                        ` : ''}
                        <div style="position: relative;">
                            <button class="header-icon-btn" onclick="toggleHeaderMenu(event)" title="More Options">
                                <i class="fa-solid fa-ellipsis-vertical"></i>
                            </button>
                            <div id="headerMenuDropdown" class="header-dropdown">
                                ${activePeer.isGroup ? `
                                <div class="dropdown-item" onclick="openAddParticipantsModal()">
                                    <i class="fa-solid fa-user-plus"></i> Add participants
                                </div>
                                ` : ''}
                                <div class="dropdown-item" onclick="blockContact()">
                                    <i class="fa-solid fa-ban"></i> Block contact
                                </div>
                                <div class="dropdown-item danger" onclick="deleteChat(${peer.id})">
                                    <i class="fa-solid fa-trash-can"></i> Delete chat
                                </div>
                                ${activePeer.isGroup ? `
                                <div class="dropdown-item danger" onclick="leaveGroup(${peer.id})">
                                    <i class="fa-solid fa-right-from-bracket"></i> Leave group
                                </div>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                </header>

                ${hasPendingFromPeer && !hasApprovedMessage && !peer.isGroup ? `
                    <div class="request-banner" id="reqBanner">
                        <div style="font-weight: 700; font-size: 18px;">Message Request</div>
                        <div style="color: var(--text-dim); font-size: 14px;">${escapeHtml(peer.username)} wants to chat with you.</div>
                        <div class="request-actions">
                            <button class="btn-approve" onclick="handleRequest('approve')">Accept</button>
                            <button class="btn-decline" onclick="handleRequest('decline')">Decline</button>
                        </div>
                    </div>
                ` : ''}

                <div class="messages-viewport" id="viewport">
                    ${messages.map(m => createMsgBlock(m)).join('')}
                </div>

                ${canSendMessages ? `
                    <div class="chat-input-container">
                        <form id="sendForm">
                            <div class="input-wrapper">
                                <input type="text" id="msgInput" placeholder="Write something nice..." autocomplete="off">
                                <button type="submit" class="send-pill" id="sendBtn">
                                    <i class="fa-solid fa-arrow-up"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                ` : `
                    <div class="chat-input-container" style="background: rgba(2, 2, 5, 0.9);">
                        <div style="display: flex; align-items: center; justify-content: center; gap: 10px; color: var(--text-dim); font-weight: 500; font-size: 14px; padding: 10px; background: rgba(255,255,255,0.03); border-radius: 12px; border: 1px dashed var(--glass-border);">
                            <i class="fa-solid fa-clock"></i>
                            Waiting for ${escapeHtml(peer.username)} to approve your request...
                        </div>
                    </div>
                `}
            `;

            initStars();
            
            // Set Active Status UI
            let statusStr = '';
            if (peer.isGroup) {
                statusStr = `${peer.member_count || '...'} participants`;
            } else {
                statusStr = formatActiveStatus(activePeer.last_active_at);
            }
            
            const statusText = document.getElementById('peerStatusText');
            const statusDot = document.getElementById('peerStatusDot');
            if(statusStr && statusText) {
                statusText.textContent = statusStr;
                statusText.style.color = (statusStr === 'Active Now' || peer.isGroup) ? '#22c55e' : 'var(--text-dim)';
                if(statusDot) {
                    statusDot.style.background = (statusStr === 'Active Now' || peer.isGroup) ? '#22c55e' : 'var(--text-dim)';
                    statusDot.style.display = peer.isGroup ? 'none' : 'block';
                }
            }
            
            // WatchTogether Button Sync
            if (peer.isGroup) {
                updateWatchTogetherButton(peer.id);
            }
            
            const viewport = document.getElementById('viewport');
            if(viewport) viewport.scrollTop = viewport.scrollHeight;

            if (document.getElementById('sendForm')) {
                document.getElementById('sendForm').onsubmit = sendMessage;
                
                // Typing Indicator (Throttled)
                const msgInput = document.getElementById('msgInput');
                let lastTypingTime = 0;
                msgInput.oninput = () => {
                    const now = Date.now();
                    if (ws && ws.readyState === WebSocket.OPEN) {
                        // Only send 'Typing' signal if we haven't sent it in the last 1.5s
                        if (now - lastTypingTime > 1500) {
                            ws.send(JSON.stringify({
                                type: 'TYPING',
                                sender_id: myUserId,
                                sender_name: myUsername,
                                receiver_id: peer.id,
                                isGroup: peer.isGroup,
                                isTyping: true
                            }));
                            lastTypingTime = now;
                        }
                        
                        clearTimeout(window.typingTimer);
                        window.typingTimer = setTimeout(() => {
                            ws.send(JSON.stringify({
                                type: 'TYPING',
                                sender_id: myUserId,
                                sender_name: myUsername,
                                receiver_id: peer.id,
                                isGroup: peer.isGroup,
                                isTyping: false
                            }));
                            lastTypingTime = 0; // Reset so next keypress sends immediately
                        }, 2000);
                    }
                };
            }

            // Mark as READ via WS
            if (ws && ws.readyState === WebSocket.OPEN) {
                ws.send(JSON.stringify({
                    type: 'PEER_STATUS',
                    user_id: myUserId,
                    receiver_id: peer.id,
                    status: 'READ'
                }));
            }
        }

        async function handleRequest(action) {
            if(!activePeer) return;
            try {
                const res = await fetch('../backend/approve_request.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ peer_id: activePeer.id, action: action })
                });
                const result = await res.json();
                if(result.success) {
                    if(action === 'decline') {
                        activePeer = null;
                        document.getElementById('chatWindow').innerHTML = `
                            <div class="empty-view">
                                <i class="fa-solid fa-message"></i>
                                <h3>Request Declined</h3>
                                <p>Conversation has been removed.</p>
                            </div>
                        `;
                    }
                    if(action === 'approve') {
                        // Notify peer via WS
                        if (ws && ws.readyState === WebSocket.OPEN) {
                            ws.send(JSON.stringify({
                                type: 'REQUEST_APPROVED',
                                sender_id: myUserId,
                                receiver_id: activePeer.id
                            }));
                        }
                        openChat(activePeer);
                    }
                }
            } catch(e) { console.error(e); }
        }

        function closeChatMobile() {
            document.getElementById('sidebarContainer').classList.remove('mobile-hide');
            document.getElementById('chatWindow').classList.remove('mobile-show');
            activePeer = null;
        }

        // --- NEW PREMIUM DELETE LOGIC ---
        function deleteChat(peerId) {
            if(!activePeer) return;
            
            const overlay = document.getElementById('deleteModalOverlay');
            const peerNameSpan = document.getElementById('peerNameModal');
            const confirmBtn = document.getElementById('confirmDeleteBtn');
            
            peerNameSpan.textContent = activePeer.username;
            overlay.classList.add('active');
            
            // Reset modal state
            document.getElementById('modalContent').style.display = 'block';
            document.getElementById('modalSuccess').style.display = 'none';
            document.querySelector('.success-checkmark').style.display = 'none';
            
            // Handle confirm click
            confirmBtn.onclick = async () => {
                const deleteEveryone = document.getElementById('deleteEveryone').checked;
                
                try {
                    confirmBtn.disabled = true;
                    confirmBtn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Deleting...';
                    
                    const res = await fetch('../backend/delete_chat.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ 
                            peer_id: peerId,
                            everyone: deleteEveryone
                        })
                    });
                    const result = await res.json();
                    
                    if(result.success) {
                        // Notify peer via WS if delete for everyone
                        if (deleteEveryone && ws && ws.readyState === WebSocket.OPEN) {
                            ws.send(JSON.stringify({
                                type: 'CHAT_DELETED',
                                sender_id: myUserId,
                                receiver_id: peerId
                            }));
                        }

                        // Show Success Animation
                        document.getElementById('modalContent').style.display = 'none';
                        document.getElementById('modalSuccess').style.display = 'block';
                        document.querySelector('.success-checkmark').style.display = 'block';
                        
                        setTimeout(() => {
                            closeDeleteModal();
                            activePeer = null;
                            document.getElementById('chatWindow').innerHTML = `
                                <div class="empty-view">
                                    <i class="fa-solid fa-message"></i>
                                    <h3>Conversation Deleted</h3>
                                    <p>The messages have been permanently removed.</p>
                                </div>
                            `;
                            loadConversations();
                        }, 2000);
                    } else {
                        confirmBtn.disabled = false;
                        confirmBtn.textContent = 'Delete Chat';
                    }
                } catch(e) {
                    console.error('Delete Error:', e);
                    confirmBtn.disabled = false;
                    confirmBtn.textContent = 'Delete Chat';
                }
            };
        }

        function closeDeleteModal() {
            document.getElementById('deleteModalOverlay').classList.remove('active');
            // Reset confirm button
            const confirmBtn = document.getElementById('confirmDeleteBtn');
            if(confirmBtn) {
                confirmBtn.disabled = false;
                confirmBtn.textContent = 'Delete Chat';
            }
        }

        function initStars() {
            const canvas = document.getElementById('chatStars');
            if (!canvas) return;
            const ctx = canvas.getContext('2d');
            canvas.width = canvas.offsetWidth;
            canvas.height = canvas.offsetHeight;

            const stars = [];
            for (let i = 0; i < 50; i++) {
                stars.push({
                    x: Math.random() * canvas.width,
                    y: Math.random() * canvas.height,
                    size: Math.random() * 2,
                    opacity: Math.random(),
                    speed: 0.01 + Math.random() * 0.05
                });
            }

            function animate() {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                stars.forEach(s => {
                    s.opacity += s.speed;
                    if (s.opacity > 1 || s.opacity < 0) s.speed *= -1;
                    ctx.beginPath();
                    ctx.fillStyle = `rgba(0, 113, 227, ${s.opacity})`;
                    ctx.arc(s.x, s.y, s.size, 0, Math.PI * 2);
                    ctx.fill();
                });
                requestAnimationFrame(animate);
            }
            animate();
        }

        function createMsgBlock(m) {
            if (m.is_deleted == 1) return '';
            
            // SYSTEM MESSAGE HANDLING
            if (String(m.sender_id) === '0') {
                return `
                    <div class="system-message" id="msg-${m.id}">
                        <span>${escapeHtml(m.message)}</span>
                    </div>
                `;
            }

            const isOwn = String(m.sender_id) === String(myUserId);
            const time = m.created_at ? formatShortTime(m.created_at) : 'Now';
            const isRead = m.is_read == 1;
            const reactions = typeof m.reactions === 'string' ? JSON.parse(m.reactions || '{}') : (m.reactions || {});
            
            let reactionHtml = '<div class' + '="reaction-container">';
            for(const [emoji, users] of Object.entries(reactions)) {
                if (users.length > 0) {
                    const hasMyReact = users.includes(String(myUserId)) || users.includes(Number(myUserId));
                    reactionHtml += `
                        <div class="reaction-chip ${hasMyReact ? 'active' : ''}" onclick="reactToMessage('${m.id}', '${emoji}')">
                            <span>${emoji}</span>
                            <span class="reaction-count">${users.length}</span>
                        </div>
                    `;
                }
            }
            reactionHtml += '</div>';

            // STATUS ICON LOGIC - must be BEFORE the return statement
            let statusIcon = '';
            if (isOwn) {
                if (m.isOptimistic) {
                    statusIcon = '<i class="fa-solid fa-clock" style="color: rgba(255,255,255,0.3); font-size: 11px;"></i>';
                } else if (isRead) {
                    statusIcon = '<i class="fa-solid fa-check-double" style="color: var(--azure-blue); font-size: 11px;"></i>';
                } else if (m.is_delivered) {
                    statusIcon = '<i class="fa-solid fa-check-double" style="color: rgba(255,255,255,0.3); font-size: 11px;"></i>';
                } else {
                    statusIcon = '<i class="fa-solid fa-check" style="color: rgba(255,255,255,0.3); font-size: 11px;"></i>';
                }
            }
            
            // GROUP AVATAR LOGIC
            let avatarHtml = '';
            let groupClass = '';
            if (activePeer && activePeer.isGroup && !isOwn) {
                groupClass = 'group-msg';
                const pic = m.sender_pic ? resolveProfilePic(m.sender_pic) : 'https://www.gravatar.com/avatar/00?d=mp';
                avatarHtml = `<img src="${pic}" class="msg-sender-avatar" title="${escapeHtml(m.sender_name || 'User')}">`;
            }

            return `
                <div class="msg-row ${isOwn ? 'sent' : 'received'} ${groupClass}" data-msg-id="${m.id}" id="msg-${m.id}">
                    ${avatarHtml}
                    <div class="msg-bubble" ondblclick="reactToMessage('${m.id}', '❤️')">
                        ${escapeHtml(m.message)}
                        ${reactionHtml}
                        <svg class="msg-tail" viewBox="0 0 28 23" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M2.50308 22.6242C0.391605 22.5736 0 21.2128 0 21.2128C0 21.2128 0.613831 21.8576 1.13275 22.0118C2.06803 22.2897 2.44507 22.0118 3.49995 21.2128C4.55482 20.4138 6.31004 15.4996 6.00004 14.2132C8.73292 13.2285 12.1798 15.8136 11.9999 18.7128C11.3051 21.1679 6.90224 22.7297 2.50308 22.6242Z" fill="currentColor"/>
                        </svg>
                    </div>
                    
                    <div class="msg-actions-trigger" onclick="toggleMsgMenu(event, '${m.id}', ${isOwn})">
                        <i class="fa-solid fa-ellipsis-vertical"></i>
                    </div>

                    <div class="msg-info">
                        <span>${time}</span>
                        ${statusIcon}
                    </div>
                </div>
            `;
        }

        // Cache for emojis to avoid re-fetching
        let emojiDataCache = null;

        


        function toggleMsgMenu(e, msgId, isOwn) {
            e.stopPropagation();
            const existing = document.getElementById('activeMsgMenu');
            if (existing) existing.remove();

            const menu = document.createElement('div');
            menu.id = 'activeMsgMenu';
            menu.className = 'msg-context-menu active';
            
            const rect = e.currentTarget.getBoundingClientRect();
            let top = rect.top + window.scrollY;
            let left = rect.left + (isOwn ? -300 : 40);

            // Vertical positioning check: if clicked low on screen, show menu ABOVE
            const menuHeight = 350; // Approximated max height for menu + emoji panel
            const spaceBelow = window.innerHeight - rect.bottom;
            
            if (spaceBelow < menuHeight) {
                // Show above
                top = (rect.top + window.scrollY) - menuHeight + 20; // 20px overlap/offset
                menu.classList.add('pop-upwards'); // Optional class for transform origin
            }

            if (left < 10) left = 10;
            if (left + 320 > window.innerWidth) left = window.innerWidth - 330;

            menu.style.top = top + 'px';
            menu.style.left = left + 'px';

            // Initial Loading State
            menu.innerHTML = `
                <div class="emoji-section" id="emoji-loading-area" style="min-height: 150px; display: flex; align-items: center; justify-content: center;">
                    <i class="fa-solid fa-spinner fa-spin" style="color: var(--text-dim);"></i>
                </div>
                <div class="menu-actions">
                    <div class="msg-menu-item" onclick="copyMessageText('${msgId}')">
                        <i class="fa-solid fa-copy"></i>
                        Copy Text
                    </div>
                    ${isOwn ? `
                        <div class="msg-menu-item" onclick="startEditMessage('${msgId}')">
                            <i class="fa-solid fa-pen"></i>
                            Edit Message
                        </div>
                        <div class="msg-menu-item danger" onclick="deleteMessage('${msgId}')">
                            <i class="fa-solid fa-trash-can"></i>
                            Delete Message
                        </div>
                    ` : ''}
                </div>
            `;

            document.body.appendChild(menu);

            // Fetch and Render
            loadEmojiData().then(categories => {
                const container = menu.querySelector('#emoji-loading-area');
                if (!container) return; // Menu closed

                // Create inner structure
                container.style.display = 'flex';
                container.style.flexDirection = 'column';
                container.style.justifyContent = 'flex-start';
                container.style.alignItems = 'stretch';
                container.innerHTML = `
                    <div style="padding: 10px; border-bottom: 1px solid var(--glass-border);">
                        <input type="text" id="emojiSearchInput" placeholder="Search emojis..." style="width: 100%; padding: 6px 10px; border-radius: 6px; border: 1px solid var(--glass-border); background: rgba(0,0,0,0.2); color: white; font-size: 13px;">
                    </div>
                    <div id="emojiGridScroll" style="overflow-y: auto; max-height: 200px; padding: 10px;"></div>
                `;
                
                const scrollArea = container.querySelector('#emojiGridScroll');
                const searchInput = container.querySelector('#emojiSearchInput');

                // Focus input
                setTimeout(() => searchInput.focus(), 100);

                // Helper for smart search (defined locally to access msgId)
                function renderSmartSearch(query, cats, target, mId) {
                    // Check if we have objects or strings
                    const isRichData = cats.length > 0 && cats[0].emojis.length > 0 && typeof cats[0].emojis[0] === 'object';
                    
                    if (!isRichData) {
                         target.innerHTML = '<div style="padding: 10px; color: var(--text-dim); font-size: 11px; text-align: center;">Emoji data update required for search.<br>Please clear browser cache or wait for update.</div>';
                         return;
                    }

                    let hits = [];
                    query = query.toLowerCase();
                    cats.forEach(cat => {
                        cat.emojis.forEach(item => {
                            if (item.name.toLowerCase().includes(query)) {
                                hits.push(item.char);
                            }
                        });
                    });

                    if (hits.length === 0) {
                        target.innerHTML = '<div style="padding: 20px; color: var(--text-dim); text-align: center;">No emojis found.</div>';
                    } else {
                        target.innerHTML = `
                            <div class="emoji-category-label">Search Results</div>
                            <div class="emoji-grid">
                                ${hits.slice(0, 100).map(char => `<div class="emoji-btn" onclick="reactToMessage('${mId}', '${char}')">${char}</div>`).join('')}
                            </div>
                        `;
                    }
                }
                   
                // Final Render Logic
                const renderEmojis = (filter = '') => {
                    let html = '';
                    filter = filter.toLowerCase();

                    if (!filter) {
                         if (categories.length > 0 && typeof categories[0].emojis[0] === 'string') {
                             // Fallback for old cache (strings)
                             categories.forEach(cat => {
                                const preview = cat.emojis.slice(0, 42); 
                                html += `<div class="emoji-category-label">${cat.name}</div>
                                         <div class="emoji-grid">
                                            ${preview.map(char => `<div class="emoji-btn" onclick="reactToMessage('${msgId}', '${char}')">${char}</div>`).join('')}
                                         </div>
                                         <div style="height: 10px;"></div>`;
                            });
                        } else {
                            // New path (objects)
                            categories.forEach(cat => {
                                const preview = cat.emojis.slice(0, 42); 
                                html += `<div class="emoji-category-label">${cat.name}</div>
                                         <div class="emoji-grid">
                                            ${preview.map(item => `<div class="emoji-btn" title="${item.name}" onclick="reactToMessage('${msgId}', '${item.char}')">${item.char}</div>`).join('')}
                                         </div>
                                         <div style="height: 10px;"></div>`;
                            });
                        }
                    } else {
                        // Search handled by smart search input listener
                    }
                    scrollArea.innerHTML = html;
                };

                renderEmojis(); // Initial render

                // Input Listener repeated logic (ensures it works with new context)
                searchInput.addEventListener('input', (e) => {
                   const val = e.target.value;
                   if(val) {
                       renderSmartSearch(val, categories, scrollArea, msgId); 
                   } else {
                       renderEmojis();
                   }
                });

            });

            // Close menu on click elsewhere
            const closeMenu = () => {
                menu.remove();
                document.removeEventListener('click', closeMenu);
            };
            setTimeout(() => document.addEventListener('click', closeMenu), 10);
        }

        function copyMessageText(msgId) {
            const row = document.getElementById(`msg-${msgId}`);
            if (row) {
                const bubble = row.querySelector('.msg-bubble');
                const text = bubble.innerText.replace(/[\n\r]|This message was deleted/g, '').trim(); 
                navigator.clipboard.writeText(text).then(() => {
                    showMsgToast('Copied', 'Message copied to clipboard');
                });
            }
        }

        async function startEditMessage(msgId) {
            const row = document.getElementById(`msg-${msgId}`);
            if (!row) return;
            const bubble = row.querySelector('.msg-bubble');
            const originalText = bubble.innerText.replace(/[\n\r]|This message was deleted/g, '').trim(); 
            
            // Simpler approach: replace bubble with input
            const input = document.createElement('input');
            input.type = 'text';
            input.className = 'edit-msg-input';
            input.value = originalText;
            input.style.width = '100%';
            input.style.background = 'rgba(0,0,0,0.3)';
            input.style.color = 'white';
            input.style.border = '1px solid var(--azure-blue)';
            input.style.borderRadius = '8px';
            input.style.padding = '5px 10px';

            const oldContent = bubble.innerHTML;
            bubble.innerHTML = '';
            bubble.appendChild(input);
            input.focus();

            input.onkeydown = async (e) => {
                if (e.key === 'Escape') {
                    bubble.innerHTML = oldContent;
                }
                if (e.key === 'Enter') {
                    const newText = input.value.trim();
                    if (!newText || newText === originalText) {
                        bubble.innerHTML = oldContent;
                        return;
                    }

                    try {
                        const res = await fetch('../backend/edit_private_message.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ message_id: msgId, new_text: newText })
                        });
                        const result = await res.json();
                        if (result.success) {
                            // Find the reaction container if it exists in old content
                            const tempDiv = document.createElement('div');
                            tempDiv.innerHTML = oldContent;
                            const reactionContainer = tempDiv.querySelector('.reaction-container');
                            
                            // Programmatically build HTML to avoid 'class' keyword in string if that's the issue
                            let rHtml = '';
                            if (reactionContainer) {
                                rHtml = reactionContainer.outerHTML;
                            } else {
                                const emptyDiv = document.createElement('div');
                                emptyDiv.className = 'reaction-container';
                                rHtml = emptyDiv.outerHTML;
                            }
                            
                            bubble.innerHTML = escapeHtml(newText) + rHtml;
                            
                            if (ws && ws.readyState === WebSocket.OPEN) {
                                ws.send(JSON.stringify({
                                    type: 'MESSAGE_EDITED',
                                    message_id: msgId,
                                    new_text: newText,
                                    sender_id: myUserId,
                                    receiver_id: activePeer.id,
                                    isGroup: activePeer.isGroup || false
                                }));
                            }
                        } else {
                            bubble.innerHTML = oldContent;
                        }
                    } catch (err) { 
                        console.error('Edit error:', err);
                        bubble.innerHTML = oldContent;
                    }
                }
            };

            // Close edit on blur
            input.onblur = () => {
                setTimeout(() => {
                    if (bubble.contains(input)) bubble.innerHTML = oldContent;
                }, 200);
            };
        }

        async function deleteMessage(msgId) {
            try {
                const res = await fetch('../backend/delete_private_message.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ message_id: msgId })
                });
                const result = await res.json();
                if (result.success) {
                    // Update UI immediately (Optimistic)
                    const row = document.getElementById(`msg-${msgId}`);
                    if (row) {
                        row.style.opacity = '0';
                        row.style.transform = 'scale(0.9)';
                        row.style.transition = 'all 0.3s ease';
                        setTimeout(() => row.remove(), 300);
                    }
                    
                    // Broadcast via WS
                    if (ws && ws.readyState === WebSocket.OPEN) {
                        ws.send(JSON.stringify({
                            type: 'MESSAGE_DELETED',
                            message_id: msgId,
                            sender_id: myUserId,
                            receiver_id: activePeer.id,
                            isGroup: activePeer.isGroup || false
                        }));
                    }
                }
            } catch (err) { console.error('Delete MSG error:', err); }
        }

        async function reactToMessage(msgId, emoji) {
            try {
                const res = await fetch('../backend/react_to_message.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ message_id: msgId, reaction: emoji })
                });
                const result = await res.json();
                if (result.success) {
                    // Result.reactions is the new JSON object
                    // Update UI - simple refresh of this message block or full reload
                    // For now, let's just refresh the chat view or find the specific container
                    if (ws && ws.readyState === WebSocket.OPEN) {
                        ws.send(JSON.stringify({
                            type: 'MESSAGE_REACTED',
                            message_id: msgId,
                            reactions: result.reactions,
                            sender_id: myUserId,
                            receiver_id: activePeer.id,
                            isGroup: activePeer.isGroup || false
                        }));
                    }
                    
                    // Optimistic update for local UI
                    updateMessageReactions(msgId, result.reactions);
                }
            } catch (err) { console.error('React error:', err); }
        }

        function updateMessageReactions(msgId, reactions) {
            const msgRow = document.getElementById(`msg-${msgId}`);
            if (!msgRow) return;
            
            let container = msgRow.querySelector('.reaction-container');
            if (!container) {
                container = document.createElement('div');
                container.className = 'reaction-container';
                msgRow.querySelector('.msg-bubble').appendChild(container);
            }
            
            let html = '';
            for(const [emoji, users] of Object.entries(reactions)) {
                const hasMyReact = users.includes(String(myUserId)) || users.includes(Number(myUserId));
                html += `
                    <div class="reaction-chip ${hasMyReact ? 'active' : ''}" onclick="reactToMessage('${msgId}', '${emoji}')">
                        <span>${emoji}</span>
                        <span class="reaction-count">${users.length}</span>
                    </div>
                `;
            }
            container.innerHTML = html;
        }

        async function sendMessage(e) {
            e.preventDefault();
            const input = document.getElementById('msgInput');
            const btn = document.getElementById('sendBtn');
            const text = input.value.trim();
            
            if (!text || !activePeer) return;

            // Optimistic Append (instant feedback)
            const isoNow = new Date().toISOString();
            appendMessage({
                sender_id: myUserId,
                text: text,
                timestamp: isoNow,
                isOptimistic: true
            });

            input.value = '';
            input.focus();

            // Optimistic Sidebar Update
            const sidebarCard = document.querySelector(`.conv-card[data-id="${activePeer.id}"]`);
            if(sidebarCard) {
                const preview = sidebarCard.querySelector('.conv-preview');
                if(preview) {
                    preview.textContent = text;
                    preview.style.fontStyle = 'normal';
                }
                const time = sidebarCard.querySelector('.conv-time');
                if(time) time.textContent = formatShortTime(isoNow);
                
                // Prepend to top of its section (or just top of list for now)
                const list = document.getElementById('convList');
                list.prepend(sidebarCard);
            }

            const payload = { 
                message: text,
                receiver_id: activePeer.id
            };
            
            // Handle Group Send
            let endpoint = '../backend/send_private_message.php';
            if (activePeer.isGroup) {
                // We need a send_group_message.php or modify send_private logic.
                // For now, let's assume valid endpoint adaptation or add 'group_id'
                payload.group_id = activePeer.id;
                delete payload.receiver_id;
                // Reuse endpoint if it handles group_id, or use new one
                // Since we haven't made send_group_message.php, we'll use send_private_message modified to handle groups?
                // Actually, let's just make a quick hack: use 'receiver_id' as group_id but pass a flag?
                // Better: Create send_private_message logic that checks for 'group_id'.
            }

            try {
                const res = await fetch('../backend/send_private_message.php', { // Ensure this backend file handles group_id check!
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                const result = await res.json();
                
                if (result.success) {
                    const toast = document.getElementById('msgToast');
                    
                    // Show different toast based on whether it's a new request
                    if (result.is_request) {
                        // New message request sent - show special toast and refresh to show pending state
                        if(toast) {
                            toast.querySelector('span').textContent = 'Message request sent!';
                            toast.classList.add('show');
                            setTimeout(() => toast.classList.remove('show'), 3000);
                        }
                        // Refresh to show the "Waiting for approval" state
                        setTimeout(() => openChat(activePeer), 500);
                    } else {
                        // Normal message - NO toast needed per user request
                        
                        // Update the optimistic message with REAL ID and checkmark
                        const optimisticMsg = document.querySelector('.msg-row.optimistic');
                        if(optimisticMsg) {
                            optimisticMsg.id = `msg-${result.message_id}`;
                            optimisticMsg.setAttribute('data-msg-id', result.message_id);
                            optimisticMsg.classList.remove('optimistic');
                            
                            // Replace its actions trigger (or add it) so it works now
                            const bubble = optimisticMsg.querySelector('.msg-bubble');
                            const trigger = document.createElement('div');
                            trigger.className = 'msg-actions-trigger';
                            trigger.onclick = (ev) => toggleMsgMenu(ev, '${result.message_id}', true);
                            trigger.innerHTML = '<i class="fa-solid fa-ellipsis-vertical"></i>';
                            optimisticMsg.insertBefore(trigger, optimisticMsg.querySelector('.msg-info'));

                            const info = optimisticMsg.querySelector('.msg-info');
                            if(info) {
                                info.innerHTML = `<span>${formatShortTime(isoNow)}</span> <i class="fa-solid fa-check" style="color: rgba(255,255,255,0.3); font-size: 11px;"></i>`;
                            }
                        }
                    }
                    
                    // Send via WS for the recipient (Safely)
                    if (ws && ws.readyState === WebSocket.OPEN) {
                        try {
                            const isGrp = activePeer.isGroup;
                            console.log(`📤 Sending WS Signal (${isGrp ? 'GROUP' : 'PRIVATE'}):`, activePeer.id);
                            ws.send(JSON.stringify({
                                type: isGrp ? 'GROUP_MESSAGE' : 'PRIVATE_MESSAGE',
                                sender_id: myUserId,
                                [isGrp ? 'group_id' : 'receiver_id']: activePeer.id,
                                message_id: result.message_id, 
                                user: myUsername,
                                text: text,
                                profilePic: myProfilePic
                            }));
                        } catch (wsErr) { console.warn('❌ WS Send Failed', wsErr); }
                    }
 else {
                        console.warn('⚠️ WS not open, signal not sent. State:', ws ? ws.readyState : 'null');
                    }
                    
                    loadConversations();
                } else {
                    // Show error toast for pending request
                    if (result.is_pending) {
                        const toast = document.getElementById('msgToast');
                        if(toast) {
                            toast.querySelector('span').textContent = 'Wait for approval first';
                            toast.style.background = '#ef4444';
                            toast.classList.add('show');
                            setTimeout(() => {
                                toast.classList.remove('show');
                                toast.style.background = '';
                            }, 3000);
                        }
                        // Remove the optimistic message since it failed
                        const lastMsg = document.querySelector('.msg-row.sent:last-child');
                        if(lastMsg) lastMsg.remove();
                        
                        // Refresh to show correct state
                        openChat(activePeer);
                    }
                    console.warn('Save failed:', result.message);
                }
            } catch (err) {
                console.error('Send Error:', err);
            }
        }

        function showMsgToast(title, text) {
            const toast = document.getElementById('msgToast');
            if(!toast) return;
            
            toast.innerHTML = `
                <div style="display: flex; align-items: center; gap: 12px;">
                    <i class="fa-solid fa-comment-dots" style="color: var(--azure-blue); font-size: 18px;"></i>
                    <div>
                        <div style="font-weight: 700; font-size: 14px; margin-bottom: 2px;">${escapeHtml(title)}</div>
                        <div style="font-size: 12px; color: var(--text-dim); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 200px;">${escapeHtml(text)}</div>
                    </div>
                </div>
            `;
            
            toast.classList.add('show');
            setTimeout(() => toast.classList.remove('show'), 4000);
            
            // Play a subtle notification sound if you want, but sticking to visual for now
        }

        async function loadEmojiData() {
            if (emojiDataCache) return emojiDataCache;
            
            // Version 2 Storage Key to force re-fetch
            const local = localStorage.getItem('flox_emoji_data_v2');
            if (local) {
                try {
                    const parsed = JSON.parse(local);
                    if (Array.isArray(parsed) && parsed.length > 0) {
                         emojiDataCache = parsed;
                         return emojiDataCache;
                    }
                } catch(e) {
                    console.error("Invalid cached emoji data", e);
                }
            }
    
            // Fetch from API
            try {
                const res = await fetch('https://emoji-api.com/emojis?access_key=a0144fbf691a1baa5592e15f709fd9c5b829b194');
                const data = await res.json();
                
                if (!Array.isArray(data)) throw new Error("Invalid API response");
    
                // Process and Group
                const groups = {};
                
                data.forEach(e => {
                    if (!groups[e.group]) groups[e.group] = [];
                    // Store Object for searchability
                    groups[e.group].push({ char: e.character, name: e.unicodeName || e.slug || '' });
                });
    
                // Convert to ordered array
                const orderedGroups = [];
                const map = {
                    'smileys-emotion': 'Smileys & Emotion',
                    'people-body': 'People & Body',
                    'animals-nature': 'Animals & Nature',
                    'food-drink': 'Food & Drink',
                    'travel-places': 'Travel',
                    'activities': 'Activities',
                    'objects': 'Objects',
                    'symbols': 'Symbols',
                    'flags': 'Flags'
                };
    
                for (const [key, label] of Object.entries(map)) {
                    if (groups[key]) {
                        orderedGroups.push({ name: label, emojis: groups[key] });
                    }
                }
    
                emojiDataCache = orderedGroups;
                localStorage.setItem('flox_emoji_data_v2', JSON.stringify(orderedGroups));
                return orderedGroups;
    
            } catch(e) {
                console.error("Failed to fetch emojis", e);
                // Fallback / standard set
                return [
                    { name: 'Offline Set', emojis: [{char:'👍',name:'thumbs up'}, {char:'❤️',name:'heart'}] }
                ];
            }
        }

        function appendMessage(data) {
            const viewport = document.getElementById('viewport');
            if(!viewport) {
                console.warn('⚠️ No viewport found to append message.');
                return;
            }
            
            // Prevent duplicate if it's our own message coming back via WS
            if(!data.isOptimistic && String(data.sender_id) === String(myUserId)) {
                return; 
            }
            
            // Map keys if incoming from WS vs local
            const messageObj = {
                id: data.id || data.message_id || 'temp-' + Date.now(),
                sender_id: data.sender_id,
                message: data.text || data.message || '',
                created_at: data.timestamp || data.created_at || new Date().toISOString(),
                is_read: data.is_read || 0,
                is_deleted: data.is_deleted || 0,
                reactions: data.reactions || {}
            };

            const html = createMsgBlock(messageObj);
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = html;
            const msgRow = tempDiv.firstElementChild;

            if (data.isOptimistic) {
                msgRow.classList.add('optimistic');
                // Hide actions on optimistic until we have a real ID
                const trigger = msgRow.querySelector('.msg-actions-trigger');
                if (trigger) trigger.remove();
            }

            viewport.appendChild(msgRow);
            viewport.scrollTop = viewport.scrollHeight;
            console.log('📢 Message appended to viewport');
        }

        // Search logic
        const searchInput = document.getElementById('userSearch');
        const resultsBox = document.getElementById('searchResults');

        searchInput.oninput = async () => {
            const q = searchInput.value.trim();
            if (q.length < 1) { resultsBox.style.display = 'none'; return; }

            const res = await fetch(`../backend/searchUsers.php?q=${encodeURIComponent(q)}`);
            const data = await res.json();
            
            if (data.success && data.users.length > 0) {
                resultsBox.innerHTML = data.users.map(u => `
                    <div class="search-user-item" onclick="startNewThread(${JSON.stringify(u).replace(/"/g, '&quot;')})">
                        <img src="${resolveProfilePic(u.profile_picture)}" onerror="this.src='https://www.gravatar.com/avatar/00?d=mp'">
                        <div style="font-weight: 600;">${escapeHtml(u.username)}</div>
                    </div>
                `).join('');
                resultsBox.style.display = 'block';
            } else {
                resultsBox.style.display = 'none';
            }
        };

        function startNewThread(user) {
            if(user.id == myUserId) return;
            resultsBox.style.display = 'none';
            searchInput.value = '';
            
            // Optimistically add to sidebar if not present
            const existing = document.querySelector(`.conv-card[data-id="${user.id}"]`);
            if(!existing) {
                const list = document.getElementById('convList');
                if(list.innerHTML.includes('No messages yet')) list.innerHTML = '';
                
                const tempCard = document.createElement('div');
                tempCard.className = 'conv-card active';
                tempCard.setAttribute('data-id', user.id);
                tempCard.onclick = () => openChat(user);
                tempCard.innerHTML = `
                    <div class="avatar-wrapper">
                        <img src="${resolveProfilePic(user.profile_picture)}" class="avatar-img">
                    </div>
                    <div class="conv-details">
                        <div class="conv-top">
                            <span class="conv-name">${escapeHtml(user.username)}</span>
                            <span class="conv-time">Now</span>
                        </div>
                        <div class="conv-preview" style="font-style: italic;">New conversation...</div>
                    </div>
                `;
                list.prepend(tempCard);
            } else {
                document.querySelectorAll('.conv-card').forEach(c => c.classList.remove('active'));
                existing.classList.add('active');
            }
            
            openChat(user);
        }

        // --- GROUP CHAT FUNCTIONS ---
        let groupMembers = [];

        async function openCreateGroupModal() {
            document.getElementById('createGroupModal').classList.add('active');
            groupMembers = [];
            renderGroupMembers();
            document.getElementById('newGroupName').value = '';
            document.getElementById('memberSearch').value = '';

            // Fetch and Show Contacts
            const panel = document.getElementById('contactsPanel');
            try {
                const res = await fetch(`../backend/get_conversations.php?_t=${Date.now()}`);
                const data = await res.json();
                if (data.success && data.conversations) {
                    // Filter out groups from the contacts list for selection
                    const contacts = data.conversations.filter(c => !c.isGroup);
                    
                    if (contacts.length === 0) {
                        panel.innerHTML = '<div style="color: var(--text-dim); font-size: 12px; width: 100%; text-align: center;">No active contacts found. Try searching below!</div>';
                        return;
                    }

                    panel.innerHTML = contacts.map(c => `
                        <div class="contact-select-item" onclick='addGroupMember(${JSON.stringify(c).replace(/'/g, "\\'")})' style="flex: 0 0 80px; text-align: center; cursor: pointer; transition: transform 0.2s;">
                            <div style="position: relative; display: inline-block;">
                                <img src="${resolveProfilePic(c.profile_picture)}" style="width: 55px; height: 55px; border-radius: 50%; border: 2px solid var(--glass-border); padding: 2px;">
                                <div style="position: absolute; bottom: 2px; right: 2px; width: 12px; height: 12px; background: #22c55e; border-radius: 50%; border: 2px solid #111;"></div>
                            </div>
                            <div style="font-size: 11px; margin-top: 5px; color: white; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; width: 75px;">${escapeHtml(c.username)}</div>
                        </div>
                    `).join('');
                }
            } catch (err) {
                console.error('Error fetching contacts for group:', err);
                panel.innerHTML = '<div style="color: #ef4444; font-size: 12px;">Failed to load contacts.</div>';
            }
        }

        function closeCreateGroupModal() {
            document.getElementById('createGroupModal').classList.remove('active');
        }

        const memSearch = document.getElementById('memberSearch');
        const memResults = document.getElementById('memberSearchResults');

        memSearch.oninput = async () => {
            const q = memSearch.value.trim();
            if(q.length < 1) { memResults.style.display = 'none'; return; }
            
            const res = await fetch(`../backend/searchUsers.php?q=${encodeURIComponent(q)}`);
            const data = await res.json();
            
            if(data.success && data.users.length > 0) {
                memResults.innerHTML = data.users.map(u => `
                    <div class="search-user-item" onclick='addGroupMember(${JSON.stringify(u)})'>
                        <img src="${resolveProfilePic(u.profile_picture)}">
                        <div>${escapeHtml(u.username)}</div>
                    </div>
                `).join('');
                memResults.style.display = 'block';
            } else {
                memResults.style.display = 'none';
            }
        };

        function addGroupMember(user) {
            if(groupMembers.find(m => m.id === user.id)) return;
            // Limit to 50?
            groupMembers.push(user);
            renderGroupMembers();
            memSearch.value = '';
            memResults.style.display = 'none';
        }

        function removeGroupMember(uid) {
            groupMembers = groupMembers.filter(m => m.id !== uid);
            renderGroupMembers();
        }

        function renderGroupMembers() {
            const container = document.getElementById('selectedMembers');
            container.innerHTML = groupMembers.map(u => `
                <div class="member-pill" onclick="removeGroupMember(${u.id})">
                    ${escapeHtml(u.username)} <i class="fa-solid fa-xmark"></i>
                </div>
            `).join('');
        }

        async function submitCreateGroup() {
            const name = document.getElementById('newGroupName').value.trim();
            if(!name) return;
            if(groupMembers.length === 0) return;
            
            try {
                const res = await fetch('../backend/create_group.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        name: name,
                        members: groupMembers.map(m => m.id)
                    })
                });
                const data = await res.json();
                if(data.success) {
                    closeCreateGroupModal();
                    loadConversations();
                    
                    // Signal members via WS
                    if (ws && ws.readyState === WebSocket.OPEN) {
                        ws.send(JSON.stringify({
                            type: 'GROUP_CREATED',
                            group_id: data.group_id,
                            group_name: data.name,
                            members: data.members // members list returned from backend includes creator
                        }));
                    }

                    // Open the new chat
                    openChat({
                        id: data.group_id,
                        username: data.name, // Mapping for unified chat interface
                        isGroup: true,
                        profile_picture: null,
                        member_count: data.members.length
                    });
                } else {
                }
            } catch(e) { console.error(e); }
        }

        // --- ADD PARTICIPANTS TO EXISTING GROUP ---
        let addPartMembers = [];

        async function openAddParticipantsModal() {
            if(!activePeer || !activePeer.isGroup) return;
            document.getElementById('addParticipantsModal').classList.add('active');
            document.getElementById('addGroupNameText').textContent = activePeer.username;
            addPartMembers = [];
            renderAddPartMembers();
            document.getElementById('addPartSearch').value = '';

            const panel = document.getElementById('addParticipantsContacts');
            try {
                const res = await fetch(`../backend/get_conversations.php?_t=${Date.now()}`);
                const data = await res.json();
                if (data.success && data.conversations) {
                    const contacts = data.conversations.filter(c => !c.isGroup);
                    if (contacts.length === 0) {
                        panel.innerHTML = '<div style="color: var(--text-dim); font-size: 12px; width: 100%; text-align: center;">No active contacts found.</div>';
                        return;
                    }
                    panel.innerHTML = contacts.map(c => `
                        <div class="contact-select-item" onclick='addToAddList(${JSON.stringify(c).replace(/'/g, "\\'")})' style="flex: 0 0 80px; text-align: center; cursor: pointer;">
                            <img src="${resolveProfilePic(c.profile_picture)}" style="width: 50px; height: 50px; border-radius: 50%; border: 2px solid var(--glass-border); padding: 2px;">
                            <div style="font-size: 11px; margin-top: 5px; color: white; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; width: 75px;">${escapeHtml(c.username)}</div>
                        </div>
                    `).join('');
                }
            } catch (err) { console.error(err); }
        }

        function closeAddParticipantsModal() {
            document.getElementById('addParticipantsModal').classList.remove('active');
        }

        function addToAddList(user) {
            if(addPartMembers.find(m => m.id === user.id)) return;
            addPartMembers.push(user);
            renderAddPartMembers();
        }

        function removeFromAddList(uid) {
            addPartMembers = addPartMembers.filter(m => m.id !== uid);
            renderAddPartMembers();
        }

        function renderAddPartMembers() {
            const container = document.getElementById('participantsToAdd');
            container.innerHTML = addPartMembers.map(u => `
                <div class="member-pill" onclick="removeFromAddList(${u.id})">
                    ${escapeHtml(u.username)} <i class="fa-solid fa-xmark"></i>
                </div>
            `).join('');
        }

        document.getElementById('addPartSearch').oninput = async () => {
            const q = document.getElementById('addPartSearch').value.trim();
            const results = document.getElementById('addPartSearchResults');
            if(q.length < 1) { results.style.display = 'none'; return; }
            
            const res = await fetch(`../backend/searchUsers.php?q=${encodeURIComponent(q)}`);
            const data = await res.json();
            if(data.success && data.users.length > 0) {
                results.innerHTML = data.users.map(u => `
                    <div class="search-user-item" onclick='addToAddList(${JSON.stringify(u)})'>
                        <img src="${resolveProfilePic(u.profile_picture)}">
                        <div>${escapeHtml(u.username)}</div>
                    </div>
                `).join('');
                results.style.display = 'block';
            } else { results.style.display = 'none'; }
        };

        async function submitAddParticipants() {
            if(addPartMembers.length === 0) return;
            try {
                const res = await fetch('../backend/add_group_members.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ group_id: activePeer.id, members: addPartMembers.map(m => m.id) })
                });
                const data = await res.json();
                if(data.success) {
                    closeAddParticipantsModal();
                    if(typeof showMsgToast === 'function') {
                        showMsgToast('Added', `Added members to the group`);
                    }
                    // Handle WS signal
                    if (ws && ws.readyState === WebSocket.OPEN) {
                        ws.send(JSON.stringify({
                            type: 'MEMBERS_ADDED',
                            group_id: activePeer.id,
                            group_name: activePeer.username,
                            sender_id: myUserId,
                            sender_name: myUsername,
                            members: addPartMembers.map(m => m.id),
                            member_names: addPartMembers.map(m => m.username)
                        }));
                    }
                    loadConversations();
                    // We don't need to openChat here if we append, but for the adder it's easier to reload
                    openChat(activePeer);
                } else {
                }
            } catch (err) { console.error(err); }
        }


        async function leaveGroup(groupId) {
            if(true) {
            
            try {
                const res = await fetch('../backend/leave_group.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ group_id: groupId })
                });
                const data = await res.json();
                if(data.success) {
                    showMsgToast('Left Group', 'You have left the group.');
                    
                    // Signal others via WS so they see the system message
                    if (ws && ws.readyState === WebSocket.OPEN) {
                        ws.send(JSON.stringify({
                            type: 'GROUP_LEAVE',
                            group_id: groupId,
                            user_id: myUserId,
                            username: myUsername
                        }));
                    }
                    
                    activePeer = null;
                    document.getElementById('chatWindow').innerHTML = `
                        <canvas id="chatStars" class="chat-stars"></canvas>
                        <div class="empty-view">
                            <i class="fa-solid fa-message"></i>
                            <h3>Your chats</h3>
                            <p>Search for a creator or friend above to start a private session.</p>
                        </div>
                    `;
                    initStars();
                    loadConversations();
                } else {
                }
            } catch (err) {
                console.error(err);
            }
        }


        function formatShortTime(dateStr) {
            if(!dateStr) return '';
            const date = new Date(dateStr);
            const now = new Date();
            const diff = now - date;
            
            if(diff < 86400000) return date.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
            return date.toLocaleDateString([], {month:'short', day:'numeric'});
        }

        function formatActiveStatus(dateStr) {
            if (!dateStr) return '';
            const date = new Date(dateStr.replace(' ', 'T') + 'Z'); // Assume UTC from server, or handle timezone
            // Better: use direct parsing if SQL output is 'YYYY-MM-DD HH:MM:SS' in local or UTC. 
            // Usually PHP `NOW()` is server time. JS `new Date()` is client time. 
            // We should treat dateStr as server time. Ideally server returns ISO8601.
            
            // Try parsing as local first if simple string, or UTC if attached Z.
            // Let's assume server sends UTC or we just rely on rough diff.
            const d = new Date(dateStr);
            const now = new Date();
            if (isNaN(d.getTime())) return ''; // Invalid date

            const diffMs = now - d;
            const diffMin = Math.floor(diffMs / 60000);
            const diffHour = Math.floor(diffMin / 60);
            const diffDays = Math.floor(diffHour / 24);

            if (diffDays >= 2) return ''; // Don't show if > 2 days
            if (diffMin < 2) return 'Active Now';
            if (diffMin < 60) return `Active ${diffMin}m ago`;
            if (diffHour < 24) {
                 // Check if it was yesterday by calendar day
                 if (d.getDate() !== now.getDate()) return 'Active yesterday';
                 return `Active ${diffHour}h ago`;
            }
            return 'Active yesterday';
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }


        // Close search on blur
        document.addEventListener('click', (e) => {
            if(!searchInput.contains(e.target) && !resultsBox.contains(e.target)) {
                resultsBox.style.display = 'none';
            }
        });

        // Toggle Header Menu
        function toggleHeaderMenu(e) {
            e.stopPropagation();
            const menu = document.getElementById('headerMenuDropdown');
            const isVisible = menu.style.display === 'block'; 
            
            // Close any other menus (if any)
            document.querySelectorAll('.dropdown-menu').forEach(m => m.style.display = 'none');
            
            if(!isVisible) {
                menu.style.display = 'block';
                const close = () => {
                     menu.style.display = 'none';
                     document.removeEventListener('click', close);
                };
                setTimeout(() => document.addEventListener('click', close), 10);
            } else {
                menu.style.display = 'none';
            }
        }

        // Start Call - Full Screen Overlay
        // Professional WebRTC Call System
        let activeCallOverlay = null;
        let callStartTime = null;
        let callTimerInterval = null;
        let currentCallPeerId = null;
        let localStream = null;
        let peerConnection = null;
        let audioContext = null;
        let audioAnalyser = null;
        let iceQueue = [];
        let activeGroupCallId = null;
        let groupConnections = {}; // userId -> { pc, name, pic }
        const rtcConfig = { iceServers: [{ urls: 'stun:stun.l.google.com:19302' }] };
        
        function startAudioVisualizer(stream) {
            try {
                if (audioContext) audioContext.close();
                audioContext = new (window.AudioContext || window.webkitAudioContext)();
                if (audioContext.state === 'suspended') {
                    audioContext.resume();
                }
                audioAnalyser = audioContext.createAnalyser();
                const source = audioContext.createMediaStreamSource(stream);
                source.connect(audioAnalyser);
                audioAnalyser.fftSize = 32;
                const bufferLength = audioAnalyser.frequencyBinCount;
                const dataArray = new Uint8Array(bufferLength);

                function draw() {
                    if (!audioContext || audioContext.state === 'closed') return;
                    requestAnimationFrame(draw);
                    audioAnalyser.getByteFrequencyData(dataArray);
                    const bars = document.querySelectorAll('.audio-visualizer .bar');
                    bars.forEach((bar, i) => {
                        const val = dataArray[i % bufferLength] / 2;
                        bar.style.height = Math.max(5, val) + 'px';
                    });
                }
                draw();
            } catch (e) { console.warn('[CALL] Visualizer Init Failed:', e); }
        }

        async function createPeerConnection(remoteUserId) {
            console.log('[RTC] Creating Peer Connection for:', remoteUserId);
            peerConnection = new RTCPeerConnection(rtcConfig);
            
            // Add local tracks
            if (localStream) {
                console.log(`[RTC] Adding ${localStream.getTracks().length} tracks to PC`);
                localStream.getTracks().forEach(track => {
                    console.log(`[RTC] Adding track: ${track.kind}`);
                    peerConnection.addTrack(track, localStream);
                });
            } else {
                console.warn('[RTC] No local stream available to add tracks!');
            }
            
            // Handle remote tracks
            peerConnection.ontrack = (event) => {
                const stream = event.streams[0];
                if (!stream) {
                    console.warn('[RTC] Received ontrack event but no stream found');
                    return;
                }
                
                console.log(`[RTC] Received Remote Track: ${event.track.kind}`);
                const remoteVid = document.getElementById('remoteVideo');
                if (remoteVid) {
                    if (remoteVid.srcObject !== stream) {
                        remoteVid.srcObject = stream;
                        console.log('[RTC] Remote stream attached to video element');
                    }
                    
                    remoteVid.play().catch(e => console.warn('[RTC] Remote play blocked:', e));
                    
                    // Logic to show video or keep audio-only
                    // We check both the stream and the individual track kind
                    const hasVideo = stream.getVideoTracks().length > 0 || event.track.kind === 'video';
                    console.log(`[RTC] TrackKind: ${event.track.kind}, HasVideoTracks: ${stream.getVideoTracks().length}`);
                    
                    if (hasVideo) {
                        console.log('[RTC] Showing remote video element');
                        remoteVid.style.display = 'block';
                        remoteVid.style.opacity = '1';
                        // Hide avatars/labels when video is active
                        const remotePart = document.getElementById('remoteParticipant');
                        if (remotePart) remotePart.style.display = 'none';
                        const localPart = document.getElementById('localParticipant');
                        if (localPart) localPart.style.display = 'none';
                    } else {
                        // Keep it hidden but enabled for audio to ensure sound output
                        remoteVid.style.display = 'block';
                        remoteVid.style.opacity = '0';
                    }
                }
            };
            
            peerConnection.onconnectionstatechange = () => {
                console.log(`[RTC] Connection State: ${peerConnection.connectionState}`);
                if (peerConnection.connectionState === 'connected') {
                    const statusText = document.getElementById('callStatusText');
                    if (statusText) statusText.textContent = 'Connected (Secure)';
                }
            };
            
            peerConnection.oniceconnectionstatechange = () => {
                console.log(`[RTC] ICE State: ${peerConnection.iceConnectionState}`);
            };
            
            // Handle ICE candidates
            peerConnection.onicecandidate = (event) => {
                if (event.candidate && ws && ws.readyState === WebSocket.OPEN) {
                    ws.send(JSON.stringify({
                        type: 'CALL_SIGNAL',
                        from_id: myUserId,
                        to_id: remoteUserId,
                        signal: { candidate: event.candidate }
                    }));
                }
            };
            
            return peerConnection;
        }

        async function handleCallSignal(data) {
            const { from_id, signal } = data;
            
            // Handle Group Signals separately
            if (signal.isGroup || (activeGroupCallId && activeGroupCallId !== 'null')) {
                handleGroupSignal(data);
                return;
            }

            if (!peerConnection) await createPeerConnection(from_id);
            
            try {
                if (signal.offer) {
                    console.log('[RTC] Handling Offer');
                    await peerConnection.setRemoteDescription(new RTCSessionDescription(signal.offer));
                    
                    // Process queued candidates
                    while(iceQueue.length > 0) {
                        const candidate = iceQueue.shift();
                        await peerConnection.addIceCandidate(candidate);
                    }
                    
                    const answer = await peerConnection.createAnswer();
                    await peerConnection.setLocalDescription(answer);
                    ws.send(JSON.stringify({
                        type: 'CALL_SIGNAL', from_id: myUserId, to_id: from_id,
                        signal: { answer: answer }
                    }));
                } else if (signal.answer) {
                    console.log('[RTC] Handling Answer');
                    await peerConnection.setRemoteDescription(new RTCSessionDescription(signal.answer));
                    
                    // Process queued candidates
                    while(iceQueue.length > 0) {
                        const candidate = iceQueue.shift();
                        await peerConnection.addIceCandidate(candidate);
                    }
                } else if (signal.candidate) {
                    const candidate = new RTCIceCandidate(signal.candidate);
                    if (peerConnection.remoteDescription) {
                        console.log('[RTC] Adding ICE Candidate');
                        await peerConnection.addIceCandidate(candidate);
                    } else {
                        console.log('[RTC] Queuing ICE Candidate');
                        iceQueue.push(candidate);
                    }
                }
            } catch (err) {
                console.error('[RTC] Signal Handling Error:', err);
            }
        }

        async function initMedia(type) {
            console.log(`[CALL] Requesting media: ${type}`);
            const statusText = document.getElementById('callStatusText');
            if (statusText && (statusText.textContent === 'Calling...' || statusText.textContent === 'Connected')) {
                statusText.textContent = 'Activating Hardware...';
            }
            
            try {
                // Simple constraints for max compatibility
                const constraints = {
                    audio: true,
                    video: type === 'video' ? true : false
                };
                
                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    throw new Error('MediaDevices API not supported. Use Localhost or HTTPS.');
                }

                localStream = await navigator.mediaDevices.getUserMedia(constraints);
                console.log('[CALL] localStream acquired');
                
                if (statusText && statusText.textContent === 'Activating Hardware...') {
                    statusText.textContent = 'Hardware Active';
                    setTimeout(() => { if(statusText.textContent === 'Hardware Active') statusText.textContent = (peerConnection ? 'Connected' : 'Calling...'); }, 2000);
                }

                if (type === 'video') {
                    const localVid = document.getElementById('localVideo');
                    const localMe  = document.getElementById('video-me');
                    [localVid, localMe].forEach(vid => {
                        if (vid) {
                            vid.srcObject = localStream;
                            vid.onloadedmetadata = () => {
                                vid.play().catch(e => console.warn('[CALL] Play error:', e));
                                vid.style.display = 'block';
                                vid.style.opacity = '1';
                            };
                            if (vid.id === 'video-me') {
                                const avatar = document.getElementById('myVisualNode');
                                if (avatar) avatar.style.display = 'none';
                            }
                        }
                    });
                }

                // Start Visualizer for Microphone feedback
                startAudioVisualizer(localStream);
                
                return localStream;
            } catch (err) {
                console.error('[CALL] Hardware Error:', err);
                if (statusText) {
                    statusText.textContent = 'Hardware: ' + (err.name === 'NotAllowedError' ? 'Permission Denied' : 'Not Supported (SSL Required)');
                    statusText.style.color = '#ef4444';
                }

                // Friendly Helper for LAN Users
                if (!navigator.mediaDevices && window.location.hostname !== 'localhost' && window.location.protocol === 'http:') {
                } else if (err.name === 'NotAllowedError') {
                }
            }
        }

        async function initiateGroupPeerConnection(remoteUserId, name, pic) {
            if (groupConnections[remoteUserId]) return; // Already connected
            
            // Re-check hardware if video is active but stream is missing
            if (!localStream && activeCallOverlay && activeCallOverlay.querySelector('#cameraBtn:not(.muted)')) {
                const callType = activeGroupCallId ? 'video' : 'audio'; // Simplified
                await initMedia(callType);
            }

            console.log(`[GROUP] Initiating P2P with ${name} (${remoteUserId})`);
            
            // Add to UI Grid
            const stage = document.getElementById('callStage');
            if (stage && !document.getElementById(`participant-${remoteUserId}`)) {
                const card = document.createElement('div');
                card.className = 'participant-card';
                card.id = `participant-${remoteUserId}`;
                card.innerHTML = `
                    <video id="video-${remoteUserId}" autoplay playsinline style="display:none;"></video>
                    <img src="${resolveProfilePic(pic)}" class="avatar-pulse">
                    <div class="participant-label">${escapeHtml(name)}</div>
                `;
                stage.appendChild(card);
            }

            const pc = await createGroupPC(remoteUserId);
            
            // As the initiator (one who was already in call), we create the offer
            const offer = await pc.createOffer();
            await pc.setLocalDescription(offer);
            
            ws.send(JSON.stringify({
                type: 'CALL_SIGNAL',
                from_id: myUserId,
                to_id: remoteUserId,
                signal: { offer: offer, isGroup: true, group_id: String(activeGroupCallId) }
            }));
        }

        async function createGroupPC(remoteUserId) {
            console.log('[GROUP-RTC] Creating PC for Participant:', remoteUserId);
            const pc = new RTCPeerConnection(rtcConfig);
            
            groupConnections[remoteUserId] = { pc: pc, iceQueue: [] };

            if (localStream) {
                localStream.getTracks().forEach(track => pc.addTrack(track, localStream));
            }

            pc.onicecandidate = (event) => {
                if (event.candidate && ws && ws.readyState === WebSocket.OPEN) {
                    ws.send(JSON.stringify({
                        type: 'CALL_SIGNAL',
                        from_id: myUserId,
                        to_id: remoteUserId,
                        signal: { candidate: event.candidate, isGroup: true, group_id: activeGroupCallId }
                    }));
                }
            };

            pc.ontrack = (event) => {
                const stream = event.streams[0];
                console.log(`[GROUP-RTC] Received track from ${remoteUserId}: ${event.track.kind}`);
                const vid = document.getElementById(`video-${remoteUserId}`);
                if (vid) {
                    vid.srcObject = stream;
                    vid.style.display = 'block';
                    vid.play().catch(e => console.warn('[GROUP-RTC] Play failed', e));
                    // Hide avatar
                    const card = document.getElementById(`participant-${remoteUserId}`);
                    if (card) {
                        const avatar = card.querySelector('.avatar-pulse');
                        if (avatar) avatar.style.display = 'none';
                    }
                }
            };

            pc.onconnectionstatechange = () => {
                console.log(`[GROUP-RTC] PC State with ${remoteUserId}: ${pc.connectionState}`);
                if (pc.connectionState === 'disconnected' || pc.connectionState === 'failed' || pc.connectionState === 'closed') {
                    removeGroupParticipant(remoteUserId);
                }
            };

            return pc;
        }

        function removeGroupParticipant(userId) {
            if (groupConnections[userId]) {
                if (groupConnections[userId].pc) groupConnections[userId].pc.close();
                delete groupConnections[userId];
            }
            const card = document.getElementById(`participant-${userId}`);
            if (card) card.remove();
        }

        async function handleGroupSignal(data) {
            const { from_id, signal } = data;
            let targetPC = groupConnections[from_id] ? groupConnections[from_id].pc : null;

            if (!targetPC && (signal.offer || signal.candidate)) {
                // If we don't have a PC for this user yet, and they sent an offer/candidate, create it
                targetPC = await createGroupPC(from_id);
                
                // Add to UI Grid if missing
                const stage = document.getElementById('callStage');
                if (stage && !document.getElementById(`participant-${from_id}`)) {
                    const card = document.createElement('div');
                    card.className = 'participant-card';
                    card.id = `participant-${from_id}`;
                    card.innerHTML = `
                        <video id="video-${from_id}" autoplay playsinline style="display:none;"></video>
                        <img src="https://www.gravatar.com/avatar/00?d=mp" class="avatar-pulse">
                        <div class="participant-label">Participant</div>
                    `;
                    stage.appendChild(card);
                }
            }

            if (!targetPC) return;

            try {
                if (signal.offer) {
                    await targetPC.setRemoteDescription(new RTCSessionDescription(signal.offer));
                    
                    // Process queued candidates
                    const queue = groupConnections[from_id].iceQueue;
                    while(queue.length > 0) {
                        const cand = queue.shift();
                        await targetPC.addIceCandidate(cand);
                    }

                    const answer = await targetPC.createAnswer();
                    await targetPC.setLocalDescription(answer);
                    ws.send(JSON.stringify({
                        type: 'CALL_SIGNAL', from_id: myUserId, to_id: from_id,
                        signal: { answer: answer, isGroup: true, group_id: String(activeGroupCallId) }
                    }));
                } else if (signal.answer) {
                    await targetPC.setRemoteDescription(new RTCSessionDescription(signal.answer));
                    
                    // Process queued candidates
                    const queue = groupConnections[from_id].iceQueue;
                    while(queue.length > 0) {
                        const cand = queue.shift();
                        await targetPC.addIceCandidate(cand);
                    }
                } else if (signal.candidate) {
                    const cand = new RTCIceCandidate(signal.candidate);
                    if (targetPC.remoteDescription) {
                        await targetPC.addIceCandidate(cand);
                    } else {
                        groupConnections[from_id].iceQueue.push(cand);
                    }
                }
            } catch (e) {
                console.error('[GROUP-RTC] Signal Error:', e);
            }
        }

        function showCallUI(peerName, peerPic, peerId, type, status = 'Calling...', groupId = null) {
            currentCallPeerId = peerId;
            activeGroupCallId = groupId;
            
            // Remove existing overlay if any
            if (activeCallOverlay) activeCallOverlay.remove();
            
            const myPic = resolveProfilePic(myProfilePic);
            const resolvedPeerPic = resolveProfilePic(peerPic);
            
            const overlay = document.createElement('div');
            overlay.id = 'callOverlay';
            overlay.className = 'call-overlay fullscreen';
            if (groupId) overlay.classList.add('grid-mode');
            overlay.innerHTML = `
                <style>
                    .call-overlay {
                        position: fixed;
                        background: rgba(15,16,20,0.98);
                        backdrop-filter: blur(20px);
                        display: flex;
                        flex-direction: column;
                        z-index: 10000;
                        transition: all 0.3s ease;
                    }
                    .call-overlay.fullscreen { top: 0; left: 0; right: 0; bottom: 0; }
                    .call-overlay.minimized {
                        top: auto; left: auto;
                        right: 20px; bottom: 20px;
                        width: 320px; height: 180px;
                        border-radius: 16px;
                        border: 1px solid rgba(255,255,255,0.1);
                        box-shadow: 0 10px 40px rgba(0,0,0,0.5);
                        cursor: grab;
                    }
                    .call-stage {
                        flex: 1; display: flex; align-items: center; justify-content: center;
                        gap: 80px; position: relative; z-index: 5;
                    }
                    /* GRID MODE FOR GROUPS */
                    .call-overlay.grid-mode .call-stage {
                        display: grid;
                        grid-template-columns: repeat(auto-fit, minmax(380px, 1fr));
                        max-width: 1400px; margin: 0 auto;
                        grid-auto-rows: min-content;
                        gap: 30px; padding: 120px 40px; align-items: center; justify-content: center;
                    }
                    .call-overlay.minimized.grid-mode .call-stage {
                        display: flex; padding: 10px; gap: 10px;
                    }

                    .participant-card {
                        background: rgba(40, 44, 52, 0.4);
                        border-radius: 20px;
                        border: 1px solid rgba(255, 255, 255, 0.1);
                        display: flex; flex-direction: column; align-items: center;
                        justify-content: center; position: relative; overflow: hidden;
                        aspect-ratio: 16/9; transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
                        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
                        backdrop-filter: blur(10px);
                    }
                    .participant-card:hover { transform: translateY(-5px); border-color: var(--azure-blue); }
                    .participant-card.active { border: 2px solid var(--azure-blue); }
                    .participant-card video {
                        position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover;
                        background: #000; z-index: 1;
                    }
                    .participant-card .avatar-pulse {
                        width: 120px; height: 120px; border-radius: 50%;
                        border: 4px solid var(--azure-blue); z-index: 2;
                        animation: pulse-call 2s infinite; object-fit: cover;
                    }
                    
                    .call-overlay.minimized .participant-card .avatar-pulse { width: 40px; height: 40px; }
                    .participant-label {
                        position: absolute; bottom: 15px; left: 15px;
                        background: rgba(0,0,0,0.5); padding: 4px 12px;
                        border-radius: 20px; color: white; font-size: 13px;
                        z-index: 3; backdrop-filter: blur(5px);
                    }
                    .call-overlay.minimized .call-stage { gap: 30px; }
                    .call-overlay.minimized .avatar-pulse { width: 50px; height: 50px; }
                    .call-overlay.minimized .participant-label { font-size: 11px; }
                    .avatar-wrapper { display: flex; flex-direction: column; align-items: center; gap: 15px; }
                    .avatar-pulse {
                        width: 130px; height: 130px;
                        border-radius: 50%;
                        border: 3px solid var(--azure-blue, #007bff);
                        object-fit: cover;
                        animation: pulse-call 2s infinite;
                    }
                    @keyframes pulse-call {
                        0%, 100% { box-shadow: 0 0 0 0 rgba(0,123,255,0.5); }
                        50% { box-shadow: 0 0 0 15px rgba(0,123,255,0); }
                    }
                    .participant-label { font-weight: 500; font-size: 15px; color: white; margin-top: 10px; opacity: 0.8; }
                    .call-status {
                        position: absolute;
                        top: 60px;
                        font-size: 14px;
                        color: #22c55e;
                        font-weight: 600;
                        text-transform: uppercase;
                        letter-spacing: 1.5px;
                        background: rgba(255,255,255,0.05);
                        padding: 8px 24px;
                        border-radius: 100px;
                        backdrop-filter: blur(20px);
                        border: 1px solid rgba(255,255,255,0.1);
                        z-index: 100;
                    }
                    .participant-count {
                        position: absolute; top: 60px; right: 40px;
                        background: rgba(0,123,255,0.15); border: 1px solid var(--azure-blue);
                        color: var(--azure-blue); padding: 8px 16px; border-radius: 12px;
                        font-weight: 700; font-size: 14px; backdrop-filter: blur(10px);
                        display: flex; align-items: center; gap: 8px; z-index: 100;
                    }
                    .call-controls {
                        position: absolute;
                        bottom: 40px;
                        left: 50%;
                        transform: translateX(-50%);
                        background: rgba(18, 19, 23, 0.85);
                        backdrop-filter: blur(30px);
                        -webkit-backdrop-filter: blur(30px);
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        gap: 18px;
                        padding: 16px 32px;
                        border-radius: 100px;
                        border: 1px solid rgba(255,255,255,0.12);
                        box-shadow: 0 15px 40px rgba(0,0,0,0.4);
                        z-index: 150;
                        transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
                    }
                    .call-btn {
                        width: 54px; height: 54px;
                        border-radius: 50%;
                        border: none;
                        background: rgba(255,255,255,0.1);
                        color: white;
                        font-size: 20px;
                        cursor: pointer;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        transition: all 0.3s;
                    }
                    .call-btn:hover { background: rgba(255,255,255,0.2); transform: scale(1.05); }
                    .call-btn:active { transform: scale(0.95); }
                    .call-btn.danger { background: #ff3b30; color: white; }
                    .call-btn.danger:hover { background: #ff453a; box-shadow: 0 0 20px rgba(255,59,48,0.4); }
                    .call-btn.muted { background: #ffffff; color: #1c1c1e; }
                    .call-btn i { transition: transform 0.3s; }
                    
                    /* Modern Floating Footer Sub-labels */
                    .call-btn-wrapper { display: flex; flex-direction: column; align-items: center; gap: 8px; }
                    .btn-label { font-size: 10px; color: rgba(255,255,255,0.5); font-weight: 500; text-transform: uppercase; }

                    
                    .local-video-preview {
                        position: absolute;
                        bottom: 120px;
                        right: 30px;
                        width: 240px;
                        aspect-ratio: 16/9;
                        border-radius: 12px;
                        border: 2px solid rgba(255,255,255,0.2);
                        box-shadow: 0 10px 40px rgba(0,0,0,0.5);
                        object-fit: cover;
                        background: #000;
                        z-index: 50;
                        display: none;
                        transform: scaleX(-1); /* Mirror effect */
                    }
                    .remote-video-full {
                        position: absolute;
                        top: 0; left: 0; width: 100%; height: 100%;
                        object-fit: cover;
                        z-index: 1; /* Above overlay background, below stage content */
                        display: none;
                        background: #000;
                    }
                    .call-overlay.grid-mode .local-video-preview { display: none !important; }
                    .call-overlay.minimized .local-video-preview {
                        width: 100px; bottom: 60px; right: 10px;
                    }
                </style>
                
                <video id="remoteVideo" class="remote-video-full" autoplay playsinline></video>
                <video id="localVideo" class="local-video-preview" autoplay muted playsinline></video>
                
                ${groupId ? `<div class="participant-count" id="participantCount"><i class="fa-solid fa-users"></i> <span>1 participant</span></div>` : ''}
                
                <div class="call-stage" id="callStage">
                    <!-- Local Participant -->
                    <div class="participant-card" id="participant-me">
                        <video id="video-me" autoplay muted playsinline style="display:none; position: absolute; top:0; left:0; width:100%; height:100%; object-fit: cover; z-index:1; transform: scaleX(-1);"></video>
                        <img src="${myPic}" class="avatar-pulse" id="myVisualNode">
                        <div class="participant-label">You</div>
                    </div>
                    
                    ${!groupId ? `
                        <!-- Remote Participant (Standard 1:1) -->
                        <div class="participant-card" id="participant-${peerId}">
                            <img src="${resolvedPeerPic}" class="avatar-pulse" style="animation-delay: 1s;">
                            <div class="participant-label">${escapeHtml(peerName)}</div>
                            <video id="remoteVideo" autoplay playsinline style="display:none;"></video>
                        </div>
                    ` : ''}
                </div>
                
                <div class="call-controls">
                    <div class="call-btn-wrapper">
                        <button class="call-btn" id="muteBtn" title="Toggle Mute" onclick="toggleCallMute()">
                            <i class="fa-solid fa-microphone"></i>
                        </button>
                    </div>
                    <div class="call-btn-wrapper">
                        <button class="call-btn" id="cameraBtn" title="Toggle Camera" onclick="toggleCallCamera()">
                            <i class="fa-solid fa-video"></i>
                        </button>
                    </div>
                    <div class="call-btn-wrapper">
                        <button class="call-btn danger" title="End Call" onclick="endCall()">
                            <i class="fa-solid fa-phone-slash"></i>
                        </button>
                    </div>
                    <div class="call-btn-wrapper">
                        <button class="call-btn" title="Add Participants"><i class="fa-solid fa-user-plus"></i></button>
                    </div>
                    <div class="call-btn-wrapper">
                        <button class="call-btn minimize-btn" id="minimizeBtn" title="Minimize" onclick="toggleCallMinimize()">
                            <i class="fa-solid fa-compress"></i>
                        </button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(overlay);
            activeCallOverlay = overlay;
            makeDraggable(overlay);
            
            // Media will be initialized by startCall or acceptCall
            // initMedia(type); (REMOVED)
            
            if (status === 'Connected') {
                startCallTimer();
            }
        }

        async function startCall(type) {
            if(!activePeer) {
                console.warn('[CALL] No active peer selected to call.');
                return;
            }
            
            console.log(`%c[CALL-INIT] Target: ${activePeer.username} (${activePeer.id}) | Type: ${type} | isGroup: ${activePeer.isGroup}`, 'color: #007bff; font-weight: bold;');
            showCallUI(activePeer.username, activePeer.profile_picture, activePeer.id, type, type === 'video' ? 'Video Calling...' : 'Calling...', activePeer.isGroup ? activePeer.id : null);
            
            // Wait for hardware before notifying peer
            try {
                await initMedia(type);
                console.log('[CALL-INIT] Hardware initialized successfully.');
            } catch (e) {
                console.error('[CALL-INIT] Media Failure:', e);
                return; // Hardware error handled in initMedia UI
            }
            
            if (ws && ws.readyState === WebSocket.OPEN) {
                const signalPayload = {
                    caller_id: myUserId,
                    caller_name: myUsername,
                    caller_pic: myProfilePic,
                    call_type: type
                };

                if (activePeer.isGroup) {
                    activeGroupCallId = String(activePeer.id);
                    Object.assign(signalPayload, { 
                        type: 'GROUP_CALL_START',
                        group_id: String(activePeer.id),
                        group_name: activePeer.username
                    });
                } else {
                    Object.assign(signalPayload, { 
                        type: 'INCOMING_CALL',
                        callee_id: activePeer.id
                    });
                }
                
                console.log('[WS-SENT] Signal:', signalPayload.type, signalPayload);
                ws.send(JSON.stringify(signalPayload));
            } else {
                console.error('[CALL] WebSocket not open. State:', ws ? ws.readyState : 'null');
                const statusText = document.getElementById('callStatusText');
                if (statusText) {
                    statusText.textContent = 'Connection Error';
                    statusText.style.color = '#ef4444';
                }
                // Try to reconnect?
                if (!ws || ws.readyState === WebSocket.CLOSED) connectWS();
                setTimeout(() => { if (activeCallOverlay) endCall(); }, 3000);
            }
        }
        
        function toggleCallMute() {
            const btn = document.getElementById('muteBtn');
            if (btn && localStream) {
                const audioTrack = localStream.getAudioTracks()[0];
                if (audioTrack) {
                    audioTrack.enabled = !audioTrack.enabled;
                    btn.classList.toggle('muted', !audioTrack.enabled);
                    const icon = btn.querySelector('i');
                    icon.className = !audioTrack.enabled ? 'fa-solid fa-microphone-slash' : 'fa-solid fa-microphone';
                }
            }
        }
        
        async function toggleCallCamera() {
            const btn = document.getElementById('cameraBtn');
            if (!btn) return;
            
            // 1. If we don't have a local stream yet, initialize it
            if (!localStream) {
                await initMedia('video');
                return;
            }

            let videoTrack = localStream.getVideoTracks()[0];
            
            // 2. If we are in an audio call and want to TURN ON the camera for the first time
            if (!videoTrack) {
                console.log('[CALL] Upgrading to video...');
                try {
                    const newStream = await navigator.mediaDevices.getUserMedia({ video: true });
                    const newTrack = newStream.getVideoTracks()[0];
                    localStream.addTrack(newTrack);
                    videoTrack = newTrack;
                    
                    // Show local preview
                    const localVid = document.getElementById('localVideo');
                    const localMe  = document.getElementById('video-me');
                    [localVid, localMe].forEach(v => {
                        if (v) {
                            v.srcObject = localStream;
                            v.style.display = 'block';
                            v.style.opacity = '1';
                            v.play().catch(e => console.warn('[CALL] Play error:', e));
                        }
                    });
                    
                    const avatar = document.getElementById('myVisualNode');
                    if (avatar) avatar.style.display = 'none';
                    
                    // Renegotiate 1:1 if exists
                    if (peerConnection && currentCallPeerId) {
                        peerConnection.addTrack(newTrack, localStream);
                        const offer = await peerConnection.createOffer();
                        await peerConnection.setLocalDescription(offer);
                        ws.send(JSON.stringify({
                            type: 'CALL_SIGNAL', from_id: myUserId, to_id: currentCallPeerId,
                            signal: { offer: offer }
                        }));
                    }
                    
                    // Renegotiate Groups
                    if (activeGroupCallId && activeGroupCallId !== 'null') {
                        for (const [remoteId, conn] of Object.entries(groupConnections)) {
                            if (conn.pc) {
                                conn.pc.addTrack(newTrack, localStream);
                                const gOffer = await conn.pc.createOffer();
                                await conn.pc.setLocalDescription(gOffer);
                                ws.send(JSON.stringify({
                                    type: 'CALL_SIGNAL', from_id: myUserId, to_id: remoteId,
                                    signal: { offer: gOffer, isGroup: true, group_id: String(activeGroupCallId) }
                                }));
                            }
                        }
                    }
                    
                    btn.classList.remove('muted');
                    btn.querySelector('i').className = 'fa-solid fa-video';
                } catch (err) {
                    console.error('[CALL] Failed to upgrade to video:', err);
                }
                return;
            }

            // 3. Normal toggle (Enable/Disable existing track)
            videoTrack.enabled = !videoTrack.enabled;
            btn.classList.toggle('muted', !videoTrack.enabled);
            const icon = btn.querySelector('i');
            icon.className = !videoTrack.enabled ? 'fa-solid fa-video-slash' : 'fa-solid fa-video';
            
            const lVid = document.getElementById('localVideo');
            if (lVid) lVid.style.opacity = videoTrack.enabled ? '1' : '0';
            
            const lMe = document.getElementById('video-me');
            if (lMe) {
                lMe.style.display = videoTrack.enabled ? 'block' : 'none';
                const avatar = document.getElementById('myVisualNode');
                if (avatar) avatar.style.display = videoTrack.enabled ? 'none' : 'block';
            }
            
            // If in group call, others might need a signal or just enable/disable track is enough if already added
            // Actually WebRTC track.enabled only affects the bits sent, connection stays.
            // But we should ensure all group connections have this track if it was just added.
        }
        
        function toggleCallMinimize() {
            const overlay = document.getElementById('callOverlay');
            const btn = document.getElementById('minimizeBtn');
            if (overlay && btn) {
                overlay.classList.toggle('minimized');
                overlay.classList.toggle('fullscreen');
                const icon = btn.querySelector('i');
                icon.className = overlay.classList.contains('minimized') ? 'fa-solid fa-expand' : 'fa-solid fa-compress';
            }
        }
        
        function endCall() {
            if (callTimerInterval) clearInterval(callTimerInterval);
            
            // Stop media tracks
            if (localStream) {
                localStream.getTracks().forEach(track => track.stop());
                localStream = null;
            }
            
            // Close standard P2P connection
            if (peerConnection) {
                peerConnection.close();
                peerConnection = null;
            }

            // Close all group connections
            Object.values(groupConnections).forEach(conn => {
                if (conn.pc) conn.pc.close();
            });
            groupConnections = {};

            iceQueue = [];

            // Notify peers
            if (ws && ws.readyState === WebSocket.OPEN) {
                if (activeGroupCallId && activeGroupCallId !== 'null') {
                    ws.send(JSON.stringify({
                        type: 'GROUP_CALL_LEAVE',
                        group_id: String(activeGroupCallId),
                        user_id: myUserId
                    }));
                } else if (currentCallPeerId) {
                    ws.send(JSON.stringify({
                        type: 'CALL_ENDED',
                        from_id: myUserId,
                        other_id: currentCallPeerId
                    }));
                }
            }
            
            if (activeCallOverlay) {
                if (audioContext) {
                    audioContext.close();
                    audioContext = null;
                }
                activeCallOverlay.remove();
                activeCallOverlay = null;
            }
            currentCallPeerId = null;
            activeGroupCallId = null;
        }

        function updateParticipantCount(count) {
            const el = document.getElementById('participantCount');
            if (el) {
                const span = el.querySelector('span');
                if (span) span.textContent = `${count} ${count === 1 ? 'participant' : 'participants'}`;
            }
        }

        function startCallTimer() {
            if (callTimerInterval) clearInterval(callTimerInterval);
            callStartTime = Date.now();
            const statusText = document.getElementById('callStatusText');
            
            callTimerInterval = setInterval(() => {
                const elapsed = Math.floor((Date.now() - callStartTime) / 1000);
                const minutes = Math.floor(elapsed / 60);
                const seconds = elapsed % 60;
                if (statusText) {
                    statusText.textContent = `Connected • ${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
                }
            }, 1000);
        }
        
        function makeDraggable(el) {
            let isDragging = false;
            let offsetX, offsetY;
            
            el.addEventListener('mousedown', (e) => {
                if (!el.classList.contains('minimized')) return;
                if (e.target.tagName === 'BUTTON' || e.target.tagName === 'I') return;
                
                isDragging = true;
                offsetX = e.clientX - el.getBoundingClientRect().left;
                offsetY = e.clientY - el.getBoundingClientRect().top;
                el.style.cursor = 'grabbing';
            });
            
            document.addEventListener('mousemove', (e) => {
                if (!isDragging) return;
                let newX = e.clientX - offsetX;
                let newY = e.clientY - offsetY;
                newX = Math.max(0, Math.min(newX, window.innerWidth - el.offsetWidth));
                newY = Math.max(0, Math.min(newY, window.innerHeight - el.offsetHeight));
                el.style.right = 'auto'; el.style.bottom = 'auto';
                el.style.left = newX + 'px'; el.style.top = newY + 'px';
            });
            
            document.addEventListener('mouseup', () => { isDragging = false; if (el.classList.contains('minimized')) el.style.cursor = 'grab'; });
        }
        
        function blockContact() {
            if (!activePeer) return;
        }

        // Incoming Call UI
        let incomingCallAudio = null;
        let pendingCallerId = null;
        
        function showIncomingCall(callerId, callerName, callerPic, callType, groupId = null, groupName = null) {
            console.log(`[CALL] UI: Showing incoming ${callType} from ${callerName}. Group: ${groupName}`);
            pendingCallerId = callerId;
            const existing = document.getElementById('incomingCallOverlay');
            if (existing) existing.remove();
            
            if (incomingCallAudio) { incomingCallAudio.pause(); incomingCallAudio = null; }
            
            incomingCallAudio = new Audio('./floxwatch_call_V1.mp3');
            incomingCallAudio.loop = true;
            incomingCallAudio.play().catch(e => console.warn('Audio auto-play failed', e));
            
            const overlay = document.createElement('div');
            overlay.id = 'incomingCallOverlay';
            
            let contextText = callType === 'video' ? 'Video Calling...' : 'Calling...';
            if (groupName) {
                contextText = `Group ${callType === 'video' ? 'Video' : 'Voice'} Call • ${groupName}`;
            }

            // Using JSON.stringify for attributes to handle special characters safely
            const acceptArgs = [
                `'${String(callerId).replace(/'/g, "\\'")}'`,
                `'${String(callerName).replace(/'/g, "\\'")}'`,
                `'${String(callerPic).replace(/'/g, "\\'")}'`,
                `'${String(callType).replace(/'/g, "\\'")}'`,
                groupId ? `'${String(groupId).replace(/'/g, "\\'")}'` : 'null',
                groupName ? `'${String(groupName).replace(/'/g, "\\'")}'` : 'null'
            ].join(', ');

            overlay.innerHTML = `
                <style>
                    #incomingCallOverlay {
                        position: fixed; top: 0; left: 0; right: 0; bottom: 0;
                        background: rgba(10,11,15,0.95); display: flex; flex-direction: column;
                        align-items: center; justify-content: center; z-index: 10001;
                        backdrop-filter: blur(20px); animation: fadeIn 0.4s ease;
                    }
                    .caller-avatar {
                        width: 140px; height: 140px; border-radius: 50%;
                        border: 4px solid var(--azure-blue);
                        animation: pulse-ring-incoming 2s infinite; margin-bottom: 30px;
                        object-fit: cover;
                    }
                    @keyframes pulse-ring-incoming { 0% { box-shadow: 0 0 0 0 rgba(0,123,255,0.6); } 70% { box-shadow: 0 0 0 30px rgba(0,123,255,0); } 100% { box-shadow: 0 0 0 0 rgba(0,123,255,0); } }
                    .caller-name { font-size: 32px; font-weight: 800; color: white; margin-bottom: 10px; }
                    .call-type { font-size: 18px; color: rgba(255,255,255,0.5); margin-bottom: 60px; font-weight: 500; letter-spacing: 1px; }
                    .call-actions { display: flex; gap: 60px; }
                    .call-btn-act { width: 76px; height: 76px; border-radius: 50%; border: none; cursor: pointer; font-size: 28px; display: flex; align-items: center; justify-content: center; transition: all 0.3s; }
                    .call-btn-act:hover { transform: scale(1.1); }
                    .accept-btn { background: #22c55e; color: white; box-shadow: 0 10px 30px rgba(34, 197, 94, 0.4); }
                    .decline-btn { background: #ef4444; color: white; box-shadow: 0 10px 30px rgba(239, 68, 68, 0.4); }
                </style>
                <img src="${resolveProfilePic(callerPic)}" class="caller-avatar" onerror="this.src='https://www.gravatar.com/avatar/00?d=mp'">
                <div class="caller-name">${escapeHtml(callerName)}</div>
                <div class="call-type">${contextText}</div>
                <div class="call-actions">
                    <button class="call-btn-act decline-btn" onclick="declineCall()"><i class="fa-solid fa-phone-slash"></i></button>
                    <button class="call-btn-act accept-btn" onclick="acceptCall(${acceptArgs})"><i class="fa-solid fa-phone"></i></button>
                </div>
            `;
            document.body.appendChild(overlay);
        }
        
        function declineCall() {
            if (incomingCallAudio) { incomingCallAudio.pause(); incomingCallAudio = null; }
            const overlay = document.getElementById('incomingCallOverlay');
            if (overlay) overlay.remove();
            
            if (ws && ws.readyState === WebSocket.OPEN && pendingCallerId) {
                ws.send(JSON.stringify({ type: 'CALL_ENDED', from_id: myUserId, other_id: pendingCallerId }));
            }
            pendingCallerId = null;
        }
        
        async function acceptCall(callerId, callerName, callerPic, callType, groupId = null, groupName = null) {
            if (incomingCallAudio) { incomingCallAudio.pause(); incomingCallAudio = null; }
            const overlay = document.getElementById('incomingCallOverlay');
            if (overlay) overlay.remove();
            
            showCallUI(groupName || callerName, callerPic, callerId, callType, 'Connected', groupId);
            
            // IMPORTANT: Wait for hardware BEFORE sending acceptance
            await initMedia(callType);
            
            if (groupId) {
                 activeGroupCallId = groupId;
                 // Notify group we joined
                 if (ws && ws.readyState === WebSocket.OPEN) {
                     ws.send(JSON.stringify({
                         type: 'GROUP_CALL_JOIN',
                         group_id: groupId,
                         user_id: myUserId,
                         user_name: myUsername,
                         user_pic: myProfilePic
                     }));
                 }
            } else {
                 // Send signal back to caller (P2P)
                 activeGroupCallId = null; // Ensure 1:1 mode
                 if (ws && ws.readyState === WebSocket.OPEN) {
                     ws.send(JSON.stringify({
                         type: 'CALL_ACCEPTED',
                         caller_id: callerId,
                         callee_id: myUserId,
                         callee_name: myUsername,
                         callee_pic: myProfilePic
                     }));
                 }
            }
        }

        // WatchTogether Party Tracking
        let wtActiveParties = new Map(); // groupId -> { session_id, admin_name }

        function handleWatchTogetherClick(groupId) {
            // Check if there's an active party
            if (wtActiveParties.has(String(groupId))) {
                const party = wtActiveParties.get(String(groupId));
                // Join existing party
                if (typeof joinWatchTogether === 'function') {
                    joinWatchTogether(groupId, party.session_id);
                }
            } else {
                // Start new party
                if (typeof startWatchTogether === 'function') {
                    startWatchTogether(groupId);
                }
            }
        }

        function updateWatchTogetherButton(groupId) {
            const btnText = document.getElementById('wtBtnText');
            const btn = document.getElementById('wtStartBtn');
            if (!btnText || !btn) return;

            if (wtActiveParties.has(String(groupId))) {
                const party = wtActiveParties.get(String(groupId));
                btnText.textContent = 'Join Party';
                btn.classList.add('wt-active');
                btn.title = party.admin_name + ' started a watch party - Click to join';
            } else {
                btnText.textContent = 'Watch Together';
                btn.classList.remove('wt-active');
                btn.title = 'Watch Together';
            }
        }

        // Handle party status messages from WS
        function handleWatchTogetherPartyMessage(data) {
            if (data.type === 'WATCHTOGETHER_PARTY_STARTED') {
                wtActiveParties.set(String(data.group_id), {
                    session_id: data.session_id,
                    admin_name: data.admin_name,
                    admin_id: data.admin_id
                });
                // Update button if we're in that group chat
                if (activePeer && activePeer.isGroup && String(activePeer.id) === String(data.group_id)) {
                    updateWatchTogetherButton(data.group_id);
                    // Show party started message in chat
                    showPartyNotification(data.admin_name + ' started a watch party!', data.group_id);
                }
            } else if (data.type === 'WATCHTOGETHER_PARTY_ENDED') {
                wtActiveParties.delete(String(data.group_id));
                // Update button if we're in that group chat  
                if (activePeer && activePeer.isGroup && String(activePeer.id) === String(data.group_id)) {
                    updateWatchTogetherButton(data.group_id);
                }
            }
        }

        function showPartyNotification(message, groupId) {
            const msgList = document.getElementById('msgList');
            if (!msgList) return;
            
            const notification = document.createElement('div');
            notification.className = 'wt-party-notification';
            notification.innerHTML = `
                <div class="wt-party-badge">
                    <i class="fa-solid fa-tv"></i>
                    <span>${escapeHtml(message)}</span>
                    <button onclick="handleWatchTogetherClick(${groupId})">Join</button>
                </div>
            `;
            msgList.appendChild(notification);
            msgList.scrollTop = msgList.scrollHeight;
        }

        connectWS();
        loadConversations();
    }