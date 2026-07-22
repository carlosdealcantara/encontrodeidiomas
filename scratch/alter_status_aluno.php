<?php
require_once __DIR__ . '/../config.php';
$conn = connectDB();
try {
    $conn->exec("ALTER TABLE mentoria_alunos MODIFY COLUMN status_aluno ENUM('Ativo','Inativo','Vitalício','Comunidade') DEFAULT 'Ativo'");
    echo "ENUM modificado com sucesso!";
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
