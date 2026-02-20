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
    <title>Customization - Loop</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="home.css?v=3">
    <link rel="stylesheet" href="layout.css">
    <link rel="stylesheet" href="settings_layout.css">
    <!-- Inline styles for page-specific elements -->
    <style>
        .theme-options { display: flex; gap: 12px; }
        .theme-option { width: 48px; height: 48px; border-radius: 12px; cursor: pointer; border: 3px solid transparent; transition: var(--transition); display: flex; align-items: center; justify-content: center; position: relative; }
        .theme-option.active { border-color: var(--accent-color); transform: scale(1.05); }
        .theme-option-dark { background: #0f0f0f; } .theme-option-dark i { color: #fff; }
        .theme-option-light { background: #fff; } .theme-option-light i { color: #000; }
        .theme-option-system { background: linear-gradient(135deg, #0f0f0f 50%, #fff 50%); } .theme-option-system i { color: var(--accent-color); }
        .gradient-option { width: 48px; height: 48px; }

        .custom-gradient-pickers { display: flex; align-items: center; gap: 12px; margin-top: 12px; padding: 12px; background: var(--tertiary-color); border-radius: 12px; }
        .color-picker-input { width: 40px; height: 40px; border: none; border-radius: 8px; cursor: pointer; padding: 0; background: none; }
        
        .layout-options { display: flex; gap: 12px; }
        .layout-option { padding: 12px 20px; border-radius: 8px; cursor: pointer; border: 2px solid var(--border-color); background: var(--tertiary-color); display: flex; gap: 8px; align-items: center; }
        .layout-option.active { border-color: var(--accent-color); background: rgba(var(--accent-rgb), 0.1); }
        
        /* Custom Dropdown Styles reused from main css or inline */
        .custom-dropdown { position: relative; min-width: 160px; }
        .custom-dropdown-trigger { background: var(--tertiary-color); border: 1px solid var(--border-color); border-radius: 8px; padding: 10px 14px; display: flex; justify-content: space-between; align-items: center; cursor: pointer; }
        .custom-dropdown-menu { position: absolute; top: 100%; left: 0; right: 0; background: var(--secondary-color); border: 1px solid var(--border-color); border-radius: 8px; margin-top: 4px; display: none; z-index: 100; overflow: hidden; }
        .custom-dropdown.open .custom-dropdown-menu { display: block; animation: fadeIn 0.1s; }
        .custom-dropdown-item { padding: 10px 14px; cursor: pointer; transition: background 0.2s; }
        .custom-dropdown-item:hover { background: var(--hover-bg); }
        .custom-dropdown-item.selected { color: var(--accent-color); background: rgba(var(--accent-rgb), 0.1); }
        
        /* Toggle Switch */
        .settings-toggle { position: relative; width: 48px; height: 26px; display: inline-block; }
        .settings-toggle input { opacity: 0; width: 0; height: 0; }
        .settings-toggle-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background: var(--tertiary-color); border-radius: 26px; transition: .3s; }
        .settings-toggle-slider:before { position: absolute; content: ""; height: 20px; width: 20px; left: 3px; bottom: 3px; background: white; border-radius: 50%; transition: .3s; }
        .settings-toggle input:checked + .settings-toggle-slider { background: var(--accent-color); }
        .settings-toggle input:checked + .settings-toggle-slider:before { transform: translateX(22px); }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    <div class="app-layout">
        <?php include 'sidebar.php'; ?>
        <main class="main-content">
            <div class="settings-wrapper">
                <?php include 'settings_sidebar.php'; ?>
                
                <div class="settings-content">
                    <div class="settings-page-header">
                        <h1>Customization</h1>
                        <p>Personalize your viewing experience and interface</p>
                    </div>

                    <!-- Appearance Card -->
                    <div class="settings-card">
                        <div class="settings-group-title">
                            <i class="fa-solid fa-paintbrush" style="color: var(--accent-color);"></i>
                            Appearance
                        </div>

                        <div class="settings-row">
                            <div class="settings-info">
                                <div class="settings-label">App Theme</div>
                                <div class="settings-desc">Select your preferred color scheme</div>
                            </div>
                            <div class="theme-options">
                                <div class="theme-option theme-option-dark active" data-theme="dark" title="Dark"><i class="fa-solid fa-moon"></i></div>
                                <div class="theme-option theme-option-light" data-theme="light" title="Light"><i class="fa-solid fa-sun"></i></div>
                                <div class="theme-option theme-option-system" data-theme="system" title="System"><i class="fa-solid fa-desktop"></i></div>
                            </div>
                        </div>

                         <div class="settings-row">
                            <div class="settings-info">
                                <div class="settings-label">Accent Gradient</div>
                                <div class="settings-desc">Choose a premium background style</div>
                            </div>
                            <div class="theme-options gradient-options">
                                <div class="theme-option gradient-option active" data-gradient="default" title="Deep Carbon" style="background: #121212;"></div>
                                <div class="theme-option gradient-option" data-gradient="midnight" title="Midnight Purple" style="background: linear-gradient(135deg, #121212 0%, #2d1b4d 100%);"></div>
                                <div class="theme-option gradient-option" data-gradient="ocean" title="Ocean Blue" style="background: linear-gradient(135deg, #121212 0%, #1b3d4d 100%);"></div>
                                <div class="theme-option gradient-option" data-gradient="aurora" title="Aurora Green" style="background: linear-gradient(135deg, #121212 0%, #1b3d1b 100%);"></div>
                                <div class="theme-option gradient-option" data-gradient="sunset" title="Sunset Ruby" style="background: linear-gradient(135deg, #121212 0%, #4d1b1b 100%);"></div>
                                <div class="theme-option gradient-option" data-gradient="custom" title="Custom" id="customGradientPreview" style="background: linear-gradient(135deg, #121212 0%, #1a1a2e 100%);">
                                    <i class="fa-solid fa-plus" style="color: #fff; font-size: 14px;"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="settings-row" id="customGradientControls" style="display: none; background: rgba(0,0,0,0.2); padding: 16px; border-radius: 8px; margin-top: 8px;">
                            <div class="settings-info">
                                <div class="settings-label">Custom Colors</div>
                                <div class="settings-desc">Pick start and end colors</div>
                            </div>
                            <div class="custom-gradient-pickers">
                                <input type="color" id="gradientColor1" value="#121212" class="color-picker-input">
                                <input type="color" id="gradientColor2" value="#1a1a2e" class="color-picker-input">
                                <button class="settings-btn" id="applyCustomGradient" style="padding: 8px 16px; border-radius: 6px;">Apply</button>
                            </div>
                        </div>

                        <div class="settings-row">
                            <div class="settings-info">
                                <div class="settings-label">Video Layout</div>
                                <div class="settings-desc">How videos appear on the home screen</div>
                            </div>
                            <div class="layout-options">
                                <div class="layout-option active" data-layout="grid">
                                    <i class="fa-solid fa-grip"></i> Grid
                                </div>
                                <div class="layout-option" data-layout="list">
                                    <i class="fa-solid fa-list"></i> List
                                </div>
                            </div>
                        </div>
                        
                         <div class="settings-row">
                            <div class="settings-info">
                                <div class="settings-label">Font Size</div>
                                <div class="settings-desc">Adjust UI text scale</div>
                            </div>
                             <div class="custom-dropdown" id="fontSizeSelect" data-value="medium">
                                <div class="custom-dropdown-trigger">
                                    <span>Medium</span>
                                    <i class="fa-solid fa-chevron-down"></i>
                                </div>
                                <div class="custom-dropdown-menu">
                                    <div class="custom-dropdown-item" data-value="small">Small</div>
                                    <div class="custom-dropdown-item selected" data-value="medium">Medium</div>
                                    <div class="custom-dropdown-item" data-value="large">Large</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Playback Card -->
                    <div class="settings-card">
                         <div class="settings-group-title">
                            <i class="fa-solid fa-play-circle" style="color: var(--accent-color);"></i>
                            Playback & Interface
                        </div>
                        
                        <div class="settings-row">
                            <div class="settings-info">
                                <div class="settings-label">Default Quality</div>
                                <div class="settings-desc">Higher quality uses more data</div>
                            </div>
                            <div class="custom-dropdown" id="videoQuality" data-value="auto">
                                <div class="custom-dropdown-trigger">
                                    <span>Auto</span>
                                    <i class="fa-solid fa-chevron-down"></i>
                                </div>
                                <div class="custom-dropdown-menu">
                                    <div class="custom-dropdown-item selected" data-value="auto">Auto</div>
                                    <div class="custom-dropdown-item" data-value="1080p">1080p</div>
                                    <div class="custom-dropdown-item" data-value="720p">720p</div>
                                    <div class="custom-dropdown-item" data-value="480p">480p</div>
                                </div>
                            </div>
                        </div>

                        <div class="settings-row">
                            <div class="settings-info">
                                <div class="settings-label">Autoplay Next Video</div>
                                <div class="settings-desc">When a video finishes</div>
                            </div>
                            <label class="settings-toggle">
                                <input type="checkbox" id="autoplayToggle" checked>
                                <span class="settings-toggle-slider"></span>
                            </label>
                        </div>

                         <div class="settings-row">
                            <div class="settings-info">
                                <div class="settings-label">Hover Previews</div>
                                <div class="settings-desc">Play videos when hovering thumbnails</div>
                            </div>
                            <label class="settings-toggle">
                                <input type="checkbox" id="autoplayHover" checked>
                                <span class="settings-toggle-slider"></span>
                            </label>
                        </div>
                    </div>

                </div>
            </div>
        </main>
    </div>
    
    <?php include 'mobile_footer.php'; ?>

    <script src="theme.js"></script>
    <script src="popup.js"></script>
    <script src="notifications.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // -- Helpers --
            const setDropdown = (id, val) => {
                const el = document.getElementById(id);
                if(!el) return;
                el.dataset.value = val;
                const items = el.querySelectorAll('.custom-dropdown-item');
                let found = false;
                items.forEach(i => {
                    if(i.dataset.value === val) {
                        i.classList.add('selected');
                        el.querySelector('.custom-dropdown-trigger span').textContent = i.textContent;
                        found = true;
                    } else i.classList.remove('selected');
                });
                if(!found && items.length > 0) { // Fallback
                     items[0].classList.add('selected');
                     el.dataset.value = items[0].dataset.value;
                     el.querySelector('.custom-dropdown-trigger span').textContent = items[0].textContent;
                }
            };
            const getDropdown = (id) => document.getElementById(id)?.dataset.value;

            // -- Load Settings --
            const loadState = () => {
                const s = JSON.parse(localStorage.getItem('floxwatch_settings') || '{}');
                
                // Theme
                document.querySelectorAll('.theme-option').forEach(t => {
                    if(t.dataset.theme) t.classList.toggle('active', t.dataset.theme === (s.theme || 'dark'));
                });
                
                // Gradient
                const grad = localStorage.getItem('floxwatch_gradient') || 'default';
                document.querySelectorAll('.gradient-option').forEach(g => {
                    g.classList.toggle('active', g.dataset.gradient === grad);
                    if(g.dataset.gradient === 'custom' && grad === 'custom') {
                        document.getElementById('customGradientControls').style.display = 'block';
                    }
                });

                // Layout
                document.querySelectorAll('.layout-option').forEach(l => {
                    l.classList.toggle('active', l.dataset.layout === (s.layout || 'grid'));
                });

                // Dropdowns
                if(s.fontSize) setDropdown('fontSizeSelect', s.fontSize);
                if(s.videoQuality) setDropdown('videoQuality', s.videoQuality);
                
                // Toggles
                if(s.autoplay !== undefined) document.getElementById('autoplayToggle').checked = s.autoplay;
                if(s.autoplayHover !== undefined) document.getElementById('autoplayHover').checked = s.autoplayHover;
            };

            // -- Save Settings --
            const saveState = () => {
                 let s = JSON.parse(localStorage.getItem('floxwatch_settings') || '{}');
                 
                 s.theme = document.querySelector('.theme-option.active:not(.gradient-option)')?.dataset.theme || 'dark';
                 s.fontSize = getDropdown('fontSizeSelect');
                 s.layout = document.querySelector('.layout-option.active')?.dataset.layout || 'grid';
                 s.videoQuality = getDropdown('videoQuality');
                 s.autoplay = document.getElementById('autoplayToggle').checked;
                 s.autoplayHover = document.getElementById('autoplayHover').checked;
                 
                 localStorage.setItem('floxwatch_settings', JSON.stringify(s));
                 
                 // Sync specific keys used by theme.js
                 localStorage.setItem('floxwatch_fontsize', s.fontSize);
                 localStorage.setItem('floxwatch_layout', s.layout);
                 
                 if(typeof Popup !== 'undefined') Popup.show('Preferences Saved', 'success');
            };

            // -- Event Listeners --
            
            // Theme Options
            document.querySelectorAll('.theme-option:not(.gradient-option)').forEach(opt => {
                opt.addEventListener('click', () => {
                     document.querySelectorAll('.theme-option:not(.gradient-option)').forEach(o => o.classList.remove('active'));
                     opt.classList.add('active');
                     saveState();
                });
            });

            // Gradient Options
            document.querySelectorAll('.gradient-option').forEach(opt => {
                opt.addEventListener('click', () => {
                     document.querySelectorAll('.gradient-option').forEach(o => o.classList.remove('active'));
                     opt.classList.add('active');
                     
                     const isCustom = opt.dataset.gradient === 'custom';
                     document.getElementById('customGradientControls').style.display = isCustom ? 'block' : 'none';
                     
                     saveState(); // theme.js listens to storage or click, usually logic is there
                });
            });

            // Layout
            document.querySelectorAll('.layout-option').forEach(opt => {
                opt.addEventListener('click', () => {
                     document.querySelectorAll('.layout-option').forEach(o => o.classList.remove('active'));
                     opt.classList.add('active');
                     
                     if(window.FloxTheme) window.FloxTheme.setLayout(opt.dataset.layout);
                     saveState();
                });
            });
            
            // Dropdowns logic
            document.querySelectorAll('.custom-dropdown').forEach(dd => {
                const trig = dd.querySelector('.custom-dropdown-trigger');
                const items = dd.querySelectorAll('.custom-dropdown-item');
                
                trig.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const isOpen = dd.classList.contains('open');
                    document.querySelectorAll('.custom-dropdown').forEach(d => d.classList.remove('open'));
                    if(!isOpen) dd.classList.add('open');
                });
                
                items.forEach(itm => {
                    itm.addEventListener('click', () => {
                        dd.dataset.value = itm.dataset.value;
                        trig.querySelector('span').textContent = itm.textContent;
                        items.forEach(i => i.classList.remove('selected'));
                        itm.classList.add('selected');
                        dd.classList.remove('open');
                        
                        if(dd.id === 'fontSizeSelect' && window.FloxTheme) window.FloxTheme.setFontSize(itm.dataset.value);
                        saveState();
                    });
                });
            });
            
            document.addEventListener('click', () => document.querySelectorAll('.custom-dropdown').forEach(d => d.classList.remove('open')));
            
            // Toggles
            ['autoplayToggle', 'autoplayHover'].forEach(id => {
                document.getElementById(id).addEventListener('change', saveState);
            });
            
            // Custom Gradient Logic
             const btn = document.getElementById('applyCustomGradient');
             const c1 = document.getElementById('gradientColor1');
             const c2 = document.getElementById('gradientColor2');
             const prev = document.getElementById('customGradientPreview');
             
             if(btn) {
                 btn.addEventListener('click', () => {
                     if(window.FloxTheme) window.FloxTheme.applyCustomGradient(c1.value, c2.value);
                     // Save to storage handled by theme.js usually, but let's encourage it
                 });
                 
                 const upPrev = () => { prev.style.background = `linear-gradient(135deg, ${c1.value} 0%, ${c2.value} 100%)`; };
                 c1.addEventListener('input', upPrev);
                 c2.addEventListener('input', upPrev);
                 
                 // Init colors
                 if(window.FloxTheme && window.FloxTheme.getSavedCustomColors) {
                     const saved = window.FloxTheme.getSavedCustomColors();
                     c1.value = saved.color1; c2.value = saved.color2;
                     upPrev();
                 }
             }

            // Init
            loadState();
        });
    </script>
</body>
</html>
