<?php
require_once __DIR__ . '/../config.php';

try {
    $conn = connectDB();
    
    // Rename tables
    $conn->exec("RENAME TABLE meetup_schedule TO class_schedule");
    echo "Tabela class_schedule renomeada com sucesso.<br>";
    
    $conn->exec("RENAME TABLE meetup_attendances TO class_attendances");
    echo "Tabela class_attendances renomeada com sucesso.<br>";

    // Update mentoria_auto_logs to replace 'meetup' with 'class'
    $conn->exec("UPDATE mentoria_auto_logs SET tipo = REPLACE(tipo, 'meetup_', 'class_') WHERE tipo LIKE 'meetup_%'");
    echo "Logs renomeados com sucesso.<br>";
    
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
