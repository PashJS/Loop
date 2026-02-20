<?php
header('Content-Type: application/json');

// PHP Proxy for the media server to avoid direct-to-port fetch errors in dev
$url = "http://127.0.0.1:8080/streams";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 1); // Fast timeout for a proxy
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($response === false) {
    // Media server is offline (e.g. Connection Refused)
    echo json_encode([
        'success' => false, 
        'message' => 'Media server is unreachable',
        'error' => $error
    ]);
} else if ($httpCode !== 200) {
    echo json_encode([
        'success' => false, 
        'message' => 'Media server returned error',
        'code' => $httpCode
    ]);
} else {
    // Success, pass through the response
    echo $response;
}
?>
