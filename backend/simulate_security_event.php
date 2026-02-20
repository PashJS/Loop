<?php
// backend/simulate_security_event.php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require 'security_helper.php';

// Simulate a random security event
$events = [
    [
        'title' => 'New Login Detected',
        'message' => 'A new login from Chrome on Windows 10 was detected near New York, USA.',
        'severity' => 'info'
    ],
    [
        'title' => 'Password Changed',
        'message' => 'Your account password was successfully updated.',
        'severity' => 'info'
    ],
    [
        'title' => 'Suspicious Activity Blocked',
        'message' => 'We blocked an attempt to access your account from an unrecognized location.',
        'severity' => 'warning'
    ],
    [
        'title' => 'Account Strike',
        'message' => 'Your recent comment was flagged for specific community guidelines violations.',
        'severity' => 'critical'
    ]
];

$event = $events[array_rand($events)];

if (logSecurityEvent($_SESSION['user_id'], $event['title'], $event['message'], $event['severity'])) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to log event']);
}
