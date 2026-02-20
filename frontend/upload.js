// ============================================
// UPLOAD VIDEO PAGE FUNCTIONALITY
// ============================================
document.addEventListener('DOMContentLoaded', () => {
    setupFileInputs();
    setupDragAndDrop();
    setupHashtags();
    setupTypeSelector();
    setupEventListeners();
    setupPartsEditor();
});

function setupTypeSelector() {
    const videoBtn = document.getElementById('typeVideoBtn');
    const clipBtn = document.getElementById('typeClipBtn');
    const uploadTypeInput = document.getElementById('uploadType');
    const videoSubtext = document.querySelector('.file-subtext');

    if (videoBtn && clipBtn && uploadTypeInput) {
        videoBtn.addEventListener('click', () => {
            videoBtn.classList.add('active');
            clipBtn.classList.remove('active');
            uploadTypeInput.value = 'false';
            if (videoSubtext) videoSubtext.textContent = 'MP4, WebM, OGG, MOV, AVI, MKV (Max 256GB)';
        });

        clipBtn.addEventListener('click', () => {
            clipBtn.classList.add('active');
            videoBtn.classList.remove('active');
            uploadTypeInput.value = 'true';
            if (videoSubtext) videoSubtext.textContent = 'Vertical Video Recommended (9:16) - Max 60s';
        });
    }
}

function setupFileInputs() {
    const videoFileInput = document.getElementById('videoFile');
    const thumbnailFileInput = document.getElementById('thumbnailFile');
    const captionsFileInput = document.getElementById('captionsFile');
    const videoFileName = document.getElementById('videoFileName');
    const thumbnailFileName = document.getElementById('thumbnailFileName');
    const captionsFileName = document.getElementById('captionsFileName');

    // Video file input
    if (videoFileInput) {
        videoFileInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file) {
                handleVideoFile(file, videoFileName);
            } else {
                videoFileName.classList.remove('show');
            }
        });
    }

    // Thumbnail file input
    if (thumbnailFileInput) {
        thumbnailFileInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file) {
                handleThumbnailFile(file, thumbnailFileName);
            } else {
                thumbnailFileName.classList.remove('show');
            }
        });
    }

    // Captions file input
    if (captionsFileInput) {
        captionsFileInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file) {
                handleCaptionsFile(file, captionsFileName);
            } else {
                captionsFileName.classList.remove('show');
            }
        });
    }
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
}

function handleVideoFile(file, displayElement) {
    // Validate file type - expanded list for large files
    const allowedTypes = [
        'video/mp4', 'video/webm', 'video/ogg', 'video/quicktime',
        'video/x-msvideo', 'video/x-matroska', 'video/x-flv',
        'video/x-ms-wmv', 'video/x-m4v'
    ];
    const allowedExtensions = ['mp4', 'webm', 'ogg', 'mov', 'avi', 'mkv', 'flv', 'wmv', 'm4v'];
    const fileExtension = file.name.split('.').pop().toLowerCase();

    if (!allowedTypes.includes(file.type) && !allowedExtensions.includes(fileExtension)) {
        Popup.show('Invalid video format. Allowed: MP4, WebM, OGG, MOV, AVI, MKV, FLV, WMV, M4V', 'error');
        return;
    }

    // Validate file size (256GB = 274877906944 bytes)
    const maxSize = 256 * 1024 * 1024 * 1024; // 256GB
    if (file.size > maxSize) {
        const fileSizeGB = (file.size / 1024 / 1024 / 1024).toFixed(2);
        Popup.show(`Video file is too large (${fileSizeGB} GB). Maximum size is 256 GB.`, 'error');
        return;
    }

    // Warn for very large files
    if (file.size > 10 * 1024 * 1024 * 1024) { // > 10GB
        const fileSizeGB = (file.size / 1024 / 1024 / 1024).toFixed(2);
        Popup.show(`Large file detected (${fileSizeGB} GB). Upload may take a while. Please keep this page open.`, 'info', 10000);
    }

    displayElement.innerHTML = `
        <i class="fa-solid fa-file-video"></i>
        <span>${escapeHtml(file.name)}</span>
        <span class="file-size">(${formatFileSize(file.size)})</span>
    `;
    displayElement.classList.add('show');

    // Trigger Parts Editor
    const partsEditor = document.getElementById('partsEditorContainer');
    const partsVideo = document.getElementById('partsVideoPreview');
    if (partsEditor && partsVideo) {
        partsEditor.style.display = 'block';
        const url = URL.createObjectURL(file);
        partsVideo.src = url;
        partsVideo.onloadedmetadata = () => {
            initParts(partsVideo.duration);
        };
    }
}

