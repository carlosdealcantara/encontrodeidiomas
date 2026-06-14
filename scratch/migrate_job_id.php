<?php
require_once dirname(__DIR__) . '/config.php';
$conn = connectDB();

try {
    // Adiciona a coluna job_id se ela não existir
    $stmt = $conn->query("SHOW COLUMNS FROM wpp_broadcast_queue LIKE 'job_id'");
    if ($stmt->rowCount() == 0) {
        $conn->exec("ALTER TABLE wpp_broadcast_queue ADD COLUMN job_id VARCHAR(50) NULL AFTER total_grupos");
        echo "Coluna job_id adicionada com sucesso.\n";
    } else {
        echo "Coluna job_id já existe.\n";
    }
} catch (PDOException $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}
