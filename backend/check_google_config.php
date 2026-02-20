<?php
// backend/check_google_config.php
require_once 'google_config.php';

header('Content-Type: text/plain');

echo "Checking Google OAuth Configuration:\n\n";

echo "CLIENT ID: " . GOOGLE_CLIENT_ID . "\n";
if (strpos(GOOGLE_CLIENT_ID, 'YOUR_CLIENT_ID_HERE') !== false) {
    echo "❌ ERROR: You still have the placeholder in your Client ID!\n";
} else if (substr_count(GOOGLE_CLIENT_ID, '.apps.googleusercontent.com') > 1) {
    echo "❌ ERROR: Double suffix detected! You likely pasted the full ID into the placeholder that already had the suffix.\n";
} else {
    echo "✅ Client ID looks formatted correctly.\n";
}

echo "\nCLIENT SECRET: " . (GOOGLE_CLIENT_SECRET === 'YOUR_CLIENT_SECRET_HERE' ? '❌ STILL PLACEHOLDER' : '✅ SET') . "\n";

echo "\nREDIRECT URI: " . GOOGLE_REDIRECT_URL . "\n";
echo "Make sure this EXACT URI is added to 'Authorized redirect URIs' in Google Cloud Console.\n";
?>
