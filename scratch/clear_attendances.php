<?php
require_once __DIR__ . '/../config.php';

try {
    $conn = connectDB();
    $conn->exec("TRUNCATE TABLE class_attendances");
    echo "Todas as presenças de teste foram apagadas com sucesso!";
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
