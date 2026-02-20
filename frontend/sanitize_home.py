import re

with open(r'c:\MAMP\htdocs\FloxWatch\frontend\home_remote.php', 'r', encoding='utf-8') as f:
    content = f.read()

# 1. Fix clobbered cache busters
content = re.sub(r'\?v=<\?php echo time\(\); \?>(_REFRESH_V3|_LIVE_FIX|_force_refresh)+', '?v=<?php echo time(); ?>_STABLE_V1', content)

# 2. Remove injected style blocks after </html>
content = re.sub(r'</html>.*<style>.*</style>.*', '</html>', content, flags=re.DOTALL)

# 3. Ensure padding-top in .app-layout and .main-content is 90px (Clean existing)
content = re.sub(r'\.app-layout\s*{[^}]*padding-top:[^}]*}', '.app-layout { position: relative; z-index: 1; background: transparent !important; flex: 1; display: flex; overflow: hidden; min-height: 0; padding-top: 0 !important; height: 100vh; }', content)
content = re.sub(r'\.main-content\s*{[^}]*padding-top:[^}]*}', '.main-content { flex: 1; height: 100%; position: relative; display: block; overflow-y: auto; padding-top: 90px !important; scroll-padding-top: 90px; }', content)
content = re.sub(r'\.side-nav\s*{[^}]*padding-top:[^}]*}', '.side-nav { position: relative !important; top: 0 !important; height: 100% !important; max-height: 100%; padding-top: 90px !important; border-right: none !important; }', content)

# 4. Add side-nav-item fix into the main style block if missing
if '.side-nav-item {' not in content:
    content = content.replace('/* Hero Cinema Section */', '.side-nav-item { color: #fff !important; margin-top: 0 !important; }\n\n        /* Hero Cinema Section */')

with open(r'c:\MAMP\htdocs\FloxWatch\frontend\home_fixed.php', 'w', encoding='utf-8') as f:
    f.write(content)

print("Sanitization complete. home_fixed.php created.")
