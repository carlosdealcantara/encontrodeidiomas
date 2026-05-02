<?php
require_once __DIR__ . '/../config.php';
$conn = connectDB();

echo "<h2>Limpando duplicatas...</h2><pre>";

try {
    // Mantém apenas o menor ID para cada combinação de título e URL
    $sql = "DELETE t1 FROM useful_links t1
            INNER JOIN useful_links t2 
            WHERE t1.id > t2.id 
            AND t1.title = t2.title 
            AND t1.url = t2.url";
    
    $count = $conn->exec($sql);
    echo "Removidos $count registros duplicados.\n";
} catch (PDOException $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}

echo "</pre>";
