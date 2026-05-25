<?php
require_once __DIR__ . '/../config.php';

try {
    $conn = connectDB();
    $sql = file_get_contents(__DIR__ . '/../schema.sql');
    $conn->exec($sql);
    echo "Sucesso: O banco de dados foi atualizado com o novo schema.sql!\n";
} catch (PDOException $e) {
    echo "Erro ao atualizar o banco de dados: " . $e->getMessage() . "\n";
}
?>
