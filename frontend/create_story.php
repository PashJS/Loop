<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: loginb.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Create Story - Loop</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="home.css">
    <style>
        body, html {
            margin: 0;
            padding: 0;
            height: 100%;
            background: #000;
            overflow: hidden;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: #fff;
        }

        .story-container {
            position: relative;
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        #camera-preview {
            width: 100%;
            height: 100%;
            object-fit: cover;
            background: #000;
        }

        #media-preview-container {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: none;
            z-index: 10;
            background: #000;
        }

        #media-preview, #video-preview {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .controls {
            position: absolute;
            bottom: 60px;
            width: 100%;
            display: flex;
            justify-content: space-around;
            align-items: center;
            z-index: 20;
        }

        .mode-switcher {
            position: absolute;
            bottom: 20px;
            width: 100%;
            display: flex;
            justify-content: center;
            gap: 20px;
            z-index: 20;
            font-weight: 700;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .mode-item {
            opacity: 0.5;
            cursor: pointer;
            transition: opacity 0.3s;
            padding: 5px 10px;
        }

        .mode-item.active {
            opacity: 1;
            text-shadow: 0 0 10px rgba(255,255,255,0.5);
        }

        .capture-btn-outer {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            border: 4px solid #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            position: relative;
            transition: transform 0.2s;
        }

        .capture-btn-inner {
            width: 66px;
            height: 66px;
            background: #fff;
            border-radius: 50%;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .video-mode .capture-btn-inner {
            background: #ff3b30;
        }

        .recording .capture-btn-inner {
            transform: scale(0.6);
            border-radius: 8px;
        }

        .capture-progress-svg {
            position: absolute;
            top: -4px;
            left: -4px;
            width: 88px;
            height: 88px;
            pointer-events: none;
            transform: rotate(-90deg);
        }

        .capture-progress-bg {
            fill: none;
            stroke: rgba(255,255,255,0.2);
            stroke-width: 4;
        }

        .capture-progress-fill {
            fill: none;
            stroke: #ff3b30;
            stroke-width: 4;
            stroke-linecap: round;
            stroke-dasharray: 264;
            stroke-dashoffset: 264;
            transition: stroke-dashoffset 0.1s linear;
        }

        .icon-btn {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.1);
            color: #fff;
            font-size: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }

        .top-controls {
            position: absolute;
            top: 20px;
            width: 100%;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 20;
            box-sizing: border-box;
        }

        .preview-controls {
            position: absolute;
            bottom: 40px;
            width: 100%;
            display: none;
            justify-content: center;
            gap: 20px;
            z-index: 30;
        }

        .send-btn {
            background: linear-gradient(45deg, #0071e3, #a033ff);
            color: #fff;
            padding: 14px 40px;
            border-radius: 40px;
            border: none;
            font-size: 16px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            box-shadow: 0 8px 25px rgba(0,0,0,0.4);
        }

        .cancel-btn {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            color: #fff;
            padding: 14px 25px;
            border-radius: 40px;
            border: 1px solid rgba(255,255,255,0.1);
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
        }

        #recording-status {
            position: absolute;
            top: 80px;
            background: rgba(0,0,0,0.5);
            padding: 5px 12px;
            border-radius: 20px;
            display: none;
            align-items: center;
            gap: 8px;
            color: #fff;
            font-weight: 700;
            z-index: 20;
            backdrop-filter: blur(5px);
        }

        .dot {
            width: 8px;
            height: 8px;
            background: #ff3b30;
            border-radius: 50%;
            animation: pulse 1s infinite;
        }

        @keyframes pulse {
            0% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.3; transform: scale(1.2); }
            100% { opacity: 1; transform: scale(1); }
        }

        #loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.9);
            display: none;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 100;
            color: #fff;
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid rgba(255,255,255,0.1);
            border-top: 4px solid #0071e3;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>

