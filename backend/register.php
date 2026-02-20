<?php
require 'config.php';

$username = $_POST['username'];
$email = $_POST['email'];
$password = $_POST['password'];
$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("INSERT INTO users (username,email,password) VALUES (?,?,?)");
if ($stmt->execute([$username,$email,$hash])) {
    echo "User registered!";
} else {
    echo "Error!";
}
?>
