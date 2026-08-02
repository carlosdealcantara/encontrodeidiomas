<?php
require_once dirname(__DIR__) . '/config.php';
$conn = connectDB();
$stmt = $conn->query("SELECT * FROM meetup_whatsapp_logs WHERE data_disparo = '2026-08-01' ORDER BY id DESC LIMIT 20");
print_r($stmt->fetchAll());
