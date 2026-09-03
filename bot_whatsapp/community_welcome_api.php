<?php
/**
 * API consumida pelo bot (Baileys) para obter intros e perguntas aleatórias
 * de boas-vindas para novos membros.
 */
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

// Proteção por token
$token_secreto = '83x9aZ2pLQw1'; // Usado globalmente no projeto
if (!isset($_GET['token']) || $_GET['token'] !== $token_secreto) {
    http_response_code(403);
    die(json_encode(["error" => "Acesso Negado."]));
}

$group_jid = $_GET['group_jid'] ?? '';
if (empty($group_jid)) {
    http_response_code(400);
    die(json_encode(["error" => "group_jid is required"]));
}

try {
    $conn = connectDB();

    // 1. Checa se o grupo está com welcome_enabled
    $stmt = $conn->prepare("SELECT welcome_enabled, lang_code FROM meetup_whatsapp_groups WHERE group_id = ? AND ativo = 1");
    $stmt->execute([$group_jid]);
    $group = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$group || $group['welcome_enabled'] == 0) {
        echo json_encode(["enabled" => false]);
        exit;
    }

    $lang_code = $group['lang_code'] ?? 'en';

    // 2. Sorteia 1 intro
    $introStmt = $conn->query("SELECT text_target, text_en FROM community_welcome_intros WHERE ativo = 1 ORDER BY RAND() LIMIT 1");
    $intro = $introStmt->fetch(PDO::FETCH_ASSOC);

    // Se não houver intros ativas, aborta a mensagem
    if (!$intro) {
        echo json_encode(["enabled" => false, "error" => "No active intros found"]);
        exit;
    }

    // 3. Sorteia 3 perguntas
    $qStmt = $conn->query("SELECT text_target, text_en FROM community_welcome_questions WHERE ativo = 1 ORDER BY RAND() LIMIT 3");
    $questions = $qStmt->fetchAll(PDO::FETCH_ASSOC);

    // Formatar perguntas para o JSON
    $formattedQuestions = [];
    foreach ($questions as $q) {
        $formattedQuestions[] = [
            "target" => $q['text_target'],
            "en" => $q['text_en']
        ];
    }

    // 4. Retorna a resposta
    echo json_encode([
        "enabled" => true,
        "lang_code" => $lang_code,
        "intro_target" => $intro['text_target'],
        "intro_en" => $intro['text_en'],
        "questions" => $formattedQuestions
    ]);

} catch (Exception $e) {
    error_log("community_welcome_api Erro: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "Internal Server Error"]);
}
