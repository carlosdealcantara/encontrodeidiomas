<?php
/**
 * Recebe o webhook do servidor Node quando um áudio é salvo do grupo The Lounge.
 */
require_once __DIR__ . '/../config.php';

$rawBody = file_get_contents('php://input');
$data = json_decode($rawBody, true);

if (!isset($data['apikey']) || $data['apikey'] !== 'SenhaMeetups2026') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if (isset($data['action']) && $data['action'] === 'new_audio') {
    $audioPath = $data['audio_path'] ?? '';
    if (empty($audioPath)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing audio_path']);
        exit;
    }

    try {
        $conn = connectDB();
        
        // Determina um título provisório (Rascunho Data/Hora)
        date_default_timezone_set('America/Sao_Paulo');
        $titulo = "Rascunho Capturado - " . date('d/m/Y H:i');

        $stmt = $conn->prepare("INSERT INTO pilulas_conteudo (tipo, titulo, audio_path, ativo) VALUES ('audio', ?, ?, 0)");
        $stmt->execute([$titulo, $audioPath]);
        
        $insertedId = $conn->lastInsertId();

        echo json_encode(['success' => true, 'id' => $insertedId]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'error' => 'Invalid action']);
