# FloxWatch - Backend Setup Guide

A complete guide to setting up the FloxWatch backend on MAMP.

## Prerequisites

- **MAMP** (or MAMP PRO) installed and running
- **PHP 7.4+** (included with MAMP)
- **MySQL/MariaDB** (included with MAMP)
- A web browser for testing
- **Node.js** (for WebSocket server)

## Step 1: Start MAMP Services

1. Open **MAMP** (or MAMP PRO)
2. Click **Start Servers**
3. Ensure both **Apache** and **MySQL** are running (green indicators)
4. Note your Apache port (usually `80` or `8888`) and MySQL port (usually `3306`)

## Step 2: Start WebSocket Server

For real-time features like Chat, Notifications, and WatchTogether to work, you must start the Node.js WebSocket server:

1. Open a terminal/command prompt.
2. Navigate to the project root: `cd c:\MAMP\htdocs\FloxWatch`
3. Run the start script: `.\start_ws.bat` (Windows) or `node websocket/server.js`
4. Keep this terminal open while using the app.

## Step 3: Database Setup

### Option A: Using phpMyAdmin (Recommended)

1. Open your browser and go to: `http://localhost/phpMyAdmin` (or `http://localhost:8888/phpMyAdmin` if using port 8888)
2. Click on **SQL** tab
3. Copy and paste the following SQL commands:

```sql
-- Create the database
CREATE DATABASE IF NOT EXISTS floxwatch CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Use the database
USE floxwatch;

-- Create the users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(24) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create the videos table (after users table exists)
CREATE TABLE IF NOT EXISTS videos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    description TEXT,
    video_url VARCHAR(500) NOT NULL,
    thumbnail_url VARCHAR(500),
    user_id INT NOT NULL,
    status ENUM('draft', 'published', 'archived') DEFAULT 'published',
    views INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    FULLTEXT idx_search (title, description)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create the likes table
CREATE TABLE IF NOT EXISTS likes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    video_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE,
    UNIQUE KEY unique_like (user_id, video_id),
    INDEX idx_user_id (user_id),
    INDEX idx_video_id (video_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create the comments table
CREATE TABLE IF NOT EXISTS comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    video_id INT NOT NULL,
    comment TEXT NOT NULL,
    parent_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES comments(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_video_id (video_id),
    INDEX idx_parent_id (parent_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create the dislikes table
CREATE TABLE IF NOT EXISTS dislikes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    video_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE,
    UNIQUE KEY unique_dislike (user_id, video_id),
    INDEX idx_user_id (user_id),
    INDEX idx_video_id (video_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create the favorites table
CREATE TABLE IF NOT EXISTS favorites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    video_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE,
    UNIQUE KEY unique_favorite (user_id, video_id),
    INDEX idx_user_id (user_id),
    INDEX idx_video_id (video_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create the comment_likes table
CREATE TABLE IF NOT EXISTS comment_likes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    comment_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE,
    UNIQUE KEY unique_comment_like (user_id, comment_id),
    INDEX idx_user_id (user_id),
    INDEX idx_comment_id (comment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create the comment_dislikes table
CREATE TABLE IF NOT EXISTS comment_dislikes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    comment_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE,
    UNIQUE KEY unique_comment_dislike (user_id, comment_id),
    INDEX idx_user_id (user_id),
    INDEX idx_comment_id (comment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add profile_picture column to users table
-- Note: MySQL doesn't support IF NOT EXISTS in ALTER TABLE, so check first or run addBioColumn.php
ALTER TABLE users ADD COLUMN profile_picture VARCHAR(500) NULL;

-- Add bio column to users table  
-- Run backend/addBioColumn.php instead, or check if column exists first
-- ALTER TABLE users ADD COLUMN bio TEXT NULL;
```

4. Click **Go** to execute
5. Verify the database and table were created successfully

### Option B: Using MySQL Command Line

1. Open Terminal/Command Prompt
2. Navigate to MAMP's MySQL bin directory:
   ```bash
   # macOS
   cd /Applications/MAMP/Library/bin
   
   # Windows
   cd C:\MAMP\bin\mysql\bin
   ```
3. Connect to MySQL:
   ```bash
   ./mysql -u root -proot
   ```
4. Run the SQL commands from Option A above

## 🚨 Fix for Missing Features (Replies, Drag & Drop)

If your **replies aren't working** or **video uploads fail**, run this SQL code in phpMyAdmin immediately. This adds the missing columns required for these features.

1. Go to **phpMyAdmin**
2. Click on your `floxwatch` database
3. Click **SQL** tab
4. Paste and Run this code:

```sql
USE floxwatch;

-- 1. Fix for Replies (Adds parent_id to comments)
ALTER TABLE comments ADD COLUMN IF NOT EXISTS parent_id INT NULL;
ALTER TABLE comments ADD CONSTRAINT fk_comment_parent FOREIGN KEY (parent_id) REFERENCES comments(id) ON DELETE CASCADE;

-- 2. Fix for Drag & Drop Uploads (Ensures thumbnail support)
ALTER TABLE videos ADD COLUMN IF NOT EXISTS thumbnail_url VARCHAR(255) NULL;
ALTER TABLE videos ADD COLUMN IF NOT EXISTS video_url VARCHAR(255) NOT NULL;

-- 3. Fix for Profile Pictures
ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_picture VARCHAR(500) NULL;

-- 4. Fix for Comment Likes/Dislikes (if missing)
CREATE TABLE IF NOT EXISTS comment_likes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    comment_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE,
    UNIQUE KEY unique_comment_like (user_id, comment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS comment_dislikes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    comment_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE,
    UNIQUE KEY unique_comment_dislike (user_id, comment_id)
$dbname = "floxwatch";
$user = "root";
$pass = "root"; // MAMP default password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("DB connection failed: " . $e->getMessage());
}
?>
```