<div class="story-container">
    <div class="top-controls">
        <button class="icon-btn" onclick="window.location.href='home.php'"><i class="fa-solid fa-xmark"></i></button>
        <button class="icon-btn" id="flip-camera"><i class="fa-solid fa-camera-rotate"></i></button>
    </div>

    <div id="recording-status">
        <div class="dot"></div>
        <span>REC <span id="timer">00:00</span></span>
    </div>

    <video id="camera-preview" autoplay playsinline muted></video>

    <div id="media-preview-container">
        <img id="media-preview" style="display: none;">
        <video id="video-preview" style="display: none;" loop controls></video>
    </div>

    <div class="controls" id="capture-controls">
        <button class="icon-btn" onclick="document.getElementById('file-input').click()"><i class="fa-solid fa-image"></i></button>
        
        <div class="capture-btn-outer" id="btn-capture-container">
            <svg class="capture-progress-svg">
                <circle class="capture-progress-bg" cx="44" cy="44" r="42"></circle>
                <circle class="capture-progress-fill" id="progress-circle" cx="44" cy="44" r="42"></circle>
            </svg>
            <div class="capture-btn-inner" id="btn-capture-inner"></div>
        </div>

        <button class="icon-btn" id="toggle-flash"><i class="fa-solid fa-bolt"></i></button>
    </div>

    <div class="mode-switcher" id="mode-switcher">
        <div class="mode-item active" data-mode="photo">Photo</div>
        <div class="mode-item" data-mode="video">Video</div>
    </div>

    <div class="preview-controls" id="preview-actions">
        <button class="cancel-btn" id="btn-cancel">Discard</button>
        <button class="send-btn" id="btn-send">
            <span>Post Story</span>
            <i class="fa-solid fa-paper-plane"></i>
        </button>
    </div>

    <input type="file" id="file-input" accept="image/*,video/*" style="display: none;">
</div>

<div id="loading-overlay">
    <div class="loading-spinner"></div>
    <p style="margin-top: 20px; font-weight: 700;" id="loading-text">Uploading Story...</p>
</div>

