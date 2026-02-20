<?php
// backend/check_upload_settings.php - Check PHP and server settings for large file uploads
header('Content-Type: application/json');

$settings = [
    'php' => [
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size'),
        'memory_limit' => ini_get('memory_limit'),
        'max_execution_time' => ini_get('max_execution_time'),
        'max_input_time' => ini_get('max_input_time'),
        'file_uploads' => ini_get('file_uploads') ? 'Enabled' : 'Disabled'
    ],
    'server' => [
        'php_version' => phpversion(),
        'upload_tmp_dir' => ini_get('upload_tmp_dir') ?: sys_get_temp_dir(),
        'disk_free_space' => disk_free_space(__DIR__ . '/../uploads/') ?: 0,
        'disk_free_space_gb' => disk_free_space(__DIR__ . '/../uploads/') ? round(disk_free_space(__DIR__ . '/../uploads/') / 1024 / 1024 / 1024, 2) : 0
    ]
];

// Convert PHP size strings to bytes for comparison
function convertToBytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    $val = (int)$val;
    switch($last) {
        case 'g': $val *= 1024;
        case 'm': $val *= 1024;
        case 'k': $val *= 1024;
    }
    return $val;
}

$requiredSize = 256 * 1024 * 1024 * 1024; // 256GB
$uploadMaxBytes = convertToBytes($settings['php']['upload_max_filesize']);
$postMaxBytes = convertToBytes($settings['php']['post_max_size']);
$phpLimit = min($uploadMaxBytes, $postMaxBytes);

$settings['recommendations'] = [];
$settings['status'] = 'ok';

if ($phpLimit < $requiredSize) {
    $settings['status'] = 'warning';
    $settings['recommendations'][] = "PHP upload_max_filesize ({$settings['php']['upload_max_filesize']}) is too small. Should be at least 256G";
    $settings['recommendations'][] = "PHP post_max_size ({$settings['php']['post_max_size']}) is too small. Should be at least 256G";
}

if ($settings['php']['max_execution_time'] > 0 && $settings['php']['max_execution_time'] < 3600) {
    $settings['status'] = 'warning';
    $settings['recommendations'][] = "max_execution_time ({$settings['php']['max_execution_time']}s) may be too low for large uploads. Consider setting to 0 (unlimited)";
}

if ($settings['server']['disk_free_space'] < $requiredSize) {
    $settings['status'] = 'error';
    $settings['recommendations'][] = "Insufficient disk space. Available: {$settings['server']['disk_free_space_gb']} GB, Required: 256 GB";
}

echo json_encode([
    'success' => true,
    'settings' => $settings,
    'max_file_size_supported' => min($phpLimit, $settings['server']['disk_free_space']),
    'max_file_size_supported_gb' => round(min($phpLimit, $settings['server']['disk_free_space']) / 1024 / 1024 / 1024, 2)
], JSON_PRETTY_PRINT);
?>

