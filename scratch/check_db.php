<?php
require_once dirname(__DIR__) . '/config.php';
$conn = connectDB();
$stmt = $conn->query("UPDATE odysee_publish_queue SET status = 'pending', retry_count = 0 WHERE status = 'error'");
echo "Affected rows: " . $stmt->rowCount();
