<?php
require_once __DIR__ . '/../config.php';
$conn = connectDB();
$stmt = $conn->prepare("SELECT * FROM mentoria_auto_logs WHERE data_execucao >= '2026-06-20' ORDER BY id DESC LIMIT 10");
$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt2 = $conn->prepare("SELECT * FROM mentoria_desafio_streaks WHERE last_completed_date >= '2026-06-19'");
$stmt2->execute();
$streaks = $stmt2->fetchAll(PDO::FETCH_ASSOC);

echo "=== Auto Logs ===\n";
print_r($logs);
echo "\n=== Streaks ===\n";
print_r($streaks);
