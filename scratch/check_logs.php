<?php
require_once dirname(__DIR__) . '/config.php';
$conn = connectDB();
$stmt = $conn->query("SELECT COUNT(*) as total FROM meetup_whatsapp_logs WHERE data_disparo >= '2026-07-01'");
echo "Count: " . print_r($stmt->fetch(), true);
$ch = curl_init("http://136.248.92.126:3000/connection-status");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$res = curl_exec($ch);
echo "DIRECT BODY: " . $res . "\n";
curl_close($ch);
