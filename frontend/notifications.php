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
    <title>Notifications - Loop</title>
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Base styles and layouts -->
    <link rel="stylesheet" href="home.css?v=1"/>
    <link rel="stylesheet" href="layout.css?v=3"/>
    <link rel="stylesheet" href="notifications.css?v=1"/>
    
    <style>
        /* Base styles for space theme transparency */
        body {
            background: transparent !important;
            margin: 0; padding: 0;
            font-family: 'Outfit', sans-serif;
            overflow-x: hidden;
            overflow-y: auto;
        }
        
        html { background: #000; }

        .app-layout { 
            position: relative;
            z-index: 1;
            background: transparent !important; 
            height: auto !important; 
        }

        .side-nav {
            background: rgba(0,0,0,0.1) !important;
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
        }

        .top-nav {
            background: rgba(0,0,0,0.4) !important;
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255,255,255,0.05) !important;
        }

        .main-content { 
            background: transparent !important; 
            min-height: 100vh;
            position: relative;
            z-index: 1;
        }

        #starfield {
            position: fixed; 
            top: 0; 
            left: 0;
            width: 100%; 
            height: 100%;
            z-index: 0; 
            pointer-events: none;
            background: #000;
        }
    </style>
</head>
<body>
    <canvas id="starfield"></canvas>

    <?php include 'header.php'; ?>

    <div class="app-layout">
        <?php include 'sidebar.php'; ?>
        
        <main class="main-content">
            <div class="notifications-wrapper">
                <div class="notif-header-section">
                    <div>
                        <h1>Notifications</h1>
                        <p>Stay updated with your latest interactions.</p>
                    </div>
                    <button id="markAllReadBtn" class="mark-all-read-btn">
                        <i class="fa-solid fa-check-double"></i>
                        <span>Mark all read</span>
                    </button>
                </div>

                <div id="fullNotificationsList" class="notifications-hub">
                    <div style="text-align:center; padding: 100px; opacity: 0.5;">
                        <span style="color:rgba(255,255,255,0.3); font-size: 14px; letter-spacing: 2px; text-transform: uppercase;">Syncing Activity Data...</span>
                    </div>
                </div>
                
                <div id="fullNotificationsEmpty" class="null-activity" style="display: none;">
                    <i class="fa-solid fa-bell-slash"></i>
                    <h3>No Notifications</h3>
                    <p>You're all caught up! No new interactions found.</p>
                </div>
            </div>
        </main>
    </div>

    <!-- Starfield Animation Logic -->
    <script>
        const canvas = document.getElementById('starfield');
        const ctx = canvas.getContext('2d');
        let stars = [];
        const starCount = 180;

        function resize() {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
            initStars();
        }

        function initStars() {
            stars = [];
            for (let i = 0; i < starCount; i++) {
                stars.push({
                    x: Math.random() * canvas.width,
                    y: Math.random() * canvas.height,
                    size: Math.random() * 1.5,
                    speed: Math.random() * 0.12 + 0.05,
                    opacity: Math.random() * 0.3 + 0.1,
                    twinkle: Math.random() * 0.01
                });
            }
        }

        function animate() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            ctx.fillStyle = '#000';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            
            stars.forEach(star => {
                star.y -= star.speed;
                if (star.y < 0) star.y = canvas.height;
                
                star.opacity += Math.sin(Date.now() * star.twinkle) * 0.01;
                ctx.fillStyle = `rgba(255, 255, 255, ${Math.max(0.1, Math.min(0.5, star.opacity))})`;
                ctx.beginPath();
                ctx.arc(star.x, star.y, star.size, 0, Math.PI * 2);
                ctx.fill();
            });
            requestAnimationFrame(animate);
        }

        window.addEventListener('resize', resize);
        resize();
        animate();
    </script>

    <script src="theme.js"></script>
    <script src="notifications.js?v=<?php echo time(); ?>"></script>
</body>
</html>
