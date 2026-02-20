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
    <title>Loop - Upload Video</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="home.css?v=1"/>
    <link rel="stylesheet" href="layout.css"/>
    <link rel="stylesheet" href="upload.css?v=2"/>
    <style>
        .starfield-canvas {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            background: #020205;
        }
        .upload-container {
            background: rgba(255, 255, 255, 0.03) !important;
            backdrop-filter: blur(40px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 40px 100px rgba(0,0,0,0.6);
        }
        .file-input-wrapper {
            background: rgba(255, 255, 255, 0.02) !important;
            border: 2px dashed rgba(255, 255, 255, 0.1) !important;
        }
        .form-group input, .form-group textarea {
            background: rgba(255, 255, 255, 0.05) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
        }
        .parts-editor-bg {
            background: rgba(255, 255, 255, 0.02) !important;
            backdrop-filter: blur(20px) !important;
        }
        .hashtags-container {
            background: rgba(255, 255, 255, 0.03) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
        }
    </style>
</head>
<body>
    <canvas id="starfield" class="starfield-canvas"></canvas>
    <?php include 'header.php'; ?>

    <div class="app-layout">
        <?php include 'sidebar.php'; ?>
        
        <main class="main-content">
            <div class="upload-main">
                <div class="upload-container">
                    <div class="upload-header">
                        <h1>Upload Video</h1>
                        <p>Share your story with the galaxy</p>
                    </div>

                    <form class="upload-form" id="uploadForm">
                        <input type="hidden" id="uploadType" name="is_clip" value="false">
                        
                        <!-- Video File Input -->
                        <div class="form-group file-group">
                            <label for="videoFile" class="file-label">
                                <div class="file-input-wrapper">
                                    <i class="fa-solid fa-cloud-arrow-up"></i>
                                    <span class="file-text">Choose Video File</span>
                                    <span class="file-subtext">MP4, WebM, OGG, MOV (Max 500MB)</span>
                                    <input type="file" id="videoFile" name="video" accept="video/*" required class="file-input-hidden"/>
                                </div>
                                <div class="file-name-display" id="videoFileName"></div>
                            </label>
                        </div>

                        <!-- Thumbnail File Input -->
                        <div class="form-group file-group">
                            <label for="thumbnailFile" class="file-label">
                                <div class="file-input-wrapper">
                                    <i class="fa-solid fa-image"></i>
                                    <span class="file-text">Choose Thumbnail (Optional)</span>
                                    <span class="file-subtext">JPEG, PNG, GIF</span>
                                    <input type="file" id="thumbnailFile" name="thumbnail" accept="image/*" class="file-input-hidden"/>
                                </div>
                                <div class="file-name-display" id="thumbnailFileName"></div>
                            </label>
                        </div>

                        <!-- Subtitles (Captions) File Input -->
                        <div class="form-group file-group">
                            <label for="captionsFile" class="file-label">
                                <div class="file-input-wrapper">
                                    <i class="fa-solid fa-closed-captioning"></i>
                                    <span class="file-text">Choose Subtitles (Optional)</span>
                                    <span class="file-subtext">VTT format</span>
                                    <input type="file" id="captionsFile" name="captions" accept=".vtt" class="file-input-hidden"/>
                                </div>
                                <div class="file-name-display" id="captionsFileName"></div>
                            </label>
                        </div>

                        <!-- Title -->
                        <div class="form-group">
                            <label for="videoTitle">Title *</label>
                            <input type="text" id="videoTitle" name="title" required maxlength="100" placeholder="Enter video title"/>
                        </div>

                        <!-- Description -->
                        <div class="form-group">
                            <label for="videoDescription">Description</label>
                            <textarea id="videoDescription" name="description" rows="4" placeholder="Enter video description (optional)" style="resize: none;"></textarea>
                        </div>

                        <!-- Hashtags -->
                        <div class="form-group">
                            <label for="hashtagsContainer">Hashtags</label>
                            <div class="hashtags-container" id="hashtagsContainer">
                                <div class="hashtags-list" id="hashtagsList"></div>
                                <button type="button" class="add-hashtag-btn" id="addHashtagBtn">
                                    <i class="fa-solid fa-plus"></i>
                                    <span>Add a hashtag...</span>
                                </button>
                            </div>
                            <div class="hashtag-input-wrapper" id="hashtagInputWrapper" style="display: none;">
                                <input type="text" id="hashtagInput" class="hashtag-input" placeholder="Enter hashtag (without #)" maxlength="50" />
                                <div class="hashtag-input-actions">
                                    <button type="button" class="hashtag-btn-cancel" id="hashtagCancelBtn">Cancel</button>
                                    <button type="button" class="hashtag-btn-add" id="hashtagAddBtn">Add</button>
                                </div>
                            </div>
                        </div>

                        <!-- Video Parts Editor (Interactive Chapters) -->
                        <div class="form-group parts-editor-group" id="partsEditorContainer" style="display: none;">
                            <label>Video Chapters & Interactive Parts</label>
                            <div class="parts-editor-card">
                                <div class="parts-editor-bg"></div>
                                <div class="parts-editor-content">
                                    <!-- Video Preview -->
                                    <div class="parts-video-preview">
                                        <video id="partsVideoPreview" playsinline></video>
                                        <div class="preview-overlay">
                                            <span id="previewPartTitle">Part 1</span>
                                        </div>
                                    </div>

                                    <!-- Interactive Segment Bar -->
                                    <div class="segments-container">
                                        <div class="segment-titles-row" id="segmentTitlesRow">
                                            <!-- Dynamic titles float here -->
                                        </div>
                                        <div class="segments-bar" id="segmentsBar">
                                            <!-- Dynamic segments appear here -->
                                        </div>
                                        <div class="segments-actions">
                                            <button type="button" class="add-part-btn" id="addPartBtn">
                                                <i class="fa-solid fa-scissors"></i>
                                                <span>Add a Part</span>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Parts List Table -->
                                    <div class="parts-list-wrapper">
                                        <div class="parts-list-header">
                                            <span>List of Parts</span>
                                            <button type="button" class="manual-add-btn" id="manualAddPart">
                                                <i class="fa-solid fa-plus"></i>
                                            </button>
                                        </div>
                                        <div class="parts-table" id="partsTable">
                                            <!-- List items appear here -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Status Messages -->
                        <div class="form-status" id="formStatus"></div>

                        <!-- Actions -->
                        <div class="form-actions">
                            <a href="home.php" class="btn-cancel">Cancel</a>
                            <button type="submit" class="btn-submit" id="submitBtn">
                                <span class="btn-text">Upload Video</span>
                                <span class="btn-loader" style="display: none;">
                                    <span class="loader-spinner"></span>
                                    <span>Uploading...</span>
                                </span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <?php include 'mobile_footer.php'; ?>

    <script src="theme.js"></script>
    <script>
        // Starfield (Shared Logic)
        const canvas = document.getElementById('starfield');
        const ctx = canvas.getContext('2d');
        let stars = [];
        function initStars() {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
            stars = [];
            for(let i=0; i<400; i++) {
                stars.push({
                    x: Math.random() * canvas.width,
                    y: Math.random() * canvas.height,
                    size: Math.random() * 1.5,
                    speed: Math.random() * 0.4
                });
            }
        }
        function animate() {
            ctx.clearRect(0,0,canvas.width,canvas.height);
            ctx.fillStyle = 'rgba(255, 255, 255, 0.4)';
            stars.forEach(s => {
                ctx.beginPath();
                ctx.arc(s.x, s.y, s.size, 0, Math.PI*2);
                ctx.fill();
                s.y += s.speed;
                if(s.y > canvas.height) s.y = 0;
            });
            requestAnimationFrame(animate);
        }
        window.addEventListener('resize', initStars);
        initStars();
        animate();
    </script>
    <script src="upload.js"></script>
    <script src="popup.js?v=2"></script>
</body>
</html>
