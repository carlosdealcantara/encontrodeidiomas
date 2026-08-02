<?php
require_once dirname(__DIR__) . '/config.php';
$conn = connectDB();
$stmt = $conn->query("SELECT COUNT(*) as total FROM meetup_whatsapp_logs WHERE data_disparo >= '2026-07-01'");
print_r($stmt->fetch());
