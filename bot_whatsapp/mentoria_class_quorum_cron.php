<?php
/**
 * CRON: Cancelamento Classes (Deadline)
 * Frequência: Quanto mais frequente, melhor (ex: a cada 5 min).
 * Lógica: Dispara em qualquer run onde o deadline já passou e a aula ainda
 * não começou. O mentoria_auto_logs é o deduplicador — impede envio duplo.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/whatsapp_helper.php';

$token_secreto = '83x9aZ2pLQw1'; 
$is_cli = (php_sapi_name() === 'cli');

if (!$is_cli && (!isset($_GET['token']) || $_GET['token'] !== $token_secreto)) {
    http_response_code(403);
    die("Acesso Negado.");
}

$conn = connectDB();
$hoje = date('Y-m-d');
$diaSemana = date('N');

// Encontra aulas que começam daqui a 1 hora exata (tolerância de +- 5 min)
$stmt = $conn->prepare("SELECT * FROM class_schedule WHERE day_of_week = ? AND is_active = 1");
$stmt->execute([$diaSemana]);
$schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

$now = new DateTime();

foreach ($schedules as $s) {
    $classTime = new DateTime($hoje . ' ' . $s['start_time']);
    $deadlineTime = clone $classTime;
    $deadlineTime->modify('-1 hour');
    
    // Dispara se o deadline já passou E a aula ainda não começou (ou se for teste manual).
    // Não há janela de tempo fixa: o mentoria_auto_logs (tipo='class_cancel') impede envio duplo.
    // Isso funciona como fila: qualquer run do cron depois do deadline tenta enviar,
    // mas só o primeiro que ainda não encontrar o log de duplicidade vai de fato disparar.
    $diff = $now->getTimestamp() - $deadlineTime->getTimestamp();
    $isTest = isset($_GET['test_now']);
    if (($diff >= 0 && $now < $classTime) || $isTest) { // deadline passou, aula ainda não começou
        
        // Verifica anti-duplicidade para evitar enviar vários cancelamentos
        $check = $conn->prepare("SELECT id FROM mentoria_auto_logs WHERE tipo = 'class_cancel' AND data_execucao = ? AND membro_jid = ?");
        $check->execute([$hoje, $s['id']]);
        if ($check->rowCount() > 0 && !isset($_GET['force'])) {
            continue; // Já processou o deadline hoje para esta aula
        }

        // Conta confirmações
        $stmtCount = $conn->prepare("SELECT COUNT(*) FROM class_attendances WHERE schedule_id = ? AND aula_date = ?");
        $stmtCount->execute([$s['id'], $hoje]);
        $attendees = $stmtCount->fetchColumn();
        
        $sessionType = $s['session_type'] ?? 'teacher_class';
        $minQuorum = ($sessionType === 'student_practice') ? 2 : 1;
        
        if ($attendees < $minQuorum) {
            $config = getMentoriaConfig();
            $tplKey = ($sessionType === 'student_practice') ? 'practice_cancel' : 'class_cancel';
            $defaultTpl = ($sessionType === 'student_practice') 
                ? "❌ *Practice Session Cancelled*\n\nUnfortunately, we didn't get enough confirmations for the {horario} practice session today. Registrations are now closed and the session is cancelled. See you next time! 👋"
                : "❌ *Class Cancelled*\n\nUnfortunately, we didn't get any confirmations for the {horario} session today. Registrations are now closed and the class is cancelled. See you next time! 👋";
            $tpl = $config['templates'][$tplKey] ?? $defaultTpl;
            $msg = str_replace('{horario}', formatTime12h($classTime), $tpl);
            
            enviarWhatsApp($s['group_jid'], $msg, 'class_cancel');
            $conn->prepare("INSERT INTO mentoria_auto_logs (tipo, data_execucao, membro_jid) VALUES ('class_cancel', ?, ?)")->execute([$hoje, $s['id']]);
            
            // Deleta as confirmações de presença para que não contabilize pontos na aula cancelada
            $conn->prepare("DELETE FROM class_attendances WHERE schedule_id = ? AND aula_date = ?")->execute([$s['id'], $hoje]);
            
            echo "Sessão " . $s['start_time'] . " cancelada por falta de quórum (< $minQuorum). Presenças removidas.\n";
        } else {
            echo "Sessão " . $s['start_time'] . " confirmada com $attendees presentes.\n";
        }
    }
}
echo "Deadline check finished.";
