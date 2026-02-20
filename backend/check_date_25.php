<?php
$json = file_get_contents('http://localhost/FloxWatch/backend/getVideoById.php?id=25');
$data = json_decode($json, true);
echo "Created At: " . $data['video']['created_at'];
?>
