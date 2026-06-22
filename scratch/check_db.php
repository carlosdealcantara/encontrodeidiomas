<?php
require_once dirname(__DIR__) . '/config.php';
$conn = connectDB();
try {
    $stmt = $conn->query("SELECT id, status, last_screenshot_time FROM odysee_publish_queue WHERE language_id = (SELECT id FROM languages WHERE name = 'Inglês' LIMIT 1)");
    $row = $stmt->fetch();
    echo "Status Atual: " . $row['status'] . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