<script>
    let stream = null;
    let recorder = null;
    let chunks = [];
    let isRecording = false;
    let captureTimer = null;
    let seconds = 0;
    let facingMode = "user";
    let capturedBlob = null;
    let capturedType = null;
    let currentMode = 'photo'; // 'photo' or 'video'

    const video = document.getElementById('camera-preview');
    const captureBtn = document.getElementById('btn-capture-container');
    const captureInner = document.getElementById('btn-capture-inner');
    const progressCircle = document.getElementById('progress-circle');
    const modeItems = document.querySelectorAll('.mode-item');
    const previewContainer = document.getElementById('media-preview-container');
    const imgPreview = document.getElementById('media-preview');
    const videoPreview = document.getElementById('video-preview');
    const captureControls = document.getElementById('capture-controls');
    const modeSwitcher = document.getElementById('mode-switcher');
    const previewActions = document.getElementById('preview-actions');
    const fileInput = document.getElementById('file-input');
    const loadingOverlay = document.getElementById('loading-overlay');

    async function initCamera() {
        if (stream) stream.getTracks().forEach(t => t.stop());
        try {
            stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: facingMode },
                audio: true
            });
            video.srcObject = stream;
        } catch (err) {
        }
    }

    initCamera();

    document.getElementById('flip-camera').addEventListener('click', () => {
        facingMode = facingMode === "user" ? "environment" : "user";
        initCamera();
    });

    // Mode Switching
    modeItems.forEach(item => {
        item.addEventListener('click', () => {
            modeItems.forEach(i => i.classList.remove('active'));
            item.classList.add('active');
            currentMode = item.dataset.mode;
            
            if (currentMode === 'video') {
                captureControls.classList.add('video-mode');
            } else {
                captureControls.classList.remove('video-mode');
            }
        });
    });

    // Capture Photo
    function takePhoto() {
        const canvas = document.createElement('canvas');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0);
        
        canvas.toBlob(blob => {
            capturedBlob = blob;
            capturedType = 'image';
            showPreview(URL.createObjectURL(blob), 'image');
        }, 'image/jpeg', 0.8);
    }

    // Capture Video Logic
    function startRecording() {
        chunks = [];
        const options = { mimeType: 'video/webm;codecs=vp8' };
        if (!MediaRecorder.isTypeSupported(options.mimeType)) {
            delete options.mimeType;
        }

        recorder = new MediaRecorder(stream, options);
        recorder.ondataavailable = e => chunks.push(e.data);
        recorder.onstop = () => {
            const blob = new Blob(chunks, { type: 'video/mp4' });
            capturedBlob = blob;
            capturedType = 'video';
            showPreview(URL.createObjectURL(blob), 'video');
        };
        recorder.start();
        isRecording = true;
        captureBtn.classList.add('recording');
        document.getElementById('recording-status').style.display = 'flex';
        
        seconds = 0;
        const maxSeconds = 30;
        captureTimer = setInterval(() => {
            seconds += 0.1;
            let displaySecs = Math.floor(seconds);
            let mins = Math.floor(displaySecs / 60);
            let s = displaySecs % 60;
            document.getElementById('timer').innerText = `${mins < 10 ? '0' : ''}${mins}:${s < 10 ? '0' : ''}${s}`;
            
            // Progress circle
            const progress = (seconds / maxSeconds) * 264;
            progressCircle.style.strokeDashoffset = 264 - progress;

            if (seconds >= maxSeconds) stopRecording();
        }, 100);
    }

    function stopRecording() {
        if (!isRecording) return;
        recorder.stop();
        isRecording = false;
        captureBtn.classList.remove('recording');
        document.getElementById('recording-status').style.display = 'none';
        progressCircle.style.strokeDashoffset = 264;
        clearInterval(captureTimer);
    }

    // Press Logic
    let pressTimer;
    let isLongPress = false;

    captureBtn.addEventListener('mousedown', (e) => {
        if (currentMode === 'video') {
            if (isRecording) stopRecording();
            else startRecording();
        } else {
            isLongPress = false;
            pressTimer = setTimeout(() => {
                isLongPress = true;
                startRecording();
            }, 300);
        }
    });

    captureBtn.addEventListener('mouseup', () => {
        if (currentMode === 'photo') {
            clearTimeout(pressTimer);
            if (isRecording) {
                stopRecording();
            } else if (!isLongPress) {
                takePhoto();
            }
        }
    });

    // Touch support
    captureBtn.addEventListener('touchstart', (e) => {
        e.preventDefault();
        if (currentMode === 'video') {
            if (isRecording) stopRecording();
            else startRecording();
        } else {
            isLongPress = false;
            pressTimer = setTimeout(() => {
                isLongPress = true;
                startRecording();
            }, 300);
        }
    });

    captureBtn.addEventListener('touchend', (e) => {
        e.preventDefault();
        if (currentMode === 'photo') {
            clearTimeout(pressTimer);
            if (isRecording) {
                stopRecording();
            } else if (!isLongPress) {
                takePhoto();
            }
        }
    });

    function showPreview(src, type) {
        previewContainer.style.display = 'block';
        captureControls.style.display = 'none';
        modeSwitcher.style.display = 'none';
        previewActions.style.display = 'flex';
        
        if (type === 'image') {
            imgPreview.src = src;
            imgPreview.style.display = 'block';
            videoPreview.style.display = 'none';
        } else {
            videoPreview.src = src;
            videoPreview.style.display = 'block';
            imgPreview.style.display = 'none';
            videoPreview.play();
        }
    }

    document.getElementById('btn-cancel').addEventListener('click', () => {
        previewContainer.style.display = 'none';
        captureControls.style.display = 'flex';
        modeSwitcher.style.display = 'flex';
        previewActions.style.display = 'none';
        imgPreview.src = "";
        videoPreview.src = "";
        capturedBlob = null;
    });

    fileInput.addEventListener('change', (e) => {
        const file = e.target.files[0];
        if (!file) return;
        capturedBlob = file;
        capturedType = file.type.startsWith('video') ? 'video' : 'image';
        showPreview(URL.createObjectURL(file), capturedType);
    });

    document.getElementById('btn-send').addEventListener('click', async () => {
        if (!capturedBlob) return;

        loadingOverlay.style.display = 'flex';
        const formData = new FormData();
        formData.append('story', capturedBlob, capturedType === 'image' ? 'story.jpg' : 'story.mp4');
        formData.append('type', capturedType);

        try {
            const res = await fetch('../backend/upload_story.php', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if (data.success) {
                window.location.href = 'home.php';
            } else {
                loadingOverlay.style.display = 'none';
            }
        } catch (err) {
            console.error(err);
            loadingOverlay.style.display = 'none';
        }
    });
</script>

</body>
</html>
