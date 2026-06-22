<?php
require_once dirname(__DIR__) . '/config.php';
$conn = connectDB();
$stmt = $conn->query("SELECT id, status, retry_count, error_message, titulo_final FROM odysee_publish_queue ORDER BY id ASC LIMIT 5");
$res = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($res, JSON_PRETTY_PRINT);
