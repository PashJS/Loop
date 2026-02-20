<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loop - Sign In</title>
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

        .login-card {
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

        .btn-primary:active {
            transform: translateY(-1px);
        }

        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .divider {
            position: relative;
            text-align: center;
            margin: 32px 0;
            opacity: 0.4;
        }

        .divider::before {
            content: '';
            position: absolute;
            left: 0; top: 50%; width: 100%; height: 1px;
            background: var(--glass-border);
        }

        .divider span {
            position: relative;
            background: #0d0d15;
            padding: 0 16px;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: var(--text-secondary);
        }

        .btn-social {
            width: 100%;
            padding: 14px;
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid var(--glass-border);
            color: #fff;
            font-size: 15px;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-social:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }

        .btn-social svg {
            width: 20px;
            height: 20px;
        }

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

        /* Verification Form UI */
        .verification-header {
            text-align: center;
            margin-bottom: 32px;
        }

        .verification-icon {
            width: 64px;
            height: 64px;
            background: rgba(0, 113, 227, 0.1);
            border: 1px solid var(--accent);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 28px;
            color: var(--accent);
            box-shadow: 0 0 30px var(--accent-glow);
        }

        .verification-input {
            text-align: center;
            letter-spacing: 12px;
            font-size: 28px;
            font-weight: 700;
            padding: 16px;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <div class="background-container">
        <canvas id="starField" class="star-field"></canvas>
        <div class="galaxy-glow glow-1"></div>
        <div class="galaxy-glow glow-2"></div>
    </div>

    <main class="login-card">
        <h1>Welcome Back<?php include('icon.html');?></h1>
        <p class="subtext">Sign in to sync your collections across devices.</p>

        <form id="loginForm" autocomplete="off">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input id="email" name="email" type="email" placeholder="name@domain.com" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input id="password" name="password" type="password" placeholder="••••••••" required>
                <div style="text-align: right; margin-top: 8px;">
                    <a href="#" id="forgotPasswordLink" style="color: var(--accent); text-decoration: none; font-size: 12px; font-weight: 500;">Forgot password?</a>
                </div>
            </div>

            <button type="submit" class="btn-primary" id="loginBtn">Sign In</button>
        </form>

        <!-- 2FA Verification Form -->
        <form id="twoFactorForm" style="display: none;" autocomplete="off">
            <div class="verification-header">
                <div class="verification-icon">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
                <h2 style="font-size: 24px; margin-bottom: 8px;">Security Check</h2>
                <p style="color: var(--text-secondary); font-size: 14px;">A code was sent to your inbox.</p>
            </div>

            <div class="form-group">
                <label for="twoFactorCode">6-Digit Code</label>
                <input id="twoFactorCode" class="verification-input" name="code" type="text" maxlength="6" inputmode="numeric" placeholder="000000" required>
            </div>

            <button type="submit" class="btn-primary" id="verifyBtn">Verify Account</button>
            
            <div style="text-align: center; margin-top: 24px;">
                <a href="#" id="backToLogin" style="color: var(--text-secondary); text-decoration: none; font-size: 13px; font-weight: 500;">Back to Login</a>
            </div>
        </form>

        <div id="oauthContainer">
            <div class="divider">
                <span>Or connect with</span>
            </div>

            <button type="button" class="btn-social" onclick="location.href='../backend/google_auth.php'">
                <svg viewBox="0 0 18 18"><path fill="#4285F4" d="M17.64 9.2c0-.637-.057-1.251-.164-1.84H9v3.48h4.844c-.209 1.125-.843 2.078-1.796 2.717v2.258h2.908c1.702-1.567 2.684-3.874 2.684-6.615z"/><path fill="#34A853" d="M9 18c2.43 0 4.467-.806 5.965-2.18l-2.908-2.258c-.806.54-1.837.86-3.057.86-2.35 0-4.34-1.587-5.053-3.72H.957v2.332C2.438 15.983 5.482 18 9 18z"/><path fill="#FBBC05" d="M3.947 10.712c-.18-.54-.282-1.117-.282-1.712 0-.595.102-1.172.282-1.712V4.956H.957C.348 6.175 0 7.55 0 9s.348 2.825.957 4.044l2.99-2.332z"/><path fill="#EA4335" d="M9 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.463.891 11.426 0 9 0 5.482 0 2.438 2.017.957 4.956L3.947 7.288C4.66 5.153 6.65 3.58 9 3.58z"/></svg>
                Google
            </button>
        </div>

        <div class="footer-links" id="registerLink">
            New here? <a href="registerf.php">Create an account</a>
        </div>
    </main>
    </main>

    <script src="popup.js"></script>
    <script>
        const form = document.getElementById('loginForm');
        const twoFactorForm = document.getElementById('twoFactorForm');
        const oauthContainer = document.getElementById('oauthContainer');
        const registerLink = document.getElementById('registerLink');
        const button = document.getElementById('loginBtn');
        const verifyBtn = document.getElementById('verifyBtn');
        const forgotPasswordLink = document.getElementById('forgotPasswordLink');
        const backToLogin = document.getElementById('backToLogin');
        
        let pendingLoginEmail = '';

        form.addEventListener('submit', (event) => {
            event.preventDefault();
            button.disabled = true;
            button.textContent = 'Authenticating...';

            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;

            fetch('../backend/login.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email, password })
            })
            .then(res => res.json())
            .then(data => {
                if(data.success){
                    if (data.two_factor_required) {
                        pendingLoginEmail = data.email;
                        form.style.display = 'none';
                        oauthContainer.style.display = 'none';
                        registerLink.style.display = 'none';
                        twoFactorForm.style.display = 'block';
                        document.querySelector('.subtext').style.display = 'none';
                        Popup.show('Verification code sent!', 'info');
                    } else {
                        Popup.show('Redirecting...', 'success');
                        setTimeout(() => window.location.href = 'home.php', 800);
                    }
                } else {
                    if (data.banned && data.redirect) {
                        window.location.href = data.redirect;
                        return;
                    }
                    Popup.show(data.message || 'Invalid credentials.', 'error');
                }
            })
            .catch(() => Popup.show('Network error.', 'error'))
            .finally(() => {
                button.disabled = false;
                button.textContent = 'Sign In';
            });
        });

        twoFactorForm.addEventListener('submit', (e) => {
            e.preventDefault();
            verifyBtn.disabled = true;
            verifyBtn.textContent = 'Verifying...';

            const code = document.getElementById('twoFactorCode').value.trim();

            fetch('../backend/verify_2fa.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email: pendingLoginEmail, code: code })
            })
            .then(res => res.json())
            .then(data => {
                if(data.success){
                    Popup.show('Verified!', 'success');
                    setTimeout(() => window.location.href = 'home.php', 800);
                } else {
                    Popup.show(data.message || 'Invalid code.', 'error');
                }
            })
            .catch(() => Popup.show('Network error.', 'error'))
            .finally(() => {
                verifyBtn.disabled = false;
                verifyBtn.textContent = 'Verify Account';
            });
        });

        backToLogin.addEventListener('click', (e) => {
            e.preventDefault();
            twoFactorForm.style.display = 'none';
            form.style.display = 'block';
            oauthContainer.style.display = 'block';
            registerLink.style.display = 'block';
            document.querySelector('.subtext').style.display = 'block';
        });

        forgotPasswordLink.addEventListener('click', (e) => {
            e.preventDefault();
            showForgotPasswordModal();
        });

        function showForgotPasswordModal() {
            const modal = document.createElement('div');
            modal.className = 'popup-overlay';
            modal.innerHTML = `
                <div class="popup-dialog" style="max-width: 400px; background: rgba(13, 13, 21, 0.95); backdrop-filter: blur(20px); border: 1px solid var(--glass-border); border-radius: 24px;">
                    <div class="popup-dialog-header" style="border-bottom: 1px solid var(--glass-border); padding: 20px;">
                        <h3 style="font-size: 20px;">Account Recovery</h3>
                        <button class="popup-close" style="background: none; border: none; font-size: 24px; cursor: pointer; color: var(--text-secondary);">×</button>
                    </div>
                    <div class="popup-dialog-body" style="padding: 24px;">
                        <p style="margin-bottom: 24px; color: var(--text-secondary); font-size: 14px;">Lost your access? We'll help you get back in.</p>
                        <form id="forgotPasswordForm">
                            <div class="form-group">
                                <label>Registered Email</label>
                                <input type="email" id="resetEmail" required style="width: 100%; padding: 14px; border-radius: 12px; background: rgba(255,255,255,0.04); border: 1px solid var(--glass-border); color: #fff;" placeholder="name@domain.com">
                            </div>
                            <div id="codeSection" style="display: none;" class="form-group">
                                <label>6-Digit Reset Code</label>
                                <input type="text" id="resetCode" maxlength="6" style="width: 100%; padding: 14px; border-radius: 12px; background: rgba(255,255,255,0.04); border: 1px solid var(--glass-border); color: #fff; text-align: center; letter-spacing: 8px; font-size: 20px; font-family: monospace;">
                            </div>
                            <div id="newPasswordSection" style="display: none;" class="form-group">
                                <label>New Secure Password</label>
                                <input type="password" id="newPassword" style="width: 100%; padding: 14px; border-radius: 12px; background: rgba(255,255,255,0.04); border: 1px solid var(--glass-border); color: #fff;" placeholder="••••••••">
                            </div>
                            <div class="popup-dialog-footer" style="padding-top: 24px; display: flex; gap: 12px;">
                                <button type="button" class="btn-secondary" style="flex: 1; padding: 14px; border-radius: 12px; background: rgba(255,255,255,0.05); color: #fff; border: 1px solid var(--glass-border); cursor: pointer;" onclick="this.closest('.popup-overlay').remove()">Cancel</button>
                                <button type="submit" class="btn-primary" id="resetSubmitBtn" style="flex: 2; padding: 14px; border-radius: 12px; background: var(--accent); color: #fff; border: none; cursor: pointer;">Send Code</button>
                            </div>
                        </form>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            
            let step = 1;
            let resetEmail = '';
            
            modal.querySelector('#forgotPasswordForm').addEventListener('submit', async (e) => {
                e.preventDefault();
                const submitBtn = modal.querySelector('#resetSubmitBtn');
                
                if (step === 1) {
                    resetEmail = modal.querySelector('#resetEmail').value.trim();
                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Sending...';
                    
                    const response = await fetch('../backend/forgotPassword.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `email=${encodeURIComponent(resetEmail)}`
                    });
                    const data = await response.json();
                    
                    if (data.success) {
                        step = 2;
                        modal.querySelector('#codeSection').style.display = 'block';
                        submitBtn.textContent = 'Verify Code';
                        submitBtn.disabled = false;
                        Popup.show('Reset code sent!', 'success');
                    } else {
                        Popup.show(data.message, 'error');
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'Send Code';
                    }
                } else if (step === 2) {
                    const code = modal.querySelector('#resetCode').value.trim();
                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Verifying...';
                    
                    const response = await fetch('../backend/verifyResetCode.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `email=${encodeURIComponent(resetEmail)}&code=${encodeURIComponent(code)}`
                    });
                    const data = await response.json();
                    
                    if (data.success) {
                        step = 3;
                        modal.querySelector('#newPasswordSection').style.display = 'block';
                        submitBtn.textContent = 'Reset Password';
                        submitBtn.disabled = false;
                    } else {
                        Popup.show(data.message, 'error');
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'Verify Code';
                    }
                } else if (step === 3) {
                    const password = modal.querySelector('#newPassword').value;
                    const code = modal.querySelector('#resetCode').value.trim();
                    submitBtn.disabled = true;
                    
                    const response = await fetch('../backend/resetPassword.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `email=${encodeURIComponent(resetEmail)}&code=${encodeURIComponent(code)}&password=${encodeURIComponent(password)}`
                    });
                    const data = await response.json();
                    
                    if (data.success) {
                        Popup.show('Password reset successful!', 'success');
                        setTimeout(() => window.location.href = 'home.php', 1500);
                    } else {
                        Popup.show(data.message, 'error');
                        submitBtn.disabled = false;
                    }
                }
            });
            
            modal.querySelector('.popup-close').onclick = () => modal.remove();
        }

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
</body>
</html>
