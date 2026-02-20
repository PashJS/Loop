<?php
session_start();

// Check admin authentication
if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
    header('Location: index.php');
    exit;
}

require_once '../backend/config.php';

// Fetch stats
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalVideos = $pdo->query("SELECT COUNT(*) FROM videos")->fetchColumn();
$proUsers = $pdo->query("SELECT COUNT(*) FROM users WHERE is_pro = 1")->fetchColumn();

// SQL Search
$searchQuery = isset($_GET['q']) ? trim($_GET['q']) : '';
$searchTable = isset($_GET['t']) ? trim($_GET['t']) : 'users';
$searchError = '';
$searchResults = [];
$searchMode = !empty($searchQuery);

if ($searchMode) {
    try {
        $sql = "SELECT * FROM $searchTable WHERE $searchQuery LIMIT 50";
        $searchResults = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $searchError = $e->getMessage();
    }
}

// Fetch recent users (only if not searching)
if (!$searchMode) {
    $recentUsers = $pdo->query("SELECT id, username, email, created_at, is_pro FROM users ORDER BY created_at DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
}

// Handle logout
if (isset($_GET['logout'])) {
    unset($_SESSION['admin_authenticated']);
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - FloxWatch</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #0a0a0a;
            color: #fff;
            min-height: 100vh;
        }
        .admin-header {
            background: #111;
            border-bottom: 1px solid #222;
            padding: 20px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .admin-header h1 {
            font-size: 18px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .admin-header h1 i { color: #3ea6ff; }
        .logout-btn {
            background: transparent;
            border: 1px solid #333;
            color: #888;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 13px;
            cursor: pointer;
            text-decoration: none;
        }
        .logout-btn:hover { border-color: #555; color: #fff; }
        .admin-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        .stat-card {
            background: #111;
            border: 1px solid #222;
            border-radius: 12px;
            padding: 25px;
        }
        .stat-card h3 {
            color: #666;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 10px;
        }
        .stat-card .value { font-size: 32px; font-weight: 600; color: #fff; }
        .section {
            background: #111;
            border: 1px solid #222;
            border-radius: 12px;
            margin-bottom: 30px;
        }
        .section-header {
            padding: 20px;
            border-bottom: 1px solid #222;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .section-header h2 { font-size: 16px; font-weight: 500; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 15px 20px; text-align: left; border-bottom: 1px solid #1a1a1a; }
        th { color: #666; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; font-weight: 500; }
        td { color: #ccc; font-size: 14px; }
        tr:hover { background: rgba(255,255,255,0.02); }
        .badge { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .badge-pro { background: rgba(255, 215, 0, 0.15); color: #ffd700; }
        .badge-free { background: rgba(100, 100, 100, 0.2); color: #888; }
        .nav-tabs { display: flex; gap: 10px; margin-bottom: 30px; }
        .nav-tab {
            background: transparent;
            border: 1px solid #222;
            color: #888;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 13px;
            cursor: pointer;
            text-decoration: none;
        }
        .nav-tab:hover, .nav-tab.active { background: #1a1a1a; border-color: #333; color: #fff; }
    </style>
</head>
<body>
    <div class="admin-header">
        <h1><i class="fa-solid fa-shield-halved"></i> Admin Panel</h1>
        <a href="?logout=1" class="logout-btn">Logout</a>
    </div>

    <div class="admin-container">
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Users</h3>
                <div class="value"><?php echo number_format($totalUsers); ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Videos</h3>
                <div class="value"><?php echo number_format($totalVideos); ?></div>
            </div>
            <div class="stat-card">
                <h3>Pro Users</h3>
                <div class="value"><?php echo number_format($proUsers); ?></div>
            </div>
            <div id="systemStatusCard" class="stat-card <?php echo $isSiteShutdown ? 'shutdown-active' : ''; ?>" style="display: none;">
                <h3>System Status</h3>
                <div class="value" style="font-size: 14px; margin-top: 5px;">
                    <?php if ($isSiteShutdown): ?>
                        <span style="color: #ff5555;"><i class="fa-solid fa-power-off"></i> OFFLINE</span>
                        <button onclick="restoreSite()" class="action-btn" style="margin-top: 10px; border-color: #3ea6ff; color: #3ea6ff;">Go Online</button>
                    <?php else: ?>
                        <span style="color: #44ff44;"><i class="fa-solid fa-circle-check"></i> ONLINE</span>
                        <?php if ($shutdownLocked): ?>
                            <div style="color: #ff5555; font-size: 11px; margin-top: 10px;">Locked for 24h</div>
                        <?php else: ?>
                            <button onclick="openShutdownModal()" class="action-btn danger" style="margin-top: 10px;">Shut Down</button>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="nav-tabs">
            <a href="admin_panel.php" class="nav-tab active">Dashboard</a>
            <a href="admin_users.php" class="nav-tab">Users</a>
            <a href="admin_videos.php" class="nav-tab">Videos</a>
            <a href="admin_announcements.php" class="nav-tab">Announcements</a>
            <a href="admin_activity.php" class="nav-tab">Activity</a>
        </div>

        <!-- SQL Search Bar -->
        <div style="margin-bottom: 20px;">
            <form method="GET" style="display: flex; gap: 10px; align-items: flex-start; flex-wrap: wrap;">
                <select name="t" style="background: #0a0a0a; border: 1px solid #333; padding: 12px 16px; border-radius: 8px; color: #fff; font-size: 13px;">
                    <option value="users" <?php echo $searchTable === 'users' ? 'selected' : ''; ?>>users</option>
                    <option value="videos" <?php echo $searchTable === 'videos' ? 'selected' : ''; ?>>videos</option>
                    <option value="comments" <?php echo $searchTable === 'comments' ? 'selected' : ''; ?>>comments</option>
                    <option value="notifications" <?php echo $searchTable === 'notifications' ? 'selected' : ''; ?>>notifications</option>
                </select>
                <div style="flex: 1; min-width: 300px;">
                    <div style="display: flex; gap: 10px;">
                        <input type="text" id="sqlSearchInput" name="q" value="<?php echo htmlspecialchars($searchQuery); ?>" 
                               placeholder="Enter SQL WHERE clause..." 
                               style="flex: 1; background: #0a0a0a; border: 1px solid #333; padding: 12px 16px; border-radius: 8px; color: #fff; font-family: monospace; font-size: 13px;">
                        <button type="submit" style="background: #3ea6ff; border: none; padding: 12px 24px; border-radius: 8px; color: #000; font-weight: 600; cursor: pointer;">Search</button>
                        <?php if ($searchMode): ?>
                            <a href="admin_panel.php" style="background: #333; border: none; padding: 12px 16px; border-radius: 8px; color: #888; text-decoration: none;">Clear</a>
                        <?php endif; ?>
                    </div>
                    <div style="margin-top: 8px; font-size: 11px; color: #555;">
                        <strong>Examples:</strong>
                        <code style="background: #1a1a1a; padding: 2px 6px; border-radius: 4px; margin-left: 5px; cursor: pointer;" onclick="document.querySelector('input[name=q]').value = this.textContent">1=1</code>
                        <code style="background: #1a1a1a; padding: 2px 6px; border-radius: 4px; margin-left: 5px; cursor: pointer;" onclick="document.querySelector('input[name=q]').value = this.textContent">id = 1</code>
                        <code style="background: #1a1a1a; padding: 2px 6px; border-radius: 4px; margin-left: 5px; cursor: pointer;" onclick="document.querySelector('input[name=q]').value = this.textContent">created_at > '2026-01-01'</code>
                    </div>
                </div>
            </form>
            
            <?php if ($searchError): ?>
                <div style="margin-top: 10px; background: rgba(255,68,68,0.1); border: 1px solid rgba(255,68,68,0.3); padding: 12px; border-radius: 8px; color: #ff6666; font-size: 13px;">
                    <strong>SQL Error:</strong> <?php echo htmlspecialchars($searchError); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($searchMode && !$searchError): ?>
                <div style="margin-top: 10px; color: #888; font-size: 13px;">
                    Found <strong style="color: #3ea6ff;"><?php echo count($searchResults); ?></strong> result(s) in <strong><?php echo $searchTable; ?></strong>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($searchMode && !$searchError && !empty($searchResults)): ?>
        <div class="section">
            <div class="section-header">
                <h2>Search Results (<?php echo $searchTable; ?>)</h2>
            </div>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <?php foreach (array_keys($searchResults[0]) as $col): ?>
                                <th><?php echo htmlspecialchars($col); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($searchResults as $row): ?>
                        <tr>
                            <?php foreach ($row as $val): ?>
                                <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?php echo htmlspecialchars(substr($val ?? '', 0, 100)); ?></td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php else: ?>
        <div class="section">
            <div class="section-header">
                <h2>Recent Users</h2>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Joined</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentUsers as $user): ?>
                    <tr>
                        <td><?php echo $user['id']; ?></td>
                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td>
                            <?php if ($user['is_pro']): ?>
                                <span class="badge badge-pro">PRO</span>
                            <?php else: ?>
                                <span class="badge badge-free">Free</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Shutdown Modal -->
    <div id="shutdownModal" class="modal" style="display: none; position: fixed; z-index: 10001; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); backdrop-filter: blur(10px);">
        <div style="background: #111; border: 1px solid #222; width: 90%; max-width: 450px; margin: 100px auto; border-radius: 16px; padding: 30px; position: relative;">
            <h2 style="color: #ff5555; margin-bottom: 15px;"><i class="fa-solid fa-triangle-exclamation"></i> Emergency Shutdown</h2>
            <p style="color: #888; font-size: 14px; margin-bottom: 25px;">This action will immediately take FloxWatch offline. Everyone except admins will be redirected to the maintenance page.</p>
            
            <div id="shutdownStep1">
                <button onclick="requestCode()" id="reqCodeBtn" style="width: 100%; background: #fff; color: #000; border: none; padding: 14px; border-radius: 8px; font-weight: 600; cursor: pointer;">
                    Send Security Code to floxxteam@gmail.com
                </button>
            </div>

            <div id="shutdownStep2" style="display: none;">
                <div style="margin-bottom: 20px;">
                    <label style="display: block; color: #666; font-size: 11px; text-transform: uppercase; margin-bottom: 8px;">20-Character Security Code</label>
                    <input type="text" id="shutdownCode" style="width: 100%; background: #0a0a0a; border: 1px solid #222; padding: 12px; border-radius: 8px; color: #fff; font-family: monospace;">
                </div>
                <div style="margin-bottom: 25px;">
                    <label style="display: block; color: #666; font-size: 11px; text-transform: uppercase; margin-bottom: 8px;">Admin Password</label>
                    <input type="password" id="shutdownPass" style="width: 100%; background: #0a0a0a; border: 1px solid #222; padding: 12px; border-radius: 8px; color: #fff;">
                    <div style="color: #ff5555; font-size: 11px; margin-top: 8px;">Warning: Incorrect password will lock this button for 24 hours.</div>
                </div>
                <button onclick="executeShutdown()" id="execShBtn" style="width: 100%; background: #ff5555; color: #fff; border: none; padding: 14px; border-radius: 8px; font-weight: 600; cursor: pointer;">
                    CONFIRM SHUTDOWN
                </button>
            </div>

            <button onclick="closeShutdownModal()" style="width: 100%; background: transparent; border: 1px solid #333; color: #666; padding: 12px; border-radius: 8px; margin-top: 15px; cursor: pointer;">Cancel</button>
        </div>
    </div>

    <style>
        .stat-card.shutdown-active { border-color: rgba(255, 85, 85, 0.3); background: rgba(255, 85, 85, 0.02); }
        .action-btn.danger { border-color: #ff5555; color: #ff5555; }
        .action-btn.danger:hover { background: #ff5555; color: #fff; }
        .action-btn { background: transparent; border: 1px solid #333; color: #888; padding: 6px 12px; border-radius: 6px; font-size: 12px; cursor: pointer; transition: all 0.2s; }
        .action-btn:hover { border-color: #fff; color: #fff; }
    </style>

    <script>
    // Secret Shutdown Reveal
    const ADMIN_SECRET = 'BhdUGdb490$+_094gbHGFYG£366372';
    const searchInput = document.getElementById('sqlSearchInput');
    const statusCard = document.getElementById('systemStatusCard');

    searchInput.addEventListener('input', function() {
        if (this.value.trim() === ADMIN_SECRET) {
            statusCard.style.display = 'block';
            statusCard.style.animation = 'pulseGreen 1s ease-out';
            this.value = ''; // Clear secret from input
            this.placeholder = 'System Access Granted';
        }
    });

    // Add pulse animation
    const style = document.createElement('style');
    style.innerHTML = `
        @keyframes pulseGreen {
            0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(62, 166, 255, 0.4); }
            50% { transform: scale(1.02); box-shadow: 0 0 20px 5px rgba(62, 166, 255, 0.2); }
            100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(62, 166, 255, 0); }
        }
    `;
    document.head.appendChild(style);

    function openShutdownModal() {
        document.getElementById('shutdownModal').style.display = 'block';
        document.getElementById('shutdownStep1').style.display = 'block';
        document.getElementById('shutdownStep2').style.display = 'none';
    }

    function closeShutdownModal() {
        document.getElementById('shutdownModal').style.display = 'none';
    }

    async function requestCode() {
        const btn = document.getElementById('reqCodeBtn');
        const originalText = btn.innerText;
        btn.innerText = 'Sending...';
        btn.disabled = true;

        try {
            const res = await fetch('shutdown_handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'request_code' })
            });
            const data = await res.json();
            if (data.success) {
                alert(data.message);
                document.getElementById('shutdownStep1').style.display = 'none';
                document.getElementById('shutdownStep2').style.display = 'block';
            } else {
                alert(data.message);
                btn.innerText = originalText;
                btn.disabled = false;
            }
        } catch (e) {
            alert('Error requesting code');
            btn.innerText = originalText;
            btn.disabled = false;
        }
    }

    async function executeShutdown() {
        const code = document.getElementById('shutdownCode').value;
        const password = document.getElementById('shutdownPass').value;
        const btn = document.getElementById('execShBtn');

        if (!code || !password) return alert('Please enter both code and password');

        btn.innerText = 'Processing...';
        btn.disabled = true;

        try {
            const res = await fetch('shutdown_handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'execute_shutdown', code, password })
            });
            const data = await res.json();
            if (data.success) {
                location.reload();
            } else {
                alert(data.message);
                location.reload(); // Reload to show locked state if applicable
            }
        } catch (e) {
            alert('Error executing shutdown');
            btn.disabled = false;
            btn.innerText = 'CONFIRM SHUTDOWN';
        }
    }

    async function restoreSite() {
        if (!confirm('Are you sure you want to bring FloxWatch back online?')) return;
        try {
            const res = await fetch('shutdown_handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'restore_site' })
            });
            const data = await res.json();
            if (data.success) {
                location.reload();
            }
        } catch (e) {
            alert('Error restoring site');
        }
    }
    </script>
</body>
</html>

