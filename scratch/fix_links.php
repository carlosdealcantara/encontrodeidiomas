<?php
require_once __DIR__ . '/../config.php';
$conn = connectDB();

echo "<h2>Limpando duplicatas (Tentativa 2)...</h2><pre>";

try {
    // Mantém apenas o menor ID para cada combinação de título e URL, usando TRIM para garantir
    $sql = "DELETE t1 FROM useful_links t1
            INNER JOIN useful_links t2 
            WHERE t1.id > t2.id 
            AND TRIM(t1.title) = TRIM(t2.title) 
            AND TRIM(t1.url) = TRIM(t2.url)";
    
    $count = $conn->exec($sql);
    echo "Removidos $count registros duplicados.\n";
} catch (PDOException $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}

echo "</pre>";
