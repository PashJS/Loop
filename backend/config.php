<?php
// backend/config.php (Optimized for Production)
$host = "127.0.0.1";
$dbname = "floxwatch";
$user = "root";

$pass = "root"; // Both local and remote use 'root'

// WebSocket Host Configuration
$ws_host = $_SERVER['HTTP_HOST'] ?? 'localhost';
if (strpos($ws_host, ':') !== false) {
    $ws_host = explode(':', $ws_host)[0];
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    // Performance settings
    try {
        $pdo->exec("SET SESSION wait_timeout = 600");
    } catch(PDOException $e) {}
} catch(PDOException $e) {
    error_log("DB connection failed: " . $e->getMessage());
    // Fallback: try localhost if 127.0.0.1 failed
    try {
        $pdo = new PDO("mysql:host=localhost;dbname=$dbname;charset=utf8mb4", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch(PDOException $ex) { throw $e; }
}

// Global Shutdown Check
try {
    // We keep this as it's a critical feature, but it's only 1 query
    $siteSettings = $pdo->query("SELECT is_shutdown FROM site_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
    if ($siteSettings && ($siteSettings['is_shutdown'] ?? false)) {
        if (strpos($_SERVER['REQUEST_URI'], '/admin/') === false && strpos($_SERVER['REQUEST_URI'], '/backend/') === false) {
            die("FloxWatch is currently offline.");
        }
    }
} catch (Exception $e) {}

if (session_status() === PHP_SESSION_NONE) session_start();
?>