**Important Notes:**
- If your MAMP MySQL password is different, change `$pass = "root"` to your actual password
- If using a different port, add it to the host: `$host = "localhost:3306"`
- For MAMP PRO, you may need to use different credentials (check MAMP PRO settings)

## Step 4: Verify Project Structure

Ensure your project structure looks like this:

```
FloxWatch/
├── backend/
│   ├── config.php          # Database configuration
│   ├── login.php           # Login endpoint
│   ├── register.php        # Registration endpoint
│   ├── logout.php          # Logout endpoint
│   ├── getUser.php         # Get user info endpoint
│   └── updateUser.php      # Update user endpoint
├── frontend/
│   ├── main.php
│   ├── loginb.php
│   ├── registerf.php
│   └── ...
└── README.md
```

## Step 5: Test the Backend

### Test Database Connection

1. Create a test file `backend/test-connection.php`:

```php
<?php
require 'config.php';
echo "Database connection successful!";
echo "<br>Database: " . $dbname;
?>
```

2. Open in browser: `http://localhost/FloxWatch/backend/test-connection.php`
3. If you see "Database connection successful!", you're good to go!
4. **Delete this test file** after verification for security

### Test Registration

1. Open `http://localhost/FloxWatch/frontend/registerf.php`
2. Fill in the registration form
3. Submit and check for success message
4. Verify the user was created in phpMyAdmin:
   - Go to `floxwatch` database → `users` table
   - You should see your new user entry

### Test Login

1. Open `http://localhost/FloxWatch/frontend/loginb.php`
2. Use the credentials you just registered
3. You should be redirected on successful login

## Backend API Endpoints

### POST `/backend/register.php`
Registers a new user.

**Request (Form Data):**
- `username` (string, 3-24 chars)
- `email` (string, valid email)
- `password` (string, min 6 chars)

**Response:**
- Success: `"User registered!"`
- Error: `"Error!"`

### POST `/backend/login.php`
Authenticates a user.

**Request (JSON):**
```json
{
  "email": "user@example.com",
  "password": "password123"
}
```

**Response (JSON):**
```json
{
  "success": true,
  "message": "Login successful!",
  "username": "NovaWatcher"
}
```

**Error Response:**
```json
{
  "success": false,
  "message": "Invalid email or password."
}
```

### GET/POST `/backend/logout.php`
Logs out the current user (destroys session).

### GET `/backend/getUser.php`
Gets current user information (requires active session).

### POST `/backend/updateUser.php`
Updates user information (requires active session).

### GET `/backend/getComments.php`
Returns the full comments tree for a video — includes top-level comments and nested replies. This endpoint performs a tree-build server-side and will also detect/promote orphan replies so they remain visible in the UI.

Query parameters:
- `video_id` (required)
- `user_id` (optional) — include to compute whether the requesting user has liked/disliked comments

Response: JSON { success: true, comments: [...], count: N }

### GET `/backend/getComment.php`
Fetches a single comment (by id) with nested replies and reaction metadata. Useful for client reconciliation or to refresh a single item after an update.

Query parameters:
- `comment_id` or `id` (required)
- `user_id` (optional)

Response: JSON { success: true, comment: { id, comment, replies: [...], likes, is_liked } }

## Troubleshooting

### "Access denied for user 'root'@'localhost'"

**Solution:**
1. Check your MySQL password in MAMP settings
2. Update `$pass` in `backend/config.php` to match
3. For MAMP PRO, check the MySQL settings in the MAMP PRO interface

### "Unknown database 'floxwatch'"

**Solution:**
1. Make sure you created the database (Step 2)
2. Verify the database name in `config.php` matches exactly
3. Check for typos (case-sensitive on some systems)

### "Table 'users' doesn't exist"

**Solution:**
1. Run the CREATE TABLE SQL command from Step 2
2. Verify you're using the correct database: `USE floxwatch;`

### Port Conflicts

**If port 80 or 8888 is already in use:**
1. In MAMP, go to **Preferences** → **Ports**
2. Change Apache port to an available port (e.g., `8080`)
3. Update your URLs accordingly: `http://localhost:8080/FloxWatch/...`

### Session Issues

**If sessions aren't working:**
1. Check PHP session configuration in `php.ini`
2. Ensure `session.save_path` is writable
3. Verify `session_start()` is called before any output

## Security Notes

- ⚠️ **Never commit `config.php` with real production credentials to version control**
- ⚠️ **Use environment variables or a `.env` file for production**
- ⚠️ **Change default MySQL password in production**
- ⚠️ **Enable HTTPS in production**
- ⚠️ **Validate and sanitize all user inputs** (already implemented with prepared statements)

## Next Steps

- Set up user profile functionality
- Add password reset feature
- Implement email verification
- Add rate limiting for API endpoints
- Set up proper error logging

## Support

If you encounter issues:
1. Check MAMP error logs: `Applications/MAMP/logs/`
2. Check PHP error logs: `Applications/MAMP/logs/php_error.log`
3. Enable error display in `php.ini` for debugging (disable in production)

---

**Happy coding! 🚀**

