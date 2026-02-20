<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$setting = $data['setting'] ?? '';
$value = $data['value'] ?? '';

$allowed_settings = ['weather_widget', 'resume_widget', 'theme', 'font', 'comment_badge', 'name_badge', 'time_widget', 'clock_type', 'clock_weight', 'clock_blur', 'clock_no_box', 'clock_font_blur'];

if (!in_array($setting, $allowed_settings)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid setting']);
    exit;
}

try {
    // 1. Check if table pro_settings exists, if not create it
    $pdo->exec("CREATE TABLE IF NOT EXISTS pro_settings (
        user_id INT PRIMARY KEY,
        weather_widget VARCHAR(10) DEFAULT 'on',
        resume_widget VARCHAR(10) DEFAULT 'on',
        theme VARCHAR(50) DEFAULT 'liquid',
        font VARCHAR(50) DEFAULT 'outfit',
        comment_badge VARCHAR(50) DEFAULT 'pro',
        name_badge VARCHAR(10) DEFAULT 'on',
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // 1.5 Auto-migration: Check if columns exist and add if missing (e.g. resume_widget added later)
    if ($setting === 'resume_widget') {
        try {
            $pdo->query("SELECT resume_widget FROM pro_settings LIMIT 1");
        } catch (PDOException $e) {
            $pdo->exec("ALTER TABLE pro_settings ADD COLUMN resume_widget VARCHAR(10) DEFAULT 'on'");
        }
    }

    if ($setting === 'time_widget') {
        try {
            $pdo->query("SELECT time_widget FROM pro_settings LIMIT 1");
        } catch (PDOException $e) {
            $pdo->exec("ALTER TABLE pro_settings ADD COLUMN time_widget VARCHAR(10) DEFAULT 'on'");
        }
    }

    if ($setting === 'clock_type') {
        try {
            $pdo->query("SELECT clock_type FROM pro_settings LIMIT 1");
        } catch (PDOException $e) {
            $pdo->exec("ALTER TABLE pro_settings ADD COLUMN clock_type VARCHAR(20) DEFAULT 'analog'");
        }
    }

    if ($setting === 'clock_weight') {
        try {
            $pdo->query("SELECT clock_weight FROM pro_settings LIMIT 1");
        } catch (PDOException $e) {
            $pdo->exec("ALTER TABLE pro_settings ADD COLUMN clock_weight VARCHAR(10) DEFAULT '800'");
        }
    }

    if ($setting === 'clock_blur') {
        try {
            $pdo->query("SELECT clock_blur FROM pro_settings LIMIT 1");
        } catch (PDOException $e) {
            $pdo->exec("ALTER TABLE pro_settings ADD COLUMN clock_blur VARCHAR(10) DEFAULT '20'");
        }
    }

    if ($setting === 'clock_no_box') {
        try {
            $pdo->query("SELECT clock_no_box FROM pro_settings LIMIT 1");
        } catch (PDOException $e) {
            $pdo->exec("ALTER TABLE pro_settings ADD COLUMN clock_no_box VARCHAR(10) DEFAULT 'off'");
        }
    }

    if ($setting === 'clock_font_blur') {
        try {
            $pdo->query("SELECT clock_font_blur FROM pro_settings LIMIT 1");
        } catch (PDOException $e) {
            $pdo->exec("ALTER TABLE pro_settings ADD COLUMN clock_font_blur VARCHAR(10) DEFAULT '0'");
        }
    }

    // 2. Insert or Update setting
    // We use a separate table to keep the main users table clean
    $stmt = $pdo->prepare("INSERT INTO pro_settings (user_id, $setting) VALUES (?, ?) 
                           ON DUPLICATE KEY UPDATE $setting = VALUES($setting)");
    $stmt->execute([$_SESSION['user_id'], $value]);

    echo json_encode(['status' => 'success']);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
