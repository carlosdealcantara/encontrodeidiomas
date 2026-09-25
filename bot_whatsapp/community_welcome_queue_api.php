<?php
/**
 * API de fila persistente de boas-vindas da Comunidade Global.
 *
 * Ações (GET ?action=):
 *  - enqueue   : insere um participante como 'pending'
 *  - mark_sent : marca um registro como 'sent'
 *  - mark_failed: marca um registro como 'failed'
 *  - pending   : retorna registros 'pending' mais antigos que N minutos (default 10)
 */
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

// Proteção por token
$token_secreto = '83x9aZ2pLQw1';
if (!isset($_GET['token']) || $_GET['token'] !== $token_secreto) {
    http_response_code(403);
    die(json_encode(['error' => 'Acesso Negado.']));
}

$action = $_GET['action'] ?? '';

try {
    $conn = connectDB();

    // ── ENQUEUE ───────────────────────────────────────────────────────────────
    if ($action === 'enqueue') {
        $group_jid       = $_POST['group_jid']       ?? '';
        $participant_jid = $_POST['participant_jid'] ?? '';
        $delay_seconds   = intval($_POST['delay_seconds'] ?? 0);

        if (empty($group_jid) || empty($participant_jid)) {
            http_response_code(400);
            die(json_encode(['error' => 'group_jid e participant_jid são obrigatórios']));
        }

        // Evita duplicatas: se já há um 'pending' recente para esse participante+grupo, não insere
        $check = $conn->prepare(
            "SELECT id FROM community_welcome_queue
             WHERE group_jid = ? AND participant_jid = ? AND status = 'pending'
             AND created_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
             LIMIT 1"
        );
        $check->execute([$group_jid, $participant_jid]);
        if ($check->fetch()) {
            echo json_encode(['success' => true, 'skipped' => true, 'reason' => 'already_pending']);
            exit;
        }

        $scheduled_at = date('Y-m-d H:i:s', time() + $delay_seconds);
        $stmt = $conn->prepare(
            "INSERT INTO community_welcome_queue (group_jid, participant_jid, status, scheduled_at)
             VALUES (?, ?, 'pending', ?)"
        );
        $stmt->execute([$group_jid, $participant_jid, $scheduled_at]);
        $id = $conn->lastInsertId();
        echo json_encode(['success' => true, 'id' => $id]);
        exit;
    }

    // ── MARK SENT ─────────────────────────────────────────────────────────────
    if ($action === 'mark_sent') {
        $ids = $_POST['ids'] ?? '';
        if (empty($ids)) {
            http_response_code(400);
            die(json_encode(['error' => 'ids é obrigatório']));
        }
        $idList = array_map('intval', explode(',', $ids));
        $placeholders = implode(',', array_fill(0, count($idList), '?'));
        $stmt = $conn->prepare(
            "UPDATE community_welcome_queue
             SET status = 'sent', sent_at = NOW()
             WHERE id IN ($placeholders)"
        );
        $stmt->execute($idList);
        echo json_encode(['success' => true, 'updated' => $stmt->rowCount()]);
        exit;
    }

    // ── MARK FAILED ───────────────────────────────────────────────────────────
    if ($action === 'mark_failed') {
        $ids = $_POST['ids'] ?? '';
        if (empty($ids)) {
            http_response_code(400);
            die(json_encode(['error' => 'ids é obrigatório']));
        }
        $idList = array_map('intval', explode(',', $ids));
        $placeholders = implode(',', array_fill(0, count($idList), '?'));
        $stmt = $conn->prepare(
            "UPDATE community_welcome_queue
             SET status = 'failed', attempts = attempts + 1
             WHERE id IN ($placeholders)"
        );
        $stmt->execute($idList);
        echo json_encode(['success' => true, 'updated' => $stmt->rowCount()]);
        exit;
    }

    // ── PENDING (recovery na inicialização) ───────────────────────────────────
    if ($action === 'pending') {
        // Retorna pendentes cujo scheduled_at já passou + registros 'failed' com até 3 tentativas
        // que tenham mais de 5 minutos (para evitar conflito com o timer em memória)
        $min_age = intval($_GET['min_age_minutes'] ?? 10);
        $stmt = $conn->prepare(
            "SELECT id, group_jid, participant_jid, scheduled_at, attempts, created_at
             FROM community_welcome_queue
             WHERE (
                 (status = 'pending' AND scheduled_at <= NOW() AND created_at <= DATE_SUB(NOW(), INTERVAL ? MINUTE))
                 OR
                 (status = 'failed'  AND attempts < 3 AND created_at <= DATE_SUB(NOW(), INTERVAL ? MINUTE))
             )
             ORDER BY group_jid, created_at ASC"
        );
        $stmt->execute([$min_age, $min_age]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'items' => $rows]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['error' => "Ação desconhecida: $action"]);

} catch (Exception $e) {
    error_log('community_welcome_queue_api Erro: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal Server Error']);
}
