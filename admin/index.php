<?php
session_start();

// Admin credentials (password is hashed)
define('ADMIN_USERNAME', 'pashhh221');
define('ADMIN_PASSWORD_HASH', password_hash('BhdUGdb490$+_094gbHGFYG£366372', PASSWORD_DEFAULT));

// Block file location
$blockFile = __DIR__ . '/admin_blocks.json';

// Get client IP
function getClientIP() {
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

// Check if IP is blocked
function isBlocked($blockFile) {
    if (!file_exists($blockFile)) return false;
    
    $blocks = json_decode(file_get_contents($blockFile), true) ?? [];
    $ip = getClientIP();
    
    if (isset($blocks[$ip])) {
        $blockTime = $blocks[$ip]['blocked_until'];
        if (time() < $blockTime) {
            return $blockTime - time(); // Return seconds remaining
        } else {
            // Block expired, remove it
            unset($blocks[$ip]);
            file_put_contents($blockFile, json_encode($blocks));
        }
    }
    return false;
}

// Record failed attempt
function recordFailedAttempt($blockFile) {
    $blocks = [];
    if (file_exists($blockFile)) {
        $blocks = json_decode(file_get_contents($blockFile), true) ?? [];
    }
    
    $ip = getClientIP();
    $blocks[$ip] = [
        'blocked_until' => time() + 3600, // 1 hour
        'blocked_at' => date('Y-m-d H:i:s')
    ];
    
    file_put_contents($blockFile, json_encode($blocks));
}

// Check if already logged in
if (isset($_SESSION['admin_authenticated']) && $_SESSION['admin_authenticated'] === true) {
    header('Location: admin_panel.php');
    exit;
}

// Secret bypass to clear blocks (use ?reset_blocks=floxadmin221)
if (isset($_GET['reset_blocks']) && $_GET['reset_blocks'] === 'floxadmin221') {
    if (file_exists($blockFile)) {
        unlink($blockFile);
    }
    header('Location: index.php');
    exit;
}

$error = '';
$blocked = isBlocked($blockFile);

if ($blocked) {
    $minutes = ceil($blocked / 60);
    $error = "Access blocked. Try again in {$minutes} minute(s).";
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$blocked) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Verify credentials
    if ($username === ADMIN_USERNAME && $password === 'BhdUGdb490$+_094gbHGFYG£366372') {
        $_SESSION['admin_authenticated'] = true;
        $_SESSION['admin_login_time'] = time();
        header('Location: admin_panel.php');
        exit;
    } else {
        recordFailedAttempt($blockFile);
        $error = "Invalid credentials. Access blocked for 1 hour.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Access</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #0a0a0a;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-box {
            background: #111;
            border: 1px solid #222;
            border-radius: 12px;
            padding: 40px;
            width: 100%;
            max-width: 380px;
        }
        h1 {
            color: #fff;
            font-size: 20px;
            margin-bottom: 30px;
            text-align: center;
            font-weight: 500;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            color: #666;
            font-size: 12px;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        input {
            width: 100%;
            background: #0a0a0a;
            border: 1px solid #222;
            border-radius: 8px;
            padding: 14px;
            color: #fff;
            font-size: 14px;
        }
        input:focus {
            outline: none;
            border-color: #333;
        }
        button {
            width: 100%;
            background: #fff;
            color: #000;
            border: none;
            padding: 14px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 10px;
        }
        button:hover {
            background: #eee;
        }
        button:disabled {
            background: #333;
            color: #666;
            cursor: not-allowed;
        }
        .error {
            background: rgba(255, 50, 50, 0.1);
            border: 1px solid rgba(255, 50, 50, 0.3);
            color: #ff5555;
            padding: 12px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 20px;
            text-align: center;
        }
        .icon {
            text-align: center;
            margin-bottom: 20px;
        }
        .icon i {
            font-size: 40px;
            color: #333;
        }
    </style>
</head>
<body>
    <div class="login-box">
        <div class="icon">
            <i class="fa-solid fa-shield-halved"></i>
        </div>
        <h1>Admin Access</h1>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST" <?php echo $blocked ? 'style="opacity: 0.5; pointer-events: none;"' : ''; ?>>
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" required autocomplete="off">
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required autocomplete="off">
            </div>
            <button type="submit" <?php echo $blocked ? 'disabled' : ''; ?>>Access Panel</button>
        </form>
    </div>
</body>
</html>
