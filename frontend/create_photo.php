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
    <title>Loop - Share Photo</title>
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
                        <h1>Share Photo</h1>
                        <p>Post a high-quality visual to your feed</p>
                    </div>

                    <form class="upload-form" id="photoForm" action="../backend/create_photo_post.php" method="POST" enctype="multipart/form-data">
                        <div class="form-group file-group">
                            <label for="photoFile" class="file-label">
                                <div class="file-input-wrapper">
                                    <i class="fa-solid fa-image"></i>
                                    <span class="file-text">Choose Photo</span>
                                    <span class="file-subtext">JPEG, PNG, WEBP (Max 10MB)</span>
                                    <input type="file" id="photoFile" name="photo" accept="image/*" required class="file-input-hidden"/>
                                </div>
                                <div class="file-name-display" id="photoFileName"></div>
                            </label>
                        </div>

                        <div class="form-group">
                            <label for="photoTitle">Title</label>
                            <input type="text" id="photoTitle" name="title" required maxlength="100" placeholder="Give your photo a title"/>
                        </div>

                        <div class="form-group">
                            <label for="photoDescription">Description</label>
                            <textarea id="photoDescription" name="description" rows="4" placeholder="Tell the story behind this photo..."></textarea>
                        </div>

                        <div class="form-actions">
                            <a href="home.php" class="btn-cancel">Cancel</a>
                            <button type="submit" class="btn-submit">
                                <i class="fa-solid fa-paper-plane"></i>
                                <span>Share Photo</span>
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
        // Starfield (Shared Logic)
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
                    speed: Math.random() * 0.3
                });
            }
        }
        function animate() {
            ctx.clearRect(0,0,canvas.width,canvas.height);
            ctx.fillStyle = 'rgba(255, 255, 255, 0.5)';
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
        document.getElementById('photoFile').addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name || '';
            const display = document.getElementById('photoFileName');
            if(fileName) {
                display.textContent = 'Selected: ' + fileName;
                display.classList.add('show');
            }
        });
    </script>
</body>
</html>
