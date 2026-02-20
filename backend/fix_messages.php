<?php
// Quick fix script - approve all existing messages between users
session_start();
require_once __DIR__ . '/config.php';

// Approve ALL messages in the system (for testing purposes)
$pdo->exec("UPDATE messages SET is_approved = 1");

echo "Done! All messages are now approved. You can delete this file.";
?>
