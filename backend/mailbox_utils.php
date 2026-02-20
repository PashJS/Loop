<?php
// backend/mailbox_utils.php

if (!function_exists('sendMailboxNotification')) {
    function sendMailboxNotification($pdo, $userId, $type, $title, $message) {
        try {

            $stmt = $pdo->prepare("INSERT INTO floxsync_mailbox (user_id, type, title, message) VALUES (?, ?, ?, ?)");
            $stmt->execute([$userId, $type, $title, $message]);
            return true;
        } catch (PDOException $e) {
            error_log("Mailbox Notification Error: " . $e->getMessage());
            return false;
        }
    }
}
