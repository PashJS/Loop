<?php
session_start();

if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
    header('Location: index.php');
    exit;
}

require_once '../backend/config.php';

// Prepare historical data for charts (last 7 days)
function getChartData($pdo) {
    $data = [
        'labels' => [],
        'logins' => [],
        'comments' => [],
        'videos' => []
    ];

    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $data['labels'][] = date('M j', strtotime($date));

        // Logins
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_activity WHERE DATE(login_time) = ?");
        $stmt->execute([$date]);
        $data['logins'][] = (int)$stmt->fetchColumn();

        // Comments
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM comments WHERE DATE(created_at) = ?");
        $stmt->execute([$date]);
        $data['comments'][] = (int)$stmt->fetchColumn();

        // Videos
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM videos WHERE DATE(created_at) = ?");
        $stmt->execute([$date]);
        $data['videos'][] = (int)$stmt->fetchColumn();
    }

    return $data;
}

try {
    $chartData = getChartData($pdo);

    // Calculate Extended Metrics
    $today = date('Y-m-d');
    $thisMonth = date('Y-m');

    $dauToday = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM login_activity WHERE DATE(login_time) = '$today'")->fetchColumn();
    $loginsToday = $pdo->query("SELECT COUNT(*) FROM login_activity WHERE DATE(login_time) = '$today'")->fetchColumn();
    $videosToday = $pdo->query("SELECT COUNT(*) FROM videos WHERE DATE(created_at) = '$today'")->fetchColumn();
    $videosThisMonth = $pdo->query("SELECT COUNT(*) FROM videos WHERE created_at LIKE '$thisMonth%'")->fetchColumn();

    // Fetch recent real-time activity
    $activities = $pdo->query("
        (SELECT 'login' as type, u.username, la.login_time as timestamp, la.ip_address as detail
         FROM login_activity la 
         JOIN users u ON la.user_id = u.id)
        UNION ALL
        (SELECT 'comment' as type, u.username, c.created_at as timestamp, c.comment as detail
         FROM comments c 
         JOIN users u ON c.user_id = u.id)
        UNION ALL
        (SELECT 'video' as type, u.username, v.created_at as timestamp, v.title as detail
         FROM videos v 
         JOIN users u ON v.user_id = u.id)
        ORDER BY timestamp DESC LIMIT 30
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("<div style='background:#111;color:#ff5555;padding:20px;margin:20px;border-radius:12px;border:1px solid #333;'>
            <h3>Database Error</h3>
            <p>".$e->getMessage()."</p>
         </div>");
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity - Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        
        .grid-activity {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }

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
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Chart Canvas Size */
        .chart-container {
            position: relative;
            height: 350px;
            width: 100%;
        }

        /* Live Feed Styles */
        .feed-container {
            max-height: 800px;
            overflow-y: auto;
            padding-right: 10px;
        }
        .feed-container::-webkit-scrollbar { width: 5px; }
        .feed-container::-webkit-scrollbar-thumb { background: #333; border-radius: 10px; }

        .feed-item {
            display: flex;
            gap: 15px;
            padding: 15px;
            border-bottom: 1px solid #1a1a1a;
            animation: slideIn 0.3s ease-out;
        }
        @keyframes slideIn {
            from { opacity: 0; transform: translateX(20px); }
            to { opacity: 1; transform: translateX(0); }
        }

        .feed-icon {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            flex-shrink: 0;
        }
        .icon-login { background: rgba(62, 166, 255, 0.15); color: #3ea6ff; }
        .icon-comment { background: rgba(255, 170, 0, 0.15); color: #ffaa00; }
        .icon-video { background: rgba(255, 85, 85, 0.15); color: #ff5555; }

        .feed-content { flex: 1; }
        .feed-user { font-weight: 600; font-size: 14px; color: #fff; }
        .feed-text { font-size: 13px; color: #888; margin-top: 4px; line-height: 1.4; }
        .feed-time { font-size: 11px; color: #444; margin-top: 6px; }

        @media (max-width: 1024px) {
            .grid-activity { grid-template-columns: 1fr; }
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
            <a href="admin_announcements.php" class="nav-tab">Announcements</a>
            <a href="admin_activity.php" class="nav-tab active">Activity</a>
        </div>

        <div class="grid-activity">
            <!-- Charts Column -->
            <div class="main-stats">
                <div class="section">
                    <h2><i class="fa-solid fa-chart-line"></i> Performance Overview</h2>
                    <div class="chart-container">
                        <canvas id="activityChart"></canvas>
                    </div>
                </div>

                <div class="section">
                    <h2><i class="fa-solid fa-gauge-high"></i> Real-Time Totals</h2>
                    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px;">
                        <div style="background: #0a0a0a; padding: 15px; border-radius: 10px; border: 1px solid #1a1a1a; text-align: center;">
                            <div style="color: #666; font-size: 10px; text-transform: uppercase; margin-bottom: 5px;">Active Users (DAU)</div>
                            <div style="font-size: 20px; font-weight: 700; color: #3ea6ff;"><?php echo number_format($dauToday); ?></div>
                        </div>
                        <div style="background: #0a0a0a; padding: 15px; border-radius: 10px; border: 1px solid #1a1a1a; text-align: center;">
                            <div style="color: #666; font-size: 10px; text-transform: uppercase; margin-bottom: 5px;">Logins Today</div>
                            <div style="font-size: 20px; font-weight: 700; color: #44ff44;"><?php echo number_format($loginsToday); ?></div>
                        </div>
                        <div style="background: #0a0a0a; padding: 15px; border-radius: 10px; border: 1px solid #1a1a1a; text-align: center;">
                            <div style="color: #666; font-size: 10px; text-transform: uppercase; margin-bottom: 5px;">Videos Today</div>
                            <div style="font-size: 20px; font-weight: 700; color: #ff5555;"><?php echo number_format($videosToday); ?></div>
                        </div>
                        <div style="background: #0a0a0a; padding: 15px; border-radius: 10px; border: 1px solid #1a1a1a; text-align: center;">
                            <div style="color: #666; font-size: 10px; text-transform: uppercase; margin-bottom: 5px;">Videos Month</div>
                            <div style="font-size: 20px; font-weight: 700; color: #ffaa00;"><?php echo number_format($videosThisMonth); ?></div>
                        </div>
                    </div>
                </div>

                <div class="section">
                    <h2><i class="fa-solid fa-info-circle"></i> Insight Analytics</h2>
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
                        <div style="background: #0a0a0a; padding: 20px; border-radius: 12px; border: 1px solid #222;">
                            <div style="color: #666; font-size: 11px; text-transform: uppercase;">Growth</div>
                            <div style="font-size: 24px; font-weight: 700; color: #44ff44; margin-top: 5px;">+<?php echo array_sum($chartData['logins']); ?></div>
                            <div style="color: #444; font-size: 10px; margin-top: 5px;">Total Logins (7d)</div>
                        </div>
                        <div style="background: #0a0a0a; padding: 20px; border-radius: 12px; border: 1px solid #222;">
                            <div style="color: #666; font-size: 11px; text-transform: uppercase;">Engagement</div>
                            <div style="font-size: 24px; font-weight: 700; color: #ffaa00; margin-top: 5px;"><?php echo array_sum($chartData['comments']); ?></div>
                            <div style="color: #444; font-size: 10px; margin-top: 5px;">New Comments (7d)</div>
                        </div>
                        <div style="background: #0a0a0a; padding: 20px; border-radius: 12px; border: 1px solid #222;">
                            <div style="color: #666; font-size: 11px; text-transform: uppercase;">Creation</div>
                            <div style="font-size: 24px; font-weight: 700; color: #ff5555; margin-top: 5px;"><?php echo array_sum($chartData['videos']); ?></div>
                            <div style="color: #444; font-size: 10px; margin-top: 5px;">Uploads (7d)</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Live Feed Column -->
            <div class="live-feed">
                <div class="section" style="padding: 25px 0 0 0;">
                    <h2 style="padding: 0 25px 20px 25px; border-bottom: 1px solid #222;">
                        <i class="fa-solid fa-bolt" style="color: #ffaa00;"></i> Live Activity
                    </h2>
                    <div class="feed-container">
                        <?php foreach($activities as $act): ?>
                            <div class="feed-item">
                                <div class="feed-icon icon-<?php echo $act['type']; ?>">
                                    <?php if($act['type'] === 'login'): ?>
                                        <i class="fa-solid fa-right-to-bracket"></i>
                                    <?php elseif($act['type'] === 'comment'): ?>
                                        <i class="fa-solid fa-comment"></i>
                                    <?php else: ?>
                                        <i class="fa-solid fa-video"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="feed-content">
                                    <div class="feed-user"><?php echo htmlspecialchars($act['username']); ?></div>
                                    <div class="feed-text">
                                        <?php if($act['type'] === 'login'): ?>
                                            Logged in from <code><?php echo htmlspecialchars($act['detail']); ?></code>
                                        <?php elseif($act['type'] === 'comment'): ?>
                                            Commented: "<?php echo htmlspecialchars(mb_strimwidth($act['detail'], 0, 60, "...")); ?>"
                                        <?php else: ?>
                                            Uploaded a new video: <strong><?php echo htmlspecialchars($act['detail']); ?></strong>
                                        <?php endif; ?>
                                    </div>
                                    <div class="feed-time"><?php echo date('M j, g:i A', strtotime($act['timestamp'])); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Initialize Chart
    const ctx = document.getElementById('activityChart').getContext('2d');
    const chart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($chartData['labels']); ?>,
            datasets: [
                {
                    label: 'Logins',
                    data: <?php echo json_encode($chartData['logins']); ?>,
                    borderColor: '#3ea6ff',
                    backgroundColor: 'rgba(62, 166, 255, 0.1)',
                    borderWidth: 3,
                    tension: 0.4,
                    fill: true,
                    pointRadius: 4,
                    pointBackgroundColor: '#3ea6ff'
                },
                {
                    label: 'Comments',
                    data: <?php echo json_encode($chartData['comments']); ?>,
                    borderColor: '#ffaa00',
                    backgroundColor: 'rgba(255, 170, 0, 0.1)',
                    borderWidth: 3,
                    tension: 0.4,
                    fill: true,
                    pointRadius: 4,
                    pointBackgroundColor: '#ffaa00'
                },
                {
                    label: 'Videos',
                    data: <?php echo json_encode($chartData['videos']); ?>,
                    borderColor: '#ff5555',
                    backgroundColor: 'rgba(255, 85, 85, 0.1)',
                    borderWidth: 3,
                    tension: 0.4,
                    fill: true,
                    pointRadius: 4,
                    pointBackgroundColor: '#ff5555'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    labels: { color: '#888', font: { family: 'Inter', size: 12 } }
                },
                tooltip: {
                    backgroundColor: '#111',
                    titleColor: '#fff',
                    bodyColor: '#ccc',
                    borderColor: '#333',
                    borderWidth: 1,
                    padding: 12,
                    displayColors: true
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { color: '#666', font: { size: 11 } }
                },
                y: {
                    beginAtZero: true,
                    grid: { color: '#1a1a1a' },
                    ticks: { 
                        color: '#666', 
                        font: { size: 11 },
                        stepSize: 1
                    }
                }
            },
            interaction: {
                intersect: false,
                mode: 'index'
            }
        }
    });

    // Simple auto-refresh for fake "real-time" feel (restores data every 30s)
    setInterval(() => {
        // In a real app we'd fetch JSON here, for now just reload
        // window.location.reload();
    }, 30000);
    </script>
</body>
</html>
