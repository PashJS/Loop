<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    // Check if user is pro
    $stmt = $pdo->prepare("SELECT is_pro FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $is_pro = $stmt->fetchColumn();
    
    if (!$is_pro) {
        echo json_encode([
            'success' => true,
            'weather_visible' => false,
            'resume_visible' => false
        ]);
        exit;
    }
    
    // Get widget settings from database
    $stmt = $pdo->prepare("SELECT weather_widget, resume_widget FROM pro_settings WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Default to 'on' if no settings found
    $weather_widget = $settings['weather_widget'] ?? 'on';
    $resume_widget = $settings['resume_widget'] ?? 'on';
    
    echo json_encode([
        'success' => true,
        'weather_visible' => $weather_widget === 'on',
        'resume_visible' => $resume_widget === 'on'
    ]);
} catch (PDOException $e) {
    // If table doesn't exist or error occurs, default to visible (fail-safe)
    error_log('Widget visibility error: ' . $e->getMessage());
    echo json_encode([
        'success' => true,
        'weather_visible' => true,
        'resume_visible' => true
    ]);
}
?>