function handleThumbnailFile(file, displayElement) {
    // Validate file type
    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    if (!allowedTypes.includes(file.type)) {
        Popup.show('Invalid image format. Allowed: JPEG, PNG, GIF', 'error');
        return;
    }

    // Validate file size (5MB)
    const maxSize = 5 * 1024 * 1024;
    if (file.size > maxSize) {
        Popup.show('Image file is too large. Maximum size is 5MB.', 'error');
        return;
    }

    displayElement.innerHTML = `
        <i class="fa-solid fa-image"></i>
        <span>${escapeHtml(file.name)}</span>
        <span class="file-size">(${formatFileSize(file.size)})</span>
    `;
    displayElement.classList.add('show');
}

function handleCaptionsFile(file, displayElement) {
    // Validate file type
    const fileExtension = file.name.split('.').pop().toLowerCase();
    if (fileExtension !== 'vtt') {
        Popup.show('Invalid subtitles format. Only .vtt files are allowed', 'error');
        return;
    }

    // Validate file size (2MB)
    const maxSize = 2 * 1024 * 1024;
    if (file.size > maxSize) {
        Popup.show('Subtitles file is too large. Maximum size is 2MB.', 'error');
        return;
    }

    displayElement.innerHTML = `
        <i class="fa-solid fa-closed-captioning"></i>
        <span>${escapeHtml(file.name)}</span>
        <span class="file-size">(${formatFileSize(file.size)})</span>
    `;
    displayElement.classList.add('show');
}

function setupDragAndDrop() {
    const videoWrapper = document.querySelector('#videoFile').closest('.file-input-wrapper');
    const thumbnailWrapper = document.querySelector('#thumbnailFile').closest('.file-input-wrapper');
    const videoFileInput = document.getElementById('videoFile');
    const thumbnailFileInput = document.getElementById('thumbnailFile');
    const videoFileName = document.getElementById('videoFileName');
    const thumbnailFileName = document.getElementById('thumbnailFileName');

    // Video drag and drop
    if (videoWrapper && videoFileInput) {
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            videoWrapper.addEventListener(eventName, preventDefaults, false);
        });

        ['dragenter', 'dragover'].forEach(eventName => {
            videoWrapper.addEventListener(eventName, () => {
                videoWrapper.classList.add('drag-over');
            }, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            videoWrapper.addEventListener(eventName, () => {
                videoWrapper.classList.remove('drag-over');
            }, false);
        });

        videoWrapper.addEventListener('drop', (e) => {
            const dt = e.dataTransfer;
            const files = dt.files;
            if (files.length > 0) {
                const file = files[0];
                if (file.type.startsWith('video/')) {
                    videoFileInput.files = files;
                    handleVideoFile(file, videoFileName);
                } else {
                    Popup.show('Please drop a video file', 'error');
                }
            }
        }, false);
    }

    // Thumbnail drag and drop
    if (thumbnailWrapper && thumbnailFileInput) {
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            thumbnailWrapper.addEventListener(eventName, preventDefaults, false);
        });

        ['dragenter', 'dragover'].forEach(eventName => {
            thumbnailWrapper.addEventListener(eventName, () => {
                thumbnailWrapper.classList.add('drag-over');
            }, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            thumbnailWrapper.addEventListener(eventName, () => {
                thumbnailWrapper.classList.remove('drag-over');
            }, false);
        });

        thumbnailWrapper.addEventListener('drop', (e) => {
            const dt = e.dataTransfer;
            const files = dt.files;
            if (files.length > 0) {
                const file = files[0];
                if (file.type.startsWith('image/')) {
                    thumbnailFileInput.files = files;
                    handleThumbnailFile(file, thumbnailFileName);
                } else {
                    Popup.show('Please drop an image file', 'error');
                }
            }
        }, false);
    }
}

