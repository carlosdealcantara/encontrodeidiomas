<?php
/**
 * SCRIPT TEMPORÁRIO - busca intros e perguntas em japonês para revisão.
 * Deletar após uso.
 */
require_once '../config.php';

header('Content-Type: application/json; charset=utf-8');

$token = $_GET['token'] ?? '';
if ($token !== '83x9aZ2pLQw1') {
    http_response_code(403);
    die(json_encode(['error' => 'Acesso negado']));
}

try {
    $conn = connectDB();

    // Intros em japonês
    $introStmt = $conn->prepare("
        SELECT 
            cwi.id, 
            cwi.text_en,
            cwt.text AS text_ja
        FROM community_welcome_intros cwi
        LEFT JOIN community_welcome_translations cwt 
            ON cwt.entity_type = 'intro' 
            AND cwt.entity_id = cwi.id 
            AND cwt.lang_code = 'ja'
        WHERE cwi.ativo = 1
        ORDER BY cwi.id ASC
    ");
    $introStmt->execute();
    $intros = $introStmt->fetchAll(PDO::FETCH_ASSOC);

    // Perguntas em japonês
    $qStmt = $conn->prepare("
        SELECT 
            cwq.id, 
            cwq.text_en,
            cwt.text AS text_ja
        FROM community_welcome_questions cwq
        LEFT JOIN community_welcome_translations cwt 
            ON cwt.entity_type = 'question' 
            AND cwt.entity_id = cwq.id 
            AND cwt.lang_code = 'ja'
        WHERE cwq.ativo = 1
        ORDER BY cwq.id ASC
    ");
    $qStmt->execute();
    $questions = $qStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'intros' => $intros,
        'questions' => $questions
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
