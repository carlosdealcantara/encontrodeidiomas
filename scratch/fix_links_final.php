<?php
require_once __DIR__ . '/../config.php';
$conn = connectDB();

echo "<h2>Limpando por ID (Tentativa Final)...</h2><pre>";

try {
    $count = $conn->exec("DELETE FROM useful_links WHERE id > 6");
    echo "Removidos $count registros.\n";
} catch (PDOException $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}

echo "</pre>";
