<?php
session_start();
require_once '../backend/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: loginb.php');
    exit;
}

$userId = $_SESSION['user_id'];
$displayName = $_SESSION['display_name'] ?? $_SESSION['username'] ?? 'User';

// Time-based greeting
$hour = (int)date('H');
if ($hour < 12) {
    $greeting = "Good morning";
} elseif ($hour < 18) {
    $greeting = "Good afternoon";
} else {
    $greeting = "Good evening";
}

// Fetch Featured Extensions (top rated/most installed)
$featuredStmt = $pdo->query("
    SELECT m.*, u.username as author_name,
        (SELECT COUNT(*) FROM extension_installs WHERE extension_id = m.id) as installs,
        (SELECT AVG(rating) FROM extension_stars WHERE extension_id = m.id) as avg_rating
    FROM market_extensions m
    JOIN users u ON m.user_id = u.id
    ORDER BY installs DESC, avg_rating DESC
    LIMIT 12
");
$featured = $featuredStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Downloaded Extensions
$downloadedStmt = $pdo->prepare("
    SELECT m.*, u.username as author_name, i.installed_at
    FROM extension_installs i
    JOIN market_extensions m ON i.extension_id = m.id
    JOIN users u ON m.user_id = u.id
    WHERE i.user_id = ?
    ORDER BY i.installed_at DESC
");
$downloadedStmt->execute([$userId]);
$downloaded = $downloadedStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch My Extensions (published by user)
$myExtStmt = $pdo->prepare("
    SELECT m.*,
        (SELECT COUNT(*) FROM extension_installs WHERE extension_id = m.id) as installs,
        (SELECT AVG(rating) FROM extension_stars WHERE extension_id = m.id) as avg_rating
    FROM market_extensions m
    WHERE m.user_id = ?
    ORDER BY m.created_at DESC
");
$myExtStmt->execute([$userId]);
$myExtensions = $myExtStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Extensions | Loop</title>
    <link rel="stylesheet" href="home.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="layout.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #000;
            --card-bg: rgba(255,255,255,0.02);
            --border: rgba(255,255,255,0.06);
            --accent: #a855f7;
            --accent-soft: rgba(168,85,247,0.1);
        }

        body {
            background: #000 !important;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            height: 100vh;
        }

        .home-bg-canvas {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            z-index: 0;
            pointer-events: none;
            background: #000;
        }

        .top-nav {
            position: absolute !important;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 2000;
            background: rgba(2, 2, 5, 0.2) !important;
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
        }

        .app-layout {
            position: relative;
            z-index: 1;
            flex: 1;
            display: flex;
            overflow: hidden;
            height: 100vh;
        }

        .main-content {
            flex: 1;
            height: 100%;
            overflow-y: auto;
            padding-top: var(--header-height, 80px) !important;
        }

        .ext-page {
            min-height: 100vh;
            padding: 30px 50px;
            position: relative;
        }

        .ext-hero {
            margin-bottom: 40px;
        }

        .ext-greeting {
            font-size: 32px;
            font-weight: 900;
            color: #fff;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }

        .ext-greeting span {
            background: linear-gradient(135deg, #a855f7, #6366f1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .ext-subtitle {
            font-size: 15px;
            color: #666;
        }

        /* Tabs Navigation */
        .ext-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 40px;
            border-bottom: 1px solid var(--border);
            padding-bottom: 15px;
        }

        .ext-tab {
            padding: 12px 24px;
            font-size: 14px;
            font-weight: 700;
            color: #555;
            background: transparent;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .ext-tab:hover {
            color: #fff;
            background: rgba(255,255,255,0.03);
        }

        .ext-tab.active {
            color: #fff;
            background: var(--accent-soft);
        }

        .ext-tab i {
            font-size: 16px;
        }

        .ext-tab .badge {
            background: var(--accent);
            color: #fff;
            font-size: 10px;
            padding: 2px 8px;
            border-radius: 10px;
            font-weight: 800;
        }

        /* Tab Content */
        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Extension Cards Grid */
        .ext-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 24px;
        }

        .ext-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 20px;
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.23, 1, 0.32, 1);
            position: relative;
        }

        .ext-card:hover {
            transform: translateY(-8px);
            border-color: var(--accent);
            box-shadow: 0 20px 60px rgba(168, 85, 247, 0.15);
        }

        .ext-thumb {
            width: 100%;
            aspect-ratio: 16/9;
            object-fit: cover;
            background: #111;
        }

        .ext-info {
            padding: 20px;
        }

        .ext-name {
            font-size: 16px;
            font-weight: 800;
            color: #fff;
            margin-bottom: 6px;
            display: -webkit-box;
            -webkit-line-clamp: 1;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .ext-author {
            font-size: 12px;
            color: #666;
            margin-bottom: 12px;
        }

        .ext-author b {
            color: var(--accent);
        }

        .ext-meta {
            display: flex;
            gap: 15px;
            font-size: 11px;
            color: #555;
        }

        .ext-meta span {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .ext-meta i {
            color: var(--accent);
        }

        .ext-actions {
            padding: 15px 20px;
            border-top: 1px solid var(--border);
            display: flex;
            gap: 10px;
        }

        .ext-btn {
            flex: 1;
            padding: 10px;
            border-radius: 10px;
            border: none;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .ext-btn.primary {
            background: var(--accent);
            color: #fff;
        }

        .ext-btn.primary:hover {
            filter: brightness(1.2);
        }

        .ext-btn.secondary {
            background: rgba(255,255,255,0.05);
            color: #888;
        }

        .ext-btn.secondary:hover {
            background: rgba(255,255,255,0.1);
            color: #fff;
        }

        .ext-btn.danger {
            background: rgba(239,68,68,0.1);
            color: #ef4444;
        }

        .ext-btn.danger:hover {
            background: rgba(239,68,68,0.2);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 80px 40px;
            color: #444;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 20px;
            color: #333;
        }

        .empty-state h3 {
            font-size: 18px;
            color: #555;
            margin-bottom: 10px;
        }

        .empty-state p {
            font-size: 14px;
            margin-bottom: 25px;
        }

        .empty-state .ext-btn {
            width: auto;
            padding: 14px 30px;
            display: inline-flex;
        }

        /* Studio Card */
        .studio-promo {
            background: linear-gradient(135deg, rgba(168,85,247,0.1), rgba(99,102,241,0.1));
            border: 1px solid rgba(168,85,247,0.2);
            border-radius: 24px;
            padding: 40px;
            display: flex;
            align-items: center;
            gap: 40px;
            margin-bottom: 40px;
        }

        .studio-promo-icon {
            width: 80px;
            height: 80px;
            background: var(--accent);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            color: #fff;
            flex-shrink: 0;
        }

        .studio-promo-content h2 {
            font-size: 24px;
            font-weight: 900;
            color: #fff;
            margin-bottom: 8px;
        }

        .studio-promo-content p {
            color: #888;
            font-size: 14px;
            margin-bottom: 20px;
            max-width: 500px;
        }

        .studio-promo .ext-btn {
            width: auto;
            padding: 14px 30px;
        }

        /* Price Badge */
        .price-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: rgba(0,0,0,0.8);
            backdrop-filter: blur(10px);
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 800;
            color: #fff;
        }

        .price-badge.free {
            color: #22c55e;
        }

        /* Flox Modal */
        .flox-modal-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.8);
            backdrop-filter: blur(10px);
            z-index: 10000;
            display: none;
            align-items: center;
            justify-content: center;
        }

        .flox-modal-overlay.active {
            display: flex;
        }

        .flox-modal {
            background: #0a0a0f;
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 30px;
            width: 400px;
            animation: modalIn 0.3s ease;
        }

        @keyframes modalIn {
            from { opacity: 0; transform: scale(0.9); }
            to { opacity: 1; transform: scale(1); }
        }

        .flox-modal-title {
            font-size: 18px;
            font-weight: 800;
            color: #fff;
            margin-bottom: 15px;
        }

        .flox-modal-text {
            font-size: 14px;
            color: #888;
            margin-bottom: 25px;
            line-height: 1.6;
        }

        }
    </style>
