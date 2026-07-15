<?php
require_once __DIR__ . '/../config.php';

try {
    $conn = connectDB();
    
    $sql = "ALTER TABLE class_schedule ADD COLUMN session_type ENUM('teacher_class', 'student_practice') NOT NULL DEFAULT 'teacher_class' AFTER meet_link";
    
    $conn->exec($sql);
    echo "Tabela class_schedule alterada com sucesso! Coluna session_type adicionada.\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), "Duplicate column name") !== false) {
        echo "Coluna session_type já existe.\n";
    } else {
        echo "Erro: " . $e->getMessage() . "\n";
    }
}
?>
