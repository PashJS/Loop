<?php
require_once __DIR__ . '/../backend/config.php';
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN xpoints INT DEFAULT 0");
    echo "xpoints column added successfully.";
} catch (PDOException $e) {
    if ($e->getCode() == '42S21') {
        echo "xpoints column already exists.";
    } else {
        echo "Error: " . $e->getMessage();
    }
}
?>
