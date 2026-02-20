<?php
// backend/security_helper.php

require_once 'config.php';

if (!function_exists('logSecurityEvent')) {
    function logSecurityEvent($userId, $title, $message, $severity = 'info') {
        global $pdo;
        
        try {
            // 1. Log to Mailbox
            $stmt = $pdo->prepare("INSERT INTO mailbox_logs (user_id, title, message, severity) VALUES (?, ?, ?, ?)");
            $stmt->execute([$userId, $title, $message, $severity]);
            
            // 2. Push to Notifications (type: security_alert)
            // We use 'Mailbox' as the 'message' prefix or handle it in frontend
            // Note: security_alert type expects specific handling in notifications.js
            $notifMsg = $title; // The notification message
            
            $stmtNotif = $pdo->prepare("INSERT INTO notifications 
                (user_id, type, message, is_read, created_at) 
                VALUES (?, 'security_alert', ?, 0, NOW())");
            
            $stmtNotif->execute([$userId, $notifMsg]);
            
            return true;
        } catch (PDOException $e) {
            error_log("Security Log Error: " . $e->getMessage());
            return false;
        }
    }
}
