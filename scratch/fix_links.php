<?php
require_once __DIR__ . '/../config.php';
$conn = connectDB();

echo "<h2>Limpando duplicatas (Tentativa 3)...</h2><pre>";

try {
    // Mantém apenas o menor ID para cada combinação de título e URL usando NOT IN
    $sql = "DELETE FROM useful_links WHERE id NOT IN (
                SELECT min_id FROM (
                    SELECT MIN(id) as min_id FROM useful_links GROUP BY title, url
                ) as tmp
            )";
    
    $count = $conn->exec($sql);
    echo "Removidos $count registros duplicados.\n";
} catch (PDOException $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}

echo "</pre>";
