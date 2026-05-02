<?php
require_once __DIR__ . '/../config.php';
$conn = connectDB();

echo "<h2>Verificando o que será deletado...</h2><pre>";

$stmt = $conn->query("SELECT id, title FROM useful_links WHERE id NOT IN (
    SELECT id FROM (
        SELECT MIN(id) as id FROM useful_links GROUP BY title, url
    ) as tmp
)");
$toDelete = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($toDelete);

if (count($toDelete) > 0) {
    echo "Deletando...\n";
    $ids = array_column($toDelete, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $conn->prepare("DELETE FROM useful_links WHERE id IN ($placeholders)");
    $stmt->execute($ids);
    echo "Removidos " . $stmt->rowCount() . " registros.\n";
} else {
    echo "Nada para deletar.\n";
}

echo "</pre>";
