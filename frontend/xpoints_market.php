<?php
session_start();
require_once '../backend/config.php';

// Fetch Real Market Items
try {
    // Basic Marketplace Query
    $stmt = $pdo->prepare("
        SELECT m.*, u.username as author_name 
        FROM market_extensions m
        JOIN users u ON m.user_id = u.id
        ORDER BY m.created_at DESC
    ");
    $stmt->execute();
    $marketItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Recommended (Top 4 latest)
    $recommendedItems = array_slice($marketItems, 0, 4);
    
} catch (PDOException $e) {
    $marketItems = [];
    $recommendedItems = [];
}

// Get user points
$userPoints = 0;
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT xpoints FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $userPoints = $stmt->fetchColumn() ?: 0;
    } catch (Exception $e) {
        $userPoints = "ERR";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>XPoints Space Market | Loop</title>
    <link rel="stylesheet" href="layout.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="xpoints_market_v8.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
</head>
<body>
    <div class="stars-overlay"></div>

    <div class="app-layout" style="background: transparent !important;">
        <?php include 'sidebar.php'; ?>
        
        <main class="main-content market-overrides" style="background: transparent !important;">
            <!-- Truly Full Width Hero -->
            <div class="market-hero">
                <div class="hero-glow-container">
                    <div class="glow-sphere orange"></div>
                    <div class="glow-sphere blue"></div>
                    <div class="glow-sphere red"></div>
                </div>
                <div class="beta-tag">BETA</div>
                <div class="hero-content">
                    <h1 class="market-title">XPOINTS MARKET</h1>
                    <div class="search-wrapper">
                        <i class="fa-solid fa-magnifying-glass search-icon"></i>
                        <input type="text" class="market-search" placeholder="Search for extensions, themes, scripts...">
                    </div>
                    <div class="hero-actions">
                        <a href="extension_studio.php" class="studio-btn">
                            <div class="studio-btn-content">
                                <i class="fa-solid fa-wand-magic-sparkles"></i>
                                <span>Extension Studio</span>
                            </div>
                            <div class="studio-btn-glow"></div>
                        </a>
                    </div>
                </div>
            </div>

            <div class="market-container">

                <!-- Recommended Sections -->
                <section class="market-section">
                    <div class="section-title">
                        <h2><i class="fa-solid fa-sparkles"></i> Recommended for You</h2>
                    </div>
                    <div class="recommendations-scroll">
                        <?php foreach ($recommendedItems as $item): ?>
                        <a href="view_extension.php?id=<?php echo $item['id']; ?>" class="market-card" style="min-width: 250px; text-decoration: none;">
                            <img src="<?php echo '../' . htmlspecialchars($item['thumbnail_url']); ?>?v=<?php echo time(); ?>" 
                                 onerror="this.src='https://images.unsplash.com/photo-1543722530-d2c3201371e7?auto=format&fit=crop&q=80&w=400'"
                                 class="card-image" style="height: 120px; width: 100%;">
                            <div class="card-header">
                                <span class="tag">v<?php echo htmlspecialchars($item['version']); ?></span>
                                <span class="price">
                                    <?php if($item['price'] == 0): ?>FREE<?php else: ?><i class="fa-solid fa-coins"></i> <?php echo number_format($item['price']); ?><?php endif; ?>
                                </span>
                            </div>
                            <h3 class="card-title" style="font-size: 1rem;"><?php echo htmlspecialchars($item['name']); ?></h3>
                            <div style="font-size: 10px; color: #555; margin-top: 5px;">Created by @<?php echo htmlspecialchars($item['author_name']); ?></div>
                        </a>
                        <?php endforeach; ?>
                        <?php if(empty($recommendedItems)): ?>
                            <div style="padding: 40px; text-align: center; width: 100%; border: 1px dashed #222; border-radius: 20px; color: #444;">
                                No extensions published yet. Be the first!
                            </div>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- Full Marketplace Grid -->
                <section class="market-section" style="margin-top: 3rem;">
                    <div class="section-title">
                        <h2><i class="fa-solid fa-planet-ringed"></i> Explore Marketplace</h2>
                    </div>
                    
                    <div class="market-grid">
                        <?php foreach ($marketItems as $item): ?>
                        <a href="view_extension.php?id=<?php echo $item['id']; ?>" class="market-card" style="text-decoration: none;">
                            <img src="<?php echo '../' . htmlspecialchars($item['thumbnail_url']); ?>?v=<?php echo time(); ?>" 
                                 onerror="this.src='https://images.unsplash.com/photo-1543722530-d2c3201371e7?auto=format&fit=crop&q=80&w=400'"
                                 class="card-image">
                            <div class="card-header">
                                <span class="tag">v<?php echo htmlspecialchars($item['version']); ?></span>
                                <div class="price">
                                    <?php if($item['price'] == 0): ?>
                                        <span style="color: #10b981;">FREE</span>
                                    <?php else: ?>
                                        <i class="fa-solid fa-coins"></i> <?php echo number_format($item['price']); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <h3 class="card-title"><?php echo htmlspecialchars($item['name']); ?></h3>
                            <p class="card-desc"><?php echo htmlspecialchars($item['description']); ?></p>
                            <div class="card-footer">
                                <div class="author">
                                    <div class="author-avatar"></div>
                                    <span><?php echo htmlspecialchars($item['author_name']); ?></span>
                                </div>
                                <button class="action-btn">Install</button>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </section>

            </div>
        </main>
    </div>

    <!-- Floating Actions Container -->
    <div class="floating-actions">
        <div class="balance-card">
            <i class="fa-solid fa-coins" style="color: var(--market-accent-gold);"></i>
            <span><strong><?php echo number_format($userPoints); ?></strong> XPoints</span>
        </div>
        <button class="market-create-btn" onclick="document.getElementById('createModal').style.display='flex'">
            <i class="fa-solid fa-plus"></i>
        </button>
    </div>

    <!-- Create Modal Stub -->
    <div id="createModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.9); z-index:1000; backdrop-filter:blur(15px); align-items:center; justify-content:center;">
        <div style="background:#0f0f14; padding:3rem; border-radius:32px; border:1px solid rgba(255,255,255,0.1); max-width:500px; width:90%; text-align:center; box-shadow: 0 0 50px rgba(139, 92, 246, 0.3);">
            <i class="fa-solid fa-shuttle-space" style="font-size:3.5rem; color:var(--market-accent-gold); margin-bottom:1.5rem;"></i>
            <h2 style="margin-bottom:1rem; font-size:2rem; font-weight:800; color:white;">Contributor Program</h2>
            <p style="color:#94a3b8; margin-bottom:2.5rem; line-height:1.6; font-size:1.1rem;">Build the future of Loop. Create extensions, themes, or scripts and monetize your work with XPoints.</p>
            <div style="display:flex; flex-direction:column; gap:16px;">
                <button class="action-btn" style="width:100%; padding:1rem; font-size:1.1rem; background:var(--market-accent-purple); color:white;">Start Developer Console</button>
                <button onclick="document.getElementById('createModal').style.display='none'" style="background:transparent; border:none; color:#94a3b8; padding:0.5rem; cursor:pointer; font-weight:600;">Maybe later</button>
            </div>
        </div>
    </div>
    <script>
    (function() {
        console.log("XPoints Market Starfield Initializing...");
        const canvas = document.getElementById('homeBgCanvas');
        if (!canvas) {
            console.error("Starfield error: Canvas NOT found!");
            return;
        }
        
        const ctx = canvas.getContext('2d');
        let stars = [];
        const starCount = 500; 
        let animationFrame;

        function initStars() {
            stars = [];
            for (let i = 0; i < starCount; i++) {
                stars.push({
                    x: Math.random() * canvas.width,
                    y: Math.random() * canvas.height,
                    size: Math.random() * 2.5 + 0.5,
                    speed: Math.random() * 0.05 + 0.02,
                    opacity: 0.2 + Math.random() * 0.8,
                    driftX: (Math.random() - 0.5) * 0.03
                });
            }
        }

        function resize() {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
            initStars();
        }

        function draw() {
            if (!ctx) return;
            // Clear but keep it high contrast black
            ctx.fillStyle = "#000000";
            ctx.fillRect(0, 0, canvas.width, canvas.height);

            stars.forEach(star => {
                ctx.fillStyle = `rgba(255, 255, 255, ${star.opacity})`;
                ctx.beginPath();
                ctx.arc(star.x, star.y, star.size, 0, Math.PI * 2);
                ctx.fill();
                
                // Motion
                star.y -= star.speed;
                star.x += star.driftX;
                
                // Wrap around logic
                if (star.y < 0) star.y = canvas.height;
                if (star.y > canvas.height) star.y = 0;
                if (star.x < 0) star.x = canvas.width;
                if (star.x > canvas.width) star.x = 0;
            });
            animationFrame = requestAnimationFrame(draw);
        }

        window.addEventListener('resize', () => {
            if (animationFrame) cancelAnimationFrame(animationFrame);
            resize();
            draw();
        });
        
        resize();
        draw();
        console.log("Starfield engine started with " + starCount + " stars.");
    })();
    </script>
</body>
</html>
