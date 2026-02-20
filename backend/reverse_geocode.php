<?php
// backend/reverse_geocode.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$lat = isset($_GET['lat']) ? $_GET['lat'] : null;
$lon = isset($_GET['lon']) ? $_GET['lon'] : null;

if (!$lat || !$lon) {
    echo json_encode(['error' => 'Missing lat/lon']);
    exit;
}

// Nominatim requires User-Agent
$opts = [
    "http" => [
        "header" => "User-Agent: FloxWatch/1.0\r\n"
    ]
];
$context = stream_context_create($opts);
$url = "https://nominatim.openstreetmap.org/reverse?format=json&lat={$lat}&lon={$lon}&zoom=10";

$response = @file_get_contents($url, false, $context);

if ($response === FALSE) {
     echo json_encode(['error' => 'Valid Request Failed']);
} else {
     echo $response;
}
?>
