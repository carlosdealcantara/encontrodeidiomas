<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../config.php';

try {
    $conn = connectDB();
    $stmt = $conn->query("SELECT id, cenario, dias_antes, lang_id, ativo FROM mentoria_mensagens");
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo "Erro: " . $e->getMessage();
}
