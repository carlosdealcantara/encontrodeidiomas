<?php
require_once 'config.php';
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $res = $conn->query("DESCRIBE hosts");
    echo "<h1>Colunas da tabela 'hosts':</h1><ul>";
    while($row = $res->fetch_assoc()) {
        echo "<li>" . $row['Field'] . "</li>";
    }
    echo "</ul>";
} catch (Exception $e) {
    echo "Erro ao acessar o banco: " . $e->getMessage();
}
?>
