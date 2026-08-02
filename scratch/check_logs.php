<?php
require_once dirname(__DIR__) . '/config.php';
$conn = connectDB();
$stmt = $conn->query("SELECT * FROM meetup_whatsapp_logs ORDER BY id DESC LIMIT 50");
print_r($stmt->fetchAll());
