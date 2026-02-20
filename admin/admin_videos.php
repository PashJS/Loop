<?php
session_start();

if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
    header('Location: index.php');
    exit;
}

require_once '../backend/config.php';

// Handle delete
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $pdo->prepare("DELETE FROM videos WHERE id = ?")->execute([$id]);
    header('Location: admin_videos.php');
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
        $countSql = "SELECT COUNT(*) FROM videos v LEFT JOIN users u ON v.user_id = u.id WHERE $searchQuery";
        $totalVideos = $pdo->query($countSql)->fetchColumn();
        $totalPages = ceil($totalVideos / $perPage);
        
        $sql = "SELECT v.*, u.username FROM videos v LEFT JOIN users u ON v.user_id = u.id WHERE $searchQuery ORDER BY v.created_at DESC LIMIT $perPage OFFSET $offset";
        $videos = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $totalVideos = $pdo->query("SELECT COUNT(*) FROM videos")->fetchColumn();
        $totalPages = ceil($totalVideos / $perPage);
        
        $stmt = $pdo->prepare("SELECT v.*, u.username FROM videos v LEFT JOIN users u ON v.user_id = u.id ORDER BY v.created_at DESC LIMIT :limit OFFSET :offset");
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $searchError = $e->getMessage();
    $videos = [];
    $totalVideos = 0;
    $totalPages = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Videos - Admin Panel</title>
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
        .video-thumb {
            width: 80px;
            height: 45px;
            object-fit: cover;
            border-radius: 4px;
            background: #222;
        }
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
        .title-cell {
            max-width: 250px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
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
            <a href="admin_users.php" class="nav-tab">Users</a>
            <a href="admin_videos.php" class="nav-tab active">Videos</a>
            <a href="admin_announcements.php" class="nav-tab">Announcements</a>
            <a href="admin_activity.php" class="nav-tab">Activity</a>
        </div>

        <!-- SQL Search Bar -->
        <div style="margin-bottom: 20px;">
            <form method="GET" style="display: flex; gap: 10px; align-items: flex-start; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 300px;">
                    <div style="display: flex; gap: 10px;">
                        <input type="text" name="q" value="<?php echo htmlspecialchars($searchQuery); ?>" 
                               placeholder="Enter SQL WHERE clause (use v. for videos, u. for users)..." 
                               style="flex: 1; background: #0a0a0a; border: 1px solid #333; padding: 12px 16px; border-radius: 8px; color: #fff; font-family: monospace; font-size: 13px;">
                        <button type="submit" style="background: #3ea6ff; border: none; padding: 12px 24px; border-radius: 8px; color: #000; font-weight: 600; cursor: pointer;">Search</button>
                        <?php if ($searchMode): ?>
                            <a href="admin_videos.php" style="background: #333; padding: 12px 16px; border-radius: 8px; color: #888; text-decoration: none;">Clear</a>
                        <?php endif; ?>
                    </div>
                    <div style="margin-top: 8px; font-size: 11px; color: #555;">
                        <strong>Examples:</strong>
                        <code style="background: #1a1a1a; padding: 2px 6px; border-radius: 4px; margin-left: 5px; cursor: pointer;" onclick="document.querySelector('input[name=q]').value = this.textContent">v.views > 100</code>
                        <code style="background: #1a1a1a; padding: 2px 6px; border-radius: 4px; margin-left: 5px; cursor: pointer;" onclick="document.querySelector('input[name=q]').value = this.textContent">v.title LIKE '%test%'</code>
                        <code style="background: #1a1a1a; padding: 2px 6px; border-radius: 4px; margin-left: 5px; cursor: pointer;" onclick="document.querySelector('input[name=q]').value = this.textContent">u.username = 'floxteam'</code>
                        <code style="background: #1a1a1a; padding: 2px 6px; border-radius: 4px; margin-left: 5px; cursor: pointer;" onclick="document.querySelector('input[name=q]').value = this.textContent">v.status = 'published'</code>
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
                    Found <strong style="color: #3ea6ff;"><?php echo $totalVideos; ?></strong> video(s) matching: <code style="background: #1a1a1a; padding: 2px 8px; border-radius: 4px;"><?php echo htmlspecialchars($searchQuery); ?></code>
                </div>
            <?php endif; ?>
        </div>

        <div class="section">
            <table>
                <thead>
                    <tr>
                        <th>Thumb</th>
                        <th>Title</th>
                        <th>Uploader</th>
                        <th>Views</th>
                        <th>Uploaded</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($videos as $video): ?>
                    <tr>
                        <td>
                            <?php if (!empty($video['thumbnail'])): ?>
                                <img src="../<?php echo htmlspecialchars($video['thumbnail']); ?>" class="video-thumb" alt="">
                            <?php else: ?>
                                <div class="video-thumb"></div>
                            <?php endif; ?>
                        </td>
                        <td class="title-cell"><?php echo htmlspecialchars($video['title']); ?></td>
                        <td><?php echo htmlspecialchars($video['username'] ?? 'Unknown'); ?></td>
                        <td><?php echo number_format($video['views'] ?? 0); ?></td>
                        <td><?php echo date('M j, Y', strtotime($video['created_at'])); ?></td>
                        <td>
                            <a href="../frontend/videoid.php?v=<?php echo $video['id']; ?>" class="action-btn" target="_blank">View</a>
                            <a href="?action=delete&id=<?php echo $video['id']; ?>" class="action-btn danger" onclick="return confirm('Delete this video?')">Delete</a>
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
</body>
</html>
