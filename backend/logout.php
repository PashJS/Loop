<?php
// backend/logout.php - Logout user
session_start();

$userId = $_SESSION['user_id'] ?? null;

// Clear remember token
require_once 'auth_helper.php';
if ($userId) {
    clearRememberToken($userId);
}

// Destroy session
$_SESSION = array();

// Destroy session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

session_destroy();

// Redirect to profile selection
header('Location: ../frontend/select_profile.php');
exit;
?>
