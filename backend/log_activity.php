<?php
// backend/log_activity.php

function logLoginActivity($pdo, $userId) {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        // Handle local testing IP
        if ($ip === '::1' || $ip === '127.0.0.1') {
            $ip = '81.16.1.1'; // Mock IP for Yerevan, Armenia testing if local
        }
        
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        // 1. Basic User Agent Parsing
        $device = "Desktop";
        if (preg_match('/Mobi|Android|iPhone/i', $userAgent)) {
            $device = "Mobile";
        }

        $browser = "Unknown Browser";
        if (preg_match('/Edg/i', $userAgent)) { $browser = "Edge"; }
        elseif (preg_match('/Chrome/i', $userAgent)) { $browser = "Chrome"; }
        elseif (preg_match('/Firefox/i', $userAgent)) { $browser = "Firefox"; }
        elseif (preg_match('/Safari/i', $userAgent)) { $browser = "Safari"; }

        $os = "Unknown OS";
        if (preg_match('/Windows/i', $userAgent)) { $os = "Windows"; }
        elseif (preg_match('/Mac/i', $userAgent)) { $os = "MacOS"; }
        elseif (preg_match('/Android/i', $userAgent)) { $os = "Android"; }
        elseif (preg_match('/iPhone|iPad/i', $userAgent)) { $os = "iOS"; }
        elseif (preg_match('/Linux/i', $userAgent)) { $os = "Linux"; }

        $deviceInfo = $device . " " . $browser . " " . $os;

        // 2. Geolocation Lookup
        $location = "Unknown Location";
        try {
            $ctx = stream_context_create(['http' => ['timeout' => 1]]);
            $geo = @file_get_contents("http://ip-api.com/json/$ip", false, $ctx);
            if ($geo) {
                $geoData = json_decode($geo, true);
                if ($geoData && $geoData['status'] === 'success') {
                    $location = ($geoData['city'] ?? '') . " " . ($geoData['country'] ?? '');
                }
            }
        } catch (Exception $e) {}

        // 3. Insert into Database
        $sessionId = session_id();
        $stmt = $pdo->prepare("INSERT INTO login_activity (user_id, ip_address, user_agent, device_info, location, session_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $ip, $userAgent, $deviceInfo, $location, $sessionId]);

        return $deviceInfo;

    } catch (Exception $e) {
        error_log("Failed to log activity: " . $e->getMessage());
        return "Unknown Device";
    }
}
?>
