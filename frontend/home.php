<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);
session_start();

// Try to restore session if empty but remember cookie is present
if (!isset($_SESSION['user_id']) && isset($_COOKIE['floxwatch_remember']) && (!isset($_GET['guest']) || $_GET['guest'] !== '1')) {
    require_once __DIR__ . '/../backend/auth_helper.php';
    validateRememberToken(true);
}

// Handle explicit guest mode
if (isset($_GET['guest']) && $_GET['guest'] == '1') {
    // Clear ALL user session data to force guest mode
    session_unset();
    session_destroy();
    session_start();
    $_SESSION['is_guest'] = true;
    
    // Also clear the remember cookie if we want to be 100% guest
    // setcookie('floxwatch_remember', '', time() - 3600, '/', '', false, true);
}

// Allow guest browsing - no forced login redirect
$is_guest = !isset($_SESSION['user_id']);
?>
<?php
require_once __DIR__ . '/../backend/config.php';

// Check Pro Status & Settings
$is_pro = false;
$show_weather = false;
$show_time_widget = true; 
$clock_type = 'analog'; // Default clock type
$clock_weight = '800'; 
$clock_blur = '20';
$clock_no_box = 'off';
$clock_font_blur = '0';
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT is_pro FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $is_pro = $stmt->fetchColumn();

        if ($is_pro) {
            $stmt = $pdo->prepare("SELECT * FROM pro_settings WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $pro_settings = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($pro_settings) {
                // Safely assign settings, handling missing columns gracefully
                $show_weather = (isset($pro_settings['weather_widget']) && $pro_settings['weather_widget'] === 'on');
                $show_time_widget = (!isset($pro_settings['time_widget']) || $pro_settings['time_widget'] !== 'off');
                $clock_type = $pro_settings['clock_type'] ?? 'analog';
                $clock_weight = $pro_settings['clock_weight'] ?? '800';
                $clock_blur = $pro_settings['clock_blur'] ?? '20';
                $clock_no_box = $pro_settings['clock_no_box'] ?? 'off';
                $clock_font_blur = $pro_settings['clock_font_blur'] ?? '0';
            }
        }
    } catch (Exception $e) {
        // Ignore errors
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" user-scalable="no"/>
    <title>Loop - Home</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="home.css?v=<?php echo time(); ?>_force_refresh">
    <link rel="stylesheet" href="layout.css?v=<?php echo time(); ?>_force_refresh">
    <link rel="stylesheet" href="announcements.css?v=<?php echo time(); ?>_force_refresh">
  
    <style>
        :root {
            --bg-primary: #020205;
            --bg-sidebar: rgba(10, 10, 20, 0.4);
            --accent: #0071e3;
            --accent-purple: #9d00ff;
            --card-bg: rgba(255, 255, 255, 0.03);
            --glass-border: rgba(255, 255, 255, 0.12);
            --text-glow: 0 0 10px rgba(0, 113, 227, 0.5);
        }

        body {
            background: #000 !important;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            height: 100vh;
        }

        /* Starfield Background */
        .home-bg-canvas {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            z-index: 0;
            pointer-events: none;
            background: #000;
        }

        /* Fixed Glass Header Overrides */
        .top-nav {
            position: absolute !important;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 2000;
            background: rgba(2, 2, 5, 0.2) !important; /* Lighter for better glass effect */
            border-bottom: none !important;
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
        }

        .app-layout {
            position: relative;
            z-index: 1;
            background: transparent !important;
            flex: 1;
            display: flex;
            overflow: hidden;
            min-height: 0;
            padding-top: 0 !important;
            height: 100vh;
        }

        .main-content {
            flex: 1;
            height: 100%;
            position: relative;
            display: block; /* Changed from flex to block for better flow */
            overflow-y: auto;
            /* Default fallback increased to 120px to account for chips */
            padding-top: 40px !important; /* Reduced to move content HIGHER */
            scroll-padding-top: 40px;
        }

        /* Override sidebar for overlay layout */
        .side-nav {
            position: relative !important;
            top: 0 !important;
            height: 100% !important;
            max-height: 100%;
            padding-top: 40px !important; /* Reduced to move items UP per user request */
            border-right: none !important;
        }

        /* FORCE SIDEBAR ITEMS WHITE & ALIGNED (Hotfix) */
        .side-nav-item {
            color: #fff !important;
            margin-top: 0 !important;
        }

        /* Hero Cinema Section */
        .hero-cinema {
            position: relative;
            width: 100%;
            height: 480px;
            margin-bottom: 40px;
            border-radius: 0 0 40px 40px;
            overflow: hidden;
            display: flex;
            align-items: flex-end;
            padding: 60px;
            background: #000;
        }

        .hero-backdrop {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            object-fit: cover;
            opacity: 0.4;
            filter: blur(5px);
            transition: 0.5s ease;
        }

        .hero-cinema:hover .hero-backdrop {
            opacity: 0.6;
            filter: blur(0);
        }

        .hero-gradient {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            background: linear-gradient(to top, var(--bg-primary) 5%, transparent 60%);
            z-index: 1;
        }

        .hero-content {
            position: relative;
            z-index: 2;
            max-width: 800px;
            animation: heroSlideUp 0.8s cubic-bezier(0.2, 0, 0.2, 1);
        }

        @keyframes heroSlideUp {
            from { opacity: 0; transform: translateY(40px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .hero-tag {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(0, 242, 255, 0.1);
            border: 1px solid var(--accent);
            color: var(--accent);
            padding: 6px 14px;
            border-radius: 100px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 20px;
        }

        .hero-title {
            font-size: 48px;
            font-weight: 900;
            margin-bottom: 16px;
            line-height: 1.1;
            text-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }

        .hero-meta {
            display: flex;
            align-items: center;
            gap: 20px;
            color: rgba(255,255,255,0.6);
            font-size: 15px;
            margin-bottom: 24px;
        }

        .hero-btns {
            display: flex;
            gap: 16px;
        }

        .btn-watch {
            background: var(--accent);
            color: #000;
            padding: 14px 32px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: 0.3s;
        }

        .btn-watch:hover {
            transform: translateY(-3px);
            box-shadow: 0 0 30px rgba(0, 242, 255, 0.4);
        }

        .btn-info {
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            color: #fff;
            padding: 14px 32px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            transition: 0.3s;
        }

        .btn-info:hover {
            background: rgba(255,255,255,0.2);
        }

        /* Premium Section Headers */
        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin: 0 40px 24px;
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 22px;
            font-weight: 800;
            color: #fff;
        }

        .section-title i {
            color: var(--accent);
            text-shadow: var(--text-glow);
        }

        @media (max-width: 768px) {
            .hero-cinema { height: 400px; padding: 30px; }
            .hero-title { font-size: 32px; }
            .hero-meta { font-size: 13px; }
            .section-header { margin: 0 20px 20px; }
        }
    </style>

</head>
    <canvas id="homeBgCanvas" class="home-bg-canvas"></canvas>

    <!-- Top Navigation Bar -->
    <?php include 'header.php'; ?>

    <div class="app-layout">
        <!-- Sidebar Navigation -->
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content" id="mainContent" data-flox="home.main">
            
            <!-- FEATURED HERO SECTION -->
            <section class="hero-cinema" id="heroCinema" style="display: none;">
                <img src="" class="hero-backdrop" id="heroBackdrop">
                <div class="hero-gradient"></div>
                <div class="hero-content">
                    <div class="hero-tag">
                        <i class="fa-solid fa-fire"></i>
                        <span>Featured Video</span>
                    </div>
                    <h1 class="hero-title" id="heroTitle">--</h1>
                    <div class="hero-meta">
                        <span id="heroAuthor">--</span>
                        <span>•</span>
                        <span id="heroViews">-- views</span>
                    </div>
                    <div class="hero-btns">
                        <a href="#" class="btn-watch" id="heroWatchBtn">
                            <i class="fa-solid fa-play"></i> Watch Now
                        </a>
                        <a href="#" class="btn-info" id="heroInfoBtn">More Info</a>
                    </div>
                </div>
            </section>

            <?php if (isset($is_pro) && $is_pro): ?>
            
            <!-- PRO WIDGETS ROW -->
            <div class="pro-widgets-row" id="proWidgetsRow" style="display: flex;">
                <!-- RESUME WIDGET (Pro) -->
                <div id="homeResumeWidget" class="resume-widget draggable-widget">
                    <div class="resume-thumbnail">
                        <img src="" id="resumeThumb" alt="Last Watched">
                        <div class="resume-controls">
                            <button class="ctrl-btn" id="resumePrev"><i class="fa-solid fa-backward-step"></i></button>
                            <button class="ctrl-btn play-btn" id="resumePlay"><i class="fa-solid fa-play"></i></button>
                            <button class="ctrl-btn" id="resumeNext"><i class="fa-solid fa-forward-step"></i></button>
                        </div>
                    </div>
                    
                    <div class="resume-content">
                        <div class="resume-title" id="resumeTitle">Loading...</div>
                        <div class="resume-channel" id="resumeChannel">
                            <img src="" id="resumeChannelAvatar" style="width:16px; height:16px; border-radius:50%; display:none;">
                            <span id="resumeChannelName">--</span>
                        </div>
                        
                        <div class="resume-progress-container">
                             <div class="resume-time" id="resumeTimeCurrent">00:00</div>
                             <div class="resume-progress-track">
                                  <div class="resume-progress-bar" id="resumeProgressBar"></div>
                             </div>
                             <div class="resume-time" id="resumeTimeTotal">00:00</div>
                        </div>
                    </div>

                    <!-- Edit Toggle & Handles -->
                    <button class="widget-edit-toggle" id="resumeEditToggle"><i class="fa-solid fa-pencil"></i></button>
                    <div class="widget-controls">
                        <div class="drag-handle"><i class="fa-solid fa-arrows-up-down-left-right"></i> Drag</div>
                        <div class="resize-handle"></div>
                        <button class="reset-layout-btn" title="Reset Layout"><i class="fa-solid fa-rotate-left"></i> Reset</button>
                    </div>
                </div>

                <!-- WEATHER WIDGET (Pro) -->
                <div id="homeWeatherWidget" class="home-weather-widget draggable-widget">
                    <div class="weather-widget-loading" id="homeWeatherLoading">
                        <div class="weather-loader"></div>
                        <span>Loading weather...</span>
                    </div>
                    <div class="weather-widget-content" id="homeWeatherContent" style="display: none;">
                        <div class="weather-main">
                            <div class="weather-icon-container" id="homeWeatherIconContainer">
                                <i class="fa-solid fa-sun" id="homeWeatherIcon"></i>
                            </div>
                            <div class="weather-temp-container">
                                <span class="weather-temp" id="homeWeatherTemp">--</span>
                                <span class="weather-unit">°C</span>
                            </div>
                        </div>
                        <div class="weather-details">
                            <div class="weather-location" id="homeWeatherLocation">
                                <i class="fa-solid fa-location-dot"></i>
                                <span>Detecting...</span>
                            </div>
                            <div class="weather-condition" id="homeWeatherCondition">--</div>
                        </div>
                        <div class="weather-stats">
                            <div class="weather-stat">
                                <i class="fa-solid fa-droplet"></i>
                                <span id="homeWeatherHumidity">--%</span>
                                <label>Humidity</label>
                            </div>
                            <div class="weather-stat">
                                <i class="fa-solid fa-wind"></i>
                                <span id="homeWeatherWind">-- km/h</span>
                                <label>Wind</label>
                            </div>
                            <div class="weather-stat">
                                <i class="fa-solid fa-eye"></i>
                                <span id="homeWeatherVisibility">-- km</span>
                                <label>Visibility</label>
                            </div>
                        </div>
                        <div class="weather-forecast" id="homeWeatherForecast">
                            <!-- Forecast injected via JS -->
                        </div>
                    </div>

                    <!-- Edit Toggle & Handles -->
                    <button class="widget-edit-toggle" id="weatherEditToggle" title="Customize Layout"><i class="fa-solid fa-pencil"></i></button>
                    <div class="widget-controls">
                        <div class="drag-handle"><i class="fa-solid fa-arrows-up-down-left-right"></i> Drag</div>
                        <div class="resize-handle"></div>
                        <button class="reset-layout-btn" title="Reset Layout"><i class="fa-solid fa-rotate-left"></i> Reset</button>
                    </div>
                </div>

                <!-- TIME WIDGET (Pro) -->
                <div id="homeTimeWidget" class="home-time-widget draggable-widget 
                    <?php echo ($clock_type === 'digital') ? 'digital-mode' : 'analog-mode'; ?>
                    <?php echo ($clock_no_box === 'on') ? 'no-box-mode' : ''; ?>"
                    data-weight="<?php echo $clock_weight; ?>"
                    data-blur="<?php echo $clock_blur; ?>"
                    data-nobox="<?php echo $clock_no_box; ?>"
                    data-fontblur="<?php echo $clock_font_blur; ?>">
                    
                    <div class="time-widget-content">
                        <!-- Digital Face -->
                        <div class="digital-clock" id="digitalClock" style="<?php echo ($clock_type === 'digital') ? '' : 'display:none;'; ?>">
                            <span class="time-display <?php echo ($clock_font_blur > 0 ? 'glass-mode' : ''); ?>" id="homeTimeDisplay" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif !important; font-weight: <?php echo $clock_weight; ?>; transition: none !important; opacity: <?php echo ($clock_font_blur > 0 ? max(0.1, 1 - ($clock_font_blur / 25)) : '1'); ?>; text-shadow: <?php echo ($clock_font_blur > 0 ? '0 0 '.$clock_font_blur.'px rgba(255, 255, 255, 0.8)' : '0 10px 30px rgba(0, 0, 0, 0.4)'); ?>;">00:00</span>
                        </div>
                        <!-- Analog Face -->
                        <div class="analog-clock" id="analogClock" style="<?php echo ($clock_type === 'analog') ? '' : 'display:none;'; ?>">
                            <div class="clock-face" style="backdrop-filter: blur(<?php echo $clock_blur; ?>px); -webkit-backdrop-filter: blur(<?php echo $clock_blur; ?>px);">
                                <div class="marker m12"></div><div class="marker m3"></div><div class="marker m6"></div><div class="marker m9"></div>
                                <div class="hand hour-hand" id="hourHand"></div>
                                <div class="hand minute-hand" id="minuteHand"></div>
                                <div class="hand second-hand" id="secondHand"></div>
                                <div class="center-dot"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Edit Toggle & Handles -->
                    <button class="widget-edit-toggle" id="timeEditToggle" title="Customize Layout"><i class="fa-solid fa-pencil"></i></button>
                    
                    <div class="widget-controls">
                        <!-- Clock Specific Adjustments Panel -->
                        <div class="clock-adjustment-panel">
                            <div class="adj-group">
                                <label><i class="fa-solid fa-font"></i> Weight</label>
                                <input type="range" id="adjClockWeight" min="100" max="900" step="100" value="<?php echo $clock_weight; ?>">
                            </div>
                            <div class="adj-group">
                                <label><i class="fa-solid fa-droplet"></i> Glass</label>
                                <input type="range" id="adjClockBlur" min="0" max="100" step="1" value="<?php echo $clock_blur; ?>">
                            </div>
                            <div class="adj-group">
                                <label><i class="fa-solid fa-wand-magic-sparkles"></i> Font Glass</label>
                                <input type="range" id="adjClockFontBlur" min="0" max="20" step="0.5" value="<?php echo $clock_font_blur; ?>">
                            </div>
                            <div class="adj-group">
                                <label><i class="fa-solid fa-square"></i> Box</label>
                                <button id="adjClockNoBox" class="adj-toggle-btn <?php echo ($clock_no_box === 'on') ? 'active' : ''; ?>">
                                    <i class="fa-solid <?php echo ($clock_no_box === 'on') ? 'fa-toggle-on' : 'fa-toggle-off'; ?>"></i>
                                </button>
                            </div>
                            <div class="adj-group">
                                <label><i class="fa-solid fa-clock"></i> Style</label>
                                <button id="adjClockType" class="adj-toggle-btn">
                                    <i class="fa-solid fa-repeat"></i>
                                </button>
                            </div>
                        </div>

                        <div class="drag-handle"><i class="fa-solid fa-arrows-up-down-left-right"></i> Drag</div>
                        <div class="resize-handle"></div>
                        <button class="reset-layout-btn" title="Reset Layout"><i class="fa-solid fa-rotate-left"></i> Reset</button>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="videos-container" id="videosContainer">
                
                <!-- LIVE STREAMS SECTION -->
                <div id="liveStreamsSection" style="display: none; margin-bottom: 40px;">
                    <div class="grid-section-header" style="color: #0071e3; display: flex; align-items: center; gap: 10px;">
                        <div style="width: 8px; height: 8px; background: #0071e3; border-radius: 50%; box-shadow: 0 0 10px #0071e3; animation: pulse 1.5s infinite;"></div>
                        LIVE STREAMS
                    </div>
                    <div class="videos-grid" id="liveStreamsGrid" style="margin-top: 20px;">
                        <!-- Live items injected here -->
                    </div>
                </div>

                <!-- CLIPS SECTION (Moved to JS Flow) -->
                <div id="homeClipsSectionShell" style="display: none;">
                    <div class="home-clips-section" id="homeClipsSection">
                        <div class="clips-header">
                            <div class="clips-title-group">
                                <i class="fa-solid fa-clapperboard"></i> 
                                <span>Clips</span>
                            </div>
                        </div>
                        <div class="clips-scroll-wrapper">
                            <div class="clips-track" id="clipsTrack">
                                <!-- Clips injected here -->
                            </div>
                        </div>
                    </div>
                </div>

                <div class="loading-spinner" id="loadingSpinner">
                    <div class="spinner"></div>
                    <p>Loading videos...</p>
                </div>
                <div class="videos-grid" id="videosGrid" data-flox="chat.panel"></div>
                <div class="empty-state" id="emptyState" style="display: none;">
                    <i class="fa-solid fa-film"></i>
                    <h2>No videos yet</h2>
                    <p>Be the first to create one and start the trend!</p>
                    <a href="upload_video.php" class="empty-create-btn" style="text-decoration: none;">Upload Video</a>
                </div>
            </div>
        </main>
    </div>

    <!-- Mobile Footer Navigation -->
    <?php include 'mobile_footer.php'; ?>

    <!-- existing scripts removed from here and consolidated at the bottom -->

    <!-- inline date + battery script -->
    <script>
    (function() {
        // Starfield Engine
        const canvas = document.getElementById('homeBgCanvas');
        const ctx = canvas.getContext('2d');
        let stars = [];
        const starCount = 200;

        function resize() {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
            // Only re-init if really resized to avoid flicker
            if(stars.length === 0) initStars();
        }

        function initStars() {
            stars = [];
            for (let i = 0; i < starCount; i++) {
                stars.push({
                    x: Math.random() * canvas.width,
                    y: Math.random() * canvas.height,
                    size: Math.random() * 1.5,
                    speed: Math.random() * 0.04,
                    opacity: Math.random(),
                    driftX: (Math.random() - 0.5) * 0.02
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
                star.x += star.driftX;
                
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
    })();

    (function() {
        const dateLine = document.getElementById('dateLine');
        const batteryLine = document.getElementById('batteryLine');
        if(!dateLine) return;

        function getPadded(n) { return String(n).padStart(2, '0'); }

        function updateDate() {
            const now = new Date();
            const days = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
            const day = days[now.getDay()];
            const mm = getPadded(now.getMonth() + 1);
            const dd = getPadded(now.getDate());
            const hh = getPadded(now.getHours());
            const min = getPadded(now.getMinutes());
            dateLine.textContent = `${day} ${mm}.${dd} ${hh}:${min}`;
        }

        async function updateBattery() {
            if (!('getBattery' in navigator)) return;
            try {
                const b = await navigator.getBattery();
                const render = () => {
                    const pct = Math.round(b.level * 100);
                    const icon = b.charging ? "bi-battery-charging" : (pct < 20 ? "bi-battery" : "bi-battery-full");
                    batteryLine.innerHTML = `<i class="bi ${icon}"></i> ${pct}%`;
                };
                render();
                b.onlevelchange = render;
                b.onchargingchange = render;
            } catch(e) {}
        }

        updateDate();
        updateBattery();
        setInterval(updateDate, 30000);
    })();
    // Pro Weather Widget Logic (Simplified - No Drag)
    (function() {
        const widget = document.getElementById('homeWeatherWidget');
        if(!widget) return;
        
        // --- Weather Fetch Logic ---
        async function initWeather() {
             try {
                const loading = document.getElementById('homeWeatherLoading');
                const content = document.getElementById('homeWeatherContent');
                
                let latitude, longitude, city = 'Local Weather';
                
                try {
                     // 1. Try GPS
                     const pos = await new Promise((resolve, reject) => {
                         navigator.geolocation.getCurrentPosition(resolve, reject, {timeout: 4000});
                     });
                     latitude = pos.coords.latitude;
                     longitude = pos.coords.longitude;
                     
                     // 2. Get City Name via Backend Proxy to avoid CORS
                     try {
                        let locRes = await fetch('../backend/reverse_geocode.php?lat=' + latitude + '&lon=' + longitude);
                        if(!locRes.ok) locRes = await fetch('backend/reverse_geocode.php?lat=' + latitude + '&lon=' + longitude);
                        
                        const locData = await locRes.json();
                        city = locData.address.city || locData.address.town || locData.address.village || city;
                     } catch(e) { console.warn("City fetch failed", e); }

                } catch(err) {
                     // 1b. Fallback to IP-API
                     console.log("GPS failed, trying IP Geolocation...");
                     try {
                         const ipRes = await fetch('http://ip-api.com/json/');
                         const ipData = await ipRes.json();
                         latitude = ipData.lat;
                         longitude = ipData.lon;
                         city = ipData.city || city;
                     } catch(ipErr) {
                         console.warn("IP Geo failed", ipErr);
                         latitude = 40.71; longitude = -74.00;
                     }
                }
                
                const url = `https://api.open-meteo.com/v1/forecast?latitude=${latitude}&longitude=${longitude}&current=temperature_2m,relative_humidity_2m,weather_code,wind_speed_10m,visibility&daily=weather_code,temperature_2m_max&timezone=auto&forecast_days=5`;
                const res = await fetch(url);
                const data = await res.json();

                function getCond(code) {
                    const map = {
                        0: { t: 'Clear', i: 'fa-sun', c: 'sunny' },
                        1: { t: 'Mostly Clear', i: 'fa-sun', c: 'sunny' },
                        2: { t: 'Partly Cloudy', i: 'fa-cloud-sun', c: 'cloudy' },
                        3: { t: 'Overcast', i: 'fa-cloud', c: 'cloudy' },
                        45: { t: 'Fog', i: 'fa-smog', c: 'foggy' },
                        51: { t: 'Drizzle', i: 'fa-cloud-rain', c: 'rainy' },
                        61: { t: 'Rain', i: 'fa-cloud-rain', c: 'rainy' },
                        63: { t: 'Heavy Rain', i: 'fa-cloud-showers-heavy', c: 'rainy' },
                        71: { t: 'Snow', i: 'fa-snowflake', c: 'snowy' },
                        95: { t: 'Storm', i: 'fa-bolt', c: 'stormy' }
                    };
                    return map[code] || { t: 'Cloudy', i: 'fa-cloud', c: 'cloudy' };
                }

                const current = data.current;
                const cond = getCond(current.weather_code);

                if(document.getElementById('homeWeatherTemp')) {
                    document.getElementById('homeWeatherTemp').textContent = Math.round(current.temperature_2m);
                    document.getElementById('homeWeatherLocation').innerHTML = `<i class="fa-solid fa-location-dot"></i> ${city}`;
                    document.getElementById('homeWeatherCondition').textContent = cond.t;
                    
                    const iconCont = document.getElementById('homeWeatherIconContainer');
                    iconCont.className = `weather-icon-container ${cond.c}`;
                    document.getElementById('homeWeatherIcon').className = `fa-solid ${cond.i}`;

                    document.getElementById('homeWeatherHumidity').textContent = current.relative_humidity_2m + '%';
                    document.getElementById('homeWeatherWind').textContent = Math.round(current.wind_speed_10m) + ' km/h';
                    document.getElementById('homeWeatherVisibility').textContent = Math.round(current.visibility/1000) + ' km';

                    const forecastEl = document.getElementById('homeWeatherForecast');
                    const days = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
                    
                    forecastEl.innerHTML = data.daily.time.map((d, i) => {
                        const date = new Date(d);
                        const dayName = i === 0 ? 'Today' : days[date.getDay()];
                        const dayCond = getCond(data.daily.weather_code[i]);
                        return `
                            <div class="forecast-day">
                                <span class="forecast-day-name">${dayName}</span>
                                <i class="fa-solid ${dayCond.i}"></i>
                                <span class="forecast-temp">${Math.round(data.daily.temperature_2m_max[i])}°</span>
                            </div>
                        `;
                    }).join('');
                }

                if(loading) loading.style.display = 'none';
                if(content) content.style.display = 'block';
                
            } catch(e) {
                console.error('Weather error', e);
                const loading = document.getElementById('homeWeatherLoading');
                const content = document.getElementById('homeWeatherContent');
                if(loading) loading.style.display = 'none';
                if(content) {
                    content.style.display = 'block';
                    const cond = document.getElementById('homeWeatherCondition');
                    if(cond) cond.textContent = "Unavailable";
                }
            }
        }
        
        initWeather();
    })();

    // Pro Time Widget Logic (iOS 26 Style)
    (function() {
        const widget = document.getElementById('homeTimeWidget');
        if(!widget) return;

        const digitalClock = document.getElementById('digitalClock');
        const analogClock = document.getElementById('analogClock');
        const timeDisplay = document.getElementById('homeTimeDisplay');
        const hourHand = document.getElementById('hourHand');
        const minuteHand = document.getElementById('minuteHand');
        const secondHand = document.getElementById('secondHand');

        function syncClockType() {
            const savedType = localStorage.getItem('flox_clock_type') || '<?php echo $clock_type; ?>';
            if(savedType === 'digital') {
                widget.classList.add('digital-mode');
                widget.classList.remove('analog-mode');
                if(digitalClock) digitalClock.style.display = 'flex';
                if(analogClock) analogClock.style.display = 'none';
            } else {
                widget.classList.add('analog-mode');
                widget.classList.remove('digital-mode');
                if(digitalClock) digitalClock.style.display = 'none';
                if(analogClock) analogClock.style.display = 'flex';
            }
        }

        function updateClocks() {
            const now = new Date();
            const h = now.getHours();
            const m = now.getMinutes();
            const s = now.getSeconds();

            // Digital
            if(timeDisplay) {
                const displayH = h % 12 || 12;
                const displayM = String(m).padStart(2, '0');
                if(timeDisplay.textContent !== `${displayH}:${displayM}`) {
                    timeDisplay.textContent = `${displayH}:${displayM}`;
                }
                
                // Keep glass effect synced on update if applied
                const gVal = parseFloat(widget.getAttribute('data-fontblur') || '0');
                if(gVal > 0) {
                     timeDisplay.classList.add('glass-mode');
                     const opacity = Math.max(0.1, 1 - (gVal / 25));
                     timeDisplay.style.opacity = opacity;
                     timeDisplay.style.textShadow = `0 0 ${gVal}px rgba(255, 255, 255, 0.8)`;
                } else {
                     timeDisplay.classList.remove('glass-mode');
                }
            }

            // Analog
            if(hourHand && minuteHand && secondHand) {
                const secDeg = (s / 60) * 360;
                const minDeg = ((m + s / 60) / 60) * 360;
                const hourDeg = ((h % 12 + m / 60) / 12) * 360;

                secondHand.style.transform = `rotate(${secDeg}deg)`;
                minuteHand.style.transform = `rotate(${minDeg}deg)`;
                hourHand.style.transform = `rotate(${hourDeg}deg)`;
            }
        }

        // Font scaling based on widget size
        let roThrottle = null;
        new ResizeObserver(entries => {
            // We now allow resizing updates even while 'dragging' class is present
            // but ONLY for the visual font scaling. This ensures real-time feedback.
            if(roThrottle) return;
            roThrottle = requestAnimationFrame(() => {
                for(let entry of entries) {
                    const w = entry.contentRect.width;
                    const h = entry.contentRect.height;
                    if(w === 0 || h === 0) continue;

                    if(widget.classList.contains('digital-mode') && timeDisplay) {
                        // NO LIMITS: Font height is now strictly tied to pixel height
                        // ScaleX adjusts dynamically to fit width without any caps
                        const fontSize = h * 0.9; 
                        const baseRatio = 2.0; 
                        const projectedW = fontSize * baseRatio;
                        let scaleX = w / projectedW;
                        
                        timeDisplay.style.fontSize = Math.floor(fontSize) + 'px';
                        timeDisplay.style.transform = `scaleX(${scaleX.toFixed(3)})`;
                        timeDisplay.style.lineHeight = "1";
                    }
                }
                roThrottle = null;
            });
        }).observe(widget);

        setInterval(updateClocks, 1000);
        updateClocks();
        syncClockType();

        // --- Customization Logic ---
        const adjWeight = document.getElementById('adjClockWeight');
        const adjBlur = document.getElementById('adjClockBlur');
        const adjNoBox = document.getElementById('adjClockNoBox');
        const adjFontBlur = document.getElementById('adjClockFontBlur');

        // Apply initial from data attrs
        const initialWeight = widget.getAttribute('data-weight') || '800';
        const initialBlur = widget.getAttribute('data-blur') || '20';
        const initialFontBlur = widget.getAttribute('data-fontblur') || '0';
        document.documentElement.style.setProperty('--clock-weight', initialWeight);
        document.documentElement.style.setProperty('--clock-blur', initialBlur + 'px');
        document.documentElement.style.setProperty('--clock-font-blur', initialFontBlur + 'px');
        document.documentElement.style.setProperty('--clock-font-opacity', initialFontBlur > 0 ? '0.7' : '1');

        function saveAdj(setting, value) {
            fetch('../backend/save_pro_settings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ setting, value })
            });
            // Update local state to prevent jump on refresh
            let attrKey = setting.replace('clock_', '').replace('_', '');
            widget.setAttribute('data-' + attrKey, value);
        }

        if(adjWeight) {
            adjWeight.oninput = (e) => {
                const val = e.target.value;
                document.documentElement.style.setProperty('--clock-weight', val);
                if(timeDisplay) timeDisplay.style.fontWeight = val;
                widget.setAttribute('data-weight', val);
            };
            adjWeight.onchange = (e) => saveAdj('clock_weight', e.target.value);
        }

        if(adjBlur) {
            adjBlur.oninput = (e) => {
                const val = e.target.value;
                const faces = document.querySelectorAll('.clock-face');
                document.documentElement.style.setProperty('--clock-blur', val + 'px');
                faces.forEach(f => {
                    f.style.backdropFilter = `blur(${val}px)`;
                    f.style.webkitBackdropFilter = `blur(${val}px)`;
                });
                widget.setAttribute('data-blur', val);
            };
            adjBlur.onchange = (e) => saveAdj('clock_blur', e.target.value);
        }

        if(adjFontBlur) {
            adjFontBlur.oninput = (e) => {
                const val = parseFloat(e.target.value);
                document.documentElement.style.setProperty('--clock-font-blur', val + 'px');
                widget.setAttribute('data-fontblur', val);
                
                if(timeDisplay) {
                    if (val > 0) {
                        timeDisplay.classList.add('glass-mode');
                        const opacity = Math.max(0.1, 1 - (val / 25));
                        timeDisplay.style.opacity = opacity;
                        timeDisplay.style.textShadow = `0 0 ${val}px rgba(255, 255, 255, 0.8)`;
                    } else {
                        timeDisplay.classList.remove('glass-mode');
                        timeDisplay.style.opacity = 1;
                        timeDisplay.style.textShadow = '0 10px 30px rgba(0, 0, 0, 0.4)'; 
                    }
                }
            };
            adjFontBlur.onchange = (e) => saveAdj('clock_font_blur', e.target.value);
        }

        if(adjNoBox) {
            adjNoBox.onclick = () => {
                const isActive = adjNoBox.classList.toggle('active');
                const val = isActive ? 'on' : 'off';
                widget.classList.toggle('no-box-mode', isActive);
                adjNoBox.querySelector('i').className = isActive ? 'fa-solid fa-toggle-on' : 'fa-solid fa-toggle-off';
                saveAdj('clock_no_box', val);
                widget.setAttribute('data-nobox', val);
            };
        }

        const adjType = document.getElementById('adjClockType');
        if(adjType) {
            adjType.onclick = () => {
                const isDigital = widget.classList.contains('digital-mode');
                const nextType = isDigital ? 'analog' : 'digital';
                
                // Toggle classes
                widget.classList.toggle('digital-mode', !isDigital);
                widget.classList.toggle('analog-mode', isDigital);
                
                // Toggle visibility
                if(digitalClock) digitalClock.style.display = isDigital ? 'none' : 'flex';
                if(analogClock) analogClock.style.display = isDigital ? 'flex' : 'none';
                
                // Save and sync
                saveAdj('clock_type', nextType);
                localStorage.setItem('flox_clock_type', nextType);
            };
        }

        window.addEventListener('storage', (e) => {
            if(e.key === 'flox_clock_type') syncClockType();
        });
    })();

    </script>


    <script>
    // Resume Widget Logic (Simplified - No Drag)
    (function() {
        const widget = document.getElementById('homeResumeWidget');
        if(!widget) return;

        // --- Controls ---
        const thumb = document.getElementById('resumeThumb');
        const title = document.getElementById('resumeTitle');
        const channel = document.getElementById('resumeChannelName');
        const avatar = document.getElementById('resumeChannelAvatar');
        const progress = document.getElementById('resumeProgressBar');
        const timeCur = document.getElementById('resumeTimeCurrent');
        const timeTot = document.getElementById('resumeTimeTotal');
        const btnPlay = document.getElementById('resumePlay');
        const btnPrev = document.getElementById('resumePrev');
        const btnNext = document.getElementById('resumeNext');

        let history = [];
        let currentIndex = 0;
        
        // Layout is now handled by Unified Widget Manager v2

        // --- Utils ---
        function formatTime(s) {
            const m = Math.floor(s / 60);
            const sec = Math.floor(s % 60);
            return `${m}:${sec < 10 ? '0' : ''}${sec}`;
        }

        // Resize Observer
        new ResizeObserver(entries => {
            for(let entry of entries) {
                const w = entry.contentRect.width;
                const h = entry.contentRect.height;
                
                widget.classList.toggle('size-small', w < 240);
                widget.classList.toggle('size-medium', w >= 240 && w < 500);
                widget.classList.toggle('size-short', h < 180);
            }
        }).observe(widget);


        // --- Data Logic ---
        async function loadHistory() {
            try {
                // Direct path for current structure
                res = await fetch('../backend/getLastWatched.php');
                if(!res.ok) {
                    // Fallback for root-level access
                    res = await fetch('backend/getLastWatched.php');
                }
                const text = await res.text();
                let data;
                try {
                     data = JSON.parse(text);
                } catch(e) {
                     console.error("Resume Widget JSON Parse Error:", text);
                     throw e;
                }
                
                if(!data.success) {
                     console.error("Resume Widget Backend Response:", data);
                }

                if (data.success && data.history && data.history.length > 0) {
                    history = data.history;
                    updateUI(0);
                    window.hasResumeHistory = true;
                    widget.classList.remove('no-history');
                } else {
                    window.hasResumeHistory = false;
                    widget.classList.add('no-history');
                    // UI Update for Empty State
                    const title = document.getElementById('resumeTitle');
                    const channel = document.getElementById('resumeChannelName');
                    const thumb = document.getElementById('resumeThumb');
                    if(title) title.textContent = "No recent history";
                    if(channel) channel.textContent = "Watch some videos to see them here!";
                    if(thumb) thumb.style.display = 'none';
                }
                
                if(window.checkWidgetVisibility) window.checkWidgetVisibility();

            } catch (e) {
                console.error("Resume Widget Error", e);
                window.hasResumeHistory = false;
                
                // Show error state instead of stuck loading
                const title = document.getElementById('resumeTitle');
                if(title) title.textContent = "Unable to load history";
                
                if(window.checkWidgetVisibility) window.checkWidgetVisibility();
            }
        }

        function updateUI(index) {
            if (!history[index]) return;
            const item = history[index];
            currentIndex = index;

            thumb.src = item.thumbnail_path;
            thumb.style.display = 'block';
            title.textContent = item.title;
            channel.textContent = item.channel_name;
            if(item.channel_avatar) {
               avatar.src = item.channel_avatar;
               avatar.style.display = 'inline-block';
            } else {
               avatar.style.display = 'none';
            }

            const pct = (item.progress_seconds / item.duration_seconds) * 100;
            progress.style.width = pct + '%';
            timeCur.textContent = formatTime(item.progress_seconds);
            timeTot.textContent = formatTime(item.duration_seconds);

            // Nav State
            btnPrev.disabled = index >= history.length - 1;
            btnNext.disabled = index === 0;
            btnPrev.style.opacity = btnPrev.disabled ? 0.3 : 1;
            btnNext.style.opacity = btnNext.disabled ? 0.3 : 1;
        }

        // Actions
        btnPlay.onclick = () => {
            const item = history[currentIndex];
            if(item) {
                window.location.href = `videoid.php?id=${item.video_id}&t=${Math.floor(item.progress_seconds)}`;
            }
        };

        btnPrev.onclick = () => {
            if(currentIndex < history.length - 1) updateUI(currentIndex + 1);
        };

        btnNext.onclick = () => {
            if(currentIndex > 0) updateUI(currentIndex - 1);
        };

        loadHistory();

    })();
    </script>
    <script>
    // Widget Visibility Logic (Reads State from Database via Pro Settings)
    (function() {
        const weatherWidget = document.getElementById('homeWeatherWidget');
        const resumeWidget = document.getElementById('homeResumeWidget');
        
        window.hasResumeHistory = false; // Set by resume widget logic

        window.checkWidgetVisibility = function() {
              const row = document.getElementById('proWidgetsRow');
              
              const weatherVisible = localStorage.getItem('flox_weather_visible') !== 'false';
              const resumeVisible = localStorage.getItem('flox_resume_visible') !== 'false';
              const timeVisible = localStorage.getItem('flox_time_visible') !== 'false';
              
              const timeWidget = document.getElementById('homeTimeWidget');

              if(weatherWidget) {
                  weatherWidget.style.display = (weatherVisible && !window.weatherForceHide) ? 'block' : 'none';
              }
              
              if(resumeWidget) {
                  resumeWidget.style.display = (resumeVisible && !window.resumeForceHide) ? 'flex' : 'none';
                  if(resumeWidget.style.display === 'flex') resumeWidget.dispatchEvent(new Event('resize'));
              }

              if(timeWidget) {
                  timeWidget.style.display = (timeVisible && !window.timeForceHide) ? 'flex' : 'none';
              }

              if(row) {
                  const weatherVisibleInFlow = (weatherVisible && !window.weatherForceHide && (!weatherWidget || !weatherWidget.classList.contains('absolute-mode')));
                  const resumeVisibleInFlow = (resumeVisible && !window.resumeForceHide && (!resumeWidget || !resumeWidget.classList.contains('absolute-mode')));
                  const timeVisibleInFlow = (timeVisible && !window.timeForceHide && (!timeWidget || !timeWidget.classList.contains('absolute-mode')));
                  
                  row.style.display = (weatherVisibleInFlow || resumeVisibleInFlow || timeVisibleInFlow) ? 'flex' : 'none';
              }
        };

        window.checkWidgetVisibility();
        window.addEventListener('storage', (e) => {
            if(e.key.includes('_visible')) window.checkWidgetVisibility();
        });
    })();
    </script>
    <script>
    (function() {
        /**
         * Unified Widget Manager (Draggable & Resizable)
         * Integrated with CSS Grid for perfect displacement.
         */
        function initDraggable(id, storageKey) {
            const widget = document.getElementById(id);
            if (!widget) return;
            
            let toggle;
            if (id === 'homeResumeWidget') toggle = document.getElementById('resumeEditToggle');
            else if (id === 'homeWeatherWidget') toggle = document.getElementById('weatherEditToggle');
            else if (id === 'homeTimeWidget') toggle = document.getElementById('timeEditToggle');
            
            const dragHandle = widget.querySelector('.drag-handle');
            const mainContent = document.querySelector('.main-content');
            
            let isEditing = false;
            let isDragging = false;
            let isResizing = false;
            let initialX, initialY, startLeft, startTop;
            
            // Grid Guide (Invisible spacer that reserves the slot)
            const spacer = document.createElement('div');
            spacer.className = 'grid-spacer';
            spacer.style.display = 'none';
            spacer.id = id + 'Spacer';
            
            /**
             * Transition widget INTO the grid flow.
             * This prevents overlapping because the grid will displace videos.
             */
            function makeStatic(forcedCol, forcedRow, forcedColSpan, forcedRowSpan) {
                const grid = document.getElementById('videosGrid');
                if(!grid) return;
                
                const col = forcedCol || spacer.dataset.col || 1;
                const row = forcedRow || spacer.dataset.row || 1;
                const colSpan = forcedColSpan || spacer.dataset.colSpan || 1;
                const rowSpan = forcedRowSpan || spacer.dataset.rowSpan || 1;
                
                // Only move parent if actually different to prevent focus/blink issues
                if (widget.parentElement !== grid) {
                    grid.appendChild(widget);
                }

                widget.classList.remove('absolute-mode');
                widget.classList.add('static-mode');
                
                widget.style.position = 'relative';
                widget.style.left = 'auto';
                widget.style.top = 'auto';
                widget.style.gridColumn = `${col} / span ${colSpan}`;
                widget.style.gridRow = `${row} / span ${rowSpan}`;
                widget.style.zIndex = isEditing ? '2000' : '1'; 
                
                // Reset dimensions to let grid control them, but allow custom height if it was resized smaller than a full row
                widget.style.width = '100%';
                
                const hToApply = widget.dataset.targetHeight || (JSON.parse(localStorage.getItem(storageKey) || '{}').h);

                // If the widget is only 1 row tall, we allow it to be shorter than the actual row height
                if (rowSpan == 1 && hToApply) {
                    widget.style.height = hToApply;
                    widget.dataset.targetHeight = hToApply; // Sync back
                } else {
                    widget.style.height = '100%';
                }

                spacer.style.display = 'none';
            }

            /**
             * Lift widget OUT of the grid flow for smooth free dragging.
             */
            function makeAbsolute() {
                if(!widget.classList.contains('absolute-mode')) {
                    const rect = widget.getBoundingClientRect();
                    const parent = mainContent || document.body;
                    const pr = parent.getBoundingClientRect();
                    
                    const capturedLeft = rect.left - pr.left;
                    const capturedTop = rect.top - pr.top;
                    const capturedWidth = widget.offsetWidth;
                    const capturedHeight = widget.offsetHeight;
                    
                    widget.classList.remove('static-mode');
                    widget.classList.add('absolute-mode');
                    
                    if(parent && widget.parentElement !== parent) {
                        parent.appendChild(widget);
                    }
                    
                    widget.style.position = 'absolute';
                    widget.style.left = capturedLeft + 'px';
                    widget.style.top = capturedTop + 'px';
                    widget.style.width = capturedWidth + 'px';
                    widget.style.height = capturedHeight + 'px';
                    widget.style.gridColumn = '';
                    widget.style.gridRow = '';
                    widget.style.zIndex = '10005';
                }
            }

            function updateSpacer(forcedCol, forcedRow, forcedColSpan, forcedRowSpan) {
                const grid = document.getElementById('videosGrid'); 
                if(!grid) return;

                if (spacer.parentElement !== grid) {
                    grid.appendChild(spacer);
                }

                const gridRect = grid.getBoundingClientRect();
                const wRect = widget.getBoundingClientRect();
                
                // Detect Grid Geometry - Document relative for absolute stability
                const firstCard = grid.querySelector('.video-card:not(.grid-spacer)');
                // Robust detection: fall back to 320/300 if card is not fully rendered or found
                const cardW = (firstCard && firstCard.offsetWidth > 150) ? firstCard.offsetWidth : 320;
                const cardH = (firstCard && firstCard.offsetHeight > 150) ? firstCard.offsetHeight : 300;
                const gap = parseInt(getComputedStyle(grid).gap) || 8; 
                const gridColsStyle = getComputedStyle(grid).gridTemplateColumns;
                const columnsCount = gridColsStyle.split(' ').length || 1;

                let colIndex, rowIndex, colSpan, rowSpan;

                if (forcedCol !== undefined) {
                    colIndex = parseInt(forcedCol);
                    rowIndex = parseInt(forcedRow);
                    colSpan = parseInt(forcedColSpan) || 1;
                    rowSpan = parseInt(forcedRowSpan) || 1;
                } else {
                    // Viewport-relative center calculation for absolute stability
                    const centerX = wRect.left + (wRect.width / 2);
                    const centerY = wRect.top + (wRect.height / 2);
                    
                    // Standardize measurements (fallback to 320x300 if first card not ready)
                    const cardW_actual = (firstCard && firstCard.offsetWidth > 150) ? firstCard.offsetWidth : 320;
                    const cardH_actual = (firstCard && firstCard.offsetHeight > 150) ? firstCard.offsetHeight : 300;

                    // Grid origin in relative pixel coordinates (Scroll-independent because both are from getBoundingClientRect)
                    const targetCol = Math.max(1, Math.floor((centerX - gridRect.left) / (cardW_actual + gap)) + 1);
                    const targetRow = Math.max(1, Math.floor((centerY - gridRect.top) / (cardH_actual + gap)) + 1);

                    // High-stability hysteresis (100px dead-zone for extreme stability)
                    const currentC = parseInt(spacer.dataset.col) || targetCol;
                    const currentR = parseInt(spacer.dataset.row) || targetRow;
                    
                    if (isDragging && !isResizing) {
                        const currentCenterX = gridRect.left + (currentC - 1) * (cardW_actual + gap) + (cardW_actual / 2);
                        const currentCenterY = gridRect.top + (currentR - 1) * (cardH_actual + gap) + (cardH_actual / 2);
                        const dist = Math.sqrt(Math.pow(centerX - currentCenterX, 2) + Math.pow(centerY - currentCenterY, 2));
                        
                        if (dist < 40) { // Reduced hysteresis for better control
                            colIndex = currentC;
                            rowIndex = currentR;
                        } else {
                            colIndex = targetCol;
                            rowIndex = targetRow;
                        }
                    } else {
                        colIndex = targetCol;
                        rowIndex = targetRow;
                    }

                    // Lower rounding threshold for easier downsizing
                    // SENSITIVE RESIZING: Favor downsizing with a 0.7 threshold (Downsize bias)
                    colSpan = Math.min(columnsCount, Math.max(1, Math.ceil((wRect.width - (cardW_actual * 0.3)) / (cardW_actual + gap))));
                    rowSpan = Math.max(1, Math.ceil((wRect.height - (cardH_actual * 0.3)) / (cardH_actual + gap)));
                }

                if (colIndex + colSpan - 1 > columnsCount) {
                    colIndex = Math.max(columnsCount - colSpan + 1, 1);
                }

                // Internal state tracking to prevent flickering
                spacer.dataset.col = colIndex;
                spacer.dataset.row = rowIndex;
                spacer.dataset.colSpan = colSpan;
                spacer.dataset.rowSpan = rowSpan;

                spacer.style.display = 'block';
                spacer.style.gridColumn = `${colIndex} / span ${colSpan}`;
                spacer.style.gridRow = `${rowIndex} / span ${rowSpan}`;
                
                const targetW = (colSpan * (cardW_actual + gap)) - gap;
                let targetH = (rowSpan * (cardH_actual + gap)) - gap;
                
                // MICRO-RESIZE SYNC: If the widget is shorter than one row, 
                // match the spacer to the widget's height so it doesn't look "super big"
                if (rowSpan === 1 && isResizing) {
                    const currentH = widget.offsetHeight;
                    if (currentH < targetH) {
                        targetH = currentH;
                    }
                }

                spacer.style.width = targetW + 'px';
                spacer.style.height = targetH + 'px';

                if (isEditing || isDragging || isResizing) {
                    spacer.classList.add('edit-active');
                } else {
                    spacer.classList.remove('edit-active');
                }
            }
            
            function snapToGrid(forceSave = true) {
                 updateSpacer();
                 if(spacer.style.display !== 'none') {
                     const col = spacer.dataset.col;
                     const row = spacer.dataset.row;
                     const cs = spacer.dataset.colSpan;
                     const rs = spacer.dataset.rowSpan;

                      // FLUID SNAP ANIMATION
                      const sr = spacer.getBoundingClientRect();
                      const parent = widget.offsetParent || document.body;
                      const pr = parent.getBoundingClientRect();
                      
                      const targetW = sr.width;
                      let targetH = sr.height;

                      // If resizing and we are in row 1, we want to snap to the CURRENT height we resized to
                      if (rs == 1) {
                         const currentH = widget.offsetHeight;
                         if (currentH < sr.height) {
                             targetH = currentH;
                             widget.dataset.targetHeight = targetH + 'px';
                         } else {
                             widget.dataset.targetHeight = '';
                         }
                      } else {
                         widget.dataset.targetHeight = '';
                      }

                      widget.style.transition = 'all 0.5s cubic-bezier(0.16, 1, 0.3, 1)';
                      widget.style.left = (sr.left - pr.left) + 'px';
                      widget.style.top = (sr.top - pr.top) + 'px';
                      widget.style.width = targetW + 'px';
                      widget.style.height = targetH + 'px';

                      // Switch to static AFTER animation completes to avoid "blink"
                      setTimeout(() => {
                          if (!isDragging && !isResizing) {
                              makeStatic(col, row, cs, rs);
                              // We clean up transition after makeStatic to ensure it doesn't snap
                              requestAnimationFrame(() => {
                                  widget.style.transition = '';
                              });
                              if(forceSave) save();
                          }
                      }, 500);
                  }
             }
            function save() {
                const col = parseInt(spacer.dataset.col) || 1;
                const row = parseInt(spacer.dataset.row) || 1;
                const colSpan = parseInt(spacer.dataset.colSpan) || 1;
                const rowSpan = parseInt(spacer.dataset.rowSpan) || 1;
                const w = (widget.style.width && !widget.style.width.includes('%')) ? widget.style.width : null;
                const h = (widget.style.height && !widget.style.height.includes('%')) ? widget.style.height : null;

                localStorage.setItem(storageKey, JSON.stringify({
                    col, row, colSpan, rowSpan, w, h,
                    timestamp: Date.now()
                }));
            }

            // Restore with Retry (Ensures grid is alive)
            let restoreAttempts = 0;
            const restoreSlot = () => {
                const saved = localStorage.getItem(storageKey);
                if(!saved || isDragging || isResizing) return;
                
                const grid = document.getElementById('videosGrid');
                if(!grid || grid.offsetWidth < 100) {
                    if(restoreAttempts < 10) {
                        restoreAttempts++;
                        setTimeout(restoreSlot, 100);
                    }
                    return;
                }

                try {
                    const state = JSON.parse(saved);
                    
                    // Set spacer attributes directly to prepare ground
                    spacer.dataset.col = state.col;
                    spacer.dataset.row = state.row;
                    spacer.dataset.colSpan = state.colSpan;
                    spacer.dataset.rowSpan = state.rowSpan;
                    
                    // Restore size if saved
                    if(state.w) widget.style.width = state.w;
                    if(state.h) {
                        widget.style.height = state.h;
                        widget.dataset.targetHeight = state.h;
                    }
                    
                    // Force the widget into the grid container at the saved slot
                    makeStatic(state.col, state.row, state.colSpan, state.rowSpan);
                } catch(e) { 
                    console.error("Widget restore failed", e);
                }
            };

            // Events
            if(toggle) {
                toggle.onclick = () => {
                    isEditing = !isEditing;
                    widget.classList.toggle('edit-mode', isEditing);
                    toggle.classList.toggle('active', isEditing);
                    toggle.innerHTML = isEditing ? '<i class="fa-solid fa-check"></i>' : '<i class="fa-solid fa-pencil"></i>';
                    
                    if(!isEditing) {
                        save();
                        widget.style.zIndex = '1';
                        // Final fluid snap alignment
                        setTimeout(() => makeStatic(), 10);
                    } else {
                        widget.style.zIndex = '2000';
                    }
                    updateSpacer();
                };
            }

            dragHandle.onmousedown = (e) => {
                if(!isEditing) return;
                e.preventDefault();
                e.stopPropagation();
                
                makeAbsolute();
                isDragging = true;
                initialX = e.clientX;
                initialY = e.clientY;
                startLeft = widget.offsetLeft;
                startTop = widget.offsetTop;
                
                widget.style.transition = 'none';
                widget.classList.add('dragging');
                document.getElementById('videosGrid')?.classList.add('grid-dragging-active');

                document.onmousemove = (e) => {
                    if(!isDragging) return;
                    const dx = e.clientX - initialX;
                    const dy = e.clientY - initialY;
                    widget.style.left = (startLeft + dx) + 'px';
                    widget.style.top = (startTop + dy) + 'px';
                    updateSpacer();
                };

                document.onmouseup = () => {
                    isDragging = false;
                    document.onmousemove = null;
                    document.onmouseup = null;
                    widget.classList.remove('dragging');
                    document.getElementById('videosGrid')?.classList.remove('grid-dragging-active');
                    snapToGrid(true);
                };
            };

            const resizeHandle = widget.querySelector('.resize-handle');
            if(resizeHandle) {
                resizeHandle.onmousedown = (e) => {
                    if(!isEditing) return;
                    e.preventDefault();
                    e.stopPropagation();
                    
                    makeAbsolute();
                    isResizing = true;
                    initialX = e.clientX;
                    initialY = e.clientY;
                    const startW = widget.offsetWidth;
                    const startH = widget.offsetHeight;

                    document.onmousemove = (e) => {
                        if(!isResizing) return;
                        widget.style.width = (startW + (e.clientX - initialX)) + 'px';
                        widget.style.height = (startH + (e.clientY - initialY)) + 'px';
                        updateSpacer();
                    };

                    document.onmouseup = () => {
                        isResizing = false;
                        document.onmousemove = null;
                        document.onmouseup = null;
                        snapToGrid(true);
                    };
                };
            }

            // Persistence on load/render
            window.addEventListener('videosRendered', restoreSlot);
            window.addEventListener('resize', restoreSlot);
            setTimeout(restoreSlot, 100);
        }

        const runInit = () => {
            initDraggable('homeWeatherWidget', 'flox_weather_v2');
            initDraggable('homeResumeWidget', 'flox_resume_v2');
            initDraggable('homeTimeWidget', 'flox_time_v2');
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', runInit);
        } else {
            runInit();
        }
    })();
    </script>
    <script src="theme.js?v=<?php echo time(); ?>"></script>
    <script src="popup.js?v=<?php echo time(); ?>"></script>
    <script src="home.js?v=<?php echo time(); ?>"></script>
    <script src="search-history.js?v=<?php echo time(); ?>"></script>
    <script src="voice_search.js?v=<?php echo time(); ?>"></script>
    <script src="icon-replace.js?v=<?php echo time(); ?>"></script>
    <script src="notifications.js?v=<?php echo time(); ?>"></script>
    <script src="mobile-search.js?v=<?php echo time(); ?>"></script>
    <script src="announcements.js?v=<?php echo time(); ?>"></script>
</body>
</html>


