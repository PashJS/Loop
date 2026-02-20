<?php
session_start();

if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
    header('Location: index.php');
    exit;
}

require_once '../backend/config.php';

// Ensure tables exist
try {
    $pdo->query("SELECT 1 FROM announcements LIMIT 1");
} catch (Exception $e) {
    $pdo->exec("CREATE TABLE announcements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        context TEXT NOT NULL,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE announcement_options (
        id INT AUTO_INCREMENT PRIMARY KEY,
        announcement_id INT NOT NULL,
        label VARCHAR(255) NOT NULL,
        FOREIGN KEY (announcement_id) REFERENCES announcements(id) ON DELETE CASCADE
    )");
    $pdo->exec("CREATE TABLE announcement_votes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        announcement_id INT NOT NULL,
        option_id INT NOT NULL,
        user_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_vote (announcement_id, user_id),
        FOREIGN KEY (announcement_id) REFERENCES announcements(id) ON DELETE CASCADE,
        FOREIGN KEY (option_id) REFERENCES announcement_options(id) ON DELETE CASCADE
    )");
}

// Handle create announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_announcement'])) {
    $context = trim($_POST['context'] ?? '');
    $options = $_POST['options'] ?? [];
    $options = array_filter($options, fn($o) => trim($o) !== '');
    
    if (!empty($context) && count($options) >= 2) {
        $stmt = $pdo->prepare("INSERT INTO announcements (context) VALUES (?)");
        $stmt->execute([$context]);
        $announcementId = $pdo->lastInsertId();
        
        $optStmt = $pdo->prepare("INSERT INTO announcement_options (announcement_id, label) VALUES (?, ?)");
        foreach ($options as $opt) {
            $optStmt->execute([$announcementId, trim($opt)]);
        }
    }
    header('Location: admin_announcements.php');
    exit;
}

// Handle delete
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $pdo->prepare("DELETE FROM announcements WHERE id = ?")->execute([(int)$_GET['id']]);
    header('Location: admin_announcements.php');
    exit;
}

// Handle toggle active
if (isset($_GET['action']) && $_GET['action'] === 'toggle' && isset($_GET['id'])) {
    $pdo->prepare("UPDATE announcements SET is_active = NOT is_active WHERE id = ?")->execute([(int)$_GET['id']]);
    header('Location: admin_announcements.php');
    exit;
}

