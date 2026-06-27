<?php
require_once __DIR__ . '/config.php';

try {
    $conn->exec("UPDATE odysee_publish_queue SET status = 'error' WHERE status = 'processing' AND updated_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    echo json_encode(['success' => true, 'msg' => 'Zombies fixed']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
