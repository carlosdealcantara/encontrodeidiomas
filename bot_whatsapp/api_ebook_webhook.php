<?php
/**
 * Recebe o webhook do servidor Node quando um áudio de palavra do e-book é salvo.
 * Chamado pelo bot Baileys ao detectar o comando !wordN em qualquer grupo (admin-only).
 */
require_once __DIR__ . '/../config.php';

$rawBody = file_get_contents('php://input');
$data = json_decode($rawBody, true);

if (!isset($data['apikey']) || $data['apikey'] !== 'SenhaMeetups2026') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if (isset($data['action']) && $data['action'] === 'register_word') {
    $wordNumber = isset($data['word_number']) ? (int)$data['word_number'] : 0;
    $audioPath  = $data['audio_path'] ?? '';

    if ($wordNumber <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid word_number']);
        exit;
    }
    if (empty($audioPath)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing audio_path']);
        exit;
    }

    try {
        $conn = connectDB();

        date_default_timezone_set('America/Sao_Paulo');

        // Verifica se já existe registro para este número
        $stmtCheck = $conn->prepare("SELECT id, audio_path FROM ebook_palavras WHERE numero = ?");
        $stmtCheck->execute([$wordNumber]);
        $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        $isUpdate = (bool)$existing;

        if ($isUpdate) {
            // Atualiza o registro existente (regravação)
            $stmt = $conn->prepare("
                UPDATE ebook_palavras
                SET audio_path = ?, atualizado_em = NOW()
                WHERE numero = ?
            ");
            $stmt->execute([$audioPath, $wordNumber]);
            $recordId = $existing['id'];
        } else {
            // Insere novo registro com título provisório
            $titulo = "Palavra #{$wordNumber}";
            $stmt = $conn->prepare("
                INSERT INTO ebook_palavras (numero, audio_path, titulo, ativo)
                VALUES (?, ?, ?, 0)
            ");
            $stmt->execute([$wordNumber, $audioPath, $titulo]);
            $recordId = $conn->lastInsertId();
        }

        echo json_encode([
            'success'   => true,
            'id'        => (int)$recordId,
            'numero'    => $wordNumber,
            'is_update' => $isUpdate,
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'error' => 'Invalid action']);
