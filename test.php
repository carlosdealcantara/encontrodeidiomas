<?php
require_once __DIR__ . '/config.php';
$conn = connectDB();
$stmt = $conn->query("SELECT * FROM odysee_publish_queue WHERE language_id = 13 ORDER BY id DESC LIMIT 5");
$rows = $stmt->fetchAll();
echo json_encode($rows, JSON_PRETTY_PRINT);
