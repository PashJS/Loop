<?php
session_start();

if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
    header('Location: index.php');
    exit;
}

require_once '../backend/config.php';

// Ensure ban columns exist
try {
    $pdo->query("SELECT banned_until FROM users LIMIT 1");
} catch (Exception $e) {
    $pdo->exec("ALTER TABLE users ADD COLUMN banned_until DATETIME NULL DEFAULT NULL");
    $pdo->exec("ALTER TABLE users ADD COLUMN ban_reason VARCHAR(255) NULL DEFAULT NULL");
    $pdo->exec("ALTER TABLE users ADD COLUMN ban_note TEXT NULL DEFAULT NULL");
}

// Ensure comment ban column exists
try {
    $pdo->query("SELECT comment_banned_until FROM users LIMIT 1");
} catch (Exception $e) {
    $pdo->exec("ALTER TABLE users ADD COLUMN comment_banned_until DATETIME NULL DEFAULT NULL");
}

// Handle POST ban action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ban_user_id']) && !empty($_POST['ban_user_id'])) {
    $id = (int)$_POST['ban_user_id'];
    $hours = max(1, (int)($_POST['ban_hours'] ?? 24));
    $reason = trim($_POST['ban_reason'] ?? '');
    $proof = trim($_POST['ban_proof'] ?? '');
    $note = trim($_POST['ban_note'] ?? '');
    
    // Calculate ban end time based on hours
    $banUntil = date('Y-m-d H:i:s', strtotime("+$hours hours"));
    
    // Ensure proof column exists
    try { $pdo->query("SELECT ban_proof FROM users LIMIT 1"); } 
    catch (Exception $e) { $pdo->exec("ALTER TABLE users ADD COLUMN ban_proof TEXT NULL DEFAULT NULL"); }
    
    try {
        $stmt = $pdo->prepare("UPDATE users SET banned_until = ?, ban_reason = ?, ban_proof = ?, ban_note = ? WHERE id = ?");
        $result = $stmt->execute([$banUntil, $reason, $proof, $note, $id]);
    } catch (PDOException $e) {
        die("Database error: " . $e->getMessage());
    }
    
    header('Location: admin_users.php');
    exit;
}

// Handle POST comment ban action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment_ban_user_id']) && !empty($_POST['comment_ban_user_id'])) {
    $id = (int)$_POST['comment_ban_user_id'];
    $hours = max(1, (int)($_POST['comment_ban_hours'] ?? 24));
    
    $banUntil = date('Y-m-d H:i:s', strtotime("+$hours hours"));
    
    $stmt = $pdo->prepare("UPDATE users SET comment_banned_until = ? WHERE id = ?");
    $stmt->execute([$banUntil, $id]);
    
    header('Location: admin_users.php');
    exit;
}

// Handle GET actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $action = $_GET['action'];
    
    if ($action === 'delete') {
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
    } elseif ($action === 'toggle_pro') {
        $pdo->prepare("UPDATE users SET is_pro = NOT is_pro WHERE id = ?")->execute([$id]);
    } elseif ($action === 'unban') {
        $pdo->prepare("UPDATE users SET banned_until = NULL, ban_reason = NULL, ban_note = NULL WHERE id = ?")->execute([$id]);
    } elseif ($action === 'unban_comments') {
        $pdo->prepare("UPDATE users SET comment_banned_until = NULL WHERE id = ?")->execute([$id]);
    }
    
    header('Location: admin_users.php');
    exit;
}

// SQL Search Query
$searchQuery = isset($_GET['q']) ? trim($_GET['q']) : '';
$searchError = '';
$searchMode = !empty($searchQuery);

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

