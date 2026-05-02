<?php
require_once __DIR__ . '/../config.php';
$conn = connectDB();

echo "<h2>Debug Duplicatas</h2><pre>";

$stmt = $conn->query("SELECT title, url, COUNT(*) as c FROM useful_links GROUP BY title, url HAVING c > 1");
$dupes = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Combinações duplicadas:\n";
print_r($dupes);

foreach ($dupes as $d) {
    echo "Limpando: {$d['title']}\n";
    // Pega todos os IDs menos o menor
    $stmt = $conn->prepare("SELECT id FROM useful_links WHERE title = ? AND url = ? ORDER BY id ASC");
    $stmt->execute([$d['title'], $d['url']]);
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (count($ids) > 1) {
        array_shift($ids); // Remove o primeiro (menor ID)
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $del = $conn->prepare("DELETE FROM useful_links WHERE id IN ($placeholders)");
        $del->execute($ids);
        echo "Deletados IDs: " . implode(', ', $ids) . "\n";
    }
}

echo "</pre>";
