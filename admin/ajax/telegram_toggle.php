<?php
session_start();
require_once __DIR__ . '/../../config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['error' => 'Acesso negado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Método inválido']);
    exit;
}

$meeting_id = isset($_POST['meeting_id']) ? (int)$_POST['meeting_id'] : 0;
$ativo = isset($_POST['ativo']) ? (int)$_POST['ativo'] : 0;

if ($meeting_id <= 0) {
    echo json_encode(['error' => 'ID inválido']);
    exit;
}

try {
    $conn = connectDB();
    
    // Insere se não existir ou atualiza se existir
    $stmt = $conn->prepare("
        INSERT INTO telegram_bot_slots (meeting_id, ativo) 
        VALUES (:meeting_id, :ativo)
        ON DUPLICATE KEY UPDATE ativo = :ativo_update
    ");
    
    $stmt->execute([
        'meeting_id' => $meeting_id,
        'ativo' => $ativo,
        'ativo_update' => $ativo
    ]);
    
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['error' => 'Erro no banco: ' . $e->getMessage()]);
}
?>