function preventDefaults(e) {
    e.preventDefault();
    e.stopPropagation();
}

// ============================================
// HASHTAG MANAGEMENT
// ============================================
let hashtags = [];

function setupHashtags() {
    const addHashtagBtn = document.getElementById('addHashtagBtn');
    const hashtagInput = document.getElementById('hashtagInput');
    const hashtagAddBtn = document.getElementById('hashtagAddBtn');
    const hashtagCancelBtn = document.getElementById('hashtagCancelBtn');
    const hashtagInputWrapper = document.getElementById('hashtagInputWrapper');

    if (addHashtagBtn) {
        addHashtagBtn.addEventListener('click', () => {
            hashtagInputWrapper.style.display = 'block';
            addHashtagBtn.style.display = 'none';
            hashtagInput.focus();
        });
    }

    if (hashtagAddBtn) {
        hashtagAddBtn.addEventListener('click', () => {
            addHashtag();
        });
    }

    if (hashtagCancelBtn) {
        hashtagCancelBtn.addEventListener('click', () => {
            hashtagInput.value = '';
            hashtagInputWrapper.style.display = 'none';
            addHashtagBtn.style.display = 'flex';
        });
    }

    if (hashtagInput) {
        hashtagInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                addHashtag();
            }
        });
    }
}

function addHashtag() {
    const hashtagInput = document.getElementById('hashtagInput');
    const hashtagInputWrapper = document.getElementById('hashtagInputWrapper');
    const addHashtagBtn = document.getElementById('addHashtagBtn');

    let hashtagText = hashtagInput.value.trim();

    if (!hashtagText) {
        return;
    }

    // Remove # if user added it
    if (hashtagText.startsWith('#')) {
        hashtagText = hashtagText.substring(1);
    }

    // Validate hashtag (alphanumeric and underscores only)
    if (!/^[a-zA-Z0-9_]+$/.test(hashtagText)) {
        Popup.show('Hashtags can only contain letters, numbers, and underscores', 'error');
        return;
    }

    // Check for duplicates (case insensitive)
    if (hashtags.some(tag => tag.toLowerCase() === hashtagText.toLowerCase())) {
        Popup.show('This hashtag has already been added', 'error');
        return;
    }

    // Limit to 10 hashtags
    if (hashtags.length >= 10) {
        Popup.show('Maximum 10 hashtags allowed', 'error');
        return;
    }

    // Add hashtag to array
    hashtags.push(hashtagText);

    // Display hashtag
    displayHashtags();

    // Clear input and hide
    hashtagInput.value = '';
    hashtagInputWrapper.style.display = 'none';
    addHashtagBtn.style.display = 'flex';
}

function removeHashtag(index) {
    hashtags.splice(index, 1);
    displayHashtags();
}

function displayHashtags() {
    const hashtagsList = document.getElementById('hashtagsList');

    if (!hashtagsList) return;

    if (hashtags.length === 0) {
        hashtagsList.innerHTML = '';
        return;
    }

    hashtagsList.innerHTML = hashtags.map((tag, index) => `
        <div class="hashtag-tag">
            <span class="hashtag-text">#${escapeHtml(tag)}</span>
            <button type="button" class="hashtag-remove" onclick="removeHashtag(${index})" title="Remove hashtag">
                <i class="fa-solid fa-times"></i>
            </button>
        </div>
    `).join('');
}

// ============================================
// FORM SUBMISSION
// ============================================

