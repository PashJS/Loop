# FloxWatch Database Schema Setup Guide
------------------------------------------------------------
-- 1. LIKES TABLE (Unified for Likes & Dislikes)
------------------------------------------------------------
CREATE TABLE IF NOT EXISTS likes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    video_id INT NOT NULL,
    type ENUM('like', 'dislike') NOT NULL DEFAULT 'like',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE,
    UNIQUE KEY unique_like (user_id, video_id),
    INDEX idx_user (user_id),
    INDEX idx_video (video_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Note: The 'dislikes' table is deprecated and merged into 'likes'.

- WAMP (Windows example) — update path to match installed version:

```powershell
C:\wamp\bin\mysql\mysqlX.Y.Z\bin\mysql.exe -u root -p floxwatch < C:\path\to\yourfile.sql
```

Notes & troubleshooting
- If a phpMyAdmin query fails with a foreign key or syntax error, check the error message and the **Structure** tab to confirm columns / types match.
- If phpMyAdmin is printing PHP warnings/errors into the response, disable PHP display_errors for the environment (errors shown inline can corrupt API JSON returned to AJAX clients) and check your PHP/Apache logs for details.
- For very large SQL files, prefer the command-line import to avoid web timeouts or upload size limits.

If you'd like, I can add an exact PowerShell command for your environment (MAMP defaults) to run one of the repo SQL files (e.g. `backend/schema_likes_comments.sql`) — tell me which file and I will add that ready-to-run example.
**For XAMPP Users:**
- Navigate to: `http://localhost/phpMyAdmin/`

**For WAMP Users:**
- Navigate to: `http://localhost/phpMyAdmin/`

---

## Database Creation

If the `floxwatch` database does not exist, create it using the following commands:

```sql
-- Create database with UTF-8 support
CREATE DATABASE IF NOT EXISTS floxwatch 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

-- Select the database for subsequent operations
USE floxwatch;
```

**Note:** UTF-8 MB4 encoding supports full Unicode character sets, including emojis and special characters.

---

## Schema Updates

Copy and execute all commands in the SQL tab of phpMyAdmin. These commands are idempotent and safe to run multiple times.

### Complete SQL Script

```sql
-- ============================================
-- FLOXWATCH DATABASE SCHEMA CONFIGURATION
-- Version: 1.0
-- Last Updated: November 2025
-- ============================================

USE floxwatch;

-- ============================================
-- 1. COMMENTS TABLE: Enable Nested Replies
-- ============================================

-- Add parent_id column for comment threading
SET @dbname = DATABASE();
SET @tablename = "comments";
SET @columnname = "parent_id";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 1",
  "ALTER TABLE comments ADD COLUMN parent_id INT NULL;"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Establish foreign key relationship for comment hierarchy
SET @fk_check = (SELECT COUNT(*) 
                 FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                 WHERE TABLE_SCHEMA = DATABASE() 
                 AND TABLE_NAME = 'comments' 
                 AND CONSTRAINT_NAME = 'fk_comments_parent');

SET @add_fk = IF(@fk_check > 0, 
    'SELECT 1', 
    'ALTER TABLE comments ADD CONSTRAINT fk_comments_parent FOREIGN KEY (parent_id) REFERENCES comments(id) ON DELETE CASCADE');

PREPARE stmt FROM @add_fk;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================
-- 2. COMMENT ENGAGEMENT TABLES
-- ============================================

-- Comment Likes Table
CREATE TABLE IF NOT EXISTS comment_likes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    comment_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE,
    UNIQUE KEY unique_comment_like (user_id, comment_id),
    INDEX idx_comment_id (comment_id),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Comment Dislikes Table
CREATE TABLE IF NOT EXISTS comment_dislikes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    comment_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE,
    UNIQUE KEY unique_comment_dislike (user_id, comment_id),
    INDEX idx_comment_id (comment_id),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 3. VIDEOS TABLE: Media Storage Optimization
-- ============================================

-- Add video_url column
SET @columnname = "video_url";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = "videos")
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 1",
  "ALTER TABLE videos ADD COLUMN video_url VARCHAR(255) NOT NULL DEFAULT '';"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add thumbnail_url column
SET @columnname = "thumbnail_url";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = "videos")
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 1",
  "ALTER TABLE videos ADD COLUMN thumbnail_url VARCHAR(255) NULL;"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add is_clip column
SET @columnname = "is_clip";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = "videos")
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 1",
  "ALTER TABLE videos ADD COLUMN is_clip TINYINT(1) DEFAULT 0;"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add index for is_clip
SET @indexname = "idx_is_clip";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE
      (table_name = "videos")
      AND (table_schema = @dbname)
      AND (index_name = @indexname)
  ) > 0,
  "SELECT 1",
  "CREATE INDEX idx_is_clip ON videos(is_clip);"
));
PREPARE createIndexIfNotExists FROM @preparedStatement;
EXECUTE createIndexIfNotExists;
DEALLOCATE PREPARE createIndexIfNotExists;

-- ============================================
-- 4. USERS TABLE: Password Reset Functionality
-- ============================================

-- Add reset_token column
SET @columnname = "reset_token";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = "users")
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 1",
  "ALTER TABLE users ADD COLUMN reset_token VARCHAR(64) NULL;"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add reset_expires column
SET @columnname = "reset_expires";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = "users")
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 1",
  "ALTER TABLE users ADD COLUMN reset_expires TIMESTAMP NULL;"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- ============================================
-- 5. SUBSCRIPTIONS TABLE: Channel Follow System
-- ============================================

CREATE TABLE IF NOT EXISTS subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subscriber_id INT NOT NULL COMMENT 'User who is subscribing',
    channel_id INT NOT NULL COMMENT 'User being subscribed to',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (subscriber_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (channel_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_subscription (subscriber_id, channel_id),
    INDEX idx_subscriber (subscriber_id),
    INDEX idx_channel (channel_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='User subscription relationships for channel following';
-- ============================================
-- 6. NOTIFICATIONS TABLE: User Activity Alerts
-- ============================================

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

-- ============================================
-- 7. HASHTAGS TABLES: Video Tagging System
-- ============================================

CREATE TABLE IF NOT EXISTS hashtags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tag_name VARCHAR(50) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS video_hashtags (
    video_id INT NOT NULL,
    hashtag_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (video_id, hashtag_id),
    FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE,
    FOREIGN KEY (hashtag_id) REFERENCES hashtags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 8. SEARCH HISTORY TABLE
-- ============================================

CREATE TABLE IF NOT EXISTS search_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    query VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## Schema Features

### 1. Comment Threading System
- **Purpose:** Enable nested comment replies
- **Implementation:** `parent_id` column with self-referencing foreign key
- **Benefit:** Supports unlimited reply depth with automatic cascade deletion

### 2. Comment Engagement Tracking
- **Tables:** `comment_likes`, `comment_dislikes`
- **Features:** 
  - Prevents duplicate reactions per user
  - Optimized indexes for fast query performance
  - Automatic cleanup on user/comment deletion

### 3. Media Management
- **Columns:** `video_url`, `thumbnail_url`
- **Purpose:** Store video and thumbnail file locations
- **Type:** VARCHAR(255) for flexible path storage

### 4. Account Security
- **Columns:** `reset_token`, `reset_expires`
- **Purpose:** Secure password reset functionality
- **Security:** Token-based authentication with expiration

### 5. Subscription System
- **Purpose:** User-to-user following/subscription
- **Features:**
  - Unique constraint prevents duplicate subscriptions
  - Bidirectional relationship tracking
  - Optimized for subscriber count queries

---

## Verification

After executing the SQL commands, verify the schema updates:

### Method 1: Via phpMyAdmin UI

1. Navigate to the `floxwatch` database in the left sidebar
2. Click on each table name
3. Select the **Structure** tab
4. Verify the following:

| Table | Required Columns/Features |
|-------|--------------------------|
| `comments` | Contains `parent_id` column (INT, NULL) |
| `comment_likes` | Table exists with indexes |
| `comment_dislikes` | Table exists with indexes |
| `videos` | Contains `video_url`, `thumbnail_url`, `is_clip` columns |
| `users` | Contains `reset_token` and `reset_expires` columns |
| `subscriptions` | Table exists with unique constraint |
| `notifications` | Table exists with foreign keys |
| `hashtags` | Table exists |
| `video_hashtags` | Table exists with foreign keys |

### Method 2: Via SQL Query

Execute the following verification queries:

```sql
-- Verify comments table structure
DESCRIBE comments;

-- Verify new tables exist
SHOW TABLES LIKE 'comment_%';
SHOW TABLES LIKE 'subscriptions';
SHOW TABLES LIKE 'notifications';

-- Verify videos table columns
SHOW COLUMNS FROM videos WHERE Field IN ('video_url', 'thumbnail_url', 'is_clip');

-- Verify users table columns
SHOW COLUMNS FROM users WHERE Field IN ('reset_token', 'reset_expires');

-- Check foreign key constraints
SELECT 
    CONSTRAINT_NAME,
    TABLE_NAME,
    COLUMN_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = 'floxwatch'
AND CONSTRAINT_NAME LIKE 'fk_%';
```

---

## Troubleshooting

### Orphaned replies & visibility issues

If replies exist in the `comments` table (rows with `parent_id` set) but do not appear in the UI under the expected parent, use these diagnostic steps:

- List all comments for a given video (replace VIDEO_ID):

```sql
SELECT id, parent_id, user_id, video_id, created_at FROM comments WHERE video_id = VIDEO_ID ORDER BY created_at ASC;
```

- Find replies whose parent comment is missing (orphans):

```sql
SELECT r.id, r.parent_id, r.video_id, r.created_at
FROM comments r
LEFT JOIN comments p ON r.parent_id = p.id
WHERE r.parent_id IS NOT NULL AND p.id IS NULL AND r.video_id = VIDEO_ID;
```

- Optionally promote an orphan reply to top-level (careful: do this only if you understand the data):

```sql
-- Promote reply with id = REPLY_ID to a top-level comment
UPDATE comments SET parent_id = NULL WHERE id = REPLY_ID;
```

The backend has been updated to better detect and promote orphaned replies so they remain visible in the UI (see `backend/getComments.php`). If you need to inspect a single comment and its nested replies, use the new endpoint below.

### New server endpoint for single-comment fetch

`backend/getComment.php` — Fetches a single comment by id with nested replies and reaction metadata (helpful for client reconciliation or debugging):

Example:

```
GET /backend/getComment.php?comment_id=123&user_id=1

Response: { success:true, comment: { id: 123, comment: "...", replies: [...], likes: 0, is_liked: false } }
```

### Admin utilities (inspect & repair)

Two new admin endpoints are available to help inspect and repair orphaned replies:

- `backend/list_orphans.php` - Lists replies whose `parent_id` points to missing comments.
  - Query params: `video_id` (optional)
  - Example:

    ```powershell
    Invoke-WebRequest -UseBasicParsing "http://localhost/FloxWatch/backend/list_orphans.php?video_id=11" | Select-Object -Expand Content
    ```

- `backend/repair_orphans.php` - Safely promote orphaned replies to top-level (sets `parent_id = NULL`). This requires an explicit confirmation flag `confirm=1` to perform the update; calling without `confirm` returns a preview list.
  - Preview example (no changes):

    ```powershell
    Invoke-WebRequest -UseBasicParsing "http://localhost/FloxWatch/backend/repair_orphans.php?video_id=11" | Select-Object -Expand Content
    ```

  - Repair (perform update):

    ```powershell
    Invoke-WebRequest -UseBasicParsing "http://localhost/FloxWatch/backend/repair_orphans.php?video_id=11&confirm=1" -Method POST | Select-Object -Expand Content
    ```

Notes:
- These scripts are intended for administrators and should be used with care. Always back up your DB before running repair operations.
- The repair script updates the `comments.parent_id` to NULL for detected orphans so they will appear as top-level comments in the UI.



### Common Issues and Solutions

#### Error: "Duplicate key name"
**Status:** ⚠️ Warning (Safe to ignore)  
**Cause:** Foreign key constraint already exists  
**Action:** No action required; the script handles this gracefully

#### Error: "Column already exists"
**Status:** ✅ Expected behavior  
**Cause:** Column was added in a previous execution  
**Action:** No action required; the script is idempotent

#### Error: "Cannot add foreign key constraint"
**Possible Causes:**
1. Referenced table doesn't exist
2. Data type mismatch between foreign and primary keys
3. Existing data violates referential integrity

**Solutions:**
```sql
-- Check if referenced tables exist
SHOW TABLES LIKE 'users';
SHOW TABLES LIKE 'comments';

-- Verify data type consistency
DESCRIBE users;
DESCRIBE comments;

-- Check for orphaned records
SELECT * FROM comments WHERE parent_id IS NOT NULL 
AND parent_id NOT IN (SELECT id FROM comments);
```

#### Error: "Table doesn't exist"
**Cause:** Base tables not created yet  
**Solution:** Ensure core application tables exist before running updates:
- `users`
- `videos`
- `comments`

If missing, restore from application's initial database dump or contact support.

---

## Post-Installation Testing

Once schema updates are complete, test the following features:

### 1. Comment Replies
```sql
-- Test: Create a reply to an existing comment
INSERT INTO comments (user_id, video_id, comment, parent_id) 
VALUES (1, 1, 'This is a reply', 1);

-- Verify
SELECT id, comment, parent_id FROM comments WHERE parent_id IS NOT NULL;
```

### 2. Comment Engagement
```sql
-- Test: Like a comment
INSERT INTO comment_likes (user_id, comment_id) VALUES (1, 1);

-- Verify
SELECT COUNT(*) as like_count FROM comment_likes WHERE comment_id = 1;
```

### 3. Subscriptions
```sql
-- Test: Subscribe user 1 to user 2
INSERT INTO subscriptions (subscriber_id, channel_id) VALUES (1, 2);

-- Verify
SELECT COUNT(*) as subscriber_count FROM subscriptions WHERE channel_id = 2;
```

---

## Maintenance Recommendations

### Regular Maintenance Tasks

1. **Index Optimization** (Monthly)
```sql
OPTIMIZE TABLE comments, comment_likes, comment_dislikes, subscriptions;
```

2. **Subscription Analytics** (As needed)
```sql
-- Top channels by subscriber count
SELECT 
    u.username,
    COUNT(s.id) as subscriber_count
FROM users u
LEFT JOIN subscriptions s ON u.id = s.channel_id
GROUP BY u.id, u.username
ORDER BY subscriber_count DESC
LIMIT 10;
```

3. **Comment Engagement Stats** (As needed)
```sql
-- Most liked comments
SELECT 
    c.id,
    c.comment,
    COUNT(cl.id) as likes
FROM comments c
LEFT JOIN comment_likes cl ON c.id = cl.comment_id
GROUP BY c.id, c.comment
ORDER BY likes DESC
LIMIT 10;
```

---

## Additional Resources

### Documentation
- [MySQL 8.0 Reference Manual](https://dev.mysql.com/doc/refman/8.0/en/)
- [phpMyAdmin Documentation](https://docs.phpmyadmin.net/)

### Support
For issues or questions regarding this schema:
1. Check the `TESTING.md` file for feature testing guides
2. Review application logs for specific error messages
3. Ensure all foreign key references are valid

---

## Change Log

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | November 2025 | Initial schema documentation |
|  |  | - Comment threading support |
|  |  | - Engagement tracking tables |
|  |  | - Media management columns |
|  |  | - Password reset functionality |
|  |  | - Subscription system |

---

**Document Status:** Production Ready  
**Last Reviewed:** November 2025  
**Maintained By:** FloxWatch Development Team