// Fetch announcements with results
$announcements = $pdo->query("SELECT * FROM announcements ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

foreach ($announcements as &$ann) {
    // Get options
    $optStmt = $pdo->prepare("SELECT * FROM announcement_options WHERE announcement_id = ?");
    $optStmt->execute([$ann['id']]);
    $ann['options'] = $optStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get vote counts
    $totalVotes = $pdo->prepare("SELECT COUNT(*) FROM announcement_votes WHERE announcement_id = ?");
    $totalVotes->execute([$ann['id']]);
    $ann['total_votes'] = (int)$totalVotes->fetchColumn();
    
    foreach ($ann['options'] as &$opt) {
        $voteCount = $pdo->prepare("SELECT COUNT(*) FROM announcement_votes WHERE option_id = ?");
        $voteCount->execute([$opt['id']]);
        $opt['votes'] = (int)$voteCount->fetchColumn();
        $opt['percentage'] = $ann['total_votes'] > 0 ? round(($opt['votes'] / $ann['total_votes']) * 100) : 0;
    }
}
unset($ann, $opt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements - Admin Panel</title>
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
            text-decoration: none;
        }
        .logout-btn:hover { border-color: #555; color: #fff; }
        .admin-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        .nav-tabs { display: flex; gap: 10px; margin-bottom: 30px; }
        .nav-tab {
            background: transparent;
            border: 1px solid #222;
            color: #888;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 13px;
            text-decoration: none;
        }
        .nav-tab:hover, .nav-tab.active { background: #1a1a1a; border-color: #333; color: #fff; }
        .section {
            background: #111;
            border: 1px solid #222;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
        }
        .section h2 {
            font-size: 16px;
            font-weight: 500;
            margin-bottom: 20px;
            color: #fff;
        }
        label {
            display: block;
            font-size: 12px;
            color: #666;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        input, textarea {
            width: 100%;
            background: #0a0a0a;
            border: 1px solid #333;
            padding: 12px 16px;
            border-radius: 8px;
            color: #fff;
            font-size: 14px;
            margin-bottom: 15px;
        }
        input:focus, textarea:focus { outline: none; border-color: #3ea6ff; }
        textarea { min-height: 100px; resize: vertical; }
        .options-container { margin-bottom: 15px; }
        .option-row {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
        }
        .option-row input { margin-bottom: 0; flex: 1; }
        .add-option-btn, .remove-option-btn {
            background: #1a1a1a;
            border: 1px solid #333;
            color: #888;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 13px;
            cursor: pointer;
        }
        .add-option-btn:hover { border-color: #3ea6ff; color: #3ea6ff; }
        .remove-option-btn { padding: 8px 12px; }
        .remove-option-btn:hover { border-color: #ff4444; color: #ff4444; }
        .submit-btn {
            background: linear-gradient(135deg, #3ea6ff, #0080ff);
            border: none;
            color: #fff;
            padding: 14px 28px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }
        .submit-btn:hover { opacity: 0.9; }
        .announcement-card {
            background: #0a0a0a;
            border: 1px solid #222;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
        }
        .announcement-card.inactive { opacity: 0.5; }
        .announcement-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        .announcement-context {
            font-size: 16px;
            color: #fff;
            flex: 1;
        }
        .announcement-meta {
            font-size: 12px;
            color: #555;
            margin-top: 5px;
        }
        .announcement-actions {
            display: flex;
            gap: 10px;
        }
        .action-btn {
            background: transparent;
            border: 1px solid #333;
            color: #888;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            text-decoration: none;
        }
        .action-btn:hover { border-color: #555; color: #fff; }
        .action-btn.danger:hover { border-color: #ff4444; color: #ff4444; }
        .results-container { margin-top: 15px; }
        .result-row {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        .result-label {
            width: 150px;
            font-size: 13px;
            color: #ccc;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .result-bar-container {
            flex: 1;
            background: #1a1a1a;
            height: 24px;
            border-radius: 4px;
            margin: 0 15px;
            overflow: hidden;
        }
        .result-bar {
            height: 100%;
            background: linear-gradient(90deg, #3ea6ff, #0080ff);
            transition: width 0.3s ease;
        }
        .result-percent {
            width: 50px;
            text-align: right;
            font-size: 14px;
            font-weight: 600;
            color: #3ea6ff;
        }
        .total-votes {
            font-size: 12px;
            color: #555;
            margin-top: 10px;
        }
        .badge-active {
            background: rgba(62, 166, 255, 0.15);
            color: #3ea6ff;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        .badge-inactive {
            background: rgba(100, 100, 100, 0.2);
            color: #888;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
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
            <a href="admin_users.php" class="nav-tab">Users</a>
            <a href="admin_videos.php" class="nav-tab">Videos</a>
            <a href="admin_announcements.php" class="nav-tab active">Announcements</a>
            <a href="admin_activity.php" class="nav-tab">Activity</a>
        </div>

        <!-- Create New Announcement -->
        <div class="section">
            <h2><i class="fa-solid fa-bullhorn"></i> Create Poll / Announcement</h2>
            <form method="POST">
                <input type="hidden" name="create_announcement" value="1">
                
                <label>Context / Question</label>
                <textarea name="context" placeholder="What would you like to ask users?"></textarea>
                
                <label>Options</label>
                <div class="options-container" id="optionsContainer">
                    <div class="option-row">
                        <input type="text" name="options[]" placeholder="Option 1">
                        <button type="button" class="remove-option-btn" onclick="removeOption(this)"><i class="fa-solid fa-times"></i></button>
                    </div>
                    <div class="option-row">
                        <input type="text" name="options[]" placeholder="Option 2">
                        <button type="button" class="remove-option-btn" onclick="removeOption(this)"><i class="fa-solid fa-times"></i></button>
                    </div>
                </div>
                <button type="button" class="add-option-btn" onclick="addOption()"><i class="fa-solid fa-plus"></i> Add Option</button>
                
                <div style="margin-top: 20px;">
                    <button type="submit" class="submit-btn"><i class="fa-solid fa-paper-plane"></i> Publish Poll</button>
                </div>
            </form>
        </div>

        <!-- Existing Announcements -->
        <div class="section">
            <h2><i class="fa-solid fa-chart-bar"></i> Poll Results</h2>
            
            <?php if (empty($announcements)): ?>
                <p style="color: #555; text-align: center; padding: 30px;">No announcements yet. Create one above!</p>
            <?php else: ?>
                <?php foreach ($announcements as $ann): ?>
                <div class="announcement-card <?php echo $ann['is_active'] ? '' : 'inactive'; ?>">
                    <div class="announcement-header">
                        <div>
                            <div class="announcement-context"><?php echo htmlspecialchars($ann['context']); ?></div>
                            <div class="announcement-meta">
                                Created: <?php echo date('M j, Y g:i A', strtotime($ann['created_at'])); ?>
                                &nbsp;•&nbsp;
                                <?php if ($ann['is_active']): ?>
                                    <span class="badge-active">ACTIVE</span>
                                <?php else: ?>
                                    <span class="badge-inactive">INACTIVE</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="announcement-actions">
                            <a href="?action=toggle&id=<?php echo $ann['id']; ?>" class="action-btn">
                                <?php echo $ann['is_active'] ? 'Deactivate' : 'Activate'; ?>
                            </a>
                            <a href="?action=delete&id=<?php echo $ann['id']; ?>" class="action-btn danger" onclick="return confirm('Delete this poll?')">Delete</a>
                        </div>
                    </div>
                    
                    <div class="results-container">
                        <?php foreach ($ann['options'] as $opt): ?>
                        <div class="result-row">
                            <div class="result-label" title="<?php echo htmlspecialchars($opt['label']); ?>"><?php echo htmlspecialchars($opt['label']); ?></div>
                            <div class="result-bar-container">
                                <div class="result-bar" style="width: <?php echo $opt['percentage']; ?>%"></div>
                            </div>
                            <div class="result-percent"><?php echo $opt['percentage']; ?>%</div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="total-votes">
                        <i class="fa-solid fa-users"></i> <?php echo $ann['total_votes']; ?> total vote(s)
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        let optionCount = 2;
        
        function addOption() {
            optionCount++;
            const container = document.getElementById('optionsContainer');
            const row = document.createElement('div');
            row.className = 'option-row';
            row.innerHTML = `
                <input type="text" name="options[]" placeholder="Option ${optionCount}">
                <button type="button" class="remove-option-btn" onclick="removeOption(this)"><i class="fa-solid fa-times"></i></button>
            `;
            container.appendChild(row);
        }
        
        function removeOption(btn) {
            const rows = document.querySelectorAll('.option-row');
            if (rows.length > 2) {
                btn.parentElement.remove();
            }
        }
    </script>
</body>
</html>
