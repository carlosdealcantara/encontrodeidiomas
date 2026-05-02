<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../config.php';
$conn = connectDB();

// Verifica colunas da tabela languages
echo "<h2>Colunas de 'languages'</h2><pre>";
$cols = $conn->query("DESCRIBE languages")->fetchAll(PDO::FETCH_COLUMN);
print_r($cols);
echo "</pre>";

// Testa a query com instagram_link
echo "<h2>Teste getMeetings()</h2><pre>";
try {
    $stmt = $conn->prepare("
        SELECT m.id, l.name, l.instagram_link
        FROM meetings m JOIN languages l ON m.language_id = l.id
        LIMIT 1
    ");
    $stmt->execute();
    print_r($stmt->fetch());
} catch (PDOException $e) {
    echo "ERRO: " . $e->getMessage();
}
echo "</pre>";
