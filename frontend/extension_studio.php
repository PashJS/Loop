<?php
session_start();
// Prevent caching so the user always sees the latest changes
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

require_once '../backend/config.php';

// Ensure user is authenticated
if (!isset($_SESSION['user_id'])) {
    header('Location: loginb.php');
    exit;
}

$displayName = $_SESSION['display_name'] ?? $_SESSION['username'] ?? 'Developer';
$userId = $_SESSION['user_id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Extension Studio | Loop</title>
    <link rel="stylesheet" href="layout.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/animejs/3.2.1/anime.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/prism.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-json.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-css.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-markup.min.js"></script>
    <style>
        :root {
            --studio-purple: #9333ea;
            --studio-bg: #000000;
            --panel-bg: #000000;
            --sidebar-bg: #000000;
            --border: rgba(255,255,255,0.05);
            --accent: #a855f7;
            --success: #22c55e;
            --error: #ef4444;
        }
        * { box-sizing: border-box; }
        html, body {
            background: #000 !important; color: #fff; margin: 0; padding: 0;
            width: 100%; height: 100vh; overflow: hidden; font-family: 'Inter', sans-serif;
        }
        
        .studio-shell { display: flex; height: 100vh; }
        .studio-main { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
        
        .studio-header {
            height: 50px; background: var(--panel-bg); border-bottom: 1px solid var(--border);
            display: flex; align-items: center; justify-content: space-between; padding: 0 1.5rem;
        }
        .studio-title { font-size: 1.1rem; font-weight: 800; display: flex; align-items: center; gap: 10px; }
        .studio-title i { color: var(--accent); }
        
        .header-actions { display: flex; gap: 10px; align-items: center; }
        .header-btn {
            padding: 8px 16px; border-radius: 8px; border: none; font-size: 11px; font-weight: 700;
            cursor: pointer; display: flex; align-items: center; gap: 6px; transition: all 0.2s;
        }
        .btn-reset { background: rgba(255,255,255,0.05); color: #888; }
        .btn-reset:hover { background: rgba(255,255,255,0.1); color: #fff; }
        .btn-preview { background: var(--accent); color: #fff; }
        .btn-preview:hover { filter: brightness(1.1); }
        .btn-inject { background: var(--success); color: #fff; }
        
        /* Workspace Layout */
        .workspace { flex: 1; display: flex; overflow: hidden; position: relative; background: #000; }
        
        /* Explorer Panel */
        /* Explorer Panel */
        .explorer-panel {
            width: 220px; min-width: 220px; background: #050508; border-right: 1px solid var(--border);
            display: flex; flex-direction: column; overflow: hidden; position: relative;
        }
        .explorer-header {
            height: 38px; padding: 0 12px; display: flex; align-items: center; justify-content: space-between;
            background: rgba(255,255,255,0.02); border-bottom: 1px solid var(--border);
        }
        .project-name { font-size: 11px; font-weight: 800; color: #888; text-transform: uppercase; letter-spacing: 1px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .explorer-actions { display: flex; gap: 4px; }
        .explorer-btn { 
            width: 24px; height: 24px; border-radius: 4px; border: none; background: transparent; 
            color: #555; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s;
        }
        .explorer-btn:hover { background: rgba(255,255,255,0.05); color: #fff; }

        .file-list { flex: 1; overflow-y: auto; padding: 8px 0; }
        .file-item {
            padding: 6px 16px; display: flex; align-items: center; gap: 8px; font-size: 12px; color: #888;
            cursor: pointer; transition: all 0.2s; position: relative; border-left: 3px solid transparent;
        }
        .file-item:hover { background: rgba(255,255,255,0.03); color: #fff; }
        .file-item.active { background: rgba(168, 85, 247, 0.1); color: #fff; border-left-color: var(--accent); }
        .file-item i { font-size: 13px; opacity: 0.7; }

        /* Editor Panel */
        .editor-panel { min-width: 300px; flex: 1; display: flex; flex-direction: column; border-right: 1px solid var(--border); overflow: hidden; position: relative; }
        
        /* Resizer */
        .resizer {
            width: 3px; height: 100%; background: var(--border); cursor: col-resize;
            transition: background 0.2s; z-index: 1000; position: relative;
        }
        .resizer:hover, .resizer.active { background: var(--accent); }
        .resizer-overlay { 
            position: absolute; top: 0; left: 0; right: 0; bottom: 0; 
            z-index: 9999; display: none; cursor: col-resize; 
        }
        .resizer-overlay.active { display: block; }

        /* Tabs Bar */
        .file-tabs {
            height: 38px; background: #000; display: flex; border-bottom: 1px solid var(--border);
            overflow-x: auto; overflow-y: hidden; scrollbar-width: none;
        }
        .file-tabs::-webkit-scrollbar { display: none; }
        .file-tab {
            min-width: 120px; max-width: 200px; padding: 0 12px; display: flex; align-items: center; justify-content: space-between; gap: 8px;
            font-size: 12px; color: #555; cursor: pointer; border-right: 1px solid var(--border);
            background: #000; position: relative; transition: all 0.2s;
        }
        .file-tab.active { color: #fff; background: #050508; }
        .file-tab.active::after { content: ''; position: absolute; bottom: 0; left: 0; right: 0; height: 2px; background: var(--accent); }
        .tab-close { width: 16px; height: 16px; border-radius: 40%; display: flex; align-items: center; justify-content: center; font-size: 8px; opacity: 0; transition: all 0.2s; }
        .file-tab:hover .tab-close { opacity: 0.5; }
        .tab-close:hover { background: rgba(255,255,255,0.1); opacity: 1 !important; color: var(--error); }

        .editor-body { flex: 1; display: flex; overflow: hidden; background: #000; position: relative; }
        .line-gutter {
            width: 45px; background: #000; color: #333; font-family: Consolas, monospace;
            font-size: 13px; text-align: right; padding: 20px 10px; line-height: 22px;
            border-right: 1px solid var(--border); overflow: hidden; user-select: none;
        }
        .code-wrap { flex: 1; position: relative; overflow: hidden; background: #000 !important; }
        
        /* The Editor Engine */
        .code-area, .code-highlight {
            position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            margin: 0 !important; padding: 20px !important; box-sizing: border-box !important;
            font-family: Consolas, monospace !important; 
            font-size: 13px !important; line-height: 22px !important;
            white-space: pre !important; 
            letter-spacing: normal; word-spacing: normal;
            border: none !important; outline: none !important;
        }
        
        .code-area {
            z-index: 2; background: transparent !important; color: transparent !important; 
            caret-color: #fff !important; resize: none !important; overflow: auto !important;
        }
        
        .code-highlight {
            z-index: 1; pointer-events: none; color: #fff; background: #000 !important;
            overflow: hidden !important; 
        }
        
        .code-highlight pre, .code-highlight code {
            margin: 0 !important; padding: 0 !important; display: block !important;
            background: transparent !important; font: inherit !important;
            line-height: inherit !important; white-space: pre !important;
            border: none !important; overflow: hidden !important;
        }
        
        /* High Contrast Syntax Colors (Manual theme focus) */
        .token.comment, .token.prolog, .token.doctype, .token.cdata { color: #6a9955; }
        .token.property, .token.tag, .token.boolean, .token.number, .token.constant, .token.symbol, .token.deleted { color: #f8244a; }
        .token.selector, .token.attr-name, .token.string, .token.char, .token.builtin, .token.inserted { color: #dcdcaa; }
        .token.operator, .token.entity, .token.url, .language-css .token.string, .style .token.string { color: #9cdcfe; }
        .token.atrule, .token.attr-value, .token.keyword { color: #569cd6; }
        .token.function, .token.class-name { color: #4ec9b0; }
        .token.regex, .token.important, .token.variable { color: #ce9178; }
        
        /* Fix scrollbars on Windows to prevent double overflow */
        .code-area::-webkit-scrollbar, .console-panel::-webkit-scrollbar { width: 10px; height: 10px; }
        .code-area::-webkit-scrollbar-track, .console-panel::-webkit-scrollbar-track { background: #000; }
        .code-area::-webkit-scrollbar-thumb, .console-panel::-webkit-scrollbar-thumb { background: #333; border-radius: 5px; border: 2px solid #000; }
        .code-area::-webkit-scrollbar-thumb:hover { background: #444; }
        
        /* Hide highlight scrollbar always */
        .code-highlight::-webkit-scrollbar { display: none; }
        
        .status-bar {
            height: 26px; background: #9333ea; display: flex; align-items: center;
            justify-content: space-between; padding: 0 12px;
            font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;
            border-top: 1px solid var(--border);
        }
        .status-bar.error { background: var(--error); }
        .status-bar.success { background: var(--success); }
        
        /* Live UI Panel */
        .live-panel { flex: 1.5; display: flex; flex-direction: column; background: #000; overflow: hidden; position: relative; }
        .live-header {
            height: 38px; background: #050508; border-bottom: 1px solid var(--border);
            display: flex; align-items: center; justify-content: space-between; padding: 0 1rem;
            flex-shrink: 0; position: relative; z-index: 10;
        }
        .live-label { font-size: 10px; font-weight: 800; color: #444; letter-spacing: 1px; text-transform: uppercase; }
        
        .live-frame { flex: 1; position: relative; background: #000; width: 100%; height: 100%; }
        /* Nuclear fix for cursor: No padding, no margin, no transforms on the iframe */
        .live-frame iframe { 
            position: absolute; top: 0; left: 0; width: 100%; height: 100%; 
            border: none; background: #000; display: block; overflow: auto;
        }

        .sandbox-badge {
            position: absolute; top: 12px; right: 12px;
            background: #f59e0b; color: #000; padding: 3px 8px; border-radius: 4px;
            font-size: 9px; font-weight: 900; letter-spacing: 1px; z-index: 100;
            pointer-events: none; opacity: 0.8;
        }
        /* Ghost Window (Empty State) */
        .ghost-window {
            position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
            text-align: center; z-index: 100;
        }
        .ghost-icon { font-size: 48px; color: #1a1a25; margin-bottom: 20px; }
        .ghost-title { font-size: 18px; font-weight: 700; color: #444; margin-bottom: 10px; }
        .ghost-text { font-size: 13px; color: #333; margin-bottom: 24px; max-width: 300px; line-height: 1.5; }
        .create-proj-btn { 
            padding: 12px 32px; background: var(--accent); border: none; border-radius: 12px;
            color: #fff; font-weight: 800; cursor: pointer; transition: all 0.2s;
        }
        .create-proj-btn:hover { filter: brightness(1.2); transform: scale(1.05); }

        /* Popups */
        .modal-overlay {
            position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.85); backdrop-filter: blur(12px);
            z-index: 10000; display: none; align-items: center; justify-content: center;
        }
        .modal-overlay.active { display: flex; }
        .app-modal {
            width: 450px; background: #08080c; border: 1px solid rgba(255,255,255,0.08); border-radius: 24px;
            padding: 35px; box-shadow: 0 40px 120px rgba(0,0,0,0.8);
            animation: modalIn 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        @keyframes modalIn { from { opacity: 0; transform: scale(0.9) translateY(20px); } to { opacity: 1; transform: scale(1) translateY(0); } }

        .modal-title { font-size: 20px; font-weight: 900; margin-bottom: 24px; letter-spacing: -0.5px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-size: 10px; font-weight: 800; color: #666; text-transform: uppercase; margin-bottom: 10px; letter-spacing: 1px; }
        .form-group input, .form-group textarea { 
            width: 100%; background: #000; border: 1px solid #1a1a1a; border-radius: 12px; padding: 12px 16px;
            color: #fff; font-size: 13px; outline: none; transition: all 0.2s;
        }
        .form-group textarea { resize: none; min-height: 100px; line-height: 1.5; }
        .form-group input:focus, .form-group textarea:focus { border-color: var(--accent); background: #050508; box-shadow: 0 0 0 4px rgba(168, 85, 247, 0.1); }
        
        /* Thumbnail Upload Zone */
        .thumb-upload-zone {
            width: 100%; height: 140px; background: #000; border: 2px dashed #1a1a1a; border-radius: 16px;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            cursor: pointer; transition: all 0.2s; position: relative; overflow: hidden;
            margin-bottom: 20px;
        }
        .thumb-upload-zone:hover { border-color: var(--accent); background: rgba(168,85,247,0.02); }
        .thumb-upload-zone i { font-size: 24px; color: #444; margin-bottom: 10px; }
        .thumb-upload-zone span { font-size: 11px; font-weight: 700; color: #666; }
        .thumb-preview {
            position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            object-fit: cover; display: none; z-index: 10;
        }
        .thumb-upload-zone.has-image .thumb-preview { display: block; }
        .thumb-upload-zone.has-image i, .thumb-upload-zone.has-image span { display: none; }

        .modal-actions { display: flex; gap: 12px; justify-content: flex-end; margin-top: 30px; }

        /* Screenshots Grid */
        .screenshots-grid {
            display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-top: 10px;
        }
        .screenshot-item {
            aspect-ratio: 16/9; background: #000; border: 1px solid #1a1a1a; border-radius: 8px;
            position: relative; overflow: hidden; display: flex; align-items: center; justify-content: center;
            cursor: pointer; transition: border-color 0.2s;
        }
        .screenshot-item:hover { border-color: var(--accent); }
        .screenshot-item i { font-size: 14px; color: #444; }
        .screenshot-item img { width: 100%; height: 100%; object-fit: cover; }
        .screenshot-remove {
            position: absolute; top: 4px; right: 4px; width: 18px; height: 18px;
            background: rgba(239, 68, 68, 0.8); color: #fff; border-radius: 4px;
            display: flex; align-items: center; justify-content: center; font-size: 8px;
            opacity: 0; transition: opacity 0.2s; z-index: 20;
        }
        .screenshot-item:hover .screenshot-remove { opacity: 1; }

        .header-toggle {
            display: flex; align-items: center; gap: 8px; font-size: 10px; font-weight: 700; color: #555;
            cursor: pointer; user-select: none;
        }
        .header-toggle.active { color: var(--success); }
        .header-toggle .fa-toggle-on { color: var(--success); }
        
        .console-panel {
            height: 100px; background: #050508; border-top: 1px solid var(--border);
            overflow: auto; padding: 10px; font-family: 'Fira Code', monospace; font-size: 11px;
        }
        .log { margin-bottom: 2px; color: #555; }
        .log.info { color: #3b82f6; }
        .log.success { color: var(--success); }
        .log.error { color: var(--error); }
        .log .time { color: #333; margin-right: 8px; }
        
        /* API Reference */
        .api-ref {
            padding: 8px 12px; background: rgba(147,51,234,0.1); border-top: 1px solid var(--border);
            font-size: 10px; color: #888;
        }
        .api-ref code { color: #a855f7; font-family: 'Fira Code', monospace; }
    </style>
</head>
<body>
    <div class="studio-shell">
        <?php include 'sidebar.php'; ?>
        
        <div class="studio-main">
            <header class="studio-header">
                <div class="studio-title">
                    <i class="fa-solid fa-puzzle-piece"></i>
                    Extension Studio
                </div>
                <div class="header-actions">
                    <button class="header-btn btn-reset" onclick="Studio.hardReset()" title="Wipe local storage and samples">
                        <i class="fa-solid fa-trash-arrow-up"></i> Reset IDE
                    </button>
                    <div class="header-toggle" id="autoSaveToggle" onclick="Studio.toggleAutoSave()">
                        AUTO SAVE
                        <i class="fa-solid fa-toggle-off"></i>
                    </div>
                    <button class="header-btn btn-reset" onclick="Studio.reset()">
                        <i class="fa-solid fa-rotate-left"></i> Reset UI
                    </button>
                    <button class="header-btn btn-preview" onclick="Studio.apply()">
                        <i class="fa-solid fa-bolt"></i> Apply to UI
                    </button>
                    <button class="header-btn btn-inject" onclick="Studio.showPublishModal()" style="background: var(--studio-purple);">
                        <i class="fa-solid fa-store"></i> Post to Market
                    </button>
                </div>
            </header>
            
            <div class="workspace">
                <!-- EXPLORER -->
                <div class="explorer-panel">
                    <div class="explorer-header" id="explorerHeader">
                        <div class="project-name" id="currentProjectName">NO PROJECT</div>
                        <div class="explorer-actions">
                            <button class="explorer-btn" title="New File" onclick="Studio.showNewFilePopup()">
                                <i class="fa-solid fa-plus"></i>
                            </button>
                            <button class="explorer-btn" title="Download Project" onclick="Studio.downloadProject()">
                                <i class="fa-solid fa-download"></i>
                            </button>
                            <button class="explorer-btn" title="Delete Project" onclick="Studio.deleteProject()" style="color:var(--error); opacity: 0.4;">
                                <i class="fa-solid fa-trash-can"></i>
                            </button>
                        </div>
                    </div>
                    <div class="file-list" id="explorerFileList">
                        <!-- Files injected here -->
                    </div>
                </div>

                <!-- EDITOR -->
                <div class="editor-panel">
                    <div class="file-tabs" id="tabsBar">
                        <!-- Tabs injected here -->
                    </div>
                    <div class="editor-body">
                        <div id="empty-state" class="ghost-window">
                            <div class="ghost-icon"><i class="fa-solid fa-folder-open"></i></div>
                            <div class="ghost-title">No projects active</div>
                            <div class="ghost-text">You don't have any editors open. Why not create one?</div>
                            <button class="create-proj-btn" onclick="Studio.showCreateProjectPopup()">Create Project</button>
                        </div>
                        
                        <div class="line-gutter" id="gutter">1</div>
                        <div class="code-wrap">
                            <pre class="code-highlight"><code id="highlight" class="language-json"></code></pre>
                            <textarea id="editor" class="code-area" spellcheck="false"
                                oninput="Studio.onEdit()" onscroll="Studio.syncScroll()" onkeydown="Studio.handleKey(event)">{
  "id": "my-extension",
  "name": "Nova Suite",
  "version": "1.0.0",
  "targets": ["header", "sidebar", "video.player"],
  "slots": {
    "header": true,
    "sidebar": true
  }
}</textarea>
                        </div>
                    </div>
                    <div class="api-ref">
                        <strong>Targets:</strong> <code>chat.panel</code> <code>video.player</code> <code>video.controls</code> <code>sidebar</code> <code>header</code> |
                        <strong>Slots:</strong> <code>chat.panel.extra</code> <code>sidebar.bottom</code> <code>video.overlay</code>
                    </div>
                    <div class="status-bar" id="status">
                        <span id="status-msg">Ready</span>
                        <span>JSON</span>
                    </div>
                </div>
                
                <div class="resizer" id="workspaceResizer"></div>
                <div class="resizer-overlay" id="resizerOverlay"></div>

                <!-- LIVE FLOXWATCH UI -->
                <div class="live-panel">
                    <div class="live-header">
                        <div class="live-label">Live Loop UI</div>
                    </div>
                    <div class="live-frame">
                        <div class="sandbox-badge">SANDBOX</div>
                        <iframe id="live-ui" src="home.php" allow="focus-without-user-activation; keyboard-map"></iframe>
                    </div>
                    <div class="console-panel" id="console">
                        <div class="log info"><span class="time">[init]</span> Extension Studio ready</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="modalOverlay">
        <!-- New Project Modal -->
        <div class="app-modal" id="createProjectModal" style="display:none;">
            <div class="modal-title">New Extension Project</div>
            <div class="form-group">
                <label>Project Name</label>
                <input type="text" id="projName" placeholder="e.g. My Awesome Suite">
            </div>
            <div class="form-group">
                <label>Unique ID</label>
                <input type="text" id="projId" placeholder="e.g. awesome-suite">
            </div>
            <div class="form-group">
                <label>Version</label>
                <input type="text" id="projVer" value="1.0.0">
            </div>
            <div class="modal-actions">
                <button class="header-btn btn-reset" onclick="Studio.closeModals()">Cancel</button>
                <button class="header-btn btn-preview" onclick="Studio.createProject()">Initialize</button>
            </div>
        </div>

        <!-- New File Modal -->
        <div class="app-modal" id="createFileModal" style="display:none;">
            <div class="modal-title">New File</div>
            <div class="form-group">
                <label>Filename (must end in .json, .html, .css, .js)</label>
                <input type="text" id="newFilename" placeholder="styles.css">
            </div>
            <div class="modal-actions">
                <button class="header-btn btn-reset" onclick="Studio.closeModals()">Cancel</button>
                <button class="header-btn btn-preview" onclick="Studio.createFile()">Create</button>
            </div>
        </div>

        <!-- Publish to Market Modal -->
        <div class="app-modal" id="publishModal" style="display:none; width: 500px;">
            <div class="modal-title">Publish to Market</div>
            
            <div class="form-group">
                <label>Extension Preview</label>
                <div class="thumb-upload-zone" id="thumbDropZone" onclick="document.getElementById('thumbInput').click()">
                    <div id="thumbPrompt">
                        <i class="fa-solid fa-cloud-arrow-up"></i>
                        <span>Drag & Drop Thumbnail or Click to Upload</span>
                    </div>
                    <img id="marketThumbPreview" class="thumb-preview">
                </div>
                <input type="file" id="thumbInput" style="display:none;" accept="image/*" onchange="Studio.handleThumbUpload(this)">
                <input type="hidden" id="marketThumbUrl">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label>XPoints Price (Max 5000)</label>
                    <input type="number" id="marketPrice" min="0" max="5000" value="100">
                </div>
                <div class="form-group">
                    <label>Version Tag</label>
                    <input type="text" id="marketVersion" value="1.0.0">
                </div>
            </div>
            
            <div class="form-group">
                <label>Description & Features</label>
                <textarea id="marketDesc" placeholder="Describe your extension's capabilities..."></textarea>
            </div>

            <div class="form-group">
                <label>Product Screenshots (Optional)</label>
                <div class="screenshots-grid" id="screenshotsGrid">
                    <!-- Add Button -->
                    <div class="screenshot-item" onclick="document.getElementById('ssInput').click()">
                        <i class="fa-solid fa-plus"></i>
                    </div>
                </div>
                <input type="file" id="ssInput" style="display:none;" accept="image/*" onchange="Studio.handleSSUpload(this)">
            </div>
            
            <div class="modal-actions">
                <button class="header-btn btn-reset" onclick="Studio.closeModals()">Discard</button>
                <button class="header-btn btn-preview" onclick="Studio.publishToMarket()" id="publishSubmitBtn" 
                    style="background: linear-gradient(135deg, var(--accent), #6366f1); padding: 10px 24px;">
                    <i class="fa-solid fa-rocket"></i> Launch to Market
                </button>
            </div>
        </div>
    </div>

    <script>
        /**
         * FLOXWATCH EXTENSION STUDIO (IDE PRO)
         */
        const Studio = {
            projects: JSON.parse(localStorage.getItem('flox_ext_projects') || '{}'),
            activeProjectId: localStorage.getItem('flox_ext_active_id'),
            openTabs: JSON.parse(localStorage.getItem('flox_ext_open_tabs') || '[]'),
            currentFile: null,
            autoSave: localStorage.getItem('flox_ext_autosave') === 'true',
            marketScreenshots: [],
            
            ctx: {
                template: {
                    user: {
                        name: "<?php echo addslashes($displayName); ?>",
                        username: "@<?php echo addslashes($_SESSION['username'] ?? 'dev'); ?>",
                        isPro: <?php echo isset($_SESSION['is_pro']) ? 'true' : 'false'; ?>
                    },
                    app: { theme: "dark", language: "en" },
                    time: { now: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: false }) }
                }
            },

            init() {
                this.renderExplorer();
                this.renderTabs();
                this.updateAutoSaveUI();
                
                if (this.activeProjectId && this.projects[this.activeProjectId]) {
                    // Start Focus Bridge & Input Liberation
                    window.addEventListener('message', (e) => {
                        if (e.data === 'request-focus' || e.data === 'release-focus') {
                            const frame = document.getElementById('live-ui');
                            const editor = document.getElementById('editor');
                            if (editor) editor.blur();
                            if (frame) frame.focus();
                        }
                    });

                    document.getElementById('empty-state').style.display = 'none';
                    if (this.openTabs.length > 0) {
                        this.openFile(this.openTabs[0]);
                    }
                } else {
                    document.getElementById('empty-state').style.display = 'block';
                    document.getElementById('gutter').style.display = 'none';
                    document.getElementById('editor').style.display = 'none';
                }
                
                setInterval(() => { if(this.autoSave) this.saveProject(); }, 5000);
            },

            // --- PROJECT MGMT ---
            showCreateProjectPopup() {
                this.closeModals();
                document.getElementById('modalOverlay').classList.add('active');
                document.getElementById('createProjectModal').style.display = 'block';
            },

            createProject() {
                const name = document.getElementById('projName').value.trim();
                let id = document.getElementById('projId').value.trim().toLowerCase().replace(/\s+/g, '-');
                const ver = document.getElementById('projVer').value.trim();

                if(!name || !id) return alert('Name and ID required');
                if(this.projects[id]) return alert('Project ID already exists');

                const project = {
                    name, id, version: ver,
                    files: {
                        'manifest.json': JSON.stringify({ id, name, version: ver, targets: ["header"], slots: {"header":true} }, null, 2),
                        'styles.css': '/* Scoped styles */',
                        'slots.html': '<!-- Custom HTML -->',
                        'scripts.js': '// Extension logic'
                    }
                };

                this.projects[id] = project;
                this.activeProjectId = id;
                this.openTabs = ['manifest.json', 'styles.css', 'slots.html', 'scripts.js'];
                this.persist();
                location.reload();
            },

            // --- FILE MGMT ---
            showNewFilePopup() {
                if(!this.activeProjectId) return;
                this.closeModals();
                document.getElementById('modalOverlay').classList.add('active');
                document.getElementById('createFileModal').style.display = 'block';
            },

            createFile() {
                const filename = document.getElementById('newFilename').value.trim();
                const valid = /\.(json|html|css|js)$/.test(filename);
                if(!valid) return alert('Only .json, .html, .css, .js allowed');

                const proj = this.projects[this.activeProjectId];
                if(proj.files[filename]) return alert('File already exists');

                proj.files[filename] = '';
                if(!this.openTabs.includes(filename)) this.openTabs.push(filename);
                this.persist();
                this.renderExplorer();
                this.openFile(filename);
                this.closeModals();
            },

            openFile(filename) {
                if (!this.activeProjectId) return;
                
                if (this.currentFile && document.getElementById('editor').style.display !== 'none') {
                    this.projects[this.activeProjectId].files[this.currentFile] = document.getElementById('editor').value;
                }

                this.currentFile = filename;
                if (!this.openTabs.includes(filename)) this.openTabs.push(filename);
                
                const content = this.projects[this.activeProjectId].files[filename];
                const editor = document.getElementById('editor');
                editor.value = content;
                editor.style.display = 'block';
                document.getElementById('gutter').style.display = 'block';
                document.getElementById('empty-state').style.display = 'none';

                this.renderExplorer();
                this.renderTabs();
                this.onEdit();
                this.persist();
                
                document.querySelectorAll('.file-tab').forEach(t => t.classList.toggle('active', t.dataset.file === filename));
                
                // Focus Relay Fix: Force sync with Iframe
                const frame = document.getElementById('live-ui');
                if (frame) frame.contentWindow.focus();
            },

            closeTab(e, filename) {
                e.stopPropagation();
                this.openTabs = this.openTabs.filter(t => t !== filename);
                if (this.currentFile === filename) {
                    this.currentFile = this.openTabs.length > 0 ? this.openTabs[0] : null;
                }
                
                if (!this.currentFile) {
                    document.getElementById('editor').style.display = 'none';
                    document.getElementById('gutter').style.display = 'none';
                    document.getElementById('empty-state').style.display = 'block';
                } else {
                    this.openFile(this.currentFile);
                }
                
                this.renderTabs();
                this.persist();
            },

            // --- RENDERING ---
            renderExplorer() {
                const list = document.getElementById('explorerFileList');
                const proj = this.projects[this.activeProjectId];
                if (!proj) {
                    list.innerHTML = '';
                    document.getElementById('currentProjectName').textContent = 'NO PROJECT';
                    return;
                }

                document.getElementById('currentProjectName').textContent = proj.name;
                
                list.innerHTML = Object.keys(proj.files).map(name => {
                    let icon = 'fa-file';
                    if (name.endsWith('.json')) icon = 'fa-file-code icon-json';
                    if (name.endsWith('.css')) icon = 'fa-brands fa-css3-alt icon-css';
                    if (name.endsWith('.html')) icon = 'fa-solid fa-code icon-html';
                    if (name.endsWith('.js')) icon = 'fa-brands fa-js';
                    
                    return `
                        <div class="file-item ${this.currentFile === name ? 'active' : ''}" onclick="Studio.openFile('${name}')">
                            <i class="fa-solid ${icon}"></i>
                            ${name}
                        </div>
                    `;
                }).join('');
            },

            renderTabs() {
                const bar = document.getElementById('tabsBar');
                bar.innerHTML = this.openTabs.map(name => {
                    let icon = 'fa-file';
                    if (name.endsWith('.json')) icon = 'fa-file-code icon-json';
                    if (name.endsWith('.css')) icon = 'fa-brands fa-css3-alt icon-css';
                    if (name.endsWith('.html')) icon = 'fa-solid fa-code icon-html';
                    if (name.endsWith('.js')) icon = 'fa-brands fa-js';

                    return `
                        <div class="file-tab ${this.currentFile === name ? 'active' : ''}" data-file="${name}" onclick="Studio.openFile('${name}')">
                            <div style="display:flex; align-items:center; gap:8px; overflow:hidden;">
                                <i class="fa-solid ${icon}"></i>
                                <span style="white-space:nowrap;">${name}</span>
                            </div>
                            <div class="tab-close" onclick="Studio.closeTab(event, '${name}')"><i class="fa-solid fa-xmark"></i></div>
                        </div>
                    `;
                }).join('');
            },

            // --- EDITOR LOGIC ---
            onEdit() {
                const editor = document.getElementById('editor');
                const highlight = document.getElementById('highlight');
                const gutter = document.getElementById('gutter');
                const status = document.getElementById('status');
                
                let code = editor.value;
                if (code.endsWith('\n')) code += ' ';
                
                highlight.textContent = code;
                let lang = 'markup';
                if (this.currentFile) {
                    if(this.currentFile.endsWith('.json')) lang = 'json';
                    if(this.currentFile.endsWith('.css')) lang = 'css';
                    if(this.currentFile.endsWith('.js')) lang = 'javascript';
                }
                highlight.className = `language-${lang}`;
                Prism.highlightElement(highlight);
                
                const lines = code.split('\n').length;
                gutter.innerHTML = Array.from({length: lines}, (_, i) => `<div>${i+1}</div>`).join('');
                
                status.className = 'status-bar';
                if (this.currentFile && this.currentFile.endsWith('.json')) {
                    try { JSON.parse(editor.value); document.getElementById('status-msg').textContent = 'Valid'; } 
                    catch (e) { status.classList.add('error'); document.getElementById('status-msg').textContent = 'JSON Error'; }
                } else {
                    document.getElementById('status-msg').textContent = 'Ready';
                }
                
                if(this.activeProjectId && this.currentFile) {
                    this.projects[this.activeProjectId].files[this.currentFile] = editor.value;
                }
            },

            handleKey(e) {
                if (e.key === 'Tab') {
                    e.preventDefault();
                    const editor = document.getElementById('editor');
                    const s = editor.selectionStart;
                    editor.value = editor.value.substring(0, s) + '  ' + editor.value.substring(editor.selectionEnd);
                    editor.selectionStart = editor.selectionEnd = s + 2;
                    this.onEdit();
                }
            },

            syncScroll() {
                const editor = document.getElementById('editor');
                const highlight = document.querySelector('.code-highlight');
                const gutter = document.getElementById('gutter');
                highlight.scrollTop = editor.scrollTop;
                highlight.scrollLeft = editor.scrollLeft;
                gutter.scrollTop = editor.scrollTop;
            },

            apply() {
                this.log('Applying extension to Live UI...', 'info');
                try {
                    const proj = this.projects[this.activeProjectId];
                    const manifestStr = proj.files['manifest.json'] || proj.files['manifest'];
                    const manifest = JSON.parse(manifestStr);
                    const frame = document.getElementById('live-ui');
                    const doc = frame.contentDocument || frame.contentWindow.document;
                    
                    this.injectCSS(doc, manifest.id, proj.files['styles.css'] || '');
                    this.mountSlots(doc, manifest.id, proj.files['slots.html'] || '');
                    this.runScripts(frame.contentWindow, manifest.id, proj.files['scripts.js'] || '');
                    
                    this.log(`Applied: ${manifest.name} v${manifest.version}`, 'success');
                    document.getElementById('status').className = 'status-bar success';
                    document.getElementById('status-msg').textContent = 'Applied';
                } catch (e) {
                    this.log('Error: ' + e.message, 'error');
                    document.getElementById('status').className = 'status-bar error';
                }
            },

            runScripts(win, extId, scripts) {
                if (!scripts) return;
                const studio = this;
                
                try {
                    const doc = win.document;
                    
                    // Build full ctx with platform capabilities
                    const extCtx = {
                        id: extId,
                        name: this.projects[this.activeProjectId]?.name || extId,
                        template: this.ctx.template,
                        
                        // ctx.ui API
                        ui: {
                            log: (m, t) => studio.log(`[Ext:${extId}] ${m}`, t),
                            
                            append: (selector, html) => {
                                const container = doc.querySelector(`[data-flox-extension="${extId}"] ${selector}`);
                                if (container) container.insertAdjacentHTML('beforeend', html);
                            },
                            
                            scrollToBottom: (selector) => {
                                const container = doc.querySelector(`[data-flox-extension="${extId}"] ${selector}`);
                                if (container) container.scrollTop = container.scrollHeight;
                            },
                            
                            onSubmit: (selector, callback) => {
                                const input = doc.querySelector(`[data-flox-extension="${extId}"] ${selector}`);
                                if (input) {
                                    input.addEventListener('keydown', (e) => {
                                        if (e.key === 'Enter' && !e.shiftKey) {
                                            e.preventDefault();
                                            const text = input.value;
                                            input.value = '';
                                            callback(text);
                                        }
                                    });
                                }
                            }
                        },
                        
                        // ctx.chat API
                        chat: {
                            _lastId: {},
                            _intervals: {},
                            
                            subscribe: function(room, callback) {
                                this._lastId[room] = 0;
                                
                                // Load history
                                fetch(`../backend/extension_chat_api.php?action=history&room=${room}&limit=20`)
                                    .then(r => r.json())
                                    .then(d => {
                                        if (d.success && d.messages) {
                                            d.messages.forEach(msg => {
                                                callback(msg);
                                                if (msg.id > this._lastId[room]) this._lastId[room] = msg.id;
                                            });
                                        }
                                    }).catch(() => {});
                                
                                // Poll for new messages
                                const chatCtx = this;
                                this._intervals[room] = setInterval(() => {
                                    fetch(`../backend/extension_chat_api.php?action=poll&room=${room}&since=${chatCtx._lastId[room]}`)
                                        .then(r => r.json())
                                        .then(d => {
                                            if (d.success && d.messages) {
                                                d.messages.forEach(msg => {
                                                    callback(msg);
                                                    if (msg.id > chatCtx._lastId[room]) chatCtx._lastId[room] = msg.id;
                                                });
                                            }
                                        }).catch(() => {});
                                }, 2000);
                            },
                            
                            send: function(room, text) {
                                fetch('../backend/extension_chat_api.php?action=send&room=' + room, {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json' },
                                    body: JSON.stringify({ text: text })
                                }).catch(() => {});
                            }
                        }
                    };
                    
                    const runner = new win.Function('ctx', `"use strict"; try { ${scripts} } catch(e) { ctx.ui.log("Runtime: " + e.message, "error"); }`);
                    runner.call(win, extCtx);
                } catch (e) { this.log('Script Error: ' + e.message, 'error'); }
            },

            injectCSS(doc, extId, css) {
                const id = 'ext-styles-' + extId;
                const old = doc.getElementById(id);
                if (old) old.remove();
                if (!css) return;
                const style = doc.createElement('style');
                style.id = id;
                style.textContent = css.replace(/\[data-flox=/g, `[data-flox-ext="${extId}"] [data-flox=`);
                doc.head.appendChild(style);
                doc.body.setAttribute('data-flox-ext', extId);
            },

            mountSlots(doc, extId, htmlStr) {
                const parser = new DOMParser();
                let raw = htmlStr.trim();
                if (!raw) return;
                raw = raw.replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, "").replace(/on\w+="[^"]*"/gi, "").replace(/on\w+='[^']*'/gi, "");
                const processed = raw.replace(/\{\{([^}]+)\}\}/g, (m, key) => {
                    let val = this.ctx.template;
                    key.trim().split('.').forEach(p => val = val ? val[p] : undefined);
                    return val !== undefined ? this.escapeHTML(String(val)) : m;
                });
                const fragment = parser.parseFromString(processed, 'text/html').body;
                fragment.querySelectorAll('[data-slot]').forEach(slot => {
                    this.injectToTarget(doc, slot.dataset.slot, slot.innerHTML, extId);
                    slot.remove(); 
                });
                const remaining = fragment.innerHTML.trim();
                if (remaining) {
                    const target = doc.querySelector('[data-flox="home.main"]') ? 'home.main' : 'header';
                    this.injectToTarget(doc, target, remaining, extId);
                }
            },

            injectToTarget(doc, targetName, content, extId) {
                let container = doc.querySelector(`[data-flox="${targetName}"]`);
                if (!container && targetName.includes('.')) container = doc.querySelector(`[data-flox="${targetName.split('.')[0]}"]`);
                if (!container && targetName.startsWith('chat.')) container = doc.querySelector('[data-flox="chat.panel"]');
                if (container) {
                    container.querySelectorAll(`[data-flox-extension="${extId}"]`).forEach(el => el.remove());
                    const wrapper = doc.createElement('div');
                    wrapper.setAttribute('data-flox-extension', extId);
                    wrapper.style.cssText = 'display:block; width:100%; position:relative; z-index:9999; color:#fff; pointer-events:auto;';
                    wrapper.innerHTML = content;
                    container.prepend(wrapper);
                }
            },

            // --- UTILS ---
            closeModals() {
                document.getElementById('modalOverlay').classList.remove('active');
                document.getElementById('createProjectModal').style.display = 'none';
                document.getElementById('createFileModal').style.display = 'none';
            },

            toggleAutoSave() {
                this.autoSave = !this.autoSave;
                this.updateAutoSaveUI();
                this.persist();
            },

            updateAutoSaveUI() {
                const toggle = document.getElementById('autoSaveToggle');
                if(!toggle) return;
                toggle.classList.toggle('active', this.autoSave);
                toggle.querySelector('i').className = this.autoSave ? 'fa-solid fa-toggle-on' : 'fa-solid fa-toggle-off';
            },

            deleteProject() {
                if(!this.activeProjectId) return;
                const proj = this.projects[this.activeProjectId];
                if (!confirm(`Are you sure you want to delete "${proj.name}"? This cannot be undone.`)) return;
                
                delete this.projects[this.activeProjectId];
                this.activeProjectId = Object.keys(this.projects)[0] || null;
                this.openTabs = this.activeProjectId ? ['manifest.json', 'styles.css', 'slots.html', 'scripts.js'] : [];
                this.currentFile = null;
                
                this.persist();
                location.reload();
            },

            downloadProject() {
                if(!this.activeProjectId) return;
                const proj = this.projects[this.activeProjectId];
                const blob = new Blob([JSON.stringify(proj, null, 2)], { type: 'application/json' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `${proj.id}-extension.json`;
                a.click();
            },

            saveProject() {
                if (!this.activeProjectId) return;
                if (this.currentFile) this.projects[this.activeProjectId].files[this.currentFile] = document.getElementById('editor').value;
                this.persist();
                // Subtler console log for background save
                console.log('%c Studio: Auto-saved project ', 'background: #222; color: #22c55e');
            },

            showPublishModal() {
                if(!this.activeProjectId) return alert('Select or create a project first');
                this.closeModals();
                document.getElementById('modalOverlay').classList.add('active');
                document.getElementById('publishModal').style.display = 'block';
                
                this.marketScreenshots = [];
                this.renderScreenshots();
                this.resetThumbZone();
            },

            async handleSSUpload(input) {
                if (!input.files || !input.files[0]) return;
                const file = input.files[0];
                const formData = new FormData();
                formData.append('thumbnail', file); // Use same backend helper

                try {
                    const r = await fetch('../backend/upload_market_thumb.php', { method: 'POST', body: formData });
                    const d = await r.json();
                    if (d.success) {
                        this.marketScreenshots.push(d.url);
                        this.renderScreenshots();
                    } else alert(d.message);
                } catch(e) { alert(e.message); }
                input.value = ''; // Reset input
            },

            renderScreenshots() {
                const grid = document.getElementById('screenshotsGrid');
                let html = this.marketScreenshots.map((url, i) => `
                    <div class="screenshot-item">
                        <img src="../${url}">
                        <div class="screenshot-remove" onclick="Studio.removeSS(${i})"><i class="fa-solid fa-xmark"></i></div>
                    </div>
                `).join('');
                
                html += `<div class="screenshot-item" onclick="document.getElementById('ssInput').click()"><i class="fa-solid fa-plus"></i></div>`;
                grid.innerHTML = html;
            },

            removeSS(index) {
                this.marketScreenshots.splice(index, 1);
                this.renderScreenshots();
            },

            async handleThumbUpload(input) {
                if (!input.files || !input.files[0]) return;
                
                const file = input.files[0];
                const formData = new FormData();
                formData.append('thumbnail', file);

                const prompt = document.getElementById('thumbPrompt');
                prompt.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i><span>Uploading...</span>';

                try {
                    const response = await fetch('../backend/upload_market_thumb.php', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await response.json();
                    if (data.success) {
                        document.getElementById('marketThumbUrl').value = data.url;
                        const preview = document.getElementById('marketThumbPreview');
                        preview.src = '../' + data.url;
                        document.getElementById('thumbDropZone').classList.add('has-image');
                    } else {
                        alert('Upload failed: ' + data.message);
                        this.resetThumbZone();
                    }
                } catch (e) {
                    alert('Error uploading: ' + e.message);
                    this.resetThumbZone();
                }
            },

            resetThumbZone() {
                const zone = document.getElementById('thumbDropZone');
                const prompt = document.getElementById('thumbPrompt');
                const preview = document.getElementById('marketThumbPreview');
                const urlInput = document.getElementById('marketThumbUrl');
                
                zone.classList.remove('has-image');
                prompt.innerHTML = '<i class="fa-solid fa-cloud-arrow-up"></i><span>Drag & Drop Thumbnail or Click to Upload</span>';
                preview.src = '';
                if(urlInput) urlInput.value = '';
            },

            async publishToMarket() {
                const price = parseInt(document.getElementById('marketPrice').value);
                const thumb = document.getElementById('marketThumbUrl').value;
                const desc = document.getElementById('marketDesc').value.trim();
                const version = document.getElementById('marketVersion').value.trim();
                const project = this.projects[this.activeProjectId];
                const btn = document.getElementById('publishSubmitBtn');

                if (isNaN(price) || price < 0 || price > 5000) return alert('Price must be between 0 and 5000 XPoints');
                if (!thumb) return alert('Please upload a thumbnail image');
                if (!desc) return alert('Description is required');

                project.version = version; // Sync version from modal

                btn.disabled = true;
                btn.innerHTML = '<i class="fa-solid fa-rocket fa-bounce"></i> Launching...';

                try {
                    const response = await fetch('../backend/publish_extension.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            price,
                            thumb,
                            desc,
                            screenshots: this.marketScreenshots,
                            project: project
                        })
                    });

                    const data = await response.json();
                    if (data.success) {
                        alert('Extension Launched! Your project is now live in the XPoints Market.');
                        this.closeModals();
                    } else {
                        alert('Error: ' + data.message);
                    }
                } catch (e) {
                    alert('Network error: ' + e.message);
                } finally {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-solid fa-rocket"></i> Launch to Market';
                }
            },

            persist() {
                localStorage.setItem('flox_ext_projects', JSON.stringify(this.projects));
                localStorage.setItem('flox_ext_active_id', this.activeProjectId);
                localStorage.setItem('flox_ext_open_tabs', JSON.stringify(this.openTabs));
                localStorage.setItem('flox_ext_autosave', this.autoSave);
            },

            escapeHTML(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            },

            reset() {
                const frame = document.getElementById('live-ui');
                frame.src = frame.src;
                document.getElementById('status').className = 'status-bar';
                document.getElementById('status-msg').textContent = 'Ready';
                this.log('UI Reset', 'info');
            },

            hardReset() {
                if (!confirm("Are you sure? This will wipe ALL local project data and reset the IDE to factory defaults.")) return;
                localStorage.clear();
                location.reload();
            },

            initResizer() {
                const resizer = document.getElementById('workspaceResizer');
                const overlay = document.getElementById('resizerOverlay');
                const editorPanel = document.querySelector('.editor-panel');
                const explorerPanel = document.querySelector('.explorer-panel');
                let isDragging = false;

                if(!resizer) return;
                resizer.addEventListener('mousedown', () => { 
                    isDragging = true; 
                    resizer.classList.add('active'); 
                    overlay.classList.add('active'); 
                    document.body.style.cursor = 'col-resize';
                });

                document.addEventListener('mousemove', (e) => {
                    if (!isDragging) return;
                    
                    const workspace = document.querySelector('.workspace');
                    const workspaceLeft = workspace.getBoundingClientRect().left;
                    const explorerWidth = explorerPanel.offsetWidth;
                    
                    // Width of the editor panel is its distance from its own container's left edge
                    const newWidth = e.clientX - (workspaceLeft + explorerWidth);
                    
                    // We need the relative percentage of the editor compared to the REMAINING space (Editor + Preview)
                    // But for simplicity, we'll use percentage of the total workspace
                    const percentage = (newWidth / (workspace.offsetWidth - explorerWidth)) * 100;
                    
                    if (percentage > 15 && percentage < 85) {
                        editorPanel.style.flex = `0 0 ${percentage}%`;
                    }
                });
                document.addEventListener('mouseup', () => { 
                    if (!isDragging) return; 
                    isDragging = false; 
                    resizer.classList.remove('active'); 
                    overlay.classList.remove('active'); 
                    overlay.style.display = 'none'; // HARD HIDE
                    document.body.style.cursor = 'default';
                });
            },

            log(msg, type = 'info') {
                const console = document.getElementById('console');
                if(!console) return;
                const time = new Date().toLocaleTimeString('en-US', { hour12: false, hour: '2-digit', minute: '2-digit', second: '2-digit' });
                const line = document.createElement('div');
                line.className = `log ${type}`;
                line.innerHTML = `<span class="time">[${time}]</span> ${msg}`;
                console.appendChild(line);
                console.scrollTop = console.scrollHeight;
            }
        };
        
        window.onload = () => {
            Studio.init();
            Studio.initResizer();
        };
    </script>
</body>
</html>
