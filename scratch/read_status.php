<?php
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

require_once dirname(__DIR__) . '/config.php';
$conn = connectDB();
try {
    $stmt = $conn->query("SELECT id, status, last_screenshot_time FROM odysee_publish_queue WHERE language_id = (SELECT id FROM languages WHERE name = 'Inglês' LIMIT 1)");
    $row = $stmt->fetch();
    echo "STATUS ATUAL: " . $row['status'] . "\n";
    echo "ULTIMA FOTO: " . $row['last_screenshot_time'] . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
