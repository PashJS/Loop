# Notification Bell Debugging Guide

I've enhanced the notification system with detailed error logging. Here's how to debug the "Failed to fetch" error:

## 🔍 How to Debug

1. **Hard Refresh**: Press `Ctrl+Shift+R` to clear cache
2. **Open Browser Console**: Press `F12` and go to the "Console" tab
3. **Click the Bell Icon**: This will trigger the notification fetch
4. **Check Console Output**: You should see detailed logs like:
   ```
   Fetching notifications from: ../backend/getNotifications.php?limit=50
   Response status: 200
   Response ok: true
   Notifications data: {success: true, notifications: [], ...}
   ```

## ❌ Common Errors & Solutions

### Error: "HTTP error! status: 404"
**Cause**: The backend file path is wrong or file doesn't exist
**Solution**: 
- Check that `backend/getNotifications.php` exists
- Verify you're accessing from `frontend/home.php` (so `../backend/` is correct)

### Error: "HTTP error! status: 500"
**Cause**: PHP error in the backend
**Solution**:
- Open `http://localhost:8888/FloxWatch/backend/getNotifications.php?limit=50` directly in browser
- Check for PHP errors
- Verify database connection in `backend/config.php`

### Error: "Not authenticated"
**Cause**: User is not logged in
**Solution**: Make sure you're logged in to FloxWatch

### Error: "Failed to fetch"
**Cause**: Network error or CORS issue
**Solution**:
- Make sure MAMP is running
- Check that MySQL is running in MAMP
- Verify the database `floxwatch` exists

## 🛠️ Manual Database Check

Run this SQL in phpMyAdmin to create the notifications table if it doesn't exist:

```sql
USE floxwatch;

CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('comment_like', 'comment_reply', 'video_like', 'video_comment') NOT NULL,
    actor_id INT NULL,
    target_id INT NULL,
    target_type ENUM('comment', 'video', 'comment_reply') NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_is_read (is_read),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## 📋 What to Send Me

If it's still not working, please copy and paste the **exact console output** from the browser console after clicking the bell.