function setupEventListeners() {
    const uploadForm = document.getElementById('uploadForm');

    if (uploadForm) {
        uploadForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            const submitBtn = document.getElementById('submitBtn');
            const btnText = submitBtn.querySelector('.btn-text');
            const btnLoader = submitBtn.querySelector('.btn-loader');
            const formStatus = document.getElementById('formStatus');

            // Get form values
            const title = document.getElementById('videoTitle').value.trim();
            const description = document.getElementById('videoDescription').value.trim();
            const videoFile = document.getElementById('videoFile').files[0];
            const thumbnailFile = document.getElementById('thumbnailFile').files[0];
            const captionsFile = document.getElementById('captionsFile').files[0];

            // Validation
            if (!title) {
                showStatus('Please enter a title.', 'error');
                return;
            }

            if (!videoFile) {
                showStatus('Please select a video file.', 'error');
                return;
            }

            // Validate file size (500MB)
            const maxSize = 500 * 1024 * 1024;
            if (videoFile.size > maxSize) {
                showStatus('Video file is too large. Maximum size is 500MB.', 'error');
                return;
            }

            // Disable button and show loader
            submitBtn.disabled = true;
            btnText.style.display = 'none';
            btnLoader.style.display = 'inline-flex';

            // Create FormData for file upload
            const formData = new FormData();
            formData.append('title', title);
            formData.append('description', description);
            formData.append('video', videoFile);
            const isClip = document.getElementById('uploadType').value === 'true';
            formData.append('is_clip', isClip);

            if (thumbnailFile) {
                formData.append('thumbnail', thumbnailFile);
            }
            if (captionsFile) {
                formData.append('captions', captionsFile);
            }
            // Add hashtags as JSON
            if (hashtags.length > 0) {
                formData.append('hashtags', JSON.stringify(hashtags));
            }

            // Validate file size (256GB = 274877906944 bytes)
            const maxFileSize = 256 * 1024 * 1024 * 1024; // 256GB
            if (videoFile.size > maxFileSize) {
                const fileSizeGB = (videoFile.size / 1024 / 1024 / 1024).toFixed(2);
                Popup.show(`File size (${fileSizeGB} GB) exceeds maximum allowed size of 256 GB.`, 'error');
                submitBtn.disabled = false;
                btnText.style.display = '';
                btnLoader.style.display = 'none';
                return;
            }

            // Adaptive chunk size based on file size
            // Small files (<100MB): 1MB chunks
            // Medium files (100MB-1GB): 5MB chunks
            // Large files (1GB-10GB): 10MB chunks
            // Very large files (>10GB): 20MB chunks
            let chunkSize;
            if (videoFile.size < 100 * 1024 * 1024) {
                chunkSize = 1024 * 1024; // 1MB
            } else if (videoFile.size < 1024 * 1024 * 1024) {
                chunkSize = 5 * 1024 * 1024; // 5MB
            } else if (videoFile.size < 10 * 1024 * 1024 * 1024) {
                chunkSize = 10 * 1024 * 1024; // 10MB
            } else {
                chunkSize = 20 * 1024 * 1024; // 20MB for very large files
            }

            const totalChunks = Math.ceil(videoFile.size / chunkSize);

            // Show file size info
            const fileSizeText = formatFileSize(videoFile.size);
            btnLoader.innerHTML = `<span class="loader-spinner"></span><span>Preparing upload (${fileSizeText})...</span>`;

            const uploadStartTime = Date.now();

            try {
                // Step 1: Initialize Upload
                const initData = new FormData();
                initData.append('action', 'init');
                initData.append('filename', videoFile.name);
                initData.append('filesize', videoFile.size);

                const initResponse = await fetch('../backend/upload_chunk.php', {
                    method: 'POST',
                    body: initData
                });

                if (!initResponse.ok) {
                    throw new Error(`Server error: ${initResponse.status}`);
                }

                const initResult = await initResponse.json();

                if (!initResult.success) {
                    throw new Error(initResult.message || 'Failed to initialize upload');
                }

                const uploadId = initResult.upload_id;

                // Use recommended chunk size from server if provided (override adaptive)
                if (initResult.chunk_size) {
                    chunkSize = initResult.chunk_size;
                }

                // Step 2: Upload Chunks with retry logic and progress tracking
                let uploadedChunks = 0;
                let failedChunks = [];

                for (let i = 0; i < totalChunks; i++) {
                    let retries = 3;
                    let chunkUploaded = false;

                    while (retries > 0 && !chunkUploaded) {
                        try {
                            const start = i * chunkSize;
                            const end = Math.min(start + chunkSize, videoFile.size);
                            const chunk = videoFile.slice(start, end);

                            const chunkData = new FormData();
                            chunkData.append('action', 'upload_chunk');
                            chunkData.append('upload_id', uploadId);
                            chunkData.append('chunk_index', i);
                            chunkData.append('chunk', chunk);

                            const chunkResponse = await fetch('../backend/upload_chunk.php', {
                                method: 'POST',
                                body: chunkData
                            });

                            if (!chunkResponse.ok) {
                                throw new Error(`HTTP ${chunkResponse.status}`);
                            }

                            const chunkResult = await chunkResponse.json();

                            if (!chunkResult.success) {
                                throw new Error(chunkResult.message || `Failed to upload chunk ${i}`);
                            }

                            chunkUploaded = true;
                            uploadedChunks++;

                            // Update Progress with detailed info
                            const progress = Math.round(((i + 1) / totalChunks) * 100);
                            const uploadedMB = ((i + 1) * chunkSize / 1024 / 1024).toFixed(2);
                            const totalMB = (videoFile.size / 1024 / 1024).toFixed(2);
                            const speed = uploadedMB / ((Date.now() - uploadStartTime) / 1000);

                            btnLoader.innerHTML = `
                                <span class="loader-spinner"></span>
                                <span>Uploading... ${progress}% (${uploadedMB} MB / ${totalMB} MB)</span>
                                <span style="font-size: 0.85em; opacity: 0.8; display: block; margin-top: 4px;">
                                    Chunk ${i + 1} of ${totalChunks} • ~${speed.toFixed(2)} MB/s
                                </span>
                            `;

                        } catch (error) {
                            retries--;
                            if (retries === 0) {
                                failedChunks.push(i);
                                console.error(`Failed to upload chunk ${i} after 3 retries:`, error);
                                throw new Error(`Failed to upload chunk ${i + 1}/${totalChunks}: ${error.message}`);
                            } else {
                                // Wait before retry (exponential backoff)
                                await new Promise(resolve => setTimeout(resolve, 1000 * (4 - retries)));
                            }
                        }
                    }
                }

                if (failedChunks.length > 0) {
                    throw new Error(`Failed to upload chunks: ${failedChunks.join(', ')}. Please try again.`);
                }

                // Step 3: Complete Upload
                const completeData = new FormData();
                completeData.append('action', 'complete');
                completeData.append('upload_id', uploadId);
                completeData.append('filename', videoFile.name);
                completeData.append('total_chunks', totalChunks);
                completeData.append('title', title);
                completeData.append('description', description);
                completeData.append('is_clip', isClip);
                if (thumbnailFile) completeData.append('thumbnail', thumbnailFile);
                if (captionsFile) completeData.append('captions', captionsFile);
                if (hashtags.length > 0) completeData.append('hashtags', JSON.stringify(hashtags));

                console.log('DEBUG: Complete Upload Data:', {
                    hasThumbnail: !!thumbnailFile,
                    hasCaptions: !!captionsFile,
                    captionsName: captionsFile ? captionsFile.name : 'N/A',
                    isClip: isClip
                });

                if (window.videoParts && window.videoParts.length > 0) {
                    completeData.append('chapters', JSON.stringify(window.videoParts));
                }

                const completeResponse = await fetch('../backend/upload_chunk.php', {
                    method: 'POST',
                    body: completeData
                });
                const completeResult = await completeResponse.json();

                if (completeResult.success) {
                    Popup.show('Video uploaded successfully! Redirecting...', 'success');
                    setTimeout(() => {
                        window.location.href = 'videoid.php?id=' + completeResult.video.id;
                    }, 1500);
                } else {
                    throw new Error(completeResult.message);
                }

            } catch (error) {
                console.error('Upload error:', error);
                Popup.show('Upload failed: ' + error.message, 'error');
            } finally {
                submitBtn.disabled = false;
                btnText.style.display = '';
                btnLoader.style.display = 'none';
            }
        });
    }

    // Account dropdown
    const accountBtn = document.getElementById('accountBtn');
    const accountDropdown = document.getElementById('accountDropdown');

    if (accountBtn && accountDropdown) {
        accountBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            accountDropdown.classList.toggle('active');
        });

        document.addEventListener('click', (e) => {
            if (!accountBtn.contains(e.target) && !accountDropdown.contains(e.target)) {
                accountDropdown.classList.remove('active');
            }
        });
    }

    // Logout
    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', async () => {
            const confirmed = await Popup.confirm('Are you sure you want to sign out?');
            if (confirmed) {
                await fetch('../backend/logout.php');
                window.location.href = 'loginb.php';
            }
        });
    }

    // Search
    const searchBtn = document.getElementById('searchBtn');
    const searchInput = document.getElementById('searchInput');

    function performSearch() {
        const query = searchInput.value.trim();
        if (query) {
            window.location.href = `home.php?search=${encodeURIComponent(query)}`;
        }
    }

    if (searchBtn) {
        searchBtn.addEventListener('click', performSearch);
    }

    if (searchInput) {
        searchInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                performSearch();
            }
        });
    }

    // Load user email and profile picture
    loadUserData();
}

