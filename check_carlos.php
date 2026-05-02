<?php
require_once 'config.php';
try {
    $conn = connectDB();
    $stmt = $conn->prepare("SELECT * FROM hosts LIMIT 1");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<h3>Colunas encontradas na tabela 'hosts':</h3>";
    echo "<pre>";
    print_r(array_keys($row));
    echo "</pre>";

    $stmt = $conn->prepare("SELECT * FROM hosts WHERE full_name LIKE '%Carlos de Alcântara%'");
    $stmt->execute();
    $carlos = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<h3>Dados do Carlos:</h3>";
    echo "<pre>";
    print_r($carlos);
    echo "</pre>";
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
?>
