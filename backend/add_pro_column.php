<?php
require_once __DIR__ . '/../backend/config.php';
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN is_pro TINYINT(1) DEFAULT 0");
    echo "is_pro column added successfully.";
} catch (PDOException $e) {
    if ($e->getCode() == '42S21') {
        echo "is_pro column already exists.";
    } else {
        echo "Error: " . $e->getMessage();
    }
}
?>
