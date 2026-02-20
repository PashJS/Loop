<?php
// backend/updateUser.php - Update user information
header('Content-Type: application/json');
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Not authenticated.'
    ]);
    exit;
}

// Get request payload
$rawInput = file_get_contents('php://input');
$input = is_string($rawInput) && $rawInput !== '' ? json_decode($rawInput, true) : null;
if (!is_array($input)) {
    $input = $_POST ?? [];
}

$username = isset($input['username']) ? trim($input['username']) : null;
$email = isset($input['email']) ? trim($input['email']) : null;
$password = isset($input['password']) ? trim($input['password']) : null;
$bio = isset($input['bio']) ? trim($input['bio']) : null;

try {
    $updates = [];
    $params = [];
    
    if ($username !== null) {
        if (strlen($username) < 3 || strlen($username) > 24) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Username must be between 3 and 24 characters.'
            ]);
            exit;
        }
        
        // Check if username is already taken
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt->execute([$username, $_SESSION['user_id']]);
        if ($stmt->fetch()) {
            http_response_code(409); // Conflict
            echo json_encode([
                'success' => false,
                'message' => 'Username already taken.'
            ]);
            exit;
        }
        
        $updates[] = "username = ?";
        $params[] = $username;
    }
    
    if ($email !== null) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid email address.'
            ]);
            exit;
        }
        
        // Check if email is already taken
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $_SESSION['user_id']]);
        if ($stmt->fetch()) {
            http_response_code(409); // Conflict
            echo json_encode([
                'success' => false,
                'message' => 'Email already registered.'
            ]);
            exit;
        }
        
        $updates[] = "email = ?";
        $params[] = $email;
    }
    
    if ($password !== null) {
        if (strlen($password) < 6) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Password must be at least 6 characters.'
            ]);
            exit;
        }
        
        $updates[] = "password = ?";
        $params[] = password_hash($password, PASSWORD_DEFAULT);
    }
    
    if ($bio !== null) {
        // Check if bio column exists
        $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'bio'");
        $hasBio = $stmt->rowCount() > 0;
        
        if (!$hasBio) {
            // Create bio column if it doesn't exist
            try {
                $pdo->exec("ALTER TABLE users ADD COLUMN bio TEXT NULL");
            } catch (PDOException $e) {
                // Column might have been created by another request, ignore
            }
        }
        
        if (strlen($bio) > 500) {
            echo json_encode([
                'success' => false,
                'message' => 'Bio must be 500 characters or less.'
            ]);
            exit;
        }
        
        $updates[] = "bio = ?";
        $params[] = $bio;
    }
    
    if (empty($updates)) {
        echo json_encode([
            'success' => false,
            'message' => 'No fields to update.'
        ]);
        exit;
    }
    
    $params[] = $_SESSION['user_id'];
    $sql = "UPDATE users SET " . implode(", ", $updates) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    // Update session username if username was changed
    if ($username !== null) {
        $_SESSION['username'] = $username;
    }
    
    // Fetch updated user
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'bio'");
    $hasBio = $stmt->rowCount() > 0;
    
    if ($hasBio) {
        $stmt = $pdo->prepare("SELECT id, username, email, created_at, profile_picture, bio FROM users WHERE id = ?");
    } else {
        $stmt = $pdo->prepare("SELECT id, username, email, created_at, profile_picture, NULL as bio FROM users WHERE id = ?");
    }
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'message' => 'Profile updated successfully!',
        'user' => [
            'id' => (int)$user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'created_at' => $user['created_at'],
            'profile_picture' => $user['profile_picture'] ? (strpos($user['profile_picture'], 'http') === 0 ? $user['profile_picture'] : '..' . $user['profile_picture']) : null,
            'bio' => $user['bio'] ?? ''
        ]
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    error_log('Update user error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to update profile. Please try again.',
        'error' => $e->getMessage()
    ]);
}
?>
