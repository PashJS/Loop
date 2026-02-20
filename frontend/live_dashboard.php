<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: loginb.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loop Live - Command Center</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="home.css?v=1"/>
    <link rel="stylesheet" href="layout.css"/>
    <link rel="stylesheet" href="upload.css?v=2"/>
    <style>
        .starfield-canvas {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            background: #020205;
        }
        .live-layout {
            display: grid;
            grid-template-columns: 1fr 340px;
            gap: 24px;
            padding: 24px;
            max-width: 1400px;
            margin: 0 auto;
        }
        .live-preview-card {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(30px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 40px 100px rgba(0,0,0,0.5);
        }
        .preview-placeholder {
            width: 100%;
            height: 100%;
            position: absolute;
            top: 0;
            left: 0;
            background: #000;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: rgba(255, 255, 255, 0.4);
            gap: 16px;
            z-index: 5;
            padding: 20px;
            transition: background 0.3s;
        }
        .preview-placeholder i {
            font-size: 48px;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(1); opacity: 0.4; }
            50% { transform: scale(1.1); opacity: 0.8; }
            100% { transform: scale(1); opacity: 0.4; }
        }
        .stream-info-grid {
            margin-top: 24px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        .info-panel {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 16px;
            padding: 20px;
            border: 1px solid rgba(255, 255, 255, 0.08);
        }
        .info-panel h3 {
            font-size: 14px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 15px;
            color: var(--accent-color);
        }
        .stream-key-wrapper {
            display: flex;
            gap: 10px;
        }
        .stream-key-input {
            flex: 1;
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #fff;
            padding: 8px 12px;
            border-radius: 8px;
            font-family: monospace;
            font-size: 14px;
        }
        .copy-btn {
            background: var(--accent-color);
            border: none;
            color: #fff;
            padding: 0 12px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .copy-btn:hover { background: var(--accent-hover); }
        .live-settings-sidebar {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }
        .sidebar-panel {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 20px;
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 12px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 100px;
            font-size: 12px;
            font-weight: 700;
            color: #ff4444;
        }
        .status-dot {
            width: 8px;
            height: 8px;
            background: #ff4444;
            border-radius: 50%;
            box-shadow: 0 0 10px #ff4444;
        }
    </style>
</head>
<body>
    <canvas id="starfield" class="starfield-canvas"></canvas>
    <?php include 'header.php'; ?>

    <div class="app-layout">
        <?php include 'sidebar.php'; ?>
        
        <main class="main-content">
            <div class="live-layout">
                <div class="live-main">
                    <div class="live-preview-card">
                        <div class="preview-container" style="position:relative; width:100%; aspect-ratio:16/9; background:#000; border-radius:12px; overflow:hidden;">
                            <video id="localVideo" autoplay muted playsinline style="width:100%; height:100%; object-fit:cover; display:none;"></video>
                            <div class="preview-placeholder" id="previewPlaceholder">
                                <i class="fa-solid fa-tower-broadcast"></i>
                                <span>Awaiting camera access...</span>
                            </div>
                            <div id="liveIndicator" style="display:none; position:absolute; top:20px; right:20px; background:#ff4444; color:#fff; padding:4px 12px; border-radius:4px; font-weight:800; font-size:12px; z-index:10;">
                                <i class="fa-solid fa-circle" style="font-size:8px; margin-right:5px;"></i> LIVE
                            </div>
                        </div>
                        
                        <div class="stream-info-grid">
                            <div class="info-panel">
                                <h3>Stream Metadata</h3>
                                <div class="form-group" style="margin-bottom: 12px;">
                                    <input type="text" placeholder="Stream Title" value="Live with Loop" style="width:100%; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); color:#fff; padding:10px; border-radius:8px;">
                                </div>
                                <div class="form-group">
                                    <textarea placeholder="Stream Description" style="width:100%; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); color:#fff; padding:10px; border-radius:8px; height:80px;"></textarea>
                                </div>
                            </div>
                            <div class="info-panel">
                                <h3>Connection Settings</h3>
                                <div class="form-group" style="margin-bottom: 15px;">
                                    <label style="font-size:11px; opacity:0.6; margin-bottom:5px; display:block;">Share Link</label>
                                    <div class="stream-key-wrapper">
                                        <input type="text" readonly value="" class="stream-key-input" id="shareLinkInput">
                                        <button class="copy-btn" onclick="copyShareLink()"><i class="fa-solid fa-share-nodes"></i></button>
                                    </div>
                                </div>
                                <script>
                                    // Set dynamic share link on load
                                    document.addEventListener('DOMContentLoaded', () => {
                                        const shareInput = document.getElementById('shareLinkInput');
                                        if (shareInput) {
                                            const streamId = 'stream_' + <?php echo $_SESSION['user_id']; ?>;
                                            // Using window.location.origin instead of hardcoded localhost:8888
                                            shareInput.value = window.location.origin + '/FloxWatch/frontend/live_view.php?id=' + streamId;
                                        }
                                    });

                                    function copyShareLink() {
                                        const input = document.getElementById('shareLinkInput');
                                        input.select();
                                        document.execCommand('copy');
                                    }
                                </script>
                                <div class="form-group" style="margin-bottom: 15px;">
                                    <label style="font-size:11px; opacity:0.6; margin-bottom:5px; display:block;">RTMP URL</label>
                                    <div class="stream-key-wrapper">
                                        <input type="text" readonly value="rtmp://live.floxwatch.com/app" class="stream-key-input">
                                        <button class="copy-btn"><i class="fa-solid fa-copy"></i></button>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label style="font-size:11px; opacity:0.6; margin-bottom:5px; display:block;">Stream Key</label>
                                    <div class="stream-key-wrapper">
                                        <input type="password" readonly value="fx_live_sh291kS910" class="stream-key-input">
                                        <button class="copy-btn"><i class="fa-solid fa-eye"></i></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="live-settings-sidebar">
                    <div class="sidebar-panel">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <div class="status-badge">
                                <div class="status-dot"></div>
                                OFFLINE
                            </div>
                            <div id="serverStatus" style="font-size:10px; opacity:0.6; display:flex; align-items:center; gap:5px;">
                                <div style="width:6px; height:6px; background:#666; border-radius:50%;"></div>
                                Server: Disconnected
                            </div>
                        </div>
                        <h2 style="margin-top:15px; font-size:18px;">Stream Controls</h2>
                        <div class="stream-info" style="margin-top:20px; padding:15px; background:rgba(255,255,255,0.05); border-radius:12px; border:1px solid rgba(255,255,255,0.1);">
                            <div style="font-size:11px; opacity:0.6; margin-bottom:10px; text-transform:uppercase; letter-spacing:1px;">Public Watch Link</div>
                            <div style="display:flex; gap:10px;">
                                <input type="text" id="watchLink" readonly value="" style="flex:1; background:rgba(0,0,0,0.3); border:1px solid rgba(255,255,255,0.1); border-radius:6px; color:#00ff7f; font-family:monospace; font-size:11px; padding:8px;">
                                <button onclick="copyWatchLink()" style="background:var(--accent-color); border:none; color:#fff; border-radius:6px; padding:0 12px; cursor:pointer;"><i class="fa-solid fa-copy"></i></button>
                            </div>
                        </div>

                        <button class="btn-submit" id="goLiveBtn" style="width:100%; margin-top:20px; justify-content:center; opacity:0.5; pointer-events:none;">
                            <i class="fa-solid fa-play"></i>
                            <span>Go Live Now</span>
                        </button>
                    </div>

                    <div class="sidebar-panel">
                        <h3 style="font-size:12px; opacity:0.4; text-transform:uppercase; letter-spacing:1px;">Real-time Stats</h3>
                        <div style="margin-top:15px; display:flex; flex-direction:column; gap:12px;">
                            <div style="display:flex; justify-content:space-between; font-size:13px;">
                                <span style="opacity:0.6;">Viewers</span>
                                <span style="font-weight:700;">0</span>
                            </div>
                            <div style="display:flex; justify-content:space-between; font-size:13px;">
                                <span style="opacity:0.6;">Bitrate</span>
                                <span style="font-weight:700;">0 kbps</span>
                            </div>
                            <div style="display:flex; justify-content:space-between; font-size:13px;">
                                <span style="opacity:0.6;">Uptime</span>
                                <span style="font-weight:700;">00:00:00</span>
                            </div>
                            <div style="margin-top:10px;">
                                <div style="display:flex; justify-content:space-between; font-size:11px; margin-bottom:5px;">
                                    <span style="opacity:0.6;">Mic Input</span>
                                </div>
                                <div style="width:100%; height:4px; background:#333; border-radius:10px; overflow:hidden;">
                                    <div id="micIndicator" style="width:0%; height:100%; background:#666; transition: width 0.1s;"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <?php include 'mobile_footer.php'; ?>

    <script src="theme.js"></script>
    <script>
        // Camera Logic
        const localVideo = document.getElementById('localVideo');
        const previewPlaceholder = document.getElementById('previewPlaceholder');
        let localStream;

        // Debug Logger
        function logDebug(msg, color = '#fff') {
            const container = document.getElementById('previewPlaceholder');
            const logLine = document.createElement('div');
            logLine.style.cssText = `font-size:10px; color:${color}; margin-top:5px; text-transform:none; font-family:monospace;`;
            logLine.textContent = `> ${msg}`;
            container.appendChild(logLine);
            console.log(`[LIVE_DEBUG] ${msg}`);
        }

        // Broadcast Engine (MJPEG + Audio)
        let broadcastInterval;
        const captureCanvas = document.createElement('canvas');
        const captureCtx = captureCanvas.getContext('2d');
        let audioContext;
        let processor;

        async function startCamera() {
            logDebug("Initializing Broadcast Engine...");
            if (localStream) localStream.getTracks().forEach(t => t.stop());

            try {
                const stream = await navigator.mediaDevices.getUserMedia({ 
                    video: { width: 640, height: 360 }, 
                    audio: true 
                });
                
                logDebug("Cam/Mic Active!", "#00ff7f");
                localStream = stream;
                localVideo.srcObject = stream;
                localVideo.play();
                localVideo.style.display = 'block';
                previewPlaceholder.style.display = 'none';

                // Audio Setup
                audioContext = new (window.AudioContext || window.webkitAudioContext)();
                
                // Mute local monitoring (Dashboard) but KEEP the relay data
                const relayGain = audioContext.createGain();
                relayGain.gain.value = 1.5; // Slight boost for the stream

                const monitorGain = audioContext.createGain(); 
                monitorGain.gain.value = 0; // DASHBOARD MUTE (Prevent Feedack)

                const source = audioContext.createMediaStreamSource(stream);
                const analyser = audioContext.createAnalyser();
                analyser.fftSize = 256;
                
                processor = audioContext.createScriptProcessor(4096, 1, 1);

                source.connect(analyser); // For Visualizer
                source.connect(relayGain); // For Stream
                relayGain.connect(processor);
                processor.connect(monitorGain); // Local Monitoring Path
                monitorGain.connect(audioContext.destination);

                // Mic Visualizer... (kept as is)
                const dataArray = new Uint8Array(analyser.frequencyBinCount);
                const updateMic = () => {
                    if (audioContext.state === 'suspended') audioContext.resume();
                    analyser.getByteFrequencyData(dataArray);
                    let sum = 0;
                    for(let i=0; i<dataArray.length; i++) sum += dataArray[i];
                    const avg = sum / dataArray.length;
                    const val = Math.min(100, avg * 2.5);
                    const ind = document.getElementById('micIndicator');
                    if(ind) {
                        ind.style.width = val + '%';
                        ind.style.background = val > 5 ? '#00ff7f' : '#333';
                    }
                    requestAnimationFrame(updateMic);
                };
                updateMic();

                // Audio Transmission
                let audioPacketCount = 0;
                processor.onaudioprocess = (e) => {
                    if (ws && ws.readyState === WebSocket.OPEN && goLiveBtn.classList.contains('is-live')) {
                        const inputData = e.inputBuffer.getChannelData(0);
                        const intData = new Int16Array(inputData.length);
                        for (let i = 0; i < inputData.length; i++) {
                            intData[i] = Math.max(-1, Math.min(1, inputData[i])) * 0x7FFF;
                        }
                        
                        let binary = '';
                        const bytes = new Uint8Array(intData.buffer);
                        for (let i = 0; i < bytes.byteLength; i++) {
                            binary += String.fromCharCode(bytes[i]);
                        }
                        
                        ws.send(JSON.stringify({
                            type: 'AUDIO_FRAME',
                            streamId: streamId,
                            audio: btoa(binary)
                        }));

                        audioPacketCount++;
                        if (audioPacketCount % 100 === 0) {
                            console.log(`[AUDIO_DEBUG] Sent 100 packets. Total: ${audioPacketCount}`);
                        }
                    }
                };

            } catch (err) {
                logDebug(`AUDIO_ERROR: ${err.message}`, "#ff4444");
                // Friendly Helper for LAN Users
                if (!navigator.mediaDevices && window.location.hostname !== 'localhost' && window.location.protocol === 'http:') {
                }
            }
        }

        // Add a visible trigger button if auto-init fails
        document.addEventListener('DOMContentLoaded', () => {
            const btn = document.createElement('button');
            btn.innerHTML = '<i class="fa-solid fa-camera"></i> ACTIVATE CAMERA ENGINE';
            btn.style.cssText = 'position:absolute; z-index:100; padding:12px 24px; background:var(--accent-color); color:#fff; border:none; border-radius:10px; font-weight:700; cursor:pointer; box-shadow:0 10px 20px rgba(0,0,0,0.3);';
            btn.onclick = () => {
                btn.style.display = 'none';
                startCamera();
            };
            document.getElementById('previewPlaceholder').appendChild(btn);
            
            // Try auto-start anyway
            setTimeout(startCamera, 1000);
        });

        // WebSocket Logic
        let ws;
        const streamId = 'stream_' + <?php echo json_encode($_SESSION['user_id']); ?>;
        const statusBadge = document.querySelector('.status-badge');
        const goLiveBtn = document.getElementById('goLiveBtn');
        const serverStatus = document.getElementById('serverStatus');
        const viewerCountEl = document.querySelector('.sidebar-panel:last-child div span:last-child');

        function updateServerStatus(connected) {
            if (connected) {
                serverStatus.innerHTML = '<div style="width:6px; height:6px; background:#00ff7f; border-radius:50%; box-shadow:0 0 5px #00ff7f;"></div> Server: Connected';
                goLiveBtn.style.opacity = '1';
                goLiveBtn.style.pointerEvents = 'auto';
            } else {
                serverStatus.innerHTML = '<div style="width:6px; height:6px; background:#666; border-radius:50%;"></div> Server: Disconnected';
                goLiveBtn.style.opacity = '0.5';
                goLiveBtn.style.pointerEvents = 'none';
            }
        }

        function connectWS() {
            // Generate watch link
            const url = window.location.origin + '/FloxWatch/frontend/live_view.php?id=' + streamId;
            const wlEl = document.getElementById('watchLink');
            if (wlEl) wlEl.value = url;

            // Priority: 1. Configured global host, 2. current hostname, 3. 127.0.0.1 fallback
            let serverIp = window.FLOX_CTX?.wsHost || window.location.hostname;
            if (serverIp === 'localhost') serverIp = '127.0.0.1'; // Force IPv4 to avoid ::1 issues
            
            ws = new WebSocket(`ws://${serverIp}:8080`);
            ws.onopen = () => updateServerStatus(true);
            ws.onclose = () => { updateServerStatus(false); setTimeout(connectWS, 3000); };
            ws.onmessage = (event) => {
                const data = JSON.parse(event.data);
                if (data.type === 'VIEWER_COUNT_UPDATE') {
                    if (viewerCountEl) viewerCountEl.textContent = data.count;
                }
            };
        }

        goLiveBtn.addEventListener('click', () => {
            if (ws && ws.readyState === WebSocket.OPEN) {
                if (!goLiveBtn.classList.contains('is-live')) {
                    ws.send(JSON.stringify({ type: 'START_STREAM', streamId: streamId }));
                    goLiveBtn.classList.add('is-live');
                    goLiveBtn.innerHTML = '<i class="fa-solid fa-stop"></i><span>Stop Stream</span>';
                    goLiveBtn.style.background = '#ff4444';
                    statusBadge.style.color = '#00ff7f';
                    statusBadge.innerHTML = '<div class="status-dot" style="background:#00ff7f; box-shadow: 0 0 10px #00ff7f;"></div> LIVE';
                    document.getElementById('liveIndicator').style.display = 'block';
                    
                    // Start Video Loop
                    captureCanvas.width = 480;
                    captureCanvas.height = 270;
                    broadcastInterval = setInterval(() => {
                        if (localVideo.videoWidth > 0) {
                            captureCtx.drawImage(localVideo, 0, 0, captureCanvas.width, captureCanvas.height);
                            // 0.4 quality for speed
                            const frame = captureCanvas.toDataURL('image/jpeg', 0.4);
                            ws.send(JSON.stringify({
                                type: 'VIDEO_FRAME',
                                streamId: streamId,
                                frame: frame
                            }));
                        }
                    }, 66); // ~15 FPS
                } else {
                    location.reload(); 
                }
            }
        });

        function copyWatchLink() {
            const copyText = document.getElementById("watchLink");
            copyText.select();
            copyText.setSelectionRange(0, 99999);
            navigator.clipboard.writeText(copyText.value);
            logDebug("Watch link copied to clipboard!");
        }

        connectWS();

        // Starfield (Shared Logic)
        const canvas = document.getElementById('starfield');
        const ctx = canvas.getContext('2d');
        let stars = [];
        function initStars() {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
            stars = [];
            for(let i=0; i<300; i++) {
                stars.push({
                    x: Math.random() * canvas.width,
                    y: Math.random() * canvas.height,
                    size: Math.random() * 2,
                    speed: Math.random() * 0.2
                });
            }
        }
        function animate() {
            ctx.clearRect(0,0,canvas.width,canvas.height);
            ctx.fillStyle = 'rgba(255, 255, 255, 0.4)';
            stars.forEach(s => {
                ctx.beginPath();
                ctx.arc(s.x, s.y, s.size, 0, Math.PI*2);
                ctx.fill();
                s.y += s.speed;
                if(s.y > canvas.height) s.y = 0;
            });
            requestAnimationFrame(animate);
        }
        window.addEventListener('resize', initStars);
        initStars();
        animate();
    </script>
</body>
</html>