try {
    if ($searchMode) {
        // User entered a WHERE clause - execute it
        // Security: This is admin-only, so we allow raw SQL for power users
        $countSql = "SELECT COUNT(*) FROM users WHERE $searchQuery";
        $totalUsers = $pdo->query($countSql)->fetchColumn();
        $totalPages = ceil($totalUsers / $perPage);
        
        $sql = "SELECT * FROM users WHERE $searchQuery ORDER BY created_at DESC LIMIT $perPage OFFSET $offset";
        $stmt = $pdo->query($sql);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Default: show all users
        $totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $totalPages = ceil($totalUsers / $perPage);
        
        $stmt = $pdo->prepare("SELECT * FROM users ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $searchError = $e->getMessage();
    $users = [];
    $totalUsers = 0;
    $totalPages = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users - Admin Panel</title>
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
        .nav-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
        }
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
        .nav-tab:hover, .nav-tab.active {
            background: #1a1a1a;
            border-color: #333;
            color: #fff;
        }
        .section {
            background: #111;
            border: 1px solid #222;
            border-radius: 12px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 15px 20px;
            text-align: left;
            border-bottom: 1px solid #1a1a1a;
        }
        th {
            color: #666;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 500;
        }
        td { color: #ccc; font-size: 14px; }
        tr:hover { background: rgba(255,255,255,0.02); }
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        .badge-pro { background: rgba(255, 215, 0, 0.15); color: #ffd700; }
        .badge-free { background: rgba(100, 100, 100, 0.2); color: #888; }
        .action-btn {
            background: transparent;
            border: 1px solid #333;
            color: #888;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            cursor: pointer;
            text-decoration: none;
            margin-right: 5px;
        }
        .action-btn:hover { border-color: #555; color: #fff; }
        .action-btn.danger { border-color: #ff4444; color: #ff4444; }
        .action-btn.danger:hover { background: #ff4444; color: #fff; }
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 30px;
        }
        .pagination a {
            background: #111;
            border: 1px solid #222;
            color: #888;
            padding: 10px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 13px;
        }
        .pagination a:hover, .pagination a.active {
            background: #1a1a1a;
            border-color: #333;
            color: #fff;
        }
        .badge-banned { background: rgba(255, 68, 68, 0.15); color: #ff4444; }
        .badge-muted { background: rgba(150, 100, 255, 0.15); color: #a080ff; margin-left: 5px; }
        .action-btn.warning { border-color: #ff8800; color: #ff8800; }
        .action-btn.warning:hover { background: #ff8800; color: #fff; }
        .action-btn.mute { border-color: #a080ff; color: #a080ff; }
        .action-btn.mute:hover { background: #a080ff; color: #fff; }
        
        /* Ban Modal */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }
        .modal {
            background: #111;
            border: 1px solid #222;
            border-radius: 12px;
            padding: 30px;
            width: 100%;
            max-width: 400px;
        }
        .modal h3 {
            color: #fff;
            font-size: 18px;
            margin-bottom: 20px;
        }
        .modal label {
            display: block;
            color: #888;
            font-size: 12px;
            margin-bottom: 6px;
            text-transform: uppercase;
        }
        .modal input, .modal select, .modal textarea {
            width: 100%;
            background: #0a0a0a;
            border: 1px solid #222;
            border-radius: 6px;
            padding: 10px;
            color: #fff;
            font-size: 14px;
            margin-bottom: 15px;
        }
        .modal textarea { min-height: 80px; resize: vertical; }
        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 10px;
        }
        .modal-actions button {
            padding: 10px 20px;
            border-radius: 6px;
            font-size: 13px;
            cursor: pointer;
        }
        .btn-cancel {
            background: transparent;
            border: 1px solid #333;
            color: #888;
        }
        .btn-ban {
            background: #ff4444;
            border: none;
            color: #fff;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="admin-header">
        <h1><i class="fa-solid fa-shield-halved"></i> Admin Panel</h1>
        <a href="?logout=1" class="logout-btn">Logout</a>
    </div>

    <div class="admin-container">
        <div class="nav-tabs">
            <a href="admin_panel.php" class="nav-tab">Dashboard</a>
            <a href="admin_users.php" class="nav-tab active">Users</a>
            <a href="admin_videos.php" class="nav-tab">Videos</a>
            <a href="admin_announcements.php" class="nav-tab">Announcements</a>
            <a href="admin_activity.php" class="nav-tab">Activity</a>
        </div>

        <!-- SQL Search Bar -->
        <div class="search-section" style="margin-bottom: 20px;">
            <form method="GET" style="display: flex; gap: 10px; align-items: flex-start; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 300px;">
                    <div style="display: flex; gap: 10px;">
                        <input type="text" name="q" value="<?php echo htmlspecialchars($searchQuery); ?>" 
                               placeholder="Enter SQL WHERE clause..." 
                               style="flex: 1; background: #0a0a0a; border: 1px solid #333; padding: 12px 16px; border-radius: 8px; color: #fff; font-family: monospace; font-size: 13px;">
                        <button type="submit" style="background: #3ea6ff; border: none; padding: 12px 24px; border-radius: 8px; color: #000; font-weight: 600; cursor: pointer;">Search</button>
                        <?php if ($searchMode): ?>
                            <a href="admin_users.php" style="background: #333; border: none; padding: 12px 16px; border-radius: 8px; color: #888; text-decoration: none;">Clear</a>
                        <?php endif; ?>
                    </div>
                    <div style="margin-top: 8px; font-size: 11px; color: #555;">
                        <strong>Examples:</strong>
                        <code style="background: #1a1a1a; padding: 2px 6px; border-radius: 4px; margin-left: 5px; cursor: pointer;" onclick="document.querySelector('input[name=q]').value = this.textContent">is_pro = 1</code>
                        <code style="background: #1a1a1a; padding: 2px 6px; border-radius: 4px; margin-left: 5px; cursor: pointer;" onclick="document.querySelector('input[name=q]').value = this.textContent">username LIKE '%test%'</code>
                        <code style="background: #1a1a1a; padding: 2px 6px; border-radius: 4px; margin-left: 5px; cursor: pointer;" onclick="document.querySelector('input[name=q]').value = this.textContent">banned_until IS NOT NULL</code>
                        <code style="background: #1a1a1a; padding: 2px 6px; border-radius: 4px; margin-left: 5px; cursor: pointer;" onclick="document.querySelector('input[name=q]').value = this.textContent">id > 10</code>
                        <code style="background: #1a1a1a; padding: 2px 6px; border-radius: 4px; margin-left: 5px; cursor: pointer;" onclick="document.querySelector('input[name=q]').value = this.textContent">email LIKE '%gmail%' AND is_pro = 0</code>
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
                    Found <strong style="color: #3ea6ff;"><?php echo $totalUsers; ?></strong> user(s) matching: <code style="background: #1a1a1a; padding: 2px 8px; border-radius: 4px;"><?php echo htmlspecialchars($searchQuery); ?></code>
                </div>
            <?php endif; ?>
        </div>

        <div class="section">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <?php 
                        $isBanned = !empty($user['banned_until']) && strtotime($user['banned_until']) > time();
                        $isCommentBanned = !empty($user['comment_banned_until']) && strtotime($user['comment_banned_until']) > time();
                    ?>
                    <tr>
                        <td><?php echo $user['id']; ?></td>
                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td>
                            <?php if ($isBanned): ?>
                                <span class="badge badge-banned" title="<?php echo htmlspecialchars($user['ban_reason'] ?? ''); ?>">BANNED</span>
                            <?php elseif ($user['is_pro']): ?>
                                <span class="badge badge-pro">PRO</span>
                            <?php else: ?>
                                <span class="badge badge-free">Free</span>
                            <?php endif; ?>
                            <?php if ($isCommentBanned): ?>
                                <span class="badge badge-muted" title="Cannot comment until <?php echo $user['comment_banned_until']; ?>">MUTED</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                        <td>
                            <a href="?action=toggle_pro&id=<?php echo $user['id']; ?>" class="action-btn">
                                <?php echo $user['is_pro'] ? 'Remove Pro' : 'Give Pro'; ?>
                            </a>
                            <?php if ($isBanned): ?>
                                <a href="?action=unban&id=<?php echo $user['id']; ?>" class="action-btn" onclick="return confirm('Unban this user?')">Unban</a>
                            <?php else: ?>
                                <button class="action-btn warning" onclick="openBanModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username'], ENT_QUOTES); ?>')">Ban</button>
                            <?php endif; ?>
                            <?php if ($isCommentBanned): ?>
                                <a href="?action=unban_comments&id=<?php echo $user['id']; ?>" class="action-btn" onclick="return confirm('Allow comments again?')">Unmute</a>
                            <?php else: ?>
                                <button class="action-btn mute" onclick="openCommentBanModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username'], ENT_QUOTES); ?>')">Mute</button>
                            <?php endif; ?>
                            <a href="?action=delete&id=<?php echo $user['id']; ?>" class="action-btn danger" onclick="return confirm('Delete this user?')">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?page=<?php echo $i; ?>" class="<?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Ban Modal -->
    <div class="modal-overlay" id="banModal">
        <div class="modal">
            <h3>Ban User: <span id="banUsername"></span></h3>
            <form method="POST" action="admin_users.php">
                <input type="hidden" name="ban_user_id" id="banUserId" value="">
                
                <label>Duration (hours)</label>
                <input type="number" name="ban_hours" value="24" min="1" placeholder="Enter hours (e.g. 24, 72, 168)">
                <small style="color: #555; display: block; margin: -10px 0 15px 0;">Common: 24h = 1 day, 168h = 1 week, 720h = 1 month, 8760h = 1 year</small>
                
                <label>Reason (visible to user)</label>
                <input type="text" name="ban_reason" placeholder="e.g. Spam, Harassment, Inappropriate Content">
                
                <label>Proof (visible to user)</label>
                <textarea name="ban_proof" placeholder="Evidence of violation: video links, comments, screenshots URLs, etc."></textarea>
                
                <label>Note (admin only)</label>
                <textarea name="ban_note" placeholder="Internal notes about this ban..."></textarea>
                
                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="closeBanModal()">Cancel</button>
                    <button type="submit" class="btn-ban">Ban User</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Comment Ban Modal -->
    <div class="modal-overlay" id="commentBanModal">
        <div class="modal">
            <h3>Mute User: <span id="commentBanUsername"></span></h3>
            <p style="color: #888; font-size: 13px; margin-bottom: 20px;">This will prevent the user from posting comments for the specified duration.</p>
            <form method="POST" action="admin_users.php">
                <input type="hidden" name="comment_ban_user_id" id="commentBanUserId" value="">
                
                <label>Duration (hours)</label>
                <input type="number" name="comment_ban_hours" value="24" min="1" placeholder="Enter hours">
                <small style="color: #555; display: block; margin: -10px 0 15px 0;">Common: 24h = 1 day, 168h = 1 week, 720h = 1 month</small>
                
                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="closeCommentBanModal()">Cancel</button>
                    <button type="submit" class="btn-ban" style="background: #a080ff;">Mute User</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openBanModal(userId, username) {
            document.getElementById('banUserId').value = userId;
            document.getElementById('banUsername').textContent = username;
            document.getElementById('banModal').style.display = 'flex';
        }

        function closeBanModal() {
            document.getElementById('banModal').style.display = 'none';
        }

        function openCommentBanModal(userId, username) {
            document.getElementById('commentBanUserId').value = userId;
            document.getElementById('commentBanUsername').textContent = username;
            document.getElementById('commentBanModal').style.display = 'flex';
        }

        function closeCommentBanModal() {
            document.getElementById('commentBanModal').style.display = 'none';
        }

        document.getElementById('banModal').addEventListener('click', function(e) {
            if (e.target === this) closeBanModal();
        });

        document.getElementById('commentBanModal').addEventListener('click', function(e) {
            if (e.target === this) closeCommentBanModal();
        });
    </script>
</body>
</html>

