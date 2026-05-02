<?php
require_once __DIR__ . '/../config.php';
$conn = connectDB();

echo "<h2>Limpando Um por Um</h2><pre>";

$stmt = $conn->query("SELECT id FROM useful_links");
$ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "IDs atuais: " . implode(', ', $ids) . "\n";

$toKeep = [1, 2, 3, 4, 5, 6];
$toDelete = array_diff($ids, $toKeep);

if (empty($toDelete)) {
    echo "Nada para deletar baseado nos IDs de referência.\n";
} else {
    echo "Deletando IDs: " . implode(', ', $toDelete) . "\n";
    foreach ($toDelete as $id) {
        $conn->exec("DELETE FROM useful_links WHERE id = $id");
    }
    echo "Finalizado.\n";
}

echo "</pre>";
