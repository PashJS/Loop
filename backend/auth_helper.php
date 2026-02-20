<?php
// backend/auth_helper.php - Helper functions for persistent login

require_once 'config.php';

/**
 * Generate and store a remember token for a user
 */
function createRememberToken($userId) {
    global $pdo;
    
    $token = bin2hex(random_bytes(32));
    $tokenHash = password_hash($token, PASSWORD_DEFAULT);
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
    $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
    
    try {
        // Remove old tokens for this user (limit to 5 devices)
        $stmt = $pdo->prepare("DELETE FROM remember_tokens WHERE user_id = ? ORDER BY created_at ASC LIMIT 10");
        $stmt->execute([$userId]);
        
        // Insert new token
        $stmt = $pdo->prepare("INSERT INTO remember_tokens (user_id, token_hash, ip_address, user_agent, expires_at) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $tokenHash, $ip, $userAgent, $expires]);
        
        // Set cookie (30 days)
        $cookieValue = $userId . ':' . $token;
        setcookie('floxwatch_remember', $cookieValue, time() + (30 * 24 * 60 * 60), '/', '', false, true);
        
        return true;
    } catch (PDOException $e) {
        error_log("Remember token error: " . $e->getMessage());
        return false;
    }
}

/**
 * Validate remember token and log user in
 */
function validateRememberToken($setSession = true) {
    global $pdo;
    
    if (!isset($_COOKIE['floxwatch_remember'])) {
        return false;
    }
    
    $parts = explode(':', $_COOKIE['floxwatch_remember'], 2);
    if (count($parts) !== 2) {
        return false;
    }
    
    $userId = (int)$parts[0];
    $token = $parts[1];
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    
    try {
        // Get tokens for this user
        $stmt = $pdo->prepare("SELECT id, token_hash, ip_address, expires_at FROM remember_tokens WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
        $stmt->execute([$userId]);
        $tokens = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($tokens as $row) {
            // Check expiry
            if (strtotime($row['expires_at']) < time()) {
                continue;
            }
            
            // Verify token
            if (password_verify($token, $row['token_hash'])) {
                // Token valid! Check if IP matches for extra security (optional)
                // If IP matches, it's even more trusted
                
                // Update last_used
                $pdo->prepare("UPDATE remember_tokens SET last_used = NOW() WHERE id = ?")->execute([$row['id']]);
                
                // Log user in if requested
                if ($setSession) {
                    if (session_status() === PHP_SESSION_NONE) {
                        session_start();
                    }
                    $_SESSION['user_id'] = $userId;
                }
                
                return $userId;
            }
        }
        
        // No valid token found, clear cookie
        setcookie('floxwatch_remember', '', time() - 3600, '/', '', false, true);
        return false;
        
    } catch (PDOException $e) {
        error_log("Token validation error: " . $e->getMessage());
        return false;
    }
}

/**
 * Auto-login by IP address (for recognized devices)
 */
function autoLoginByIP() {
    global $pdo;
    
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!$ip) return false;
    
    try {
        // Find recent valid token for this IP
        $stmt = $pdo->prepare("
            SELECT user_id, token_hash 
            FROM remember_tokens 
            WHERE ip_address = ? AND expires_at > NOW() 
            ORDER BY last_used DESC, created_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$ip]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            // Found a token for this IP - return user_id for profile selection
            return (int)$row['user_id'];
        }
        
        return false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Clear remember token on logout
 */
function clearRememberToken($userId = null) {
    global $pdo;
    
    if (isset($_COOKIE['floxwatch_remember'])) {
        setcookie('floxwatch_remember', '', time() - 3600, '/', '', false, true);
    }
    
    if ($userId) {
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $pdo->prepare("DELETE FROM remember_tokens WHERE user_id = ? AND ip_address = ?")->execute([$userId, $ip]);
        } catch (PDOException $e) {
            // Ignore
        }
    }
}
