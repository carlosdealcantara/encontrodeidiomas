<?php
require_once 'config.php';

function setupDatabase() {
    $conn = connectDB();
    $sql = file_get_contents('schema.sql');
    
    if (!$sql) {
        die("Erro: schema.sql não encontrado.\n");
    }

    try {
        // Executa o SQL (pode conter múltiplos comandos)
        $conn->exec($sql);
        echo "Banco de dados configurado com sucesso!\n";
    } catch (PDOException $e) {
        echo "Erro ao configurar banco de dados: " . $e->getMessage() . "\n";
    }
}

setupDatabase();
