<?php
require 'config.php';
$stmt = $pdo->query("SELECT email FROM users");
while ($row = $stmt->fetch()) {
    echo $row['email'] . "\n";
}
?>
