<?php
/**
 * Extension Studio Preview Frame
 * Loads a simplified Loop UI for extension testing
 * with the current user's session
 */
session_start();
require_once '../backend/config.php';

// Must be logged in
if (!isset($_SESSION['user_id'])) {
    echo '<html><body style="background:#0a0a0f;color:#f44;font-family:Inter,sans-serif;padding:40px;"><h2>Not Authenticated</h2><p>Please log in to Loop first.</p></body></html>';
    exit;
}

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'User';

// Get basic user data
try {
    $stmt = $pdo->prepare("SELECT username, profile_picture FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $username = $user['username'] ?? $username;
    $profilePic = $user['profile_picture'] ?? null;
} catch (Exception $e) {
    $profilePic = null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preview Frame</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/animejs/3.2.1/anime.min.js"></script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background: #0a0a0f;
            color: #fff;
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
        }
        
        /* Header */
        .header {
            height: 60px;
            background: linear-gradient(180deg, #111 0%, #0a0a0f 100%);
            border-bottom: 1px solid rgba(255,255,255,0.05);
            display: flex;
            align-items: center;
            padding: 0 24px;
            gap: 24px;
        }
        .logo {
            width: 36px; height: 36px;
            background: linear-gradient(135deg, #9333ea 0%, #a855f7 100%);
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-weight: 800; font-size: 18px;
        }
        .search-bar {
            flex: 1;
            max-width: 500px;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 24px;
            padding: 10px 20px;
            color: #888;
            font-size: 14px;
        }
        .user-area {
            display: flex; align-items: center; gap: 16px;
        }
        .user-avatar {
            width: 36px; height: 36px;
            background: #333;
            border-radius: 50%;
            <?php if ($profilePic): ?>
            background-image: url('<?php echo htmlspecialchars($profilePic); ?>');
            background-size: cover;
            <?php endif; ?>
        }
        
        /* Main Layout */
        .main {
            display: flex;
            min-height: calc(100vh - 60px);
        }
        
        /* Sidebar */
        .sidebar {
            width: 240px;
            background: #08080c;
            border-right: 1px solid rgba(255,255,255,0.05);
            padding: 20px 12px;
        }
        .nav-item {
            display: flex; align-items: center; gap: 14px;
            padding: 12px 16px;
            border-radius: 10px;
            color: #888;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            margin-bottom: 4px;
        }
        .nav-item:hover { background: rgba(255,255,255,0.03); color: #ccc; }
        .nav-item.active { background: rgba(147,51,234,0.15); color: #fff; }
        .nav-item i { width: 20px; text-align: center; }
        
        /* Content */
        .content {
            flex: 1;
            padding: 24px;
            overflow: auto;
        }
        
        /* Video Player Target */
        .video-player {
            width: 100%;
            aspect-ratio: 16/9;
            background: #000;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            border: 1px solid #222;
            position: relative;
        }
        .video-player[data-flox="video.player"] {}
        .play-icon { font-size: 64px; color: #333; }
        .video-controls {
            position: absolute;
            bottom: 0; left: 0; right: 0;
            height: 50px;
            background: linear-gradient(transparent, rgba(0,0,0,0.8));
            display: flex;
            align-items: center;
            padding: 0 16px;
            gap: 16px;
            border-radius: 0 0 16px 16px;
        }
        .video-controls[data-flox="video.controls"] {}
        .progress-bar {
            flex: 1; height: 4px; background: #333; border-radius: 2px;
        }
        .progress-fill { width: 35%; height: 100%; background: #a855f7; border-radius: 2px; }
        
        /* Video Info */
        .video-title {
            font-size: 20px; font-weight: 700; margin-bottom: 8px;
        }
        .video-meta { color: #666; font-size: 14px; margin-bottom: 20px; }
        
        /* Chat Panel Target */
        .chat-panel {
            background: #111;
            border: 1px solid #222;
            border-radius: 16px;
            padding: 16px;
            margin-top: 20px;
        }
        .chat-panel[data-flox="chat.panel"] {}
        .chat-header {
            font-size: 14px; font-weight: 700;
            color: #888;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .chat-header i { color: #a855f7; }
        .chat-messages { }
        .message {
            background: #1a1a1f;
            border-radius: 10px;
            padding: 10px 14px;
            margin-bottom: 8px;
            font-size: 13px;
            color: #aaa;
        }
        .message .author { color: #a855f7; font-weight: 600; margin-right: 8px; }
        
        .chat-input {
            display: flex;
            gap: 10px;
            margin-top: 12px;
        }
        .chat-input input {
            flex: 1;
            background: #0a0a0f;
            border: 1px solid #333;
            border-radius: 8px;
            padding: 10px 14px;
            color: #fff;
            font-size: 13px;
        }
        .chat-input input:focus { outline: none; border-color: #a855f7; }
        .chat-input button {
            background: #a855f7;
            border: none;
            border-radius: 8px;
            padding: 10px 16px;
            color: #fff;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <header class="header" data-flox="header">
        <div class="logo">F</div>
        <div class="search-bar">Search Loop...</div>
        <div class="user-area">
            <i class="fa-solid fa-bell" style="color:#666;"></i>
            <div class="user-avatar"></div>
        </div>
    </header>
    
    <div class="main">
        <aside class="sidebar" data-flox="sidebar">
            <div class="nav-item active"><i class="fa-solid fa-house"></i> Home</div>
            <div class="nav-item"><i class="fa-solid fa-compass"></i> Explore</div>
            <div class="nav-item"><i class="fa-solid fa-clock-rotate-left"></i> History</div>
            <div class="nav-item"><i class="fa-solid fa-bookmark"></i> Watchlist</div>
        </aside>
        
        <main class="content">
            <div class="video-player" data-flox="video.player">
                <i class="fa-solid fa-play play-icon"></i>
                <div class="video-controls" data-flox="video.controls">
                    <i class="fa-solid fa-play" style="color:#fff;"></i>
                    <div class="progress-bar"><div class="progress-fill"></div></div>
                    <span style="color:#888;font-size:12px;">3:24 / 10:15</span>
                    <i class="fa-solid fa-expand" style="color:#888;"></i>
                </div>
            </div>
            
            <div class="video-info" data-flox="video.info">
                <div class="video-title">Welcome to Loop Extension Studio</div>
                <div class="video-meta">12,345 views • 2 hours ago • @<?php echo htmlspecialchars($username); ?></div>
            </div>
            
            <div class="chat-panel" data-flox="chat.panel">
                <div class="chat-header"><i class="fa-solid fa-comments"></i> Live Chat</div>
                <div class="chat-messages">
                    <div class="message"><span class="author">FloxBot</span> Welcome to the stream!</div>
                    <div class="message"><span class="author"><?php echo htmlspecialchars($username); ?></span> Testing my new extension 🎉</div>
                    <div class="message"><span class="author">Viewer</span> This looks amazing!</div>
                </div>
                <div class="chat-input">
                    <input type="text" placeholder="Send a message...">
                    <button><i class="fa-solid fa-paper-plane"></i></button>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
