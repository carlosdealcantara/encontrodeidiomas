<?php
require_once 'config.php';

function listColumns($table) {
    echo "\n--- COLUNAS DA TABELA: $table ---\n";
    try {
        $conn = connectDB();
        $stmt = $conn->query("DESCRIBE $table");
        while ($row = $stmt->fetch()) {
            echo "Campo: {$row['Field']} | Tipo: {$row['Type']} | Nulo: {$row['Null']}\n";
        }
    } catch (Exception $e) {
        echo "Erro ao ler $table: " . $e->getMessage() . "\n";
    }
}

listColumns('languages');
listColumns('events');
?>
