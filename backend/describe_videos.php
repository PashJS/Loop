<?php
require 'config.php';
$stmt = $pdo->query("DESCRIBE videos");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
}
?>