</head>
<body>
    <canvas id="homeBgCanvas" class="home-bg-canvas"></canvas>

    <script>
    (function() {
        const canvas = document.getElementById('homeBgCanvas');
        const ctx = canvas.getContext('2d');
        let stars = [];
        const starCount = 200;

        function resize() {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
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
    </script>

    <?php include 'header.php'; ?>
    
    <div class="app-layout">
        <?php include 'sidebar.php'; ?>

        <main class="main-content" id="mainContent" data-flox="extensions.main">
            <div class="ext-page">
                <!-- Hero Section -->
                <div class="ext-hero">
                    <div class="ext-greeting"><?php echo $greeting; ?>, <span><?php echo htmlspecialchars($displayName); ?></span></div>
                    <div class="ext-subtitle">Discover and manage your Loop extensions</div>
                </div>

                <!-- Tabs Navigation -->
                <div class="ext-tabs">
                    <button class="ext-tab active" data-tab="featured">
                        <i class="fa-solid fa-fire"></i> Featured
                    </button>
                    <button class="ext-tab" data-tab="downloaded">
                        <i class="fa-solid fa-cloud-arrow-down"></i> Downloaded
                        <?php if (count($downloaded) > 0): ?>
                            <span class="badge"><?php echo count($downloaded); ?></span>
                        <?php endif; ?>
                    </button>
                    <button class="ext-tab" data-tab="my-extensions">
                        <i class="fa-solid fa-box-open"></i> My Extensions
                        <?php if (count($myExtensions) > 0): ?>
                            <span class="badge"><?php echo count($myExtensions); ?></span>
                        <?php endif; ?>
                    </button>
                    <button class="ext-tab" data-tab="studio">
                        <i class="fa-solid fa-code"></i> Studio
                    </button>
                </div>

                <!-- Featured Tab -->
                <div class="tab-content active" id="tab-featured">
                    <?php if (empty($featured)): ?>
                        <div class="empty-state">
                            <i class="fa-solid fa-puzzle-piece"></i>
                            <h3>No Extensions Yet</h3>
                            <p>Be the first to publish an extension to the marketplace!</p>
                            <a href="extension_studio.php" class="ext-btn primary">
                                <i class="fa-solid fa-plus"></i> Create Extension
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="ext-grid">
                            <?php foreach ($featured as $ext): ?>
                                <div class="ext-card">
                                    <div class="price-badge <?php echo $ext['price'] == 0 ? 'free' : ''; ?>">
                                        <?php echo $ext['price'] == 0 ? 'FREE' : number_format($ext['price']) . ' XP'; ?>
                                    </div>
                                    <img class="ext-thumb" src="../<?php echo htmlspecialchars($ext['thumbnail_url']); ?>" alt="<?php echo htmlspecialchars($ext['name']); ?>">
                                    <div class="ext-info">
                                        <div class="ext-name"><?php echo htmlspecialchars($ext['name']); ?></div>
                                        <div class="ext-author">by <b>@<?php echo htmlspecialchars($ext['author_name']); ?></b></div>
                                        <div class="ext-meta">
                                            <span><i class="fa-solid fa-download"></i> <?php echo number_format($ext['installs']); ?></span>
                                            <span><i class="fa-solid fa-star"></i> <?php echo $ext['avg_rating'] ? number_format($ext['avg_rating'], 1) : 'N/A'; ?></span>
                                        </div>
                                    </div>
                                    <div class="ext-actions">
                                        <a href="view_extension.php?id=<?php echo $ext['id']; ?>" class="ext-btn secondary">
                                            <i class="fa-solid fa-eye"></i> View
                                        </a>
                                        <button class="ext-btn primary" onclick="installExt(<?php echo $ext['id']; ?>)">
                                            <i class="fa-solid fa-plus"></i> Install
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Downloaded Tab -->
                <div class="tab-content" id="tab-downloaded">
                    <?php if (empty($downloaded)): ?>
                        <div class="empty-state">
                            <i class="fa-solid fa-box-open"></i>
                            <h3>No Extensions Installed</h3>
                            <p>Browse the marketplace to find extensions that enhance your experience.</p>
                            <a href="xpoints_market.php" class="ext-btn primary">
                                <i class="fa-solid fa-store"></i> Browse Marketplace
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="ext-grid">
                            <?php foreach ($downloaded as $ext): ?>
                                <div class="ext-card" id="installed-<?php echo $ext['id']; ?>">
                                    <img class="ext-thumb" src="../<?php echo htmlspecialchars($ext['thumbnail_url']); ?>" alt="<?php echo htmlspecialchars($ext['name']); ?>">
                                    <div class="ext-info">
                                        <div class="ext-name"><?php echo htmlspecialchars($ext['name']); ?></div>
                                        <div class="ext-author">by <b>@<?php echo htmlspecialchars($ext['author_name']); ?></b></div>
                                        <div class="ext-meta">
                                            <span><i class="fa-solid fa-clock"></i> Installed <?php echo date('M j', strtotime($ext['installed_at'])); ?></span>
                                        </div>
                                    </div>
                                    <div class="ext-actions">
                                        <a href="view_extension.php?id=<?php echo $ext['id']; ?>" class="ext-btn secondary">
                                            <i class="fa-solid fa-eye"></i> View
                                        </a>
                                        <button class="ext-btn danger" onclick="uninstallExt(<?php echo $ext['id']; ?>)">
                                            <i class="fa-solid fa-trash"></i> Remove
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- My Extensions Tab -->
                <div class="tab-content" id="tab-my-extensions">
                    <?php if (empty($myExtensions)): ?>
                        <div class="empty-state">
                            <i class="fa-solid fa-rocket"></i>
                            <h3>You Haven't Published Anything</h3>
                            <p>Create and share your own extensions with the Loop community!</p>
                            <a href="extension_studio.php" class="ext-btn primary">
                                <i class="fa-solid fa-hammer"></i> Open Studio
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="ext-grid">
                            <?php foreach ($myExtensions as $ext): ?>
                                <div class="ext-card" id="my-ext-<?php echo $ext['id']; ?>">
                                    <div class="price-badge <?php echo $ext['price'] == 0 ? 'free' : ''; ?>">
                                        <?php echo $ext['price'] == 0 ? 'FREE' : number_format($ext['price']) . ' XP'; ?>
                                    </div>
                                    <img class="ext-thumb" src="../<?php echo htmlspecialchars($ext['thumbnail_url']); ?>" alt="<?php echo htmlspecialchars($ext['name']); ?>">
                                    <div class="ext-info">
                                        <div class="ext-name"><?php echo htmlspecialchars($ext['name']); ?></div>
                                        <div class="ext-author">v<?php echo htmlspecialchars($ext['version']); ?></div>
                                        <div class="ext-meta">
                                            <span><i class="fa-solid fa-download"></i> <?php echo number_format($ext['installs']); ?> installs</span>
                                            <span><i class="fa-solid fa-star"></i> <?php echo $ext['avg_rating'] ? number_format($ext['avg_rating'], 1) : 'No ratings'; ?></span>
                                        </div>
                                    </div>
                                    <div class="ext-actions">
                                        <a href="view_extension.php?id=<?php echo $ext['id']; ?>" class="ext-btn secondary">
                                            <i class="fa-solid fa-chart-line"></i> Analytics
                                        </a>
                                        <button class="ext-btn danger" onclick="removeFromMarket(<?php echo $ext['id']; ?>, '<?php echo addslashes($ext['name']); ?>')">
                                            <i class="fa-solid fa-ban"></i> Remove
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Studio Tab -->
                <div class="tab-content" id="tab-studio">
                    <div class="studio-promo">
                        <div class="studio-promo-icon">
                            <i class="fa-solid fa-wand-magic-sparkles"></i>
                        </div>
                        <div class="studio-promo-content">
                            <h2>Extension Studio</h2>
                            <p>Build powerful extensions using our intuitive IDE. Create custom UI components, chat integrations, and more — all without leaving Loop.</p>
                            <a href="extension_studio.php" class="ext-btn primary">
                                <i class="fa-solid fa-rocket"></i> Launch Studio
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Confirmation Modal -->
    <div class="flox-modal-overlay" id="confirmModal">
        <div class="flox-modal">
            <div class="flox-modal-title" id="modalTitle">Confirm Action</div>
            <div class="flox-modal-text" id="modalText">Are you sure?</div>
            <div class="flox-modal-btns">
                <button class="ext-btn secondary" onclick="closeModal()">Cancel</button>
                <button class="ext-btn danger" id="modalConfirmBtn">Confirm</button>
            </div>
        </div>
    </div>

    <script src="theme.js?v=<?php echo time(); ?>"></script>
    <script src="popup.js?v=<?php echo time(); ?>"></script>
    <script src="home.js?v=<?php echo time(); ?>"></script>
    <script src="search-history.js?v=<?php echo time(); ?>"></script>
    <script src="voice_search.js?v=<?php echo time(); ?>"></script>
    <script src="icon-replace.js?v=<?php echo time(); ?>"></script>
    <script src="notifications.js?v=<?php echo time(); ?>"></script>
    <script src="mobile-search.js?v=<?php echo time(); ?>"></script>
    <script src="announcements.js?v=<?php echo time(); ?>"></script>
    <script src="extension_engine.js?v=<?php echo time(); ?>"></script>
    <script>
        // Tab Switching
        document.querySelectorAll('.ext-tab').forEach(tab => {
            tab.addEventListener('click', () => {
                document.querySelectorAll('.ext-tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                
                tab.classList.add('active');
                document.getElementById('tab-' + tab.dataset.tab).classList.add('active');
            });
        });

        // Install Extension
        async function installExt(id) {
            try {
                const r = await fetch('../backend/market_actions.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'install', extension_id: id })
                });
                const d = await r.json();
                if (d.success) {
                    alert('Extension installed! Refresh to see changes.');
                    location.reload();
                }
            } catch(e) { console.error(e); }
        }

        // Uninstall Extension
        async function uninstallExt(id) {
            if (!confirm('Remove this extension?')) return;
            try {
                const r = await fetch('../backend/market_actions.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'uninstall', extension_id: id })
                });
                const d = await r.json();
                if (d.success) {
                    document.getElementById('installed-' + id)?.remove();
                } else {
                    alert(d.message);
                }
            } catch(e) { console.error(e); }
        }

        // Remove from Market (for publishers)
        function removeFromMarket(id, name) {
            document.getElementById('modalTitle').innerText = 'Remove from Market';
            document.getElementById('modalText').innerText = `Are you sure you want to remove "${name}" from the marketplace? This will also remove it for all users who installed it.`;
            document.getElementById('confirmModal').classList.add('active');
            
            document.getElementById('modalConfirmBtn').onclick = async () => {
                try {
                    const r = await fetch('../backend/market_actions.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'delete_extension', extension_id: id })
                    });
                    const d = await r.json();
                    if (d.success) {
                        closeModal();
                        document.getElementById('my-ext-' + id)?.remove();
                    } else {
                        alert(d.message);
                    }
                } catch(e) { console.error(e); }
            };
        }

        function closeModal() {
            document.getElementById('confirmModal').classList.remove('active');
        }
    </script>
</body>
</html>
