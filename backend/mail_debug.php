<?php
/**
 * Advanced SMTP & PHPMail Diagnostic
 */
header('Content-Type: text/html; charset=UTF-8');
require_once 'config.php';

echo "<style>body{background:#0a0a10;color:#eee;font-family:sans-serif;padding:40px;} .card{background:#111;padding:20px;border-radius:12px;border:1px solid #222;margin-bottom:20px;} b{color:#a855f7;}</style>";
echo "<h1>📬 Mail System Diagnostic</h1>";

// 1. Check php.ini settings
echo "<div class='card'>";
echo "<h3>System Configuration</h3>";
echo "<b>SMTP:</b> " . ini_get('SMTP') . "<br>";
echo "<b>smtp_port:</b> " . ini_get('smtp_port') . "<br>";
echo "<b>sendmail_from:</b> " . ini_get('sendmail_from') . "<br>";
echo "<b>sendmail_path:</b> " . ini_get('sendmail_path') . "<br>";
echo "</div>";

// 2. Check activity log
echo "<div class='card'>";
echo "<h3>Recent Activity Log (Market Reports)</h3>";
try {
    $stmt = $pdo->query("SELECT * FROM activity_log WHERE action = 'report_extension' ORDER BY created_at DESC LIMIT 5");
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($logs) {
        foreach ($logs as $log) {
            echo "<div style='margin-bottom:10px; padding:10px; background:#000; border-radius:8px;'>";
            echo "<b>Time:</b> {$log['created_at']}<br>";
            echo "<b>Detail:</b> {$log['details']}<br>";
            echo "</div>";
        }
    } else {
        echo "No reports found in log.";
    }
} catch (Exception $e) {
    echo "Error reading log: " . $e->getMessage();
}
echo "</div>";

// 3. Force a test send with Verbose Error Catching
echo "<div class='card'>";
echo "<h3>Testing Live Send...</h3>";
$to = "floxxteam@gmail.com";
$subject = "🚨 TEST: FloxWatch Security Engine";
$message = "Test message generated at " . date('Y-m-d H:i:s');
$headers = "From: security@floxwatch.com\r\n" .
           "Reply-To: security@floxwatch.com\r\n" .
           "X-Mailer: PHP/" . phpversion();

// We'll use a custom error handler to catch warnings from mail()
set_error_handler(function($errno, $errstr) {
    echo "<p style='color:#ff5555;'><b>PHP Warning:</b> $errstr</p>";
});

$result = mail($to, $subject, $message, $headers);
restore_error_handler();

if ($result) {
    echo "<p style='color:#55ff55;'><b>✓ SUCCESS:</b> PHP reports the mail was accepted by the server. Check your spam folder!</p>";
} else {
    echo "<p style='color:#ff5555;'><b>✗ FAILED:</b> PHP mail() returned FALSE. Your server rejected the message.</p>";
}
echo "</div>";
