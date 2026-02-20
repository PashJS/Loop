<?php
// backend/google_auth.php
require_once 'google_config.php';
header('Location: ' . getGoogleAuthUrl());
exit;
?>
