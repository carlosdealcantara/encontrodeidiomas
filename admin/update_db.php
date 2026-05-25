<?php
require_once '../config.php';

try {
    $conn = connectDB();
    
    // Tenta adicionar a coluna data_inicio se não existir
    $conn->exec("ALTER TABLE mentoria_alunos ADD COLUMN data_inicio DATE NULL AFTER observacoes");
    
    echo "<h2>Banco de Dados Atualizado!</h2>";
    echo "<p>A coluna 'data_inicio' foi adicionada com sucesso.</p>";
} catch (PDOException $e) {
    // Se der erro, provavelmente a coluna já existe
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "A coluna 'data_inicio' já existe no banco. Tudo certo!";
    } else {
        echo "Erro ao atualizar banco: " . $e->getMessage();
    }
}
?>
