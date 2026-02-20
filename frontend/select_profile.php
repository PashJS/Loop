<?php
session_start();
require_once '../backend/auth_helper.php';

// Stay here for "Who's watching?" screen every time.
// Session-based auto-login is disabled to ensure this screen is shown.

// If already logged in, could skip to home or show profile selector
// For now, always show selector to allow switching
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Who's Watching? - Loop</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --bg-primary: #050505;
            --bg-secondary: rgba(255, 255, 255, 0.03);
            --accent: #0071e3;
            --accent-glow: rgba(0, 113, 227, 0.4);
            --text-primary: #ffffff;
            --text-secondary: rgba(255, 255, 255, 0.5);
            --glass-border: rgba(255, 255, 255, 0.08);
            --glass-bg: rgba(255, 255, 255, 0.02);
            --card-radius: 20px;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            min-height: 100vh;
            background: var(--bg-primary);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            color: var(--text-primary);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }
        
        /* Premium Background System */
        .background-container {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            z-index: 0;
            overflow: hidden;
            background: radial-gradient(circle at 50% 50%, #0a0a1a 0%, #050505 100%);
        }

        .star-field {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            pointer-events: none;
        }

        .galaxy-glow {
            position: absolute;
            width: 800px;
            height: 800px;
            background: radial-gradient(circle, rgba(0, 113, 227, 0.1) 0%, transparent 70%);
            filter: blur(80px);
            border-radius: 50%;
            pointer-events: none;
            z-index: 1;
        }

        .glow-1 { top: -200px; left: -200px; animation: drift 20s infinite alternate; }
        .glow-2 { bottom: -200px; right: -200px; animation: drift 25s infinite alternate-reverse; }

        @keyframes drift {
            from { transform: translate(0, 0) scale(1); }
            to { transform: translate(100px, 50px) scale(1.1); }
        }

        .content {
            position: relative;
            z-index: 2;
            text-align: center;
            padding: 40px 20px;
            max-width: 1000px;
            width: 100%;
            animation: fadeInPage 1.2s cubic-bezier(0.2, 0, 0.2, 1);
        }

        @keyframes fadeInPage {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .logo {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            letter-spacing: -0.5px;
            opacity: 0.8;
        }
        
        .logo svg {
            width: 38px;
            height: 38px;
            filter: drop-shadow(0 0 10px rgba(0, 113, 227, 0.5));
        }
        
        h1 {
            font-size: clamp(32px, 6vw, 48px);
            font-weight: 600;
            margin-bottom: 60px;
            letter-spacing: -1px;
            background: linear-gradient(to bottom, #fff 0%, #a1a1a6 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .profiles-grid {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 40px;
            margin-bottom: 60px;
        }
        
        .profile-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
            cursor: pointer;
            transition: all 0.5s cubic-bezier(0.2, 1, 0.2, 1);
            position: relative;
        }
        
        .avatar-container {
            position: relative;
            width: 150px;
            height: 150px;
            padding: 2px; /* Ring space */
        }

        .profile-avatar {
            width: 100%;
            height: 100%;
            border-radius: var(--card-radius);
            background: var(--bg-secondary);
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 56px;
            color: #fff;
            position: relative;
            z-index: 2;
            backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
            transition: all 0.5s cubic-bezier(0.2, 1, 0.2, 1);
        }

        .avatar-glow-ring {
            position: absolute;
            top: -2px; left: -2px; right: -2px; bottom: -2px;
            border-radius: calc(var(--card-radius) + 2px);
            background: conic-gradient(from 0deg, transparent, var(--accent), transparent, var(--accent), transparent);
            z-index: 1;
            opacity: 0;
            transition: opacity 0.4s;
            animation: rotateGlow 4s linear infinite;
        }

        @keyframes rotateGlow {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .profile-card:hover .avatar-glow-ring {
            opacity: 1;
        }

        .profile-card:hover .profile-avatar {
            transform: translateY(-8px) scale(1.02);
            border-color: rgba(255, 255, 255, 0.2);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
        }
        
        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.6s cubic-bezier(0.2, 1, 0.2, 1);
        }

        .profile-card:hover .profile-avatar img {
            transform: scale(1.1);
        }
        
        .profile-name {
            font-size: 18px;
            font-weight: 500;
            color: var(--text-secondary);
            transition: all 0.3s;
        }
        
        .profile-card:hover .profile-name {
            color: #fff;
            transform: translateY(-4px);
            text-shadow: 0 0 10px rgba(255, 255, 255, 0.3);
        }
        
        /* Add Profile Card Specialized */
        .profile-card.add-new .profile-avatar {
            background: var(--glass-bg);
            border-style: dashed;
            color: var(--text-secondary);
        }
        
        .profile-card.add-new:hover .profile-avatar {
            background: rgba(255, 255, 255, 0.05);
            color: #fff;
        }
        
        .skip-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 16px 40px;
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 40px;
            color: var(--text-secondary);
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.2, 1, 0.2, 1);
            text-decoration: none;
            backdrop-filter: blur(10px);
        }
        
        .skip-btn:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(255, 255, 255, 0.2);
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }

        .skip-btn i {
            font-size: 14px;
            opacity: 0.7;
        }
        
        /* Premium Loading */
        .loading-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0, 0, 0, 0.95);
            display: none;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            gap: 25px;
            z-index: 1000;
            backdrop-filter: blur(20px);
        }
        
        .loading-overlay.active { display: flex; }
        
        .loading-spinner-orbital {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            border: 2px solid rgba(0, 113, 227, 0.1);
            border-top: 2px solid var(--accent);
            animation: spinPremium 1s cubic-bezier(0.4, 0, 0.2, 1) infinite;
            position: relative;
        }

        .loading-spinner-orbital::after {
            content: '';
            position: absolute;
            top: 5px; right: 5px;
            width: 8px; height: 8px;
            background: var(--accent);
            border-radius: 50%;
            box-shadow: 0 0 15px var(--accent);
        }
        
        @keyframes spinPremium { 
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .loading-text {
            font-size: 14px;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        @media (max-width: 768px) {
            .avatar-container { width: 120px; height: 120px; }
            .profiles-grid { gap: 30px; }
            h1 { font-size: 32px; }
        }

        @media (max-width: 480px) {
            .avatar-container { width: 100px; height: 100px; }
            .profiles-grid { gap: 20px; }
            .skip-btn { padding: 14px 28px; font-size: 14px; }
        }
    </style>
</head>
<body>
    <div class="background-container">
        <canvas id="starField" class="star-field"></canvas>
        <div class="galaxy-glow glow-1"></div>
        <div class="galaxy-glow glow-2"></div>
    </div>
    
    <div class="content">
        <div class="logo">
            <?php include('icon.html'); ?>
            Loop
        </div>
        
        <h1>Who's watching?</h1>
        
        <div class="profiles-grid" id="profilesGrid">
            <!-- Populated by JS -->
        </div>
        
        <a href="home.php?guest=1" class="skip-btn">
            <i class="fa-solid fa-ghost"></i>
            Continue as Guest
        </a>
    </div>
    
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner-orbital"></div>
        <div class="loading-text">Authenticating</div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', async () => {
            const grid = document.getElementById('profilesGrid');
            let accounts = JSON.parse(localStorage.getItem('floxwatch_saved_accounts') || '[]');
            
            // Try to recognize user from cookie if not in localStorage
            try {
                const res = await fetch('../backend/getRememberedUser.php');
                const data = await res.json();
                if (data.success && data.user) {
                    const exists = accounts.find(a => a.id == data.user.id);
                    if (!exists) {
                        accounts.push({
                            id: data.user.id,
                            username: data.user.username,
                            avatar: data.user.profile_picture,
                            token: data.user.switchToken
                        });
                        localStorage.setItem('floxwatch_saved_accounts', JSON.stringify(accounts));
                    }
                }
            } catch (e) {
                console.error('Error recognizing user:', e);
            }

            if (accounts.length === 0) {
                grid.innerHTML = `
                    <div class="profile-card" onclick="location.href='loginb.php'">
                        <div class="avatar-container">
                            <div class="avatar-glow-ring"></div>
                            <div class="profile-avatar">
                                <i class="fa-solid fa-user-plus"></i>
                            </div>
                        </div>
                        <div class="profile-name">Sign In</div>
                    </div>
                `;
            } else {
                let html = '';
                
                accounts.forEach(acc => {
                    const initial = acc.username ? acc.username.charAt(0).toUpperCase() : '?';
                    const avatarContent = acc.avatar 
                        ? `<img src="${acc.avatar}" alt="${acc.username}">`
                        : initial;
                    
                    html += `
                        <div class="profile-card" onclick="selectProfile(${acc.id}, '${acc.token || ''}')">
                            <div class="avatar-container">
                                <div class="avatar-glow-ring"></div>
                                <div class="profile-avatar">${avatarContent}</div>
                            </div>
                            <div class="profile-name">${acc.username}</div>
                        </div>
                    `;
                });
                
                html += `
                    <div class="profile-card add-new" onclick="location.href='loginb.php'">
                        <div class="avatar-container">
                            <div class="avatar-glow-ring"></div>
                            <div class="profile-avatar">
                                <i class="fa-solid fa-plus"></i>
                            </div>
                        </div>
                        <div class="profile-name">Add Account</div>
                    </div>
                `;
                
                grid.innerHTML = html;
            }

            initStarField();
        });

        function initStarField() {
            const canvas = document.getElementById('starField');
            const ctx = canvas.getContext('2d');
            let stars = [];
            const starCount = 150;

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
                        speed: Math.random() * 0.05,
                        opacity: Math.random()
                    });
                }
            }

            function draw() {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                stars.forEach(star => {
                    ctx.fillStyle = `rgba(255, 255, 255, ${star.opacity})`;
                    ctx.beginPath();
                    ctx.arc(star.x, star.y, star.size, 0, Math.PI * 2);
                    ctx.fill();

                    star.y -= star.speed;
                    if (star.y < 0) {
                        star.y = canvas.height;
                        star.x = Math.random() * canvas.width;
                    }
                });
                requestAnimationFrame(draw);
            }

            window.addEventListener('resize', resize);
            resize();
            draw();
        }
        
        function selectProfile(userId, token) {
            if (!token) {
                window.location.href = 'loginb.php';
                return;
            }
            
            document.getElementById('loadingOverlay').classList.add('active');
            
            fetch('../backend/switch_with_token.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ user_id: userId, token: token })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    window.location.href = 'home.php';
                } else {
                    document.getElementById('loadingOverlay').classList.remove('active');
                    if (data.reauth) {
                        let saved = JSON.parse(localStorage.getItem('floxwatch_saved_accounts') || '[]');
                        saved = saved.filter(a => a.id != userId);
                        localStorage.setItem('floxwatch_saved_accounts', JSON.stringify(saved));
                        location.reload();
                    } else {
                    }
                }
            })
            .catch(() => {
                document.getElementById('loadingOverlay').classList.remove('active');
            });
        }
    </script>
</body>
</html>
