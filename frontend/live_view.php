<?php
session_start();
$stream_id = $_GET['id'] ?? 'stream_unknown';
$username = $_SESSION['username'] ?? 'Guest_' . rand(1000, 9999);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loop Live - Watching Stream</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="home.css?v=1"/>
    <link rel="stylesheet" href="layout.css"/>
    <style>
        .starfield-canvas {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            z-index: -1; background: #020205;
        }
        .watch-layout {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 24px;
            padding: 24px;
            height: calc(100vh - 100px);
            max-width: 1600px;
            margin: 0 auto;
        }
        .player-container {
            background: rgba(0, 0, 0, 0.4);
            border-radius: 20px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
        }
        .video-viewport {
            flex: 1;
            background: #000;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .stream-status-overlay {
            position: absolute;
            top: 20px;
            left: 20px;
            display: flex;
            gap: 10px;
            z-index: 10;
        }
        .live-tag {
            background: #ff4444;
            color: #fff;
            padding: 4px 12px;
            border-radius: 4px;
            font-weight: 800;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .viewer-count {
            background: rgba(0, 0, 0, 0.6);
            color: #fff;
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 12px;
            backdrop-filter: blur(10px);
        }
        .chat-container {
            background: rgba(255, 255, 255, 0.03);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            flex-direction: column;
            backdrop-filter: blur(30px);
            overflow: hidden;
        }
        .chat-header {
            padding: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 14px;
        }
        .chat-messages {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .chat-msg {
            font-size: 14px;
            line-height: 1.4;
            animation: fadeIn 0.3s ease;
        }
        @keyframes fadeIn { from { opacity: 0; transform: translateX(10px); } to { opacity: 1; transform: translateX(0); } }
        @keyframes blink { 0% { opacity: 1; } 50% { opacity: 0.3; } 100% { opacity: 1; } }
        .msg-user { color: var(--accent-color); font-weight: 700; margin-right: 8px; }
        .msg-time { font-size: 10px; opacity: 0.4; margin-left:8px; }
        .chat-input-area {
            padding: 20px;
            background: rgba(0, 0, 0, 0.2);
            border-top: 1px solid rgba(255, 255, 255, 0.05);
        }
        .chat-input-wrapper {
            display: flex;
            gap: 10px;
            background: rgba(255, 255, 255, 0.05);
            padding: 10px;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .chat-input {
            flex: 1;
            background: transparent;
            border: none;
            color: #fff;
            outline: none;
        }
        .chat-send-btn {
            background: var(--accent-color);
            border: none;
            color: #fff;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <canvas id="starfield" class="starfield-canvas"></canvas>
    <?php include 'header.php'; ?>

    <div class="app-layout">
        <main class="main-content">
            <div class="watch-layout">
                <div class="player-container">
                    <div class="video-viewport" id="streamViewport">
                        <div class="stream-status-overlay">
                            <div class="live-tag" id="liveTag" style="display:none;"><i class="fa-solid fa-circle"></i> LIVE</div>
                            <div class="viewer-count" id="viewerCountDisplay"><i class="fa-solid fa-eye"></i> 0</div>
                        </div>
                        <div id="signalMessage" style="color: rgba(255,255,255,0.4); text-align:center;">
                            <i class="fa-solid fa-satellite-dish" style="font-size: 48px; display:block; margin-bottom:15px;"></i>
                            Awaiting Stream Signal...
                        </div>
                    </div>
                    <div style="padding: 24px;">
                        <h1 style="font-size: 24px; margin-bottom: 8px;">Demo Stream</h1>
                        <p style="opacity: 0.6;">Interactive real-time demonstration</p>
                    </div>
                </div>

                <div class="chat-container">
                    <div class="chat-header">Live Chat</div>
                    <div class="chat-messages" id="chatMessages"></div>
                    <div class="chat-input-area">
                        <div class="chat-input-wrapper">
                            <input type="text" class="chat-input" id="chatInput" placeholder="Say something...">
                            <button class="chat-send-btn" id="sendBtn"><i class="fa-solid fa-paper-plane"></i></button>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        const streamId = '<?php echo $stream_id; ?>';
        const username = '<?php echo $username; ?>';
        let ws;

        // Viewer Logic (MJPEG + Audio)
        let audioContext;
        let nextStartTime = 0;
        const initialDelay = 0.5; // Seconds to buffer

        function initAudio() {
            if (!audioContext) {
                audioContext = new (window.AudioContext || window.webkitAudioContext)();
                nextStartTime = audioContext.currentTime + initialDelay;
            } else if(audioContext.state === 'suspended') {
                 audioContext.resume();
            }
        }

        function playAudioFrame(base64Audio) {
             if (!audioContext) return;
             
             try {
                const binaryString = atob(base64Audio);
                const len = binaryString.length;
                const bytes = new Uint8Array(len);
                for (let i = 0; i < len; i++) {
                    bytes[i] = binaryString.charCodeAt(i);
                }
                const intData = new Int16Array(bytes.buffer);
                const floatData = new Float32Array(intData.length);
                for (let i = 0; i < intData.length; i++) {
                    floatData[i] = intData[i] / 0x7FFF;
                }

                // Check for audio signal (Voice indicator)
                let hasSignal = false;
                for(let i=0; i<floatData.length; i+=100) {
                    if(Math.abs(floatData[i]) > 0.01) { hasSignal = true; break; }
                }
                if(hasSignal) {
                    const indicator = document.getElementById('voiceIndicator');
                    if(indicator) {
                        indicator.style.opacity = '1';
                        clearTimeout(indicator.fadeTimer);
                        indicator.fadeTimer = setTimeout(() => indicator.style.opacity = '0.3', 500);
                    }
                }

                const buffer = audioContext.createBuffer(1, floatData.length, audioContext.sampleRate);
                buffer.copyToChannel(floatData, 0);

                const source = audioContext.createBufferSource();
                source.buffer = buffer;
                source.connect(audioContext.destination);

                const now = audioContext.currentTime;
                if (nextStartTime < now - 0.1) {
                    nextStartTime = now;
                }
                source.start(nextStartTime);
                nextStartTime += buffer.duration;
             } catch(e) {
                 console.error("Audio Decode Error", e);
             }
        }

        // Auto-Enable Audio on click
        document.addEventListener('click', () => initAudio(), { once: true });
        document.addEventListener('touchstart', () => initAudio(), { once: true });

        function connect() {
            console.log('Attempting to connect to Live Server...');
            let serverIp = window.FLOX_CTX?.wsHost || window.location.hostname;
            if (serverIp === 'localhost') serverIp = '127.0.0.1';
            ws = new WebSocket(`ws://${serverIp}:8080`);

            ws.onopen = () => {
                console.log('Connected to Live Server!');
                ws.send(JSON.stringify({ type: 'JOIN_STREAM', streamId: streamId }));
            };

            ws.onmessage = (event) => {
                const data = JSON.parse(event.data);
                
                switch(data.type) {
                    case 'STREAM_STATUS':
                        const tag = document.getElementById('liveTag');
                        const viewport = document.getElementById('streamViewport');
                        const msg = document.getElementById('signalMessage');
                        
                        console.log("Stream Status Update:", data.status, "for UID:", streamId);

                        if (data.status === 'live') {
                            if (tag) tag.style.display = 'flex';
                            if (msg) msg.style.display = 'none';
                            
                            // Remove offline overlay if it exists
                            const offlineOverlay = document.getElementById('offlineOverlay');
                            if (offlineOverlay) offlineOverlay.remove();
                            
                            // Surgically add video if missing
                            if(!document.getElementById('remoteVideo')) {
                                viewport.style.background = '#000';
                                
                                const img = document.createElement('img');
                                img.id = 'remoteVideo';
                                img.style.cssText = 'width:100%; height:100%; object-fit:contain; position:absolute; top:0; left:0; z-index:1;';
                                viewport.appendChild(img);
                                
                                const info = document.createElement('div');
                                info.id = 'streamOverlay';
                                info.style.cssText = 'position:absolute; bottom:20px; left:20px; color:#00ff7f; background:rgba(0,0,0,0.6); padding:10px; border-radius:8px; font-family:monospace; font-size:11px; backdrop-filter:blur(10px); z-index:10; display:flex; flex-direction:column; gap:8px;';
                                info.innerHTML = `
                                    <div>LIVE BROADCAST<br>ID: ${streamId}</div>
                                    <div id="voiceIndicator" style="display:flex; align-items:center; gap:8px; opacity:0.3; transition:opacity 0.1s;">
                                        <div style="width:8px; height:8px; background:#00ff7f; border-radius:50%; box-shadow:0 0 10px #00ff7f;"></div>
                                        VOICE SIGNAL
                                    </div>
                                `;
                                viewport.appendChild(info);
                            }
                        } else {
                            if (tag) tag.style.display = 'none';
                            if (msg) msg.style.display = 'none'; // Hide the "Awaiting" message
                            
                            // Show "Livestream Ended" UI
                            if (!document.getElementById('offlineOverlay')) {
                                const offlineUI = document.createElement('div');
                                offlineUI.id = 'offlineOverlay';
                                offlineUI.style.cssText = 'position:absolute; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center; background:rgba(0,0,0,0.9); z-index:20; backdrop-filter:blur(10px); text-align:center; padding:20px;';
                                offlineUI.innerHTML = `
                                    <div style="width:80px; height:80px; background:rgba(255,255,255,0.05); border-radius:50%; display:flex; align-items:center; justify-content:center; margin-bottom:20px;">
                                        <i class="fa-solid fa-plug-circle-xmark" style="font-size:32px; color:rgba(255,255,255,0.4);"></i>
                                    </div>
                                    <h2 style="font-size:24px; margin-bottom:10px; color:#fff;">Livestream Ended</h2>
                                    <p style="opacity:0.6; margin-bottom:30px; font-size:14px;">Check out other streams from other creators</p>
                                    <a href="home.php" style="background:var(--accent-color); color:#fff; text-decoration:none; padding:12px 30px; border-radius:100px; font-weight:700; transition:all 0.3s; display:flex; align-items:center; gap:10px;">
                                        <i class="fa-solid fa-compass"></i> Discover More
                                    </a>
                                `;
                                viewport.appendChild(offlineUI);
                            }

                            const vid = document.getElementById('remoteVideo');
                            if (vid) vid.remove();
                            const ov = document.getElementById('streamOverlay');
                            if (ov) ov.remove();
                        }
                        break;
                    
                    case 'VIDEO_FRAME':
                        const vid = document.getElementById('remoteVideo');
                        if (vid) {
                            vid.src = data.frame;
                        }
                        break;

                    case 'AUDIO_FRAME':
                        playAudioFrame(data.audio);
                        break;

                    case 'VIEWER_COUNT_UPDATE':
                        document.getElementById('viewerCountDisplay').innerHTML = `<i class="fa-solid fa-eye"></i> ${data.count}`;
                        break;

                    case 'CHAT_HISTORY':
                        data.messages.forEach(msg => appendMessage(msg));
                        break;

                    case 'NEW_CHAT_MESSAGE':
                        appendMessage(data);
                        break;
                }
            };

            ws.onclose = () => setTimeout(connect, 3000);
        }

        function appendMessage(data) {
            const container = document.getElementById('chatMessages');
            const div = document.createElement('div');
            div.className = 'chat-msg';
            div.innerHTML = `<span class="msg-user">${data.user}</span>${data.text}<span class="msg-time">${data.timestamp}</span>`;
            container.appendChild(div);
            container.scrollTop = container.scrollHeight;
        }

        document.getElementById('sendBtn').onclick = sendMessage;
        document.getElementById('chatInput').onkeypress = (e) => { if (e.key === 'Enter') sendMessage(); };

        function sendMessage() {
            const input = document.getElementById('chatInput');
            const text = input.value.trim();
            if (text && ws && ws.readyState === WebSocket.OPEN) {
                ws.send(JSON.stringify({
                    type: 'CHAT_MESSAGE',
                    streamId: streamId,
                    user: username,
                    text: text
                }));
                input.value = '';
            }
        }

        connect();

        // Starfield logic
        const canvas = document.getElementById('starfield');
        const ctx = canvas.getContext('2d');
        let stars = [];
        function initStars() {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
            stars = [];
            for(let i=0; i<300; i++) {
                stars.push({ x: Math.random() * canvas.width, y: Math.random() * canvas.height, size: Math.random() * 2, speed: Math.random() * 0.1 });
            }
        }
        function animate() {
            ctx.clearRect(0,0,canvas.width,canvas.height);
            ctx.fillStyle = 'rgba(255, 255, 255, 0.4)';
            stars.forEach(s => {
                ctx.beginPath(); ctx.arc(s.x, s.y, s.size, 0, Math.PI*2); ctx.fill();
                s.y += s.speed; if(s.y > canvas.height) s.y = 0;
            });
            requestAnimationFrame(animate);
        }
        window.addEventListener('resize', initStars);
        initStars();
        animate();
    </script>
</body>
</html>
