<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: text/plain; charset=utf-8');

$conn = connectDB();

echo "=== HOSTS COM WELLINGTON ===\n";
$stmt = $conn->query("SELECT id, name, photo, profile_picture, active FROM hosts WHERE name LIKE '%Wellington%'");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    print_r($r);
}

echo "\n=== ARQUIVOS EM assets/images/ COM WELLINGTON ===\n";
foreach (glob(__DIR__ . '/../assets/images/*ellington*') as $f) {
    echo basename($f) . " (" . filesize($f) . " bytes)\n";
}

echo "\n=== ARQUIVOS EM assets/uploads/ COM WELLINGTON ===\n";
foreach (glob(__DIR__ . '/../assets/uploads/hosts/*ellington*') as $f) {
    echo basename($f) . " (" . filesize($f) . " bytes)\n";
}
