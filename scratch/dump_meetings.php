<?php
require_once __DIR__ . '/../config.php';
$conn = connectDB();
$stmt = $conn->query("SELECT * FROM meetings");
$meetings = $stmt->fetchAll();
echo "Total: " . count($meetings) . "\n";
foreach ($meetings as $m) {
    echo "ID: {$m['id']} | Lang: {$m['language_id']} | Host: {$m['host_id']} | Day: {$m['day_of_week']} | Active: {$m['active']}\n";
}
