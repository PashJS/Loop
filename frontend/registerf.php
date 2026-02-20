<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loop - Sign Up</title>
    <style>
        :root {
            --bg-primary: #050505;
            --bg-secondary: rgba(255, 255, 255, 0.03);
            --accent: #0071e3;
            --accent-glow: rgba(0, 113, 227, 0.4);
            --text-primary: #ffffff;
            --text-secondary: rgba(255, 255, 255, 0.5);
            --glass-border: rgba(255, 255, 255, 0.08);
            --glass-bg: rgba(255, 255, 255, 0.04);
            --card-radius: 24px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            min-height: 100vh;
            background: var(--bg-primary);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            color: var(--text-primary);
            display: flex;
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
            width: 800px; height: 800px;
            background: radial-gradient(circle, rgba(0, 113, 227, 0.08) 0%, transparent 70%);
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

        .register-card {
            position: relative;
            z-index: 2;
            width: min(420px, 92vw);
            padding: 48px;
            border-radius: var(--card-radius);
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--glass-border);
            backdrop-filter: blur(20px);
            box-shadow: 0 40px 100px rgba(0, 0, 0, 0.6);
            animation: riseIn 1s cubic-bezier(0.2, 0, 0.2, 1);
        }

        @keyframes riseIn {
            from { opacity: 0; transform: translateY(30px) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        h1 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 8px;
            letter-spacing: -1px;
            display: flex;
            align-items: center;
            gap: 12px;
            background: linear-gradient(to bottom, #fff 0%, #a1a1a6 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        h1 svg {
            width: 32px;
            height: 32px;
            -webkit-text-fill-color: initial;
            filter: drop-shadow(0 0 10px var(--accent-glow));
        }

        .subtext {
            color: var(--text-secondary);
            font-size: 15px;
            margin-bottom: 32px;
            line-height: 1.5;
        }

        .form-group {
            margin-bottom: 24px;
        }

        label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 10px;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        input {
            width: 100%;
            padding: 14px 18px;
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid var(--glass-border);
            color: #fff;
            font-size: 16px;
            transition: all 0.3s;
            outline: none;
        }

        input:focus {
            background: rgba(255, 255, 255, 0.08);
            border-color: var(--accent);
            box-shadow: 0 0 0 4px rgba(0, 113, 227, 0.15);
            transform: translateY(-1px);
        }

        .btn-primary {
            width: 100%;
            padding: 16px;
            border-radius: 14px;
            background: var(--accent);
            color: #fff;
            font-size: 16px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.2, 1, 0.2, 1);
            box-shadow: 0 10px 30px rgba(0, 113, 227, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            background: #0077ed;
            box-shadow: 0 15px 40px rgba(0, 113, 227, 0.45);
        }

        .status {
            margin-top: 20px;
            font-size: 14px;
            text-align: center;
        }
        
        .status.success { color: #4ade80; }
        .status.error { color: #f87171; }

        .footer-links {
            margin-top: 32px;
            text-align: center;
            font-size: 14px;
            color: var(--text-secondary);
        }

        .footer-links a {
            color: var(--accent);
            text-decoration: none;
            font-weight: 500;
            transition: 0.3s;
        }

        .footer-links a:hover {
            filter: brightness(1.2);
            text-shadow: 0 0 8px var(--accent-glow);
        }
    </style>
</head>
<body>
    <div class="background-container">
        <canvas id="starField" class="star-field"></canvas>
        <div class="galaxy-glow glow-1"></div>
        <div class="galaxy-glow glow-2"></div>
    </div>

    <main class="register-card">
        <h1>Join Loop<?php include('icon.html');?></h1>
        <p class="subtext">Create your account in seconds and start curating your universe.</p>

        <form id="registerForm" autocomplete="off">
            <div class="form-group">
                <label for="username">Username</label>
                <input id="username" name="username" type="text" placeholder="SpaceExplorer" required minlength="3" maxlength="24">
            </div>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input id="email" name="email" type="email" placeholder="name@domain.com" required>
            </div>

            <div class="form-group">
                <label for="password">Security Password</label>
                <input id="password" name="password" type="password" placeholder="••••••••" required minlength="6">
            </div>

            <button type="submit" class="btn-primary" id="registerBtn">Launch Journey</button>
            <div id="status" class="status"></div>
        </form>

        <div class="footer-links">
            Already a member? <a href="loginb.php">Sign in</a>
        </div>
    </main>

    <script src="popup.js"></script>
    <script>
        const form = document.getElementById('registerForm');
        const statusBox = document.getElementById('status');
        const button = document.getElementById('registerBtn');

        function setStatus(message, type = '') {
            if (window.Popup) {
                Popup.show(message, type === 'success' ? 'success' : (type === 'error' ? 'error' : 'info'));
            } else {
                statusBox.textContent = message;
                statusBox.className = `status ${type}`;
            }
        }

        form.addEventListener('submit', (event) => {
            event.preventDefault();
            setStatus('Initializing account...', '');
            button.disabled = true;
            button.textContent = 'Processing...';

            const payload = new URLSearchParams();
            for (const [key, value] of new FormData(form).entries()) {
                payload.append(key, value.trim());
            }

            fetch('../backend/register.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
                body: payload.toString()
            })
            .then((res) => res.text())
            .then((text) => {
                const normalized = text.trim().toLowerCase();
                if (normalized.includes('user registered')) {
                    setStatus('Welcome to Loop! Take a moment to sign in.', 'success');
                    form.reset();
                    button.textContent = 'Register Another';
                } else {
                    throw new Error(text || 'Registration failed');
                }
            })
            .catch((err) => {
                setStatus(err.message || 'Unable to register right now.', 'error');
                button.textContent = 'Try Again';
            })
            .finally(() => {
                button.disabled = false;
            });
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
        initStarField();
    </script>
</body>
</html>

