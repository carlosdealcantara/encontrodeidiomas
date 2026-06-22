<?php
require_once dirname(__DIR__) . '/config.php';
$conn = connectDB();
$stmt = $conn->query("UPDATE odysee_publish_queue SET status = 'pending' WHERE status = 'processing'");
echo "Affected rows: " . $stmt->rowCount();
