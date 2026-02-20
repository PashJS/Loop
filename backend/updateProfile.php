<?php
// backend/updateProfile.php - Update user profile (username, bio)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

session_start();
require 'config.php';

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

$userId = isset($input['user_id']) ? (int)$input['user_id'] : 0;
$username = isset($input['username']) ? trim($input['username']) : '';
$bio = isset($input['bio']) ? trim($input['bio']) : '';

// Validate user_id
if ($userId <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid user ID.'
    ]);
    exit;
}

// Validate username
if (empty($username)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Username is required.'
    ]);
    exit;
}

if (strlen($username) < 3 || strlen($username) > 30) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Username must be between 3 and 30 characters.'
    ]);
    exit;
}

// Validate bio length
if (strlen($bio) > 150) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Bio must be 150 characters or less.'
    ]);
    exit;
}

try {
    // Check if bio column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'bio'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE users ADD COLUMN bio TEXT DEFAULT NULL");
    }

    // Check if username is already taken by another user
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $stmt->execute([$username, $userId]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'Username is already taken.'
        ]);
        exit;
    }

    // Update the profile
    $stmt = $pdo->prepare("UPDATE users SET username = ?, bio = ? WHERE id = ?");
    $stmt->execute([$username, $bio, $userId]);

    if ($stmt->rowCount() > 0 || true) { // Always return success if no error
        echo json_encode([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'user' => [
                'id' => $userId,
                'username' => $username,
                'bio' => $bio
            ]
        ]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    error_log('Update profile error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to update profile.',
        'error' => $e->getMessage()
    ]);
}
?>
