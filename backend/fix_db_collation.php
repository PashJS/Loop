<?php
require 'config.php';
try {
    echo "Fixing entire database collation...\n";
    $pdo->exec("ALTER DATABASE floxwatch CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "Database fixed.\n";
} catch (Exception $e) { echo $e->getMessage(); }
