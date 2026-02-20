<?php
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../backend/config.php';

// Check Pro Status
$is_pro = false;
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT username, is_pro, is_gifted_pro, pro_gifts_count FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $row = $stmt->fetch();
        if ($row) {
            $username = $row['username'];
            $pro_gifts_count = $row['pro_gifts_count'] ?? 3;
            $is_gifted_pro = $row['is_gifted_pro'] == 1;
            if ($row['is_pro'] == 1) {
                $is_pro = true;
                // Fetch Pro Settings (Wrapped to prevent crashing if table hasn't been created yet)
                $pro_settings = [
                    'weather_widget' => 'on', 
                    'resume_widget' => 'on', 
                    'time_widget' => 'on',
                    'clock_type' => 'analog',
                    'theme' => 'liquid', 
                    'font' => 'outfit', 
                    'comment_badge' => 'pro', 
                    'name_badge' => 'on'
                ];
                try {
                    $stmt_settings = $pdo->prepare("SELECT * FROM pro_settings WHERE user_id = ?");
                    $stmt_settings->execute([$_SESSION['user_id']]);
                    $fetched_settings = $stmt_settings->fetch(PDO::FETCH_ASSOC);
                    if($fetched_settings) {
                        $pro_settings = array_merge($pro_settings, $fetched_settings); // Merge to ensure new keys exist
                    }
                } catch (PDOException $e) {
                    // Table might not exist yet, use defaults already set above
                }
            }
        }
    } catch (PDOException $e) {
        // Main user fetch failed
        error_log("Pro Page Error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark" class="pro-page-active">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loop Pro - Elevate Your Experience</title>
    <link rel="stylesheet" href="home.css">
    <link rel="stylesheet" href="layout.css?v=3">
    <link rel="stylesheet" href="pro.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://www.paypal.com/sdk/js?client-id=sb&currency=USD"></script>
</head>
<body class="dark-theme pro-page-active">
    <?php include('header.php'); ?>
    
    <div class="app-layout">
        <?php include('sidebar.php'); ?>
        
        <main class="main-content">
            <div class="pro-page-container">
                <div class="pro-ambient-aura"></div>
                
                <?php if (!$is_pro): ?>
                    <!-- Landing Page for Non-Pro Users -->
                    <div class="pro-content-wrapper">
                        
                        <!-- Left Hero Typography -->
                        <div class="pro-hero-section">
                            <h1 class="pro-title-giant">Loop PRO</h1>
                            <p class="pro-subtitle">Elevate your experience with Loop Pro.</p>
                            
                            <div class="pro-action-area">
                                <button class="pro-buy-btn" id="openProModal">Buy 9.99$/Mo</button>
                                <p style="margin-top: 15px; font-size: 14px; opacity: 0.6;">Cancel anytime. Securely processed by PayPal.</p>
                            </div>
                        </div>

                        <!-- Right Features List -->
                        <div class="pro-features-list">
                            <!-- Feature 1 -->
                            <div class="pro-feature-item" style="animation-delay: 0.1s;">
                                <div class="feature-icon-wrapper icon-cosmetics">
                                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                                </div>
                                <div class="feature-text">Buy gorgeous cosmetics</div>
                            </div>

                            <!-- Feature 2 -->
                            <div class="pro-feature-item" style="animation-delay: 0.2s;">
                                <div class="feature-icon-wrapper icon-xpoints">
                                    <i class="fa-solid fa-coins"></i>
                                </div>
                                <div class="feature-text">Get 5000 XPoints</div>
                            </div>

                            <!-- Feature 3 -->
                            <div class="pro-feature-item" style="animation-delay: 0.3s;">
                                <div class="feature-icon-wrapper icon-customization">
                                    <i class="fa-solid fa-palette"></i>
                                </div>
                                <div class="feature-text">Access to premium customization (widgets, themes etc.)</div>
                            </div>

                            <!-- Feature 4 -->
                            <div class="pro-feature-item" style="animation-delay: 0.4s;">
                                <div class="feature-icon-wrapper icon-profile">
                                    <i class="fa-solid fa-id-card"></i>
                                </div>
                                <div class="feature-text">Custom profile card</div>
                            </div>

                            <!-- Feature 5 -->
                            <div class="pro-feature-item" style="animation-delay: 0.5s;">
                                <div class="feature-icon-wrapper icon-storage">
                                    <i class="fa-solid fa-cloud-arrow-up"></i>
                                </div>
                                <div class="feature-text">256GB video uploading</div>
                            </div>

                            <!-- Feature 6 -->
                            <div class="pro-feature-item" style="animation-delay: 0.6s;">
                                <div class="feature-icon-wrapper icon-badge">
                                    <i class="fa-solid fa-shield-halved"></i>
                                </div>
                                <div class="feature-text">Exclusive Pro badge</div>
                            </div>

                            <!-- Feature 7 -->
                            <div class="pro-feature-item" style="animation-delay: 0.7s;">
                                <div class="feature-icon-wrapper icon-gifting">
                                    <i class="fa-solid fa-gift"></i>
                                </div>
                                <div class="feature-text">Gifting others</div>
                            </div>

                            <!-- Feature 8 -->
                            <div class="pro-feature-item" style="animation-delay: 0.8s;">
                                <div class="feature-icon-wrapper icon-more">
                                    <i class="fa-solid fa-plus"></i>
                                </div>
                                <div class="feature-text">And much more...</div>
                            </div>
                        </div>

                    </div>
                <?php else: ?>
                    <!-- Minimal Dashboard for Pro Members -->
                    <div class="pro-dashboard-minimal">
                        <header class="dash-header">
                            <h1 class="dash-greeting">Hello, <span><?php echo htmlspecialchars($username); ?></span>.</h1>
                            <p class="dash-sub">Welcome to your minimal workspace. Everything you need, nothing you don't.</p>
                        </header>

                        <div class="dash-grid">
                            <!-- Customization Section -->

                            <section class="dash-card">
                                <h3><i class="fa-solid fa-sliders"></i> Customization</h3>
                                <div class="dash-option">
                                    <label>Weather Widget</label>
                                    <div class="dash-switch-group" id="weatherToggle">
                                        <button class="dash-toggle-btn <?php echo ($pro_settings['weather_widget'] == 'on') ? 'active' : ''; ?>" data-value="on">On</button>
                                        <button class="dash-toggle-btn <?php echo ($pro_settings['weather_widget'] == 'off') ? 'active' : ''; ?>" data-value="off">Off</button>
                                    </div>
                                </div>
                                <div class="dash-option">
                                    <label>Last Watched Widget</label>
                                    <div class="dash-switch-group" id="resumeToggle">
                                        <button class="dash-toggle-btn <?php echo (($pro_settings['resume_widget'] ?? 'on') == 'on') ? 'active' : ''; ?>" data-value="on">On</button>
                                        <button class="dash-toggle-btn <?php echo (($pro_settings['resume_widget'] ?? 'on') == 'off') ? 'active' : ''; ?>" data-value="off">Off</button>
                                    </div>
                                </div>
                                <div class="dash-option">
                                    <label>Time & Date Widget</label>
                                    <div class="dash-switch-group" id="timeToggle">
                                        <button class="dash-toggle-btn <?php echo (($pro_settings['time_widget'] ?? 'on') == 'on') ? 'active' : ''; ?>" data-value="on">On</button>
                                        <button class="dash-toggle-btn <?php echo (($pro_settings['time_widget'] ?? 'on') == 'off') ? 'active' : ''; ?>" data-value="off">Off</button>
                                    </div>
                                </div>
                                <div class="dash-option">
                                    <label>Clock Style</label>
                                    <div class="dash-switch-group" id="clockTypeToggle">
                                        <button class="dash-toggle-btn <?php echo (($pro_settings['clock_type'] ?? 'analog') == 'analog') ? 'active' : ''; ?>" data-value="analog">Analog</button>
                                        <button class="dash-toggle-btn <?php echo (($pro_settings['clock_type'] ?? 'analog') == 'digital') ? 'active' : ''; ?>" data-value="digital">Digital</button>
                                    </div>
                                </div>
                                <div class="dash-option">
                                    <label>Theme</label>
                                    <select class="dash-select" id="themeSelect">
                                        <option value="liquid" <?php echo ($pro_settings['theme'] == 'liquid') ? 'selected' : ''; ?>>Liquid Glass (Default)</option>
                                        <option value="obsidian" <?php echo ($pro_settings['theme'] == 'obsidian') ? 'selected' : ''; ?>>Obsidian Dark</option>
                                        <option value="snow" <?php echo ($pro_settings['theme'] == 'snow') ? 'selected' : ''; ?>>Snow White</option>
                                        <option value="neon" <?php echo ($pro_settings['theme'] == 'neon') ? 'selected' : ''; ?>>Cyber Neon</option>
                                    </select>
                                </div>
                                <div class="dash-option">
                                    <label>Text Font</label>
                                    <select class="dash-select" id="fontSelect">
                                        <option value="outfit" <?php echo ($pro_settings['font'] == 'outfit') ? 'selected' : ''; ?>>Outfit (Modern)</option>
                                        <option value="inter" <?php echo ($pro_settings['font'] == 'inter') ? 'selected' : ''; ?>>Inter (Clean)</option>
                                        <option value="roboto" <?php echo ($pro_settings['font'] == 'roboto') ? 'selected' : ''; ?>>Roboto (Standard)</option>
                                        <option value="playfair" <?php echo ($pro_settings['font'] == 'playfair') ? 'selected' : ''; ?>>Playfair Display (Classy)</option>
                                    </select>
                                </div>
                            </section>

                            <!-- Badge & Identity Section -->
                            <section class="dash-card">
                                <h3><i class="fa-solid fa-shield-halved"></i> Badge & Identity</h3>
                                
                                <div class="dash-item-group">
                                    <label class="group-label">Comment Badge</label>
                                    <p class="card-hint">Displayed next to your name in comments (Extra Small)</p>
                                    <div class="badge-picker" id="proBadgePicker">
                                        <button class="badge-item <?php echo ($pro_settings['comment_badge'] == 'pro') ? 'active' : ''; ?>" data-badge="pro" title="PRO Icon">
                                            <div style="width: 14px; height: 14px; display: flex; align-items: center; justify-content: center; opacity: 0.8;"><?php include('proicon.html'); ?></div>
                                        </button>
                                        <button class="badge-item <?php echo ($pro_settings['comment_badge'] == 'crown') ? 'active' : ''; ?>" data-badge="crown" title="Crown"><i class="fa-solid fa-crown"></i></button>
                                        <button class="badge-item <?php echo ($pro_settings['comment_badge'] == 'bolt') ? 'active' : ''; ?>" data-badge="bolt" title="Electricity"><i class="fa-solid fa-bolt"></i></button>
                                        <button class="badge-item <?php echo ($pro_settings['comment_badge'] == 'verified') ? 'active' : ''; ?>" data-badge="verified" title="Verified"><i class="fa-solid fa-check-double"></i></button>
                                    </div>
                                </div>

                                <div class="dash-item-group" style="margin-top: 30px;">
                                    <label class="group-label">Nickname Badge Box</label>
                                    <p class="card-hint">Your name inside a premium rounded container</p>
                                    <div class="name-badge-preview">
                                        <div class="mini-name-tag">
                                            <i class="fa-solid fa-bolt" style="font-size: 8px; color: #ffeb3b;"></i>
                                            <span><?php echo htmlspecialchars($username); ?></span>
                                            <i class="fa-solid fa-crown" style="font-size: 8px; color: #ffd700;"></i>
                                        </div>
                                    </div>
                                    <div class="dash-switch-group" id="nameBadgeToggle" style="margin-top: 15px; width: fit-content;">
                                        <button class="dash-toggle-btn <?php echo ($pro_settings['name_badge'] == 'on') ? 'active' : ''; ?>" data-value="on">Enabled</button>
                                        <button class="dash-toggle-btn <?php echo ($pro_settings['name_badge'] == 'off') ? 'active' : ''; ?>" data-value="off">Disabled</button>
                                    </div>
                                </div>
                            </section>

                            <!-- More Features -->
                            <section class="dash-card">
                                <h3><i class="fa-solid fa-layer-group"></i> More Features</h3>
                                <div class="dash-feature-row">
                                    <i class="fa-solid fa-cloud-arrow-up"></i>
                                    <span>256GB Cloud Storage Active</span>
                                </div>
                                <div class="dash-feature-row">
                                    <i class="fa-solid fa-gift"></i>
                                    <span>3 Monthly Gifts Remaining</span>
                                </div>
                                <div class="dash-feature-row">
                                    <i class="fa-solid fa-palette"></i>
                                    <span>Custom CSS Injection Unlocked</span>
                                </div>
                            </section>

                            <!-- Gift Players Section -->
                            <section class="dash-card">
                                <h3><i class="fa-solid fa-gift"></i> Gift Players (<?php echo $pro_gifts_count; ?> available)</h3>
                                <?php if ($is_gifted_pro): ?>
                                    <p class="card-hint" style="color: var(--error-color);">Gifted Pro members cannot gift others.</p>
                                <?php else: ?>
                                    <p class="card-hint">Gift a free week of Pro to any Loop member!</p>
                                    <div class="dash-item-group" style="margin-top: 15px;">
                                        <div class="gift-input-group" style="display: flex; gap: 10px;">
                                            <input type="text" id="giftTargetUsername" placeholder="Enter username..." class="dash-select" style="flex: 1; height: 45px;">
                                            <button id="sendGiftBtn" class="dash-toggle-btn active" style="height: 45px; padding: 0 25px;">Send</button>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </section>

                            <!-- Account & Billing -->
                            <section class="dash-card billing-card">
                                <h3><i class="fa-solid fa-credit-card"></i> Billing</h3>
                                <div class="billing-status">
                                    <span class="status-label">Monthly Plan</span>
                                    <span class="status-price">$9.99/mo</span>
                                </div>
                                <button class="stop-billing-btn">Stop billing</button>
                            </section>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Pro Checkout Modal -->
    <div class="pro-modal-overlay" id="proModalOverlay">
        <div class="pro-card-modal">
            <button class="pro-modal-close" id="proModalClose"><i class="fa-solid fa-xmark"></i></button>
            
            <div class="pro-modal-side-info">
                <div style="width: 70px; height: 70px; margin: 0 auto 30px; filter: drop-shadow(0 0 20px rgba(var(--accent-rgb), 0.3));">
                    <?php include('proicon.html'); ?>
                </div>
                <h2 class="pro-modal-title" style="text-align: center;">Pro Member</h2>
                <p class="pro-modal-subtitle" style="text-align: center;">You're activating your Monthly Subscription. Enjoy full access and 5k bonus XP.</p>
                
                <div style="margin-top: auto; padding-top: 30px; border-top: 1px solid rgba(255,255,255,0.05);">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="color: rgba(255,255,255,0.3); font-size: 14px; font-weight: 600; text-transform: uppercase;">Total</span>
                        <span style="color: white; font-weight: 900; font-size: 28px;">$9.99</span>
                    </div>
                </div>
            </div>
            
            <div class="pro-modal-side-form" style="display: flex; flex-direction: column; justify-content: flex-start; align-items: center; min-height: 400px; padding-top: 50px;">
                <div id="paypal-pro-container" style="width: 100%; max-width: 400px;"></div>
                <p style="margin-top: 20px; font-size: 13px; color: rgba(255,255,255,0.3); font-weight: 500;">Secure payment powered by PayPal</p>
            </div>
        </div>
    </div>

    <script src="theme.js"></script>
    <script src="popup.js"></script>
    <script src="notifications.js"></script>
    
    <script>
        const proOverlay = document.getElementById('proModalOverlay');
        const openBtn = document.getElementById('openProModal');
        const closeBtn = document.getElementById('proModalClose');

        if(openBtn) {
            openBtn.onclick = () => {
                proOverlay.classList.add('active');
                document.body.classList.add('modal-open');
                initProPayments();
            };
        }

        closeBtn.onclick = () => {
            proOverlay.classList.remove('active');
            document.body.classList.remove('modal-open');
        };

        function initProPayments() {
            // Render PayPal
            if(!document.querySelector('#paypal-pro-container iframe')) {
                paypal.Buttons({
                    style: { layout: 'vertical', color: 'gold', shape: 'pill', label: 'subscribe' },
                    createOrder: (data, actions) => {
                        return actions.order.create({
                            purchase_units: [{ amount: { value: '9.99' }, description: "Loop Pro Monthly" }]
                        });
                    },
                    onApprove: (data, actions) => {
                        return actions.order.capture().then(() => activatePro(data.orderID));
                    }
                }).render('#paypal-pro-container');
            }
        }

        function activatePro(orderID) {
            fetch('../backend/capture_pro_order.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ orderID: orderID })
            })
            .then(res => res.json())
            .then(res => {
                if (res.status === 'success') {
                    // Update local flag immediately
                    new Popup('Success', 'Loop Pro activated!', 'success');
                    setTimeout(() => {
                        window.location.href = 'pro.php?refresh=1';
                    }, 2000);
                } else {
                    new Popup('Payment Error', res.message || 'Activation failed', 'error');
                }
            })
            .catch(err => {
                console.error('Activation error:', err);
                new Popup('System Error', 'Could not complete activation.', 'error');
            });
        }

        // --- Pro Customization Functions ---
        
        function saveProSetting(setting, value) {
            fetch('../backend/save_pro_settings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ setting: setting, value: value })
            })
            .then(res => res.json())
            .then(res => {
                if(res.status === 'success') {
                    // Quietly saved or show small indicator if needed
                    console.log(`Setting ${setting} saved: ${value}`);
                }
            });
        }

        function updateLocalStorageWidgets() {
            const active = [];
            const wBtn = document.querySelector('#weatherToggle .dash-toggle-btn.active');
            const rBtn = document.querySelector('#resumeToggle .dash-toggle-btn.active');
            const tBtn = document.querySelector('#timeToggle .dash-toggle-btn.active');
            
            if(wBtn && wBtn.dataset.value === 'on') active.push('weather');
            if(rBtn && rBtn.dataset.value === 'on') active.push('resume');
            if(tBtn && tBtn.dataset.value === 'on') active.push('time');
            
            localStorage.setItem('flox_active_widgets', JSON.stringify(active));
            
            // Also update separate keys used by home.php checkWidgetVisibility
            if(wBtn) localStorage.setItem('flox_weather_visible', wBtn.dataset.value === 'on' ? 'true' : 'false');
            if(rBtn) localStorage.setItem('flox_resume_visible', rBtn.dataset.value === 'on' ? 'true' : 'false');
            if(tBtn) localStorage.setItem('flox_time_visible', tBtn.dataset.value === 'on' ? 'true' : 'false');
        }

        // Initialize Local Storage on Load based on DB values
        updateLocalStorageWidgets();

        // Weather Toggle
        document.querySelectorAll('#weatherToggle .dash-toggle-btn').forEach(btn => {
            btn.onclick = function() {
                document.querySelectorAll('#weatherToggle .dash-toggle-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                saveProSetting('weather_widget', this.dataset.value);
                updateLocalStorageWidgets();
            };
        });

        // Resume Toggle
        document.querySelectorAll('#resumeToggle .dash-toggle-btn').forEach(btn => {
            btn.onclick = function() {
                document.querySelectorAll('#resumeToggle .dash-toggle-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                saveProSetting('resume_widget', this.dataset.value);
                updateLocalStorageWidgets();
            };
        });

        // Time Toggle
        document.querySelectorAll('#timeToggle .dash-toggle-btn').forEach(btn => {
            btn.onclick = function() {
                document.querySelectorAll('#timeToggle .dash-toggle-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                saveProSetting('time_widget', this.dataset.value);
                updateLocalStorageWidgets();
            };
        });

        // Clock Type Toggle
        document.querySelectorAll('#clockTypeToggle .dash-toggle-btn').forEach(btn => {
            btn.onclick = function() {
                document.querySelectorAll('#clockTypeToggle .dash-toggle-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                const val = this.dataset.value;
                saveProSetting('clock_type', val);
                localStorage.setItem('flox_clock_type', val);
            };
        });

        // Name Badge Toggle
        document.querySelectorAll('#nameBadgeToggle .dash-toggle-btn').forEach(btn => {
            btn.onclick = function() {
                document.querySelectorAll('#nameBadgeToggle .dash-toggle-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                saveProSetting('name_badge', this.dataset.value);
            };
        });

        // Theme Select
        const themeSelect = document.getElementById('themeSelect');
        if(themeSelect) {
            themeSelect.onchange = function() {
                saveProSetting('theme', this.value);
                // Optionally apply theme immediately if global logic exists
                if(window.applyTheme) window.applyTheme(this.value);
            };
        }

        // Font Select
        const fontSelect = document.getElementById('fontSelect');
        if(fontSelect) {
            fontSelect.onchange = function() {
                saveProSetting('font', this.value);
                document.body.style.fontFamily = this.value === 'outfit' ? "'Outfit', sans-serif" : 
                                               this.value === 'inter' ? "'Inter', sans-serif" : 
                                               this.value === 'roboto' ? "'Roboto', sans-serif" : "'Playfair Display', serif";
            };
        }

        // Badge Picker
        document.querySelectorAll('#proBadgePicker .badge-item').forEach(item => {
            item.onclick = function() {
                document.querySelectorAll('#proBadgePicker .badge-item').forEach(i => i.classList.remove('active'));
                this.classList.add('active');
                saveProSetting('comment_badge', this.dataset.badge);
            };
        });

        // Stop Billing
        const stopBillingBtn = document.querySelector('.stop-billing-btn');
        if(stopBillingBtn) {
            stopBillingBtn.onclick = function() {
                if(true) {
                    this.disabled = true;
                    this.textContent = 'Processing...';
                    
                    fetch('../backend/cancel_pro_subscription.php', { method: 'POST' })
                    .then(res => res.json())
                    .then(res => {
                        if(res.status === 'success') {
                            new Popup('Subscription Canceled', 'Your Pro benefits will stop soon.', 'info');
                            setTimeout(() => location.reload(), 2000);
                        } else {
                            this.disabled = false;
                            this.textContent = 'Stop billing';
                        }
                    });
                }
            };
        }

        // Weather Toggle Handler - Show/Hide widget and refresh
        document.querySelectorAll('#weatherToggle .dash-toggle-btn').forEach(btn => {
            const originalHandler = btn.onclick;
            btn.onclick = function() {
                document.querySelectorAll('#weatherToggle .dash-toggle-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                saveProSetting('weather_widget', this.dataset.value);
            };
        });

        // Gift Logic
        const sendGiftBtn = document.getElementById('sendGiftBtn');
        const giftTargetInput = document.getElementById('giftTargetUsername');

        if(sendGiftBtn) {
            sendGiftBtn.onclick = async function() {
                const target = giftTargetInput.value.trim();
                if(!target) return new Popup('Error', 'Please enter a username', 'error');

                this.disabled = true;
                this.textContent = 'Sending...';

                try {
                    const res = await fetch('../backend/giftPro.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ username: target })
                    });
                    const data = await res.json();

                    if(data.success) {
                        new Popup('Success', 'Gift sent to ' + target, 'success');
                        setTimeout(() => location.reload(), 2000);
                    } else {
                        new Popup('Gift Error', data.message, 'error');
                        this.disabled = false;
                        this.textContent = 'Send';
                    }
                } catch(e) {
                    new Popup('System Error', 'Check your connection', 'error');
                    this.disabled = false;
                    this.textContent = 'Send';
                }
            };
        }
    </script>

</body>
</html>
