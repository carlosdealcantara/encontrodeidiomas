<?php
require_once dirname(__DIR__) . '/config.php';
$conn = connectDB();
$stmt = $conn->query("SHOW CREATE TABLE meetup_whatsapp_logs");
print_r($stmt->fetch());
