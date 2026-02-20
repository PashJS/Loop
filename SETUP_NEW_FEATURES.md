# New Features Setup Guide

## Database Updates Required

### 1. Add Bio Column to Users Table

Run this SQL in phpMyAdmin or execute `backend/addBioColumn.php`:

```sql
ALTER TABLE users ADD COLUMN IF NOT EXISTS bio TEXT NULL;
```

### 2. Create Pinned Comments Table

This will be created automatically when you first pin a comment, but you can also run:

```sql
CREATE TABLE IF NOT EXISTS pinned_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    comment_id INT NOT NULL,
    video_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE,
    FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE,
    UNIQUE KEY unique_pinned_comment (comment_id),
    INDEX idx_video_id (video_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3. Create View History Table (for Analytics)

This will be created automatically when a video is viewed, but you can also run:

```sql
CREATE TABLE IF NOT EXISTS view_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    video_id INT NOT NULL,
    viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE,
    INDEX idx_video_id (video_id),
    INDEX idx_viewed_at (viewed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## New Features Summary

### ✅ Comment Moderation
- Comments are automatically filtered using `banned_words.json`
- Inappropriate words are replaced with asterisks
- Comments containing banned words are blocked

### ✅ Profile Pictures
- Profile pictures now display globally across all pages
- Navigation avatars, dropdown avatars, and comment avatars show profile pictures
- Fallback to initial letter if no picture is set

### ✅ Video Deletion
- Delete button added to each video in "My Videos" page
- Confirmation popup before deletion
- Video files and thumbnails are removed from server

### ✅ Creator Features
- Creator badge appears next to video creator's name in comments
- Creators can pin/unpin comments (one pinned comment per video)
- Creators can delete any comment on their videos
- Pinned comments appear at the top of the comment list

### ✅ Comment Editing & Deleting
- Users can edit their own comments
- Users can delete their own comments
- Creators can delete any comment on their videos
- Edit button appears for comment owners
- Delete button appears for comment owners and video creators

### ✅ User Profiles
- New `user_profile.php` page to view any user's profile
- Shows user's display name, username, profile picture, bio
- Displays all user's published videos
- Clickable links to user profiles from video cards and comments

### ✅ Bio Feature
- Bio field added to account management
- Character counter (500 max)
- Bio displays on user profile page

### ✅ Extra Polish
- Smooth animations and transitions
- Hover effects on interactive elements
- Better visual feedback
- Improved error handling
- Profile picture hover effects
- Clickable user links throughout the app

## File Changes

### New Backend Files:
- `backend/moderateComment.php` - Comment moderation function
- `backend/deleteVideo.php` - Video deletion endpoint
- `backend/deleteComment.php` - Comment deletion endpoint
- `backend/editComment.php` - Comment editing endpoint
- `backend/pinComment.php` - Comment pinning endpoint
- `backend/getUserProfile.php` - User profile data endpoint
- `backend/addBioColumn.php` - Database migration script

### New Frontend Files:
- `frontend/user_profile.php` - User profile page
- `frontend/user_profile.css` - Profile page styles
- `frontend/user_profile.js` - Profile page functionality

### Updated Files:
- All frontend pages updated with Font Awesome icons
- Profile picture display updated globally
- Comment system enhanced with creator features
- Account management updated with bio field

## Usage

1. **Run Database Migrations**: Execute the SQL commands above or run `backend/addBioColumn.php`

2. **Comment Moderation**: The system automatically uses `banned_words.json` to filter comments

3. **Profile Pictures**: Upload a profile picture in Account Management, and it will appear everywhere

4. **Video Analytics**: Click "Analytics" button on any video in "My Videos" to see view statistics

5. **User Profiles**: Click on any username or avatar to view their profile

6. **Comment Management**: 
   - Edit your comments by clicking the pencil icon
   - Delete your comments by clicking the trash icon
   - Creators can pin comments using the thumbtack icon

Enjoy your enhanced FloxWatch experience! 🚀

