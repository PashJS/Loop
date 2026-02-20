<?php
$server = "82.208.23.150";
$user = "root";
$pass = "hPwT865FSq31Z";

$files = [
    'frontend/home.php' => '/var/www/html/FloxWatch/frontend/home.php',
    'frontend/layout.css' => '/var/www/html/FloxWatch/frontend/layout.css'
];

echo "Connecting to FTP...\n";
$conn = ftp_connect($server, 21, 10);

if (!$conn) {
    die("Could not connect to FTP server.\n");
}

echo "Logging in...\n";
if (@ftp_login($conn, $user, $pass)) {
    echo "Logged in successfully!\n";
    ftp_pasv($conn, true); // Passive mode

    foreach ($files as $local => $remote) {
        echo "Uploading $local to $remote...\n";
        if (ftp_put($conn, $remote, $local, FTP_BINARY)) {
            echo "SUCCESS: Uploaded $local\n";
        } else {
            echo "FAILED: Could not upload $local\n";
        }
    }
    ftp_close($conn);
} else {
    echo "Login failed (Legacy FTP might be disabled, or wrong creds).\n";
}
?>
