<?php
require_once dirname(__DIR__) . '/config.php';
$conn = connectDB();

try {
    $conn->exec("ALTER TABLE wpp_broadcast_queue DROP COLUMN job_id");
    echo "Coluna job_id removida com sucesso.\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), "check that column/key exists") !== false) {
        echo "A coluna job_id já foi removida.\n";
    } else {
        echo "Erro: " . $e->getMessage() . "\n";
    }
}
