<?php
/**
 * Diagnóstico de Encontros de Ontem (Segunda-feira) e Logs de Disparo
 */
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

$token = $_GET['token'] ?? '';
if ($token !== '83x9aZ2pLQw1') {
    http_response_code(403);
    die(json_encode(['error' => 'Acesso negado']));
}

try {
    $conn = connectDB();

    // 1. Data de ontem
    $ontem = new DateTime('-1 day');
    $dataOntem = $ontem->format('Y-m-d');
    $diaDaSemanaOntem = (int)$ontem->format('N'); // 1 = Segunda

    // 2. Encontros de ontem (Segunda)
    $hasSessions = false;
    try {
        $check = $conn->query("SELECT 1 FROM meeting_sessions LIMIT 1");
        $hasSessions = ($check !== false);
    } catch (Exception $e) {
        $hasSessions = false;
    }

    if ($hasSessions) {
        $stmtMeetings = $conn->prepare("
            SELECT m.id, m.title, m.comunidade, m.language_id, m.active, m.meet_link,
                   ms.id as session_id, ms.day_of_week, ms.time_hour, IFNULL(m.time_minute, 0) as time_minute,
                   ms.active as session_active,
                   l.name as language_name, l.name_en, l.flag_emoji,
                   h.name as host_name
            FROM meetings m
            JOIN meeting_sessions ms ON ms.meeting_id = m.id
            JOIN languages l ON m.language_id = l.id
            LEFT JOIN hosts h ON m.host_id = h.id
            WHERE ms.day_of_week = ?
            ORDER BY ms.time_hour ASC, m.time_minute ASC
        ");
        $stmtMeetings->execute([$diaDaSemanaOntem]);
    } else {
        $stmtMeetings = $conn->prepare("
            SELECT m.id, m.title, m.comunidade, m.language_id, m.active, m.meet_link,
                   m.day_of_week, m.time_hour, IFNULL(m.time_minute, 0) as time_minute,
                   l.name as language_name, l.name_en, l.flag_emoji,
                   h.name as host_name
            FROM meetings m
            JOIN languages l ON m.language_id = l.id
            LEFT JOIN hosts h ON m.host_id = h.id
            WHERE m.day_of_week = ?
            ORDER BY m.time_hour ASC, m.time_minute ASC
        ");
        $stmtMeetings->execute([$diaDaSemanaOntem]);
    }
    $meetingsOntem = $stmtMeetings->fetchAll(PDO::FETCH_ASSOC);

    // 3. Templates ativos
    $stmtTpl = $conn->query("SELECT id, cenario, minutos_antes, escopo, frequencia, comunidade_alvo, ativo FROM meetup_whatsapp_templates WHERE ativo = 1");
    $templates = $stmtTpl->fetchAll(PDO::FETCH_ASSOC);

    // 4. Logs de disparos de ontem e de hoje
    $stmtLogs = $conn->prepare("
        SELECT l.*, g.nome as grupo_nome, g.comunidade as grupo_comunidade, g.categoria, t.cenario as template_cenario, m.title as meeting_title
        FROM meetup_whatsapp_logs l
        LEFT JOIN meetup_whatsapp_groups g ON l.grupo_id = g.id
        LEFT JOIN meetup_whatsapp_templates t ON l.template_id = t.id
        LEFT JOIN meetings m ON l.meeting_id = m.id
        WHERE l.data_disparo >= DATE_SUB(CURDATE(), INTERVAL 2 DAY)
        ORDER BY l.id DESC
        LIMIT 100
    ");
    $stmtLogs->execute();
    $logsRecentes = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);

    // 5. Configurações de contenção / crons
    $settings = [];
    $stmtSet = $conn->query("SELECT chave, valor FROM system_settings WHERE chave LIKE '%wpp%' OR chave LIKE '%meetup%'");
    if ($stmtSet) {
        $settings = $stmtSet->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    echo json_encode([
        'data_ontem' => $dataOntem,
        'dia_semana_ontem' => $diaDaSemanaOntem,
        'meetings_ontem' => $meetingsOntem,
        'templates' => $templates,
        'settings' => $settings,
        'logs_recentes' => $logsRecentes
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
