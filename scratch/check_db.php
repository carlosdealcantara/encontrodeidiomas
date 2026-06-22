<?php
require_once dirname(__DIR__) . '/config.php';
$conn = connectDB();
try {
    $conn->exec("ALTER TABLE odysee_publish_queue ADD COLUMN last_screenshot LONGTEXT AFTER error_message");
    $conn->exec("ALTER TABLE odysee_publish_queue ADD COLUMN last_screenshot_time DATETIME AFTER last_screenshot");
    echo "Columns added successfully.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
