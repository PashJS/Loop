<?php
// backend/deploy_everything.php
// This script will write all optimized files to their target locations.

$files = [
    'config.php' => '<?php
// backend/config.php (Optimized for Production)
$host = "127.0.0.1";
$dbname = "floxwatch";
$user = "root";
$isRemote = ($_SERVER["HTTP_HOST"] ?? "") === "82.208.23.150" || ($_SERVER["SERVER_ADDR"] ?? "") === "82.208.23.150";
$pass = $isRemote ? "" : "root"; 
$ws_host = $_SERVER["HTTP_HOST"] ?? "localhost";
if (strpos($ws_host, ":") !== false) { $ws_host = explode(":", $ws_host)[0]; }
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
} catch(PDOException $e) {
    if ($isRemote) {
        try {
            $pdo = new PDO("mysql:host=localhost;dbname=$dbname;charset=utf8mb4", $user, $pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $ex) { die("DB Connection Failed"); }
    } else { die("DB Connection Failed"); }
}
if (session_status() === PHP_SESSION_NONE) session_start();
?>',
    'log_activity.php' => '<?php
function logLoginActivity($pdo, $userId) {
    try {
        $ip = $_SERVER["REMOTE_ADDR"] ?? "0.0.0.0";
        $userAgent = $_SERVER["HTTP_USER_AGENT"] ?? "Unknown";
        $deviceInfo = $userAgent; // Simplified for speed
        $location = "Unknown";
        $sessionId = session_id();
        $stmt = $pdo->prepare("INSERT INTO login_activity (user_id, ip_address, user_agent, device_info, location, session_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $ip, $userAgent, $deviceInfo, $location, $sessionId]);
        return "Logged";
    } catch (Exception $e) { return "Error"; }
}
?>',
    'optimize_db.php' => '<?php
require_once "config.php";
echo "Starting Optimization...\n";
$indexes = [
    ["notifications", "idx_user_hidden_read", "(user_id, is_hidden, is_read)"],
    ["notifications", "idx_created_at", "(created_at)"],
    ["notification_preferences", "idx_user_type", "(user_id, type)"],
    ["stories", "idx_created_at", "(created_at)"],
    ["saves", "idx_user_video", "(user_id, video_id)"]
];
foreach ($indexes as $idx) {
    try {
        $pdo->exec("ALTER TABLE {$idx[0]} ADD INDEX {$idx[1]} {$idx[2]}");
        echo "Added {$idx[1]}\n";
    } catch (Exception $e) { echo "Skip {$idx[1]}\n"; }
}
echo "Optimization Done.\n";
?>'
];

foreach ($files as $name => $content) {
    echo "Writing $name...\n";
    file_put_contents($name, $content);
}

echo "Running Optimization...\n";
include 'optimize_db.php';
?>
