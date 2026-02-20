<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: loginb.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loop - Search TEST</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            background-color: #111 !important;
            color: white !important;
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        .test-box {
            background: lime;
            color: black;
            padding: 30px;
            margin: 20px 0;
            border: 5px solid red;
            font-size: 24px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="test-box">
        IF YOU SEE THIS GREEN BOX, PHP IS WORKING!
    </div>
    
    <div style="background:yellow;color:black;padding:20px;margin:20px 0;">
        <h1>Search Test Page</h1>
        <p>Query: <?php echo htmlspecialchars($_GET['q'] ?? 'None'); ?></p>
    </div>
    
    <div id="searchResults" style="background:#222;color:white;padding:20px;min-height:200px;border:2px solid white;">
        <h2>Search Results Container</h2>
        <p>This should be visible with white border</p>
    </div>
    
    <script>
        console.log('=== TEST PAGE SCRIPT ===');
        document.body.style.border = '10px solid blue';
    </script>
</body>
</html>

