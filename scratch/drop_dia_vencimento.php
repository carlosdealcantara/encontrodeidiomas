<?php
require_once __DIR__ . '/../config.php';
$conn = connectDB();
try {
    $conn->exec("ALTER TABLE mentoria_alunos DROP COLUMN dia_vencimento");
    echo "Coluna removida com sucesso!";
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
