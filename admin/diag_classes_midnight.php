<?php
/**
 * Diagnóstico minucioso da rotina da meia-noite e aviso de classes
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/whatsapp_helper.php';

header('Content-Type: application/json; charset=utf-8');

$token = $_GET['token'] ?? '';
if ($token !== '83x9aZ2pLQw1') {
    http_response_code(403);
    die(json_encode(['error' => 'Acesso negado']));
}

try {
    $conn = connectDB();
    $hoje = date('Y-m-d');
    $diaSemana = date('N'); // 1 = Segunda ... 7 = Domingo

    // 1. Timezone e Relógio
    $timeInfo = [
        'php_time' => date('Y-m-d H:i:s P'),
        'php_timezone' => date_default_timezone_get(),
        'dia_semana' => $diaSemana,
        'hoje' => $hoje
    ];
    $dbTime = $conn->query("SELECT NOW() as db_now, @@system_time_zone as sys_tz, @@time_zone as tz")->fetch(PDO::FETCH_ASSOC);

    // 2. Class Schedule (Todas)
    $stmtSched = $conn->query("SELECT * FROM class_schedule ORDER BY day_of_week ASC, start_time ASC");
    $allSchedules = $stmtSched->fetchAll(PDO::FETCH_ASSOC);

    // 3. Class Schedule de Hoje (diaSemana)
    $stmtSchedToday = $conn->prepare("SELECT * FROM class_schedule WHERE day_of_week = ? AND is_active = 1 ORDER BY start_time ASC");
    $stmtSchedToday->execute([$diaSemana]);
    $todaySchedules = $stmtSchedToday->fetchAll(PDO::FETCH_ASSOC);

    // 4. Logs da Meia-Noite e Crons dos últimos 3 dias
    $stmtLogs = $conn->query("
        SELECT id, tipo, data_execucao, membro_jid, detalhes, created_at 
        FROM mentoria_auto_logs 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 4 DAY)
        ORDER BY id DESC 
        LIMIT 100
    ");
    $autoLogs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);

    // 5. Execuções de Crons (se a tabela existir)
    $cronExecs = [];
    try {
        $stmtCron = $conn->query("
            SELECT * FROM mentoria_cron_execucoes 
            WHERE executado_em >= DATE_SUB(NOW(), INTERVAL 3 DAY)
            ORDER BY id DESC 
            LIMIT 50
        ");
        $cronExecs = $stmtCron->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $cronExecs = ['error' => $e->getMessage()];
    }

    // 6. Config do Baileys para Our Classes
    $baileysStatus = statusWhatsApp();
    $mentoriaConfig = getMentoriaConfig();
    $ourClassesJid = $mentoriaConfig['groups']['our_classes']['jid'] ?? null;

    // 7. Attendances de hoje
    $stmtAtt = $conn->prepare("SELECT * FROM class_attendances WHERE aula_date = ?");
    $stmtAtt->execute([$hoje]);
    $attendancesToday = $stmtAtt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'time_info' => $timeInfo,
        'db_time' => $dbTime,
        'baileys_status' => $baileysStatus,
        'our_classes_jid_in_config' => $ourClassesJid,
        'today_schedules_day_' . $diaSemana => $todaySchedules,
        'all_schedules' => $allSchedules,
        'auto_logs_recentes' => $autoLogs,
        'cron_execucoes' => $cronExecs,
        'attendances_today' => $attendancesToday
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