function showStatus(message, type) {
    const formStatus = document.getElementById('formStatus');
    formStatus.textContent = message;
    formStatus.className = `form-status ${type}`;
    formStatus.style.display = 'block';
}

async function loadUserData() {
    try {
        const response = await fetch('../backend/getUser.php');
        const data = await response.json();
        if (data.success) {
            const emailEl = document.getElementById('accountEmail');
            if (emailEl) emailEl.textContent = data.user.email;

            // Update profile pictures
            const avatars = document.querySelectorAll('.account-avatar, .account-dropdown-avatar');
            avatars.forEach(avatar => {
                if (data.user.profile_picture) {
                    avatar.innerHTML = `<img src="${escapeHtml(data.user.profile_picture)}" alt="Profile" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">`;
                    const fallback = document.createElement('div');
                    fallback.style.cssText = 'display: none; width: 100%; height: 100%; align-items: center; justify-content: center; background: var(--accent-color); border-radius: 50%; color: white; font-weight: 600; font-size: 16px;';
                    fallback.textContent = data.user.username.charAt(0).toUpperCase();
                    avatar.appendChild(fallback);
                } else {
                    avatar.textContent = data.user.username.charAt(0).toUpperCase();
                }
            });
        }
    } catch (error) {
        console.error('Error loading user data:', error);
    }
}

