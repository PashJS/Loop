<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: loginb.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Post - Loop</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="home.css">
    <link rel="stylesheet" href="layout.css">
    <style>
        .create-post-wrapper {
            max-width: 800px;
            margin: 120px auto 60px;
            padding: 0 20px;
            animation: fadeInUp 0.8s ease;
        }

        .glass-card {
            background: rgba(15, 15, 20, 0.7);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            padding: 40px;
            box-shadow: 0 40px 100px rgba(0, 0, 0, 0.5);
        }

        .header-title {
            font-size: 32px;
            font-weight: 800;
            margin-bottom: 30px;
            background: linear-gradient(135deg, #fff 0%, #aaa 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .post-textarea {
            width: 100%;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 20px;
            color: #fff;
            font-size: 18px;
            resize: none;
            min-height: 200px;
            outline: none;
            transition: 0.3s;
            margin-bottom: 20px;
        }

        .post-textarea:focus {
            background: rgba(255, 255, 255, 0.08);
            border-color: var(--accent-color, #0071e3);
            box-shadow: 0 0 30px rgba(0, 113, 227, 0.2);
        }

        .post-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .publish-btn {
            background: var(--accent-color, #0071e3);
            color: #fff;
            border: none;
            padding: 12px 32px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 16px;
            cursor: pointer;
            transition: 0.3s;
        }

        .publish-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 113, 227, 0.3);
        }

        .publish-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <div class="create-post-wrapper">
        <div class="glass-card">
            <h1 class="header-title"><i class="fa-solid fa-pen-nib"></i> Create Community Post</h1>
            <textarea id="postContent" class="post-textarea" placeholder="What's on your mind?"></textarea>
            
            <div class="post-controls">
                <div class="char-count" style="color: rgba(255,255,255,0.4);"><span id="charNum">0</span>/2000</div>
                <div style="display:flex; gap:12px;">
                    <a href="create_poll.php" class="publish-btn" style="background: rgba(157, 0, 255, 0.1); border: 1px solid rgba(157, 0, 255, 0.3); color: #c07bff; text-decoration: none; display: flex; align-items: center; gap: 8px;">
                        <i class="fa-solid fa-chart-simple"></i> Poll
                    </a>
                    <button id="publishBtn" class="publish-btn">Publish Post</button>
                </div>
            </div>
        </div>
    </div>

    <script src="theme.js"></script>
    <script>
        const textarea = document.getElementById('postContent');
        const publishBtn = document.getElementById('publishBtn');
        const charNum = document.getElementById('charNum');

        textarea.addEventListener('input', () => {
            const length = textarea.value.length;
            charNum.textContent = length;
            publishBtn.disabled = length === 0 || length > 2000;
        });

        publishBtn.addEventListener('click', async () => {
            const content = textarea.value.trim();
            if (!content) return;

            publishBtn.disabled = true;
            publishBtn.textContent = 'Publishing...';

            try {
                const res = await fetch('../backend/createPost.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ content })
                });
                const data = await res.json();
                
                if (data.success) {
                    window.location.href = 'accountmanagement.php?sector=posts';
                } else {
                    publishBtn.disabled = false;
                    publishBtn.textContent = 'Publish Post';
                }
            } catch (err) {
                publishBtn.disabled = false;
                publishBtn.textContent = 'Publish Post';
            }
        });
    </script>
</body>
</html>
