<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../config.php';
header('Content-Type: text/plain; charset=utf-8');

try {
    $conn = connectDB();
    echo "=== HOSTS COM WELLINGTON ===\n";
    $stmt = $conn->query("SELECT * FROM hosts WHERE full_name LIKE '%Wellington%'");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    print_r($rows);

    echo "\n=== ARQUIVOS EM assets/images/ COM WELLINGTON ===\n";
    foreach (glob(__DIR__ . '/../assets/images/*ellington*') as $f) {
        echo basename($f) . " (" . filesize($f) . " bytes)\n";
    }

    echo "\n=== ARQUIVOS EM assets/uploads/hosts/ COM WELLINGTON ===\n";
    foreach (glob(__DIR__ . '/../assets/uploads/hosts/*ellington*') as $f) {
        echo basename($f) . " (" . filesize($f) . " bytes)\n";
    }
} catch (Throwable $e) {
    echo "Erro: " . $e->getMessage() . "\n" . $e->getTraceAsString();
}
