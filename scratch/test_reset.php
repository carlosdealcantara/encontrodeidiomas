<?php
require_once dirname(__DIR__) . '/config.php';
$conn = connectDB();
try {
    $conn->exec("TRUNCATE TABLE wpp_broadcast_log");
    $conn->exec("DELETE FROM wpp_broadcast_queue");
    echo "Fila e logs resetados com sucesso para zero enviados.";
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
