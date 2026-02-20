<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loop</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<h1>
    <?php include('icon.html'); ?>
    Loop
</h1>

<hr>

<div class="intro">
    <h2>Settle into a cozy space of videos and community. Sign up to get started.</h2>

    <div class="auth-buttons">
        <a href="registerf.php" class="btn primary">Sign Up</a>
        <a href="loginb.php" class="btn secondary">Sign In</a>
    </div>
</div>

<div class="info">
    <p>A lot of info coming soon.</p>
</div>
<footer style="text-align: center; margin-top: 20px; font-opacity: 0.6;">
    <p>&copy; <?php
// Method 1: Using date()
$currentYear = date("Y"); // "Y" returns the full 4-digit year
echo $currentYear;
?>
 Loop. All rights reserved.</p>
</footer>

</body>
</html>
