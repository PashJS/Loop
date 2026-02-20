<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../backend/config.php';

$userId = $_GET['user_id'] ?? null;

// If no user_id, redirect home
if (!$userId) {
    header("Location: home.php");
    exit;
}

// Check if user exists
try {
    $stmt = $pdo->prepare("SELECT id, username FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        header("Location: home.php");
        exit;
    }
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Story - <?php echo htmlspecialchars($user['username']); ?></title>
    <link rel="stylesheet" href="home.css">
    <link rel="stylesheet" href="layout.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --story-bg: #000;
        }
        body {
            background: #000;
            margin: 0;
            padding: 0;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            width: 100vw;
            color: #fff;
            font-family: 'Inter', -apple-system, sans-serif;
        }

        /* Immersive Background Blur */
        #storyBackground {
            position: fixed;
            top: -10%;
            left: -10%;
            width: 120%;
            height: 120%;
            z-index: 1;
            filter: blur(100px) brightness(0.35);
            transition: opacity 1.2s ease, transform 1.2s ease;
            opacity: 0;
            transform: scale(1.1);
            background: #000;
        }
        #storyBackground.loaded {
            opacity: 1;
            transform: scale(1);
        }
        #storyBackground img, #storyBackground video {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        #storyContainer {
            width: 100%;
            height: 100%;
            max-width: 480px;
            max-height: 90vh;
            position: relative;
            z-index: 10;
            background: #000;
            border-radius: 30px;
            box-shadow: 0 30px 100px rgba(0,0,0,0.9);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: transform 0.4s cubic-bezier(0.2, 0.8, 0.2, 1);
            border: 1px solid rgba(255,255,255,0.08);
        }

        @media (max-width: 600px) {
            #storyContainer {
                max-width: 100%;
                max-height: 100%;
                border-radius: 0;
                border: none;
            }
            #storyBackground {
                display: none;
            }
        }

        /* Override/Enhance story viewer styles from layout.css */
        #storyViewer {
            display: flex !important;
            position: relative !important;
            width: 100% !important;
            height: 100% !important;
            z-index: 10 !important;
            background: transparent !important;
        }

        .story-viewer-header {
            padding: 30px 20px !important;
            top: 0px !important;
            background: linear-gradient(to bottom, rgba(0,0,0,0.8), transparent) !important;
            border-radius: 30px 30px 0 0;
            display: flex !important;
            align-items: center !important;
        }

        .story-viewer-avatar {
            width: 48px !important;
            height: 48px !important;
            border: 2px solid #fff !important;
            box-shadow: 0 0 20px rgba(0,0,0,0.5);
            transition: transform 0.3s ease;
        }
        .story-viewer-avatar:hover {
            transform: scale(1.1);
        }

        .story-viewer-name {
            font-size: 17px !important;
            letter-spacing: -0.3px;
        }

        .story-progress {
            top: 15px !important;
            padding: 0 15px !important;
            gap: 4px !important;
        }

        .story-bar {
            height: 3px !important;
            border-radius: 10px !important;
            background: rgba(255, 255, 255, 0.2) !important;
            backdrop-filter: blur(5px);
        }

        .story-bar-fill {
            background: #fff !important;
            box-shadow: 0 0 10px rgba(255,255,255,0.8) !important;
            border-radius: 10px;
        }

        .story-footer {
            background: linear-gradient(to top, rgba(0,0,0,0.8), transparent) !important;
            padding: 30px 20px !important;
            bottom: 0px !important;
            right: 0px !important;
            left: 0px !important;
            flex-direction: row !important;
            justify-content: flex-end;
            gap: 20px !important;
            pointer-events: none;
            z-index: 1000 !important;
        }

        .story-action {
            pointer-events: auto;
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            width: 54px;
            height: 54px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px !important;
            border: 1px solid rgba(255,255,255,0.1);
            color: #fff;
            box-shadow: 0 8px 32px rgba(0,0,0,0.3);
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .story-action:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-5px) scale(1.1);
            box-shadow: 0 12px 40px rgba(0,0,0,0.5);
        }

        #storyCloseBtn {
            background: rgba(255,255,255,0.1) !important;
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            width: 44px;
            height: 44px;
            border-radius: 50% !important;
            font-size: 22px !important;
            display: flex !important;
            align-items: center;
            justify-content: center;
            padding: 0 !important;
            border: 1px solid rgba(255,255,255,0.1) !important;
            color: #fff;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            margin-right: -5px;
        }
        #storyCloseBtn:hover {
            background: rgba(255,255,255,0.2) !important;
            transform: rotate(90deg) scale(1.1);
        }

        /* Content scaling */
        .story-content {
            background: #000;
            width: 100%;
            height: 100%;
        }
        .story-content img, .story-content video {
            width: 100%;
            height: 100%;
            object-fit: contain !important; /* Contain by default for premium look, or cover? User said full screen */
        }
        
        /* Navigation Areas Hint */
        .story-nav-area {
            opacity: 0;
            transition: opacity 0.3s ease;
            background: linear-gradient(to right, rgba(255,255,255,0.05), transparent);
        }
        .story-nav-next {
            background: linear-gradient(to left, rgba(255,255,255,0.05), transparent);
        }
        .story-nav-area:hover {
            opacity: 1;
        }

    </style>
</head>
<body>

    <div id="storyBackground"></div>

    <div id="storyContainer">
        <!-- storyViewer will be placed here by stories.js -->
    </div>

    <script>
        window.myUserId = <?php echo $_SESSION['user_id'] ?? 'null'; ?>;
        window.autoOpenStory = <?php echo (int)$userId; ?>;
    </script>
    <script src="stories.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const container = document.getElementById('storyContainer');
            const bgContainer = document.getElementById('storyBackground');
            
            // Periodically check for viewer to move it into container
            const moveInterval = setInterval(() => {
                const viewer = document.getElementById('storyViewer');
                if (viewer && viewer.parentElement !== container) {
                    container.appendChild(viewer); 
                    console.log("[ViewStory] Viewer moved to container");
                    
                    // Hook into story changes to update background
                    if (window.storySystem) {
                        const originalShowStory = window.storySystem.showStory;
                        window.storySystem.showStory = function() {
                            originalShowStory.apply(this, arguments);
                            updateBackground();
                        };
                    }
                    
                    clearInterval(moveInterval);
                }
            }, 50);

            function updateBackground() {
                if (!window.storySystem || !window.storySystem.currentStories) return;
                const story = window.storySystem.currentStories[window.storySystem.currentIndex];
                if (!story) return;

                bgContainer.classList.remove('loaded');
                setTimeout(() => {
                    bgContainer.innerHTML = '';
                    const path = '../uploads/stories/' + story.content_path;
                    
                    if (story.type === 'image') {
                        const img = document.createElement('img');
                        img.src = path;
                        bgContainer.appendChild(img);
                    } else {
                        const video = document.createElement('video');
                        video.src = path;
                        video.muted = true;
                        video.autoplay = true;
                        video.loop = true;
                        bgContainer.appendChild(video);
                    }
                    bgContainer.classList.add('loaded');
                }, 300);
            }

            // Trigger open
            if (window.storySystem && window.autoOpenStory) {
                console.log("[ViewStory] Triggering auto-open for user:", window.autoOpenStory);
                window.storySystem.open(window.autoOpenStory);
            }
            
            // Cleanup interval after 5 seconds just in case
            setTimeout(() => clearInterval(moveInterval), 5000);
        });
    </script>
</body>
</html>