// ============================================
// VIDEO PARTS EDITOR LOGIC
// ============================================
window.videoParts = [];
let totalVideoDuration = 0;

function setupPartsEditor() {
    const addPartBtn = document.getElementById('addPartBtn');
    const manualAddBtn = document.getElementById('manualAddPart');
    const partsVideo = document.getElementById('partsVideoPreview');

    if (addPartBtn) {
        addPartBtn.addEventListener('click', () => {
            splitPartsEvenly();
        });
    }

    if (manualAddBtn) {
        manualAddBtn.addEventListener('click', () => {
            addManualPart();
        });
    }

    if (partsVideo) {
        partsVideo.ontimeupdate = () => {
            updateActiveSegment(partsVideo.currentTime);
        };
    }
}

function updateActiveSegment(currentTime) {
    const previewPartTitle = document.getElementById('previewPartTitle');
    const segments = document.querySelectorAll('.video-segment');
    const rows = document.querySelectorAll('.part-row');

    window.videoParts.forEach((part, index) => {
        if (currentTime >= part.start && currentTime < part.end) {
            if (previewPartTitle) previewPartTitle.textContent = part.title;

            // Highlight bar segment
            segments.forEach((s, idx) => {
                s.style.opacity = (idx === index) ? '1' : '0.6';
                s.style.boxShadow = (idx === index) ? '0 0 15px var(--segment-color)' : 'none';
            });

            // Highlight list row
            rows.forEach((r, idx) => {
                r.style.background = (idx === index) ? 'rgba(255, 255, 255, 0.12)' : 'rgba(255, 255, 255, 0.05)';
                r.style.borderColor = (idx === index) ? 'var(--accent-color)' : 'transparent';
            });
        }
    });
}

