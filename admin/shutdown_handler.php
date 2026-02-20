<?php
session_start();
require_once '../backend/config.php';
require_once '../backend/mailer.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Admin secret password from index.php
const AUTH_PASSWORD = 'BhdUGdb490$+_094gbHGFYG£366372';

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';

// Check lockout again (24 hours)
$siteSettings = $pdo->query("SELECT * FROM site_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
if (isset($siteSettings['last_failed_shutdown'])) {
    $lockoutTime = strtotime($siteSettings['last_failed_shutdown']) + (24 * 3600);
    if (time() < $lockoutTime) {
        echo json_encode(['success' => false, 'message' => 'Shutdown controls are locked due to multiple failed attempts. Try again in ' . ceil(($lockoutTime - time()) / 3600) . ' hours.']);
        exit;
    }
}

if ($action === 'request_code') {
    // Generate 20 letter/symbol code
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+-=[]{}|;:,.<>?';
    $code = '';
    for ($i = 0; $i < 20; $i++) {
        $code .= $chars[rand(0, strlen($chars) - 1)];
    }
    
    // Store in DB
    $stmt = $pdo->prepare("UPDATE site_settings SET shutdown_secret_code = ?, shutdown_code_expires = DATE_ADD(NOW(), INTERVAL 30 MINUTE) WHERE id = 1");
    $stmt->execute([$code]);
    
    // Send Email
    $subject = "FloxWatch EMERGENCY SHUTDOWN CODE";
    $message = "
        <div style='background:#000;color:#fff;padding:40px;font-family:sans-serif;border-radius:12px;'>
            <h1 style='color:##ff3333;'>EMERGENCY SYSTEM SHUTDOWN</h1>
            <p>You requested a high-security code to shut down FloxWatch.</p>
            <div style='background:#111;border:1px solid #333;padding:20px;font-family:monospace;font-size:24px;color:#fff;text-align:center;letter-spacing:4px;margin:20px 0;'>
                $code
            </div>
            <p style='color:#666;font-size:12px;'>This code expires in 30 minutes. If you did not request this, secure your admin account immediately.</p>
        </div>
    ";
    
    if (sendFloxEmail('floxxteam@gmail.com', $subject, $message, true)) {
        echo json_encode(['success' => true, 'message' => 'Security code sent to floxxteam@gmail.com']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to send email. Check mailer settings.']);
    }
} 
elseif ($action === 'execute_shutdown') {
    $inputCode = $data['code'] ?? '';
    $inputPass = $data['password'] ?? '';
    
    // 1. Check Password
    if ($inputPass !== AUTH_PASSWORD) {
        $pdo->query("UPDATE site_settings SET last_failed_shutdown = NOW() WHERE id = 1");
        echo json_encode(['success' => false, 'message' => 'INCORRECT PASSWORD. Shutdown controls locked for 24 hours.']);
        exit;
    }
    
    // 2. Check Code
    if (!$siteSettings['shutdown_secret_code'] || $inputCode !== $siteSettings['shutdown_secret_code']) {
        $pdo->query("UPDATE site_settings SET last_failed_shutdown = NOW() WHERE id = 1");
        echo json_encode(['success' => false, 'message' => 'INVALID SECURITY CODE. Shutdown controls locked for 24 hours.']);
        exit;
    }
    
    // 3. Check Expiry
    if (strtotime($siteSettings['shutdown_code_expires']) < time()) {
        echo json_encode(['success' => false, 'message' => 'Security code expired. Please request a new one.']);
        exit;
    }

    // SUCCESS - SHUT DOWN SITE
    $pdo->query("UPDATE site_settings SET is_shutdown = 1, shutdown_secret_code = NULL, last_failed_shutdown = NULL WHERE id = 1");
    echo json_encode(['success' => true, 'message' => 'FloxWatch has been shut down.']);
}
elseif ($action === 'restore_site') {
    // Restoring doesn't require as much security, just admin auth
    $pdo->query("UPDATE site_settings SET is_shutdown = 0 WHERE id = 1");
    echo json_encode(['success' => true, 'message' => 'FloxWatch is back online.']);
}
else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
