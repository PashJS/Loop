<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (isset($data['file']) && isset($data['content'])) {
        $file = $data['file'];
        $content = base64_decode($data['content']);
        if (file_put_contents($file, $content) !== false) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to write file']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Missing data']);
    }
    exit;
}
?>
