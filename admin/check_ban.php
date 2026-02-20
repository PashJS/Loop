<?php
session_start();
require_once '../backend/config.php';

echo "<h2>Current Session:</h2>";
echo "<pre>";
if (isset($_SESSION['user_id'])) {
    echo "Logged in as User ID: " . $_SESSION['user_id'] . "\n";
    echo "Username in session: " . ($_SESSION['username'] ?? 'not set') . "\n";
} else {
    echo "NOT logged in (no session)\n";
}
echo "</pre>";

// Check all users with ban data
$stmt = $pdo->query("SELECT id, username, banned_until, ban_reason, ban_proof, ban_note FROM users WHERE banned_until IS NOT NULL");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h2>Banned Users in Database:</h2>";
echo "<pre>";
if (empty($users)) {
    echo "No users have ban data set.\n";
} else {
    foreach ($users as $user) {
        $isActive = strtotime($user['banned_until']) > time();
        echo "User ID: " . $user['id'] . "\n";
        echo "Username: " . $user['username'] . "\n";
        echo "Banned Until: " . $user['banned_until'] . "\n";
        echo "Ban Active: " . ($isActive ? "YES" : "NO (expired)") . "\n";
        echo "Reason: " . ($user['ban_reason'] ?: "(empty)") . "\n";
        echo "Proof: " . ($user['ban_proof'] ?: "(empty)") . "\n";
        echo "Note: " . ($user['ban_note'] ?: "(empty)") . "\n";
        echo "---\n";
    }
}
echo "</pre>";

// Check table structure
echo "<h2>Users Table Ban Columns:</h2>";
$cols = $pdo->query("SHOW COLUMNS FROM users LIKE 'ban%'")->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($cols);
echo "</pre>";
?>
