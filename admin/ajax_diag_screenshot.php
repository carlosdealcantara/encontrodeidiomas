<?php
require_once dirname(__DIR__) . '/config.php';
header('Content-Type: application/json');

try {
    $active = $conn->query("
        SELECT q.id, q.titulo_final, q.status, q.last_screenshot_time, l.name as language_name, q.last_screenshot
        FROM odysee_publish_queue q
        LEFT JOIN languages l ON q.language_id = l.id
        WHERE q.status = 'processing' AND q.last_screenshot IS NOT NULL
        ORDER BY q.last_screenshot_time DESC LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC);

    if (!$active) {
        $active = $conn->query("
            SELECT q.id, q.titulo_final, q.status, q.last_screenshot_time, l.name as language_name, q.last_screenshot
            FROM odysee_publish_queue q
            LEFT JOIN languages l ON q.language_id = l.id
            WHERE q.last_screenshot IS NOT NULL
            ORDER BY q.last_screenshot_time DESC LIMIT 1
        ")->fetch(PDO::FETCH_ASSOC);
    }

    if ($active) {
        $active['last_screenshot_time_fmt'] = date('d/m H:i:s', strtotime($active['last_screenshot_time']));
        echo json_encode(['success' => true, 'data' => $active]);
    } else {
        echo json_encode(['success' => false]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
