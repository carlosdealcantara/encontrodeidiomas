<?php
require_once __DIR__ . '/admin/config.php';

$conn = getDbConnection();
$stmt = $conn->prepare("UPDATE odysee_publish_queue SET status='pending' WHERE status='processing'");
$stmt->execute();
echo "Zombie tasks reset: " . $stmt->rowCount() . "\n";
