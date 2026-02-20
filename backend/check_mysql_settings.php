<?php
// backend/check_mysql_settings.php - Check MySQL settings for file uploads
header('Content-Type: application/json');
require 'config.php';

try {
    // Get current settings
    $settings = [];
    
    $stmt = $pdo->query("SHOW VARIABLES LIKE 'max_allowed_packet'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $settings['max_allowed_packet'] = [
        'value' => isset($result['Value']) ? (int)$result['Value'] : 0,
        'value_mb' => isset($result['Value']) ? round((int)$result['Value'] / 1024 / 1024, 2) : 0,
        'recommended' => 524288000, // 500MB
        'recommended_mb' => 500
    ];
    
    $stmt = $pdo->query("SHOW VARIABLES LIKE 'wait_timeout'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $settings['wait_timeout'] = [
        'value' => isset($result['Value']) ? (int)$result['Value'] : 0,
        'recommended' => 600
    ];
    
    $stmt = $pdo->query("SHOW VARIABLES LIKE 'interactive_timeout'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $settings['interactive_timeout'] = [
        'value' => isset($result['Value']) ? (int)$result['Value'] : 0,
        'recommended' => 600
    ];
    
    // Check if settings need to be updated
    $needsUpdate = false;
    $updateSQL = [];
    
    if ($settings['max_allowed_packet']['value'] < $settings['max_allowed_packet']['recommended']) {
        $needsUpdate = true;
        $updateSQL[] = "SET GLOBAL max_allowed_packet = " . $settings['max_allowed_packet']['recommended'] . ";";
    }
    
    if ($settings['wait_timeout']['value'] < $settings['wait_timeout']['recommended']) {
        $needsUpdate = true;
        $updateSQL[] = "SET GLOBAL wait_timeout = " . $settings['wait_timeout']['recommended'] . ";";
    }
    
    if ($settings['interactive_timeout']['value'] < $settings['interactive_timeout']['recommended']) {
        $needsUpdate = true;
        $updateSQL[] = "SET GLOBAL interactive_timeout = " . $settings['interactive_timeout']['recommended'] . ";";
    }
    
    echo json_encode([
        'success' => true,
        'settings' => $settings,
        'needs_update' => $needsUpdate,
        'update_sql' => $needsUpdate ? implode("\n", $updateSQL) : null,
        'message' => $needsUpdate 
            ? "MySQL settings need to be updated. Run the SQL commands shown in 'update_sql' in phpMyAdmin."
            : "MySQL settings are configured correctly."
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error checking MySQL settings: ' . $e->getMessage()
    ]);
}
?>

