# FloxWatch - Testing & Debugging Guide

## CRITICAL: Did You Run the SQL Commands?

**The #1 reason replying doesn't work is because the database is missing the `parent_id` column!**

Before testing ANYTHING, open phpMyAdmin and run the commands from `PHPMYADMIN.md`.

### Quick Check
Run this in phpMyAdmin SQL tab:
```sql
DESCRIBE comments;
```

You should see a `parent_id` column. If you DON'T see it, **replying will NOT work!**

---

## Testing the Fixes

### 1. Mobile Search (Small Screens Only)

**On mobile/small screen (< 768px width):**
✅ Search bar should be COMPLETELY hidden
✅ Only a magnifying glass button should show
✅ Clicking the button navigates to `search.php`

**Test it:**
1. Resize your browser to mobile width (< 768px)
2. Look at the top navigation
3. You should ONLY see: Logo | Magnifying Glass Button | Bell | Profile
4. Click the magnifying glass → Should go to search.php

---

### 2. Notifications/Bell Icon

**What was fixed:**
- Bell now properly shows/hides loading state
- Empty notifications display correctly

**Test it:**
1. Click the bell icon
2. It should show either:
   - Your notifications (if you have any)
   - "No notifications" message (if empty)
3. Should NOT show "Loading..." indefinitely

**Debug if broken:**
- Open browser console (F12)
- Click the bell icon
- Check for errors in console
- Look at Network tab → Check if `getNotifications.php` is being called
- If it returns errors, there's a backend issue

---

### 3. Replying to Comments

**What was fixed:**
- Added console logging to help debug
- Better error messages

**Test it:**
1. Go to any video (videoid.php?id=1)
2. Find a comment
3. Click "Reply" button
4. Type a reply and click "Reply" button
5. Open browser console (F12) to see logs

**Expected behavior:**
✅ Reply form appears when you click Reply
✅ You can type in the textarea
✅ After clicking Reply:
  - Console shows: "Sending reply: {video_id: X, parent_id: Y, comment: 'your text'}"
  - Console shows: "Reply response: {success: true, ...}"
  - Green popup: "Reply posted successfully!"
  - Comments reload and your reply appears

**If it fails:**

Check the console for one of these messages:

1. **"Reply response: {success: false, message: '...'}"**
   - Read the error message
   - Most common: "Invalid video ID or parent comment ID"
   - **FIX**: Run the SQL commands! The `parent_id` column is missing!

2. **Network error**
   - Check if `addReply.php` is being called in Network tab
   - Check for PHP errors in the response

3. **"Invalid video ID or parent comment ID"**
   - **This means you haven't run the SQL commands!**
   - The backend is trying to insert into a `parent_id` column that doesn't exist
   - **FIX**: Go to phpMyAdmin and run ALL the commands from PHPMYADMIN.md

---

## Common Issues

### "I ran the SQL but replying still doesn't work!"

1. **Did you refresh the page?** After running SQL, hard refresh (Ctrl+Shift+R)

2. **Check the console!** Open F12 and look for errors. The console logs will tell you EXACTLY what's wrong.

3. **Verify the column exists:**
   ```sql
   DESCRIBE comments;
   ```
   Look for `parent_id`. If it's not there, the SQL didn't run properly.

4. **Check for SQL errors:** When you ran the commands in phpMyAdmin, did you see any red error messages?

### "Bell icon still says Loading..."

1. Open browser console (F12)
2. Look for errors
3. Check Network tab → Filter by `getNotifications`
4. Click on the request and check the response
5. If the response is an error, the backend has an issue

### "Mobile search button doesn't work"

1. Make sure you're actually on a small screen (< 768px)
2. Check browser console for errors
3. Make sure `mobile-search.js` is loaded (check Sources tab in F12)

---

## Database Schema Checklist

Run these in phpMyAdmin and verify:

```sql
-- Should show parent_id column
DESCRIBE comments;

-- These tables should exist
SHOW TABLES LIKE 'comment_likes';
SHOW TABLES LIKE 'comment_dislikes';

-- Videos table should have these columns
DESCRIBE videos; -- Look for video_url and thumbnail_url

-- Users table should have these
DESCRIBE users; -- Look for reset_token and reset_expires
```

If ANY of these checks fail, go back to PHPMYADMIN.md and run ALL the commands again!

---

## Still Broken?

If something still doesn't work after following this guide:

1. **Open browser console (F12)**
2. **Try the action that's broken**
3. **Copy ALL the console logs (red errors especially)**
4. **Copy the exact error message**
5. **Take a screenshot if needed**

Then tell me:
- What you're trying to do
- What happens instead
- What the console says
- What SQL commands you ran (if any)

---

**Remember: 90% of issues are because the SQL wasn't run! Check the database first!**
