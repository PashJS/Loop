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
    <title>Loop - Upload Clip</title>
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
        .upload-container {
            background: rgba(255, 255, 255, 0.03) !important;
            backdrop-filter: blur(30px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 40px 100px rgba(0,0,0,0.5);
        }
        .file-input-wrapper {
            background: rgba(255, 255, 255, 0.02) !important;
            border-color: rgba(255, 255, 255, 0.1) !important;
        }
        .form-group input, .form-group textarea {
            background: rgba(255, 255, 255, 0.05) !important;
            border-color: rgba(255, 255, 255, 0.1) !important;
        }
    </style>
</head>
<body>
    <canvas id="starfield" class="starfield-canvas"></canvas>
    <?php include 'header.php'; ?>

    <div class="app-layout">
        <?php include 'sidebar.php'; ?>
        
        <main class="main-content">
            <div class="upload-main">
                <div class="upload-container">
                    <div class="upload-header">
                        <h1>New Clip</h1>
                        <p>Share a vertical short-form moment</p>
                    </div>

                    <form class="upload-form" id="uploadForm">
                        <input type="hidden" name="is_clip" value="true">
                        
                        <div class="form-group file-group">
                            <label for="videoFile" class="file-label">
                                <div class="file-input-wrapper">
                                    <i class="fa-solid fa-mobile-screen"></i>
                                    <span class="file-text">Choose Clip File</span>
                                    <span class="file-subtext">Vertical MP4 preferred (Max 60s)</span>
                                    <input type="file" id="videoFile" name="video" accept="video/*" required class="file-input-hidden"/>
                                </div>
                                <div class="file-name-display" id="videoFileName"></div>
                            </label>
                        </div>

                        <div class="form-group">
                            <label for="videoTitle">Caption *</label>
                            <input type="text" id="videoTitle" name="title" required maxlength="100" placeholder="Enter a catchy caption"/>
                        </div>

                        <div class="form-group">
                            <label for="videoDescription">Description</label>
                            <textarea id="videoDescription" name="description" rows="3" placeholder="Additional details (optional)"></textarea>
                        </div>

                        <div class="form-actions">
                            <a href="home.php" class="btn-cancel">Cancel</a>
                            <button type="submit" class="btn-submit" id="submitBtn">
                                <span class="btn-text">Publish Clip</span>
                                <span class="btn-loader" style="display: none;">
                                    <span class="loader-spinner"></span>
                                    <span>Uploading...</span>
                                </span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <?php include 'mobile_footer.php'; ?>

    <script src="theme.js"></script>
    <script>
        // Starfield
        const canvas = document.getElementById('starfield');
        const ctx = canvas.getContext('2d');
        let stars = [];
        function initStars() {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
            stars = [];
            for(let i=0; i<200; i++) {
                stars.push({
                    x: Math.random() * canvas.width,
                    y: Math.random() * canvas.height,
                    size: Math.random() * 2,
                    speed: Math.random() * 0.5
                });
            }
        }
        function animate() {
            ctx.clearRect(0,0,canvas.width,canvas.height);
            ctx.fillStyle = '#fff';
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

        // File name display
        document.getElementById('videoFile').addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name || '';
            const display = document.getElementById('videoFileName');
            if(fileName) {
                display.textContent = 'Selected: ' + fileName;
                display.classList.add('show');
            }
        });
    </script>
    <script src="upload.js"></script>
</body>
</html>