function initParts(duration) {
    totalVideoDuration = duration;
    window.videoParts = [
        { title: 'Intro', start: 0, end: duration }
    ];
    renderParts();
}

function splitPartsEvenly() {
    if (totalVideoDuration === 0) return;

    const count = window.videoParts.length + 1;
    const segmentDuration = totalVideoDuration / count;

    window.videoParts = [];
    for (let i = 0; i < count; i++) {
        window.videoParts.push({
            title: `Part ${i + 1}`,
            start: i * segmentDuration,
            end: (i + 1) * segmentDuration
        });
    }
    renderParts();
}

function addManualPart() {
    const lastPart = window.videoParts[window.videoParts.length - 1];
    const newStart = lastPart ? lastPart.end : 0;
    const newEnd = Math.min(newStart + 30, totalVideoDuration); // Default 30s or till end

    window.videoParts.push({
        title: `New Part`,
        start: newStart,
        end: newEnd
    });
    renderParts();
}

function renderParts() {
    const segmentsBar = document.getElementById('segmentsBar');
    const titlesRow = document.getElementById('segmentTitlesRow');
    const partsTable = document.getElementById('partsTable');
    const partsVideo = document.getElementById('partsVideoPreview');

    if (!segmentsBar || !titlesRow || !partsTable) return;

    segmentsBar.innerHTML = '';
    titlesRow.innerHTML = '';
    partsTable.innerHTML = '';

    const colors = ['#60b5ff', '#ff60eb', '#60ff8b', '#ffdb60', '#ff6060', '#9c60ff'];

    window.videoParts.forEach((part, index) => {
        const percentage = ((part.end - part.start) / totalVideoDuration) * 100;
        const color = colors[index % colors.length];

        // 1. Progress Bar Segment
        const segment = document.createElement('div');
        segment.className = 'video-segment';
        segment.style.width = `${percentage}%`;
        segment.style.setProperty('--segment-color', color);
        segment.title = `${part.title} (${formatTime(part.start)} - ${formatTime(part.end)})`;

        segment.onclick = () => {
            partsVideo.currentTime = part.start;
            document.getElementById('previewPartTitle').textContent = part.title;
        };
        segmentsBar.appendChild(segment);

        // 2. Floating Label
        const label = document.createElement('div');
        label.className = 'segment-title-label';
        label.style.left = `${(part.start / totalVideoDuration) * 100}%`;
        label.style.width = `${percentage}%`;
        label.textContent = part.title;
        titlesRow.appendChild(label);

        // 3. Table Row
        const row = document.createElement('div');
        row.className = 'part-row';
        row.innerHTML = `
            <input type="text" class="part-title-input" value="${part.title}" placeholder="Part Title">
            <input type="text" class="time-field part-start" value="${formatTime(part.start)}">
            <input type="text" class="time-field part-end" value="${formatTime(part.end)}">
            <button class="remove-part-btn"><i class="fa-solid fa-trash"></i></button>
        `;

        // Bind events to row inputs
        const titleInput = row.querySelector('.part-title-input');
        titleInput.oninput = (e) => {
            part.title = e.target.value;
            label.textContent = e.target.value;
            document.getElementById('previewPartTitle').textContent = e.target.value;
        };

        row.querySelector('.remove-part-btn').onclick = () => {
            window.videoParts.splice(index, 1);
            renderParts();
        };

        partsTable.appendChild(row);
    });
}

function formatTime(seconds) {
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return `${mins}:${secs.toString().padStart(2, '0')}`;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
