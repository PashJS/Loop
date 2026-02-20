# Subscribe Button Debugging Guide

## If the Subscribe Button Doesn't Show:

### Step 1: Open Browser Console
Press **F12** and go to the **Console** tab

### Step 2: Look for These Messages

You should see:
```
Setting up subscribe button: {channelId: X, isSubscribed: false/true, subscriberCount: Y}
setupSubscribeButton called with: {channelId: X, isSubscribed: false/true, subscriberCount: Y}
Showing subscribe button
```

### Step 3: Check for Errors

#### Error: "Subscribe button element not found!"
**Problem:** The HTML element is missing
**Fix:** Make sure `videoid.php` has this code:
```html
<button class="subscribe-btn" id="subscribeBtn" style="display: none;">
    <i class="fa-solid fa-bell"></i>
    <span class="subscribe-text">Subscribe</span>
</button>
```

#### Error: "Subscribe text element not found!"
**Problem:** Missing `.subscribe-text` span
**Fix:** Check that the button has `<span class="subscribe-text">Subscribe</span>` inside it

#### No console logs at all
**Problem:** `setupSubscribeButton` is not being called
**Fix:** 
1. Check that `getUserProfile.php` is returning data
2. Look in Network tab for the request to `getUserProfile.php`
3. Check the response - it should have `is_subscribed` and `subscriber_count`

### Step 4: Check the Network Tab

1. Go to Network tab in F12
2. Filter by "getUserProfile"
3. Click on the request
4. Check the Response - should look like:
```json
{
  "success": true,
  "user": {
    "id": 1,
    "username": "...",
    "subscriber_count": 0,
    "is_subscribed": false,
    ...
  }
}
```

### Step 5: Verify SQL Was Run

The subscribe button needs the `subscriptions` table!

Run this in phpMyAdmin SQL tab:
```sql
SHOW TABLES LIKE 'subscriptions';
```

If it returns nothing, you need to run the SQL from `PHPMYADMIN.md`!

---

## Quick Fix Checklist

- [ ] Run ALL SQL commands from `PHPMYADMIN.md`
- [ ] Hard refresh page (Ctrl+Shift+R)
- [ ] Check browser console for errors
- [ ] Verify subscribe button HTML exists in `videoid.php`
- [ ] Check that you're NOT viewing your own video (button hides for own videos)

---

## Still Not Working?

1. Open console (F12)
2. Copy ALL the console logs and errors
3. Check what the console says - it will tell you exactly what's wrong!

The button is set to `display: none` by default and JavaScript makes it visible. If JavaScript has an error, the button stays hidden.
