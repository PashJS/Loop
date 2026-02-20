<?php
session_start();
require_once '../backend/config.php';

$extId = $_GET['id'] ?? null;
if (!$extId) {
    header('Location: xpoints_market.php');
    exit;
}

// Table Initialization System
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS extension_stars (
        id INT AUTO_INCREMENT PRIMARY KEY,
        extension_id INT NOT NULL,
        user_id INT NOT NULL,
        rating TINYINT NOT NULL,
        UNIQUE KEY (extension_id, user_id)
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS extension_likes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        extension_id INT NOT NULL,
        user_id INT NOT NULL,
        UNIQUE KEY (extension_id, user_id)
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS extension_comments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        extension_id INT NOT NULL,
        user_id INT NOT NULL,
        parent_id INT DEFAULT NULL,
        comment TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    // Migration: ensure parent_id and image_url exist
    try { $pdo->exec("ALTER TABLE extension_comments ADD COLUMN parent_id INT DEFAULT NULL AFTER user_id"); } catch(Exception $e){}
    try { $pdo->exec("ALTER TABLE extension_comments ADD COLUMN image_url VARCHAR(255) DEFAULT NULL AFTER comment"); } catch(Exception $e){}

    $pdo->exec("CREATE TABLE IF NOT EXISTS extension_installs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        extension_id INT NOT NULL,
        user_id INT NOT NULL,
        installed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY (extension_id, user_id)
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS extension_comment_votes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        comment_id INT NOT NULL,
        user_id INT NOT NULL,
        vote_type ENUM('like', 'dislike') NOT NULL,
        UNIQUE KEY (comment_id, user_id)
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS extension_reports (
        id INT AUTO_INCREMENT PRIMARY KEY,
        extension_id INT NOT NULL,
        user_id INT NOT NULL,
        reason TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) {
    // Silent fail on setup - primary query will catch if table is truly missing
}

// Fetch extension details
try {
    $stmt = $pdo->prepare("
        SELECT m.*, u.username as author_name,
        (SELECT COUNT(*) FROM extension_likes WHERE extension_id = m.id) as likes_count,
        (SELECT COUNT(*) FROM extension_installs WHERE extension_id = m.id) as installs_count,
        (SELECT AVG(rating) FROM extension_stars WHERE extension_id = m.id) as avg_rating,
        (SELECT COUNT(*) FROM extension_stars WHERE extension_id = m.id) as total_ratings
        FROM market_extensions m
        JOIN users u ON m.user_id = u.id
        WHERE m.id = ?
    ");
    $stmt->execute([$extId]);
    $ext = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ext) {
        die("Extension not found.");
    }

    $screenshots = json_decode($ext['screenshots_json'] ?? '[]', true);
    
    // Fetch comments (Nested Query)
    $myId = $_SESSION['user_id'] ?? 0;
    $cmtStmt = $pdo->prepare("
        SELECT c.*, u.username, u.profile_picture,
        (SELECT COUNT(*) FROM extension_comment_votes WHERE comment_id = c.id AND vote_type = 'like') as likes,
        (SELECT COUNT(*) FROM extension_comment_votes WHERE comment_id = c.id AND vote_type = 'dislike') as dislikes,
        (SELECT vote_type FROM extension_comment_votes WHERE comment_id = c.id AND user_id = ?) as my_vote
        FROM extension_comments c 
        JOIN users u ON c.user_id = u.id 
        WHERE c.extension_id = ? 
        ORDER BY c.parent_id ASC, c.created_at ASC
    ");
    $cmtStmt->execute([$myId, $extId]);
    $allComments = $cmtStmt->fetchAll(PDO::FETCH_ASSOC);

    // Organize into Threaded structure
    $comments = [];
    $replies = [];
    foreach($allComments as $c) {
        if($c['parent_id']) $replies[$c['parent_id']][] = $c;
        else $comments[] = $c;
    }

    // Get current user rating
    $myRating = 0;
    if($myId > 0) {
        $rStmt = $pdo->prepare("SELECT rating FROM extension_stars WHERE extension_id = ? AND user_id = ?");
        $rStmt->execute([$extId, $myId]);
        $myRating = $rStmt->fetchColumn() ?: 0;
    }

} catch (PDOException $e) {
    die("<div style='padding:50px; background:#111; color:#ff5555; text-align:center;'>
            <h2>Database Synchronization Error</h2>
            <p>" . $e->getMessage() . "</p>
            <button onclick='location.reload()' style='background:var(--accent); color:white; border:none; padding:10px 20px; border-radius:8px; cursor:pointer;'>Retry Sync</button>
         </div>");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($ext['name']); ?> | Loop Market</title>
    <link rel="stylesheet" href="layout.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --accent: #a855f7;
            --bg: #050508;
            --panel: #0d0d14;
            --border: rgba(255,255,255,0.08);
            --text-main: #eee;
            --text-dim: #888;
        }
        body { background: var(--bg); color: var(--text-main); font-family: 'Inter', sans-serif; margin: 0; line-height: 1.6; }
        
        /* Premium Scrollbar */
        ::-webkit-scrollbar { width: 10px; }
        ::-webkit-scrollbar-track { background: var(--bg); }
        ::-webkit-scrollbar-thumb { background: #1a1a25; border-radius: 5px; border: 2px solid var(--bg); }
        ::-webkit-scrollbar-thumb:hover { background: var(--accent); }
        body { scrollbar-width: thin; scrollbar-color: #1a1a25 var(--bg); }

        /* Layout Fixes */
        .app-layout { display: flex; min-height: 100vh; background: var(--bg); }
        .main-content { 
            flex: 1; padding: 40px; width: 100%; transition: all 0.3s ease;
            max-width: 1600px; /* Increased for wider screens */
        }
        
        .ext-view-grid { display: grid; grid-template-columns: 1fr 350px; gap: 30px; }

        /* Premium Header */
        .ext-hero {
            grid-column: span 2; background: linear-gradient(135deg, #131320 0%, #050508 100%);
            border-radius: 32px; padding: 50px; border: 1px solid var(--border);
            display: flex; gap: 40px; align-items: center; position: relative; overflow: hidden;
            margin-bottom: 20px; box-shadow: 0 30px 60px rgba(0,0,0,0.5);
        }
        .hero-glow { position: absolute; top: -50%; left: -10%; width: 40%; height: 200%; background: radial-gradient(circle, rgba(168,85,247,0.1) 0%, transparent 70%); pointer-events: none; }

        .ext-main-thumb { 
            width: 200px; height: 200px; border-radius: 28px; object-fit: cover; 
            box-shadow: 0 20px 50px rgba(0,0,0,0.8); border: 1px solid rgba(255,255,255,0.1);
            cursor: pointer; transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .ext-main-thumb:hover { transform: scale(1.05) rotate(2deg); }

        .hero-info h1 { font-size: 48px; font-weight: 900; margin: 0 0 15px 0; letter-spacing: -1.5px; background: linear-gradient(to bottom, #fff, #888); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .hero-meta { display: flex; gap: 25px; align-items: center; color: var(--text-dim); font-size: 15px; }
        .hero-meta b { color: var(--accent); }
        
        .rating-badge { background: rgba(245, 158, 11, 0.1); color: #f59e0b; padding: 6px 14px; border-radius: 100px; display: flex; align-items: center; gap: 8px; font-weight: 800; border: 1px solid rgba(245, 158, 11, 0.2); }

        /* Content Sections */
        .ext-section { background: var(--panel); border: 1px solid var(--border); border-radius: 24px; padding: 35px; margin-bottom: 30px; transition: transform 0.2s; }
        .section-title { font-size: 18px; font-weight: 800; margin-bottom: 25px; display: flex; align-items: center; gap: 12px; color: #fff; text-transform: uppercase; letter-spacing: 1px; }
        .section-title i { color: var(--accent); }

        .ext-description { line-height: 1.8; color: #aaa; font-size: 15px; white-space: pre-wrap; }

        /* Screenshot Gallery */
        .ss-gallery { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; }
        .ss-item { aspect-ratio: 16/9; border-radius: 18px; overflow: hidden; border: 1px solid var(--border); cursor: pointer; position: relative; }
        .ss-item::after { content: '\f065'; font-family: 'Font Awesome 6 Free'; font-weight: 900; position: absolute; inset: 0; background: rgba(168,85,247,0.4); display: flex; align-items: center; justify-content: center; font-size: 24px; opacity: 0; transition: 0.2s; }
        .ss-item:hover::after { opacity: 1; }
        .ss-item img { width: 100%; height: 100%; object-fit: cover; }

        /* Sidebar Actions */
        .action-card { position: sticky; top: 20px; background: rgba(168, 85, 247, 0.03); border: 1px solid rgba(168, 85, 247, 0.15); backdrop-filter: blur(20px); }
        .price-tag span.free { color: #10b981; text-shadow: 0 0 20px rgba(16, 185, 129, 0.3); }

        /* Star Rating UI */
        .star-rating { display: flex; gap: 8px; justify-content: center; margin-bottom: 20px; font-size: 24px; color: #333; }
        .star-rating .star { cursor: pointer; transition: 0.2s; }
        .star-rating .star:hover, .star-rating .star.hov { color: #ffd700; transform: scale(1.2); }
        .star-rating .star.active { color: #ffd700; filter: drop-shadow(0 0 8px #ffd700); }
        .rating-count { font-size: 11px; color: rgba(255,255,255,0.4); text-align: center; margin-top: -15px; margin-bottom: 20px; }

        .primary-btn { width: 100%; padding: 20px; border-radius: 18px; border: none; background: linear-gradient(135deg, var(--accent), #6366f1); color: #fff; font-weight: 800; font-size: 16px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 10px; transition: all 0.3s cubic-bezier(0.23, 1, 0.32, 1); box-shadow: 0 10px 30px rgba(168, 85, 247, 0.2); }
        .primary-btn:hover { transform: translateY(-5px); filter: brightness(1.2); box-shadow: 0 15px 40px rgba(168, 85, 247, 0.4); }

        .secondary-actions { margin-top: 25px; display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .sec-btn { padding: 14px; background: rgba(255,255,255,0.03); border: 1px solid var(--border); border-radius: 14px; color: var(--text-dim); font-size: 13px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: all 0.2s; }
        .sec-btn:hover { background: rgba(255,255,255,0.08); color: #fff; border-color: #444; }
        .sec-btn.liked { color: #ef4444; border-color: rgba(239, 68, 68, 0.3); background: rgba(239, 68, 68, 0.05); }

        /* Comments System */
        .comment-input-box { background: #000; border: 1px solid var(--border); border-radius: 20px; padding: 25px; margin-bottom: 30px; box-shadow: inset 0 2px 10px rgba(0,0,0,0.5); }
        .comment-input-box textarea { 
            width: 100%; background: transparent !important; border: none; outline: none; 
            color: #fff; resize: none; min-height: 80px; 
            font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", "SF Pro Text", "Inter", sans-serif; 
            font-size: 16px; font-weight: 400; line-height: 1.6;
        }
        .comment-item { padding: 25px 0; border-bottom: 1px solid var(--border); animation: fadeIn 0.5s ease; display: flex; gap: 15px; flex-direction: column; }
        @keyframes fadeIn { from { opacity:0; transform: translateY(10px); } }
        
        .comment-main { display: flex; gap: 15px; }
        .c-avatar { width: 44px; height: 44px; border-radius: 50%; background: #1a1a25; border: 1px solid var(--border); overflow: hidden; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-weight: 900; color: #444; font-size: 14px; }
        .c-avatar img { width: 100%; height: 100%; object-fit: cover; }
        
        .c-body { flex: 1; }
        .comment-user { font-weight: 800; font-size: 14px; margin-bottom: 6px; display: flex; align-items: center; gap: 10px; }
        .comment-user b { color: var(--accent); }
        .comment-user span { color: #444; font-weight: 400; font-size: 11px; }
        .comment-text { color: #aaa; font-size: 15px; line-height: 1.6; margin-bottom: 12px; }
        .comment-image-attachment { width: 100%; max-width: 400px; border-radius: 12px; overflow: hidden; margin: 10px 0; border: 1px solid var(--border); cursor: pointer; }
        .comment-image-attachment img { width: 100%; display: block; }
        
        .comment-actions { display: flex; gap: 18px; margin-top: 12px; }
        .c-act { display: flex; align-items: center; gap: 6px; color: #555; font-size: 12px; font-weight: 700; cursor: pointer; transition: 0.2s; background: none; border: none; padding: 0; outline: none; }
        .c-act:hover { color: #888; }
        .c-act i { font-size: 14px; }
        .c-act.liked { color: #3b82f6; }
        .c-act.disliked { color: #ef4444; }
        
        /* Voting Animations */
        .c-act.animating i { animation: votePop 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        @keyframes votePop {
            0% { transform: scale(1); }
            50% { transform: scale(1.6); text-shadow: 0 0 15px currentColor; }
            100% { transform: scale(1); }
        }
        
        .c-act { position: relative; }
        .c-act.animating::after {
            content: ''; position: absolute; top: 50%; left: 50%; width: 4px; height: 4px; border-radius: 50%;
            box-shadow: 0 -15px 0 0 currentColor, 12px -10px 0 0 currentColor, 12px 10px 0 0 currentColor, 0 15px 0 0 currentColor, -12px 10px 0 0 currentColor, -12px -10px 0 0 currentColor;
            transform: translate(-50%, -50%) scale(0); animation: sparkBurst 0.5s ease-out; opacity: 0;
        }
        @keyframes sparkBurst {
            0% { transform: translate(-50%, -50%) scale(0); opacity: 1; }
            80% { opacity: 0.8; }
            100% { transform: translate(-50%, -50%) scale(1.5); opacity: 0; }
        }

        .reply-btn { color: var(--accent); opacity: 0.8; }
        .reply-btn:hover { opacity: 1; filter: brightness(1.2); }

        /* More Options (3 Dots) */
        .comment-header { display: flex; justify-content: space-between; align-items: center; width: 100%; margin-bottom: 5px; }
        .comment-more-container { position: relative; }
        .comment-more-btn { background: none; border: none; color: #444; cursor: pointer; padding: 5px; font-size: 16px; transition: 0.2s; border-radius: 50%; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; }
        .comment-more-btn:hover { color: #fff; background: rgba(255,255,255,0.05); }
        
        .comment-more-menu { 
            position: absolute; top: 0; right: 40px; background: rgba(20,20,30,0.9); backdrop-filter: blur(20px);
            border: 1px solid var(--border); border-radius: 12px; width: 150px; z-index: 100; display: none; overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5); transform-origin: top right;
        }
        .comment-more-menu.active { display: block; animation: menuIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        @keyframes menuIn { from { opacity: 0; transform: scale(0.8) translateY(-10px); } to { opacity: 1; transform: scale(1) translateY(0); } }
        
        .more-item { padding: 12px 15px; font-size: 13px; font-weight: 600; color: #ccc; cursor: pointer; transition: 0.2s; display: flex; align-items: center; gap: 10px; border-bottom: 1px solid rgba(255,255,255,0.03); }
        .more-item:hover { background: rgba(255,255,255,0.05); color: #fff; }
        .more-item:last-child { border-bottom: none; }
        .more-item.delete { color: #ef4444; }
        .more-item.delete:hover { background: rgba(239, 68, 68, 0.1); }

        /* Replies System */
        .replies-container { margin-left: 60px; padding-left: 20px; border-left: 2px solid #111; margin-top: 5px; }
        .reply-item { padding: 18px 0; border-bottom: 1px solid rgba(255,255,255,0.03); display: flex; gap: 12px; }
        .reply-item:last-child { border-bottom: none; }
        .r-avatar { width: 32px; height: 32px; border-radius: 50%; background: #1a1a25; overflow: hidden; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: 10px; }
        .r-avatar img { width: 100%; height: 100%; object-fit: cover; }
        
        .reply-form { margin-top: 15px; display: none; margin-left: 60px; }
        .reply-form textarea { 
            width: 100%; height: 80px; background: #000; border: 1px solid #222; border-radius: 12px; 
            padding: 15px; color: #fff; font-size: 14px; outline: none; resize: none; margin-bottom: 10px;
            font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", "SF Pro Text", "Inter", sans-serif;
        }
        
        .img-preview-area { display: flex; gap: 10px; margin-bottom: 15px; flex-wrap: wrap; }
        .preview-box { position: relative; width: 60px; height: 60px; border-radius: 8px; overflow: hidden; border: 1px solid var(--accent); }
        .preview-box img { width: 100%; height: 100%; object-fit: cover; }
        .preview-remove { position: absolute; top:0; right:0; background: rgba(0,0,0,0.7); color: #fff; width:18px; height:18px; display:flex; align-items:center; justify-content:center; font-size:10px; cursor: pointer; }
        
        .c-icon-btn { color: #555; cursor: pointer; transition: 0.2s; font-size: 16px; padding: 10px; border-radius: 50%; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; }
        .c-icon-btn:hover { color: var(--accent); background: rgba(168,85,247,0.05); }

        /* Fullscreen Lightbox */
        #lightbox { position: fixed; inset: 0; background: rgba(0,0,0,0.95); z-index: 99999; display: none; align-items: center; justify-content: center; padding: 40px; cursor: zoom-out; backdrop-filter: blur(10px); }
        #lightbox img { max-width: 90%; max-height: 90%; object-fit: contain; border-radius: 12px; box-shadow: 0 0 100px rgba(168, 85, 247, 0.2); }
        #lightbox .close { position: absolute; top: 30px; right: 40px; font-size: 40px; color: #fff; cursor: pointer; opacity: 0.5; }
        #lightbox .close:hover { opacity: 1; }

        /* Custom Popup System */
        .flox-modal-overlay {
            position: fixed; inset: 0; background: rgba(0,0,0,0.8); backdrop-filter: blur(15px);
            z-index: 100000; display: none; align-items: center; justify-content: center;
        }
        .flox-modal {
            width: 400px; background: #0f0f18; border: 1px solid var(--border); border-radius: 28px;
            padding: 35px; box-shadow: 0 40px 100px rgba(0,0,0,0.8);
            animation: floxPop 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        @keyframes floxPop { from { opacity: 0; transform: scale(0.9); } }
        .flox-modal-title { font-size: 18px; font-weight: 800; margin-bottom: 15px; display: flex; align-items: center; gap: 10px; }
        .flox-modal-text { color: #aaa; font-size: 14px; margin-bottom: 25px; line-height: 1.5; }
        .flox-modal-input { width: 100%; background: #000; border: 1px solid #222; border-radius: 12px; padding: 12px; color: #fff; margin-bottom: 20px; outline: none; }
        .flox-modal-btns { display: flex; gap: 10px; justify-content: flex-end; }
        .flox-btn-cancel { background: transparent; border: 1px solid #222; color: #666; padding: 10px 20px; border-radius: 10px; cursor: pointer; }
        .flox-btn-confirm { background: var(--accent); border: none; color: #fff; padding: 10px 25px; border-radius: 10px; cursor: pointer; font-weight: 700; }

        @media (max-width: 1100px) {
            .ext-view-grid { grid-template-columns: 1fr; }
            .ext-hero { flex-direction: column; text-align: center; padding: 40px 20px; }
        }
    </style>
</head>
<body>
    <div class="app-layout">
        <?php include 'sidebar.php'; ?>

        <main class="main-content">
            <div class="ext-view-grid">
                <!-- HERO -->
                <div class="ext-hero">
                    <div class="hero-glow"></div>
                    <img src="../<?php echo htmlspecialchars($ext['thumbnail_url']); ?>?v=<?php echo time(); ?>" 
                         class="ext-main-thumb" onclick="openLightbox(this.src)">
                    <div class="hero-info">
                        <h1><?php echo htmlspecialchars($ext['name']); ?></h1>
                        <div class="hero-meta">
                            <span>By <b>@<?php echo htmlspecialchars($ext['author_name']); ?></b></span>
                            <span>Version <b>v<?php echo htmlspecialchars($ext['version']); ?></b></span>
                            <div class="rating-badge">
                                <i class="fa-solid fa-star"></i>
                                <span><?php echo $ext['avg_rating'] ? number_format($ext['avg_rating'], 1) : 'No Ratings'; ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- LEFT COL -->
                <div class="ext-content">
                    <section class="ext-section">
                        <div class="section-title"><i class="fa-solid fa-align-left"></i> Description</div>
                        <div class="ext-description"><?php echo htmlspecialchars($ext['description']); ?></div>
                    </section>

                    <?php if(!empty($screenshots)): ?>
                    <section class="ext-section">
                        <div class="section-title"><i class="fa-solid fa-images"></i> Screenshots</div>
                        <div class="ss-gallery">
                            <?php foreach($screenshots as $ss): ?>
                            <div class="ss-item" onclick="openLightbox('../<?php echo htmlspecialchars($ss); ?>')">
                                <img src="../<?php echo htmlspecialchars($ss); ?>">
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                    <?php endif; ?>

                    <!-- Comments Section -->
                    <section class="ext-section">
                        <div class="section-title"><i class="fa-solid fa-comments"></i> Community Feedback</div>
                        
                        <?php if(isset($_SESSION['user_id'])): ?>
                        <div class="comment-input-box">
                            <textarea id="commentText" placeholder="Share your experience..."></textarea>
                            <div id="mainPreview" class="img-preview-area"></div>
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-top:15px;">
                                <div style="display:flex; gap:5px;">
                                    <label class="c-icon-btn" title="Add Photo">
                                        <i class="fa-solid fa-camera"></i>
                                        <input type="file" id="mainFileInput" hidden accept="image/*" onchange="handleFileSelect(event, 'mainPreview')">
                                    </label>
                                </div>
                                <button class="primary-btn" style="width:auto; padding:12px 30px; font-size:14px;" onclick="postComment()">
                                    <i class="fa-solid fa-paper-plane"></i> Post Review
                                </button>
                            </div>
                        </div>
                        <?php else: ?>
                        <div style="padding:20px; background:rgba(255,255,255,0.02); text-align:center; border-radius:16px; color:#666; margin-bottom:30px; border:1px dashed #222;">
                            Please <a href="loginb.php" style="color:var(--accent);">Sign In</a> to leave a review.
                        </div>
                        <?php endif; ?>

                    <div class="comments-list" id="commentsList">
                        <?php foreach($comments as $c): ?>
                        <div class="comment-item" id="comment-<?php echo $c['id']; ?>">
                            <div class="comment-main">
                                <div class="c-avatar">
                                    <?php if($c['profile_picture']): ?>
                                        <img src="../<?php echo htmlspecialchars($c['profile_picture']); ?>">
                                    <?php else: ?>
                                        <?php echo strtoupper(substr($c['username'],0,1)); ?>
                                    <?php endif; ?>
                                </div>
                                <div class="c-body">
                                    <div class="comment-header">
                                        <div class="comment-user"><b>@<?php echo htmlspecialchars($c['username']); ?></b> <span><?php echo date('M j, Y', strtotime($c['created_at'])); ?></span></div>
                                        <div class="comment-more-container">
                                            <button class="comment-more-btn" onclick="toggleMoreMenu(event, <?php echo $c['id']; ?>)">
                                                <i class="fa-solid fa-ellipsis-vertical"></i>
                                            </button>
                                            <div class="comment-more-menu" id="menu-<?php echo $c['id']; ?>">
                                                <?php if($c['user_id'] == $myId): ?>
                                                <div class="more-item delete" onclick="deleteComment(<?php echo $c['id']; ?>)"><i class="fa-solid fa-trash"></i> Delete</div>
                                                <?php endif; ?>
                                                <div class="more-item" onclick="reportComment(<?php echo $c['id']; ?>)"><i class="fa-solid fa-flag"></i> Report</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="comment-text"><?php echo htmlspecialchars($c['comment']); ?></div>
                                    
                                    <?php if($c['image_url']): ?>
                                    <div class="comment-image-attachment" onclick="openLightbox('../<?php echo $c['image_url']; ?>')">
                                        <img src="../<?php echo $c['image_url']; ?>">
                                    </div>
                                    <?php endif; ?>

                                    <div class="comment-actions">
                                        <button class="c-act" onclick="handleCommentVote(<?php echo $c['id']; ?>, 'like')" style="<?php echo ($c['my_vote'] == 'like') ? 'color:#3b82f6;' : ''; ?>">
                                            <i class="fa-solid fa-thumbs-up"></i> <span id="likes-<?php echo $c['id']; ?>"><?php echo $c['likes']; ?></span>
                                        </button>
                                        <button class="c-act" onclick="handleCommentVote(<?php echo $c['id']; ?>, 'dislike')" style="<?php echo ($c['my_vote'] == 'dislike') ? 'color:#ef4444;' : ''; ?>">
                                            <i class="fa-solid fa-thumbs-down"></i> <span id="dislikes-<?php echo $c['id']; ?>"><?php echo $c['dislikes']; ?></span>
                                        </button>
                                        <button class="c-act reply-btn" onclick="toggleReply(<?php echo $c['id']; ?>)">Reply</button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Reply Form -->
                            <div class="reply-form" id="rf-<?php echo $c['id']; ?>">
                                <textarea id="rt-<?php echo $c['id']; ?>" placeholder="Write your reply..."></textarea>
                                <div id="rp-<?php echo $c['id']; ?>" class="img-preview-area"></div>
                                <div style="display:flex; justify-content:space-between; align-items:center;">
                                    <label class="c-icon-btn" title="Add Photo">
                                        <i class="fa-solid fa-camera"></i>
                                        <input type="file" id="fi-<?php echo $c['id']; ?>" hidden accept="image/*" onchange="handleFileSelect(event, 'rp-<?php echo $c['id']; ?>')">
                                    </label>
                                    <div style="display:flex; gap:8px;">
                                        <button class="flox-btn-cancel" style="padding:4px 12px; font-size:11px;" onclick="toggleReply(<?php echo $c['id']; ?>)">Cancel</button>
                                        <button class="flox-btn-confirm" style="padding:4px 15px; font-size:11px;" onclick="postComment(<?php echo $c['id']; ?>)">Post Reply</button>
                                    </div>
                                </div>
                            </div>

                            <!-- Replies Container -->
                            <?php if(isset($replies[$c['id']])): ?>
                            <div class="replies-container">
                                <?php foreach($replies[$c['id']] as $r): ?>
                                <div class="reply-item">
                                    <div class="r-avatar">
                                        <?php if($r['profile_picture']): ?>
                                            <img src="../<?php echo htmlspecialchars($r['profile_picture']); ?>">
                                        <?php else: ?>
                                            <?php echo strtoupper(substr($r['username'],0,1)); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="c-body">
                                        <div class="comment-header">
                                            <div class="comment-user"><b>@<?php echo htmlspecialchars($r['username']); ?></b> <span><?php echo date('M j, Y', strtotime($r['created_at'])); ?></span></div>
                                            <div class="comment-more-container">
                                                <button class="comment-more-btn" onclick="toggleMoreMenu(event, <?php echo $r['id']; ?>)">
                                                    <i class="fa-solid fa-ellipsis-vertical"></i>
                                                </button>
                                                <div class="comment-more-menu" id="menu-<?php echo $r['id']; ?>">
                                                    <?php if($r['user_id'] == $myId): ?>
                                                    <div class="more-item delete" onclick="deleteComment(<?php echo $r['id']; ?>)"><i class="fa-solid fa-trash"></i> Delete</div>
                                                    <?php endif; ?>
                                                    <div class="more-item" onclick="reportComment(<?php echo $r['id']; ?>)"><i class="fa-solid fa-flag"></i> Report</div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="comment-text"><?php echo htmlspecialchars($r['comment']); ?></div>
                                        
                                        <?php if($r['image_url']): ?>
                                        <div class="comment-image-attachment" onclick="openLightbox('../<?php echo $r['image_url']; ?>')">
                                            <img src="../<?php echo $r['image_url']; ?>">
                                        </div>
                                        <?php endif; ?>

                                        <div class="comment-actions">
                                            <button class="c-act" onclick="handleCommentVote(<?php echo $r['id']; ?>, 'like')" style="<?php echo ($r['my_vote'] == 'like') ? 'color:#3b82f6;' : ''; ?>">
                                                <i class="fa-solid fa-thumbs-up"></i> <span id="likes-<?php echo $r['id']; ?>"><?php echo $r['likes']; ?></span>
                                            </button>
                                            <button class="c-act" onclick="handleCommentVote(<?php echo $r['id']; ?>, 'dislike')" style="<?php echo ($r['my_vote'] == 'dislike') ? 'color:#ef4444;' : ''; ?>">
                                                <i class="fa-solid fa-thumbs-down"></i> <span id="dislikes-<?php echo $r['id']; ?>"><?php echo $r['dislikes']; ?></span>
                                            </button>
                                            <button class="c-act reply-btn" onclick="toggleReply(<?php echo $c['id']; ?>, '@<?php echo $r['username']; ?> ')">Reply</button>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                            
                            <?php if(empty($comments)): ?>
                                <div id="noComments" style="text-align:center; padding:40px; color:#444;">No reviews yet. Be the first to share your thoughts!</div>
                            <?php endif; ?>
                        </div>
                    </section>
                </div>

                <!-- RIGHT COL -->
                <div class="ext-sidebar">
                    <div class="ext-section action-card">
                        <div class="price-tag">
                            <?php if($ext['price'] == 0): ?>
                                <span class="free">FREE</span>
                            <?php else: ?>
                                <i class="fa-solid fa-coins"></i> <?php echo number_format($ext['price']); ?>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Rate Extension UI -->
                        <div class="star-rating" id="starRating">
                            <?php for($i=1;$i<=5;$i++): ?>
                                <i class="fa-solid fa-star star <?php echo $i <= $myRating ? 'active' : ''; ?>" data-val="<?php echo $i; ?>" onclick="setRating(<?php echo $i; ?>)"></i>
                            <?php endfor; ?>
                        </div>
                        <div class="rating-count" id="totalVotes"><?php echo ($ext['avg_rating'] ? round($ext['avg_rating'], 1) : '0'); ?>/5 (<?php echo ($ext['total_ratings'] ?? 0); ?> reviews)</div>

                        <button class="primary-btn" id="installBtn" onclick="handleInstall()">
                            <i class="fa-solid fa-cloud-arrow-down"></i> Install Extension
                        </button>
                        <div class="secondary-actions">
                            <?php 
                                // Check if user already liked
                                $isLiked = false;
                                if(isset($_SESSION['user_id'])){
                                    $lk = $pdo->prepare("SELECT 1 FROM extension_likes WHERE extension_id = ? AND user_id = ?");
                                    $lk->execute([$extId, $_SESSION['user_id']]);
                                    $isLiked = (bool)$lk->fetch();
                                }
                            ?>
                            <button class="sec-btn <?php echo $isLiked ? 'liked':''; ?>" id="likeBtn" onclick="handleLike()">
                                <i class="fa-solid fa-heart"></i> <span id="likeCount"><?php echo $ext['likes_count']; ?></span>
                            </button>
                            <button class="sec-btn" style="color:#ef4444;" onclick="reportExtension()">
                                <i class="fa-solid fa-flag"></i> Report
                            </button>
                        </div>
                    </div>

                    <div class="ext-section" style="background: transparent;">
                        <div class="section-title" style="font-size:14px;">Marketplace Metrics</div>
                        <div style="display:flex; flex-direction:column; gap:15px;">
                            <div style="display:flex; justify-content:space-between; font-size:13px; color:#666;">
                                <span>Support</span>
                                <span style="color:#aaa;">floxxteam@gmail.com</span>
                            </div>
                            <div style="display:flex; justify-content:space-between; font-size:13px; color:#666;">
                                <span>Installs</span>
                                <span style="color:#3b82f6; font-weight:700;" id="installCount"><?php echo number_format($ext['installs_count']); ?></span>
                            </div>
                            <div style="display:flex; justify-content:space-between; font-size:13px; color:#666;">
                                <span>Status</span>
                                <span style="color:#22c55e; font-weight:700;">Verified</span>
                            </div>
                            <div style="display:flex; justify-content:space-between; font-size:13px; color:#666;">
                                <span>Last Update</span>
                                <span style="color:#aaa;"><?php echo date('M j, Y', strtotime($ext['created_at'])); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Lightbox Modal -->
    <div id="lightbox" onclick="this.style.display='none'">
        <div class="close"><i class="fa-solid fa-xmark"></i></div>
        <img id="lightboxImg" src="">
    </div>

    <!-- Custom Popup Modal -->
    <div class="flox-modal-overlay" id="floxModal">
        <div class="flox-modal">
            <div class="flox-modal-title" id="floxModalTitle">Notification</div>
            <div class="flox-modal-text" id="floxModalText">Message goes here.</div>
            <input type="text" id="floxModalInput" class="flox-modal-input" style="display:none;" placeholder="Type here...">
            <div class="flox-modal-btns">
                <button class="flox-btn-cancel" id="floxCancelBtn" onclick="FloxUI.close()">Cancel</button>
                <button class="flox-btn-confirm" id="floxConfirmBtn">OK</button>
            </div>
        </div>
    </div>

    <script>
        const EXT_ID = <?php echo $extId; ?>;

        const FloxUI = {
            overlay: document.getElementById('floxModal'),
            title: document.getElementById('floxModalTitle'),
            text: document.getElementById('floxModalText'),
            input: document.getElementById('floxModalInput'),
            confirmBtn: document.getElementById('floxConfirmBtn'),
            cancelBtn: document.getElementById('floxCancelBtn'),
            
            alert(title, message, callback) {
                this.setup(title, message, false);
                this.confirmBtn.onclick = () => { this.close(); if(callback) callback(); };
                this.overlay.style.display = 'flex';
            },
            
            prompt(title, message, callback) {
                this.setup(title, message, true);
                this.input.value = '';
                this.confirmBtn.onclick = () => { 
                    const val = this.input.value.trim();
                    this.close(); 
                    if(callback) callback(val); 
                };
                this.overlay.style.display = 'flex';
                this.input.focus();
            },

            setup(t, m, showInput) {
                this.title.innerText = t;
                this.text.innerText = m;
                this.input.style.display = showInput ? 'block' : 'none';
                this.cancelBtn.style.display = showInput ? 'block' : 'none';
            },

            close() { this.overlay.style.display = 'none'; }
        };

        function openLightbox(src) {
            const lb = document.getElementById('lightbox');
            const img = document.getElementById('lightboxImg');
            img.src = src;
            lb.style.display = 'flex';
        }

        function toggleMoreMenu(e, id) {
            e.stopPropagation();
            document.querySelectorAll('.comment-more-menu.active').forEach(m => {
                if(m.id !== 'menu-' + id) m.classList.remove('active');
            });
            document.getElementById('menu-' + id).classList.toggle('active');
        }

        window.onclick = function(e) {
            if(!e.target.closest('.comment-more-container')) {
                document.querySelectorAll('.comment-more-menu.active').forEach(m => m.classList.remove('active'));
            }
        }

        function deleteComment(id) {
            if(!confirm('Delete this comment?')) return;
            fetch('../backend/market_actions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete_comment', extension_id: EXT_ID, comment_id: id })
            })
            .then(r => r.json())
            .then(d => {
                if(d.success) location.reload();
                else FloxUI.alert('Error', d.message);
            })
            .catch(e => console.error(e));
        }

        function reportComment(id) {
            FloxUI.prompt('Report Comment', 'Why are you reporting this?', async (reason) => {
                if(!reason) return;
                FloxUI.alert('Report Received', 'Thank you. Our team will review this message.');
            });
        }

        // Star Hover Effects
        document.addEventListener('DOMContentLoaded', () => {
            const stars = document.querySelectorAll('#starRating .star');
            stars.forEach(s => {
                s.addEventListener('mouseenter', () => {
                    const val = parseInt(s.dataset.val);
                    stars.forEach(star => {
                        if(parseInt(star.dataset.val) <= val) star.classList.add('hov');
                        else star.classList.remove('hov');
                    });
                });
                s.addEventListener('mouseleave', () => {
                    stars.forEach(star => star.classList.remove('hov'));
                });
            });
        });

        async function setRating(r) {
            try {
                const res = await fetch('../backend/market_actions.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'rate', extension_id: EXT_ID, rating: r })
                });
                const d = await res.json();
                if(d.success) {
                    // Update UI locally
                    const stars = document.querySelectorAll('#starRating .star');
                    stars.forEach(s => {
                        const val = parseInt(s.dataset.val);
                        s.classList.toggle('active', val <= r);
                    });
                    
                    document.getElementById('totalVotes').innerText = `${d.avg}/5 (${d.total} reviews)`;
                    
                    // Update main header rating too
                    const headerRating = document.querySelector('.rating-badge span');
                    if(headerRating) headerRating.innerText = d.avg;

                } else {
                    if (d.message === 'Unauthorized') FloxUI.alert('Auth Required', 'Please log in to rate extensions');
                    else FloxUI.alert('Error', d.message);
                }
            } catch(e) { console.error(e); }
        }

        async function handleLike() {
            const btn = document.getElementById('likeBtn');
            const count = document.getElementById('likeCount');
            try {
                const r = await fetch('../backend/market_actions.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'like', extension_id: EXT_ID })
                });
                const d = await r.json();
                if (d.success) {
                    btn.classList.toggle('liked', d.liked);
                    count.textContent = d.count;
                } else {
                    if (d.message === 'Unauthorized') FloxUI.alert('Auth Required', 'Please log in to like this extension');
                }
            } catch(e) { console.error(e); }
        }

        async function handleCommentVote(commId, type) {
            const likeEl = document.getElementById('likes-' + commId);
            const dislikeEl = document.getElementById('dislikes-' + commId);
            const btn = event.currentTarget;
            
            // Check current active state
            const isCurrentlyLiked = btn.parentElement.querySelector('.c-act[onclick*="like"]').style.color === 'rgb(59, 130, 246)';
            const isCurrentlyDisliked = btn.parentElement.querySelector('.c-act[onclick*="dislike"]').style.color === 'rgb(239, 68, 68)';

            // Visual Burst Animation
            btn.classList.add('animating');
            setTimeout(() => btn.classList.remove('animating'), 500);

            // Optimistic Toggle Logic
            if (type === 'like') {
                if (isCurrentlyLiked) {
                    likeEl.textContent = parseInt(likeEl.textContent) - 1;
                    btn.style.color = '';
                } else {
                    likeEl.textContent = parseInt(likeEl.textContent) + 1;
                    btn.style.color = '#3b82f6';
                    if(isCurrentlyDisliked) {
                        dislikeEl.textContent = parseInt(dislikeEl.textContent) - 1;
                        btn.parentElement.querySelector('.c-act[onclick*="dislike"]').style.color = '';
                    }
                }
            } else {
                if (isCurrentlyDisliked) {
                    dislikeEl.textContent = parseInt(dislikeEl.textContent) - 1;
                    btn.style.color = '';
                } else {
                    dislikeEl.textContent = parseInt(dislikeEl.textContent) + 1;
                    btn.style.color = '#ef4444';
                    if(isCurrentlyLiked) {
                        likeEl.textContent = parseInt(likeEl.textContent) - 1;
                        btn.parentElement.querySelector('.c-act[onclick*="like"]').style.color = '';
                    }
                }
            }

            // Silent Backend Update
            try {
                const r = await fetch('../backend/market_actions.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'vote_comment', extension_id: EXT_ID, comment_id: commId, vote_type: type })
                });
                const d = await r.json();
                if (d.success) {
                    // Final Sync
                    likeEl.textContent = d.counts.likes;
                    dislikeEl.textContent = d.counts.dislikes;
                    
                    // Correct colors if server state differed
                    const lBtn = btn.parentElement.querySelector('.c-act[onclick*="like"]');
                    const dBtn = btn.parentElement.querySelector('.c-act[onclick*="dislike"]');
                    
                    if(d.action === 'removed') {
                        btn.style.color = '';
                    } else if(type === 'like') {
                        lBtn.style.color = '#3b82f6';
                        dBtn.style.color = '';
                    } else {
                        dBtn.style.color = '#ef4444';
                        lBtn.style.color = '';
                    }
                }
            } catch(e) { console.error('Silent sync failed', e); }
        }

        function toggleReply(commId, mention = '') {
            const form = document.getElementById('rf-' + commId);
            const box = document.getElementById('rt-' + commId);
            const isOpening = form.style.display !== 'block';
            
            form.style.display = isOpening ? 'block' : 'none';
            if(isOpening) {
                box.value = mention;
                box.focus();
            }
        }

        async function postComment(parentId = null) {
            const box = parentId ? document.getElementById('rt-' + parentId) : document.getElementById('commentText');
            const fileInput = parentId ? document.getElementById('fi-' + parentId) : document.getElementById('mainFileInput');
            const text = box.value.trim();
            if (!text && !fileInput.files[0]) return;

            const formData = new FormData();
            formData.append('action', 'comment');
            formData.append('extension_id', EXT_ID);
            formData.append('comment', text);
            if(parentId) formData.append('parent_id', parentId);
            if(fileInput.files[0]) formData.append('image', fileInput.files[0]);

            try {
                const r = await fetch('../backend/market_actions.php', {
                    method: 'POST',
                    body: formData
                });
                const d = await r.json();
                if (d.success) {
                    box.value = '';
                    fileInput.value = ''; 
                    const pArea = parentId ? document.getElementById('rp-' + parentId) : document.getElementById('mainPreview');
                    pArea.innerHTML = ''; 
                    
                    if(parentId) toggleReply(parentId);
                    
                    injectCommentLocally(parentId, d.comment_id, d.user, text, d.image_url);
                    
                    const noComms = document.getElementById('noComments');
                    if(noComms) noComms.style.display = 'none';
                } else {
                    FloxUI.alert('Error', d.message);
                }
            } catch(e) { FloxUI.alert('Error', 'Submission failed.'); }
        }

        function handleFileSelect(e, previewId) {
            const file = e.target.files[0];
            const area = document.getElementById(previewId);
            area.innerHTML = '';
            if(!file) return;

            const reader = new FileReader();
            reader.onload = function(ex) {
                area.innerHTML = `
                    <div class="preview-box">
                        <img src="${ex.target.result}">
                        <div class="preview-remove" onclick="clearFile('${previewId}')">&times;</div>
                    </div>
                `;
            };
            reader.readAsDataURL(file);
        }

        function clearFile(previewId) {
            const area = document.getElementById(previewId);
            area.innerHTML = '';
            if(previewId === 'mainPreview') document.getElementById('mainFileInput').value = '';
            else {
                const parts = previewId.split('-');
                document.getElementById('fi-' + parts[1]).value = '';
            }
        }

        async function handleInstall() {
            try {
                const r = await fetch('../backend/market_actions.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'install', extension_id: EXT_ID })
                });
                const d = await r.json();
                if(d.success) {
                    document.getElementById('installCount').innerText = d.count;
                    FloxUI.alert('Success', 'Installation started!');
                    // Trigger engine to load it immediately
                    if(window.FloxExtensionEngine) FloxExtensionEngine.init();
                }
            } catch(e) { console.error(e); }
        }

        function injectCommentLocally(parentId, id, user, text, imgUrl) {
            const dateStr = 'Just now';
            const avatar = user.profile_picture ? `<img src="../${user.profile_picture}">` : (user.username[0].toUpperCase());
            const imgHtml = imgUrl ? `
                <div class="comment-image-attachment" onclick="openLightbox('../${imgUrl}')">
                    <img src="../${imgUrl}">
                </div>` : '';
            
            if (!parentId) {
                const list = document.getElementById('commentsList');
                const html = `
                    <div class="comment-item" id="comment-${id}" style="border-left: 2px solid var(--accent); background: rgba(168,85,247,0.02)">
                        <div class="comment-main">
                            <div class="c-avatar">${avatar}</div>
                            <div class="c-body">
                                <div class="comment-header">
                                    <div class="comment-user"><b>@${user.username}</b> <span>${dateStr}</span></div>
                                </div>
                                <div class="comment-text">${text}</div>
                                ${imgHtml}
                                <div class="comment-actions">
                                    <button class="c-act"><i class="fa-solid fa-thumbs-up"></i> <span>0</span></button>
                                    <button class="c-act"><i class="fa-solid fa-thumbs-down"></i> <span>0</span></button>
                                    <button class="c-act reply-btn" onclick="location.reload()">Reply</button>
                                </div>
                            </div>
                        </div>
                    </div>`;
                list.insertAdjacentHTML('afterbegin', html);
            } else {
                const parentEl = document.getElementById('comment-' + parentId);
                let container = parentEl.querySelector('.replies-container');
                if(!container) {
                    parentEl.insertAdjacentHTML('beforeend', '<div class="replies-container"></div>');
                    container = parentEl.querySelector('.replies-container');
                }
                
                const html = `
                    <div class="reply-item" style="border-left: 2px solid var(--accent); background: rgba(168,85,247,0.02)">
                        <div class="r-avatar">${avatar}</div>
                        <div class="c-body">
                            <div class="comment-user"><b>@${user.username}</b> <span>${dateStr}</span></div>
                            <div class="comment-text">${text}</div>
                            ${imgHtml}
                            <div class="comment-actions">
                                <button class="c-act"><i class="fa-solid fa-thumbs-up"></i> <span>0</span></button>
                                <button class="c-act"><i class="fa-solid fa-thumbs-down"></i> <span>0</span></button>
                            </div>
                        </div>
                    </div>`;
                container.insertAdjacentHTML('afterbegin', html);
            }
        }

        function reportExtension() {
            FloxUI.prompt('Report Extension', 'Please provide a reason for this report:', async (reason) => {
                if (!reason) return;
                try {
                    const r = await fetch('../backend/market_actions.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'report', extension_id: EXT_ID, reason: reason })
                    });
                    const d = await r.json();
                    if (d.success) {
                        FloxUI.alert('Reported', 'Thank you. Our team at floxxteam@gmail.com has been notified.');
                    } else {
                        FloxUI.alert('Error', d.message);
                    }
                } catch(e) { FloxUI.alert('Error', 'Report submission failed.'); }
            });
        }
    </script>
</body>
</html>
