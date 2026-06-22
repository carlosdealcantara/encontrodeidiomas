<?php
require_once dirname(__DIR__) . '/config.php';
$conn = connectDB();
$stmt = $conn->query("SELECT * FROM odysee_publish_queue ORDER BY id DESC LIMIT 5");
$res = $stmt->fetchAll();
echo json_encode($res, JSON_PRETTY_PRINT);
