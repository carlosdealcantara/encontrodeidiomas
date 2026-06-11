<?php
/**
 * CRON: Cancelamento Meetups (Deadline)
 * Frequência: 1x/hora (ex: 19:00 para cancelar aula de 20:00)
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
$stmt = $conn->prepare("SELECT * FROM meetup_schedule WHERE day_of_week = ? AND is_active = 1");
$stmt->execute([$diaSemana]);
$schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

$now = new DateTime();

foreach ($schedules as $s) {
    $classTime = new DateTime($hoje . ' ' . $s['start_time']);
    $deadlineTime = clone $classTime;
    $deadlineTime->modify('-1 hour');
    
    // Verifica se estamos no exato momento do deadline (intervalo de 10 min para cobrir o cron)
    $diff = $now->getTimestamp() - $deadlineTime->getTimestamp();
    if (abs($diff) <= 300) { // dentro de 5 minutos do deadline
        
        // Conta confirmações
        $stmtCount = $conn->prepare("SELECT COUNT(*) FROM meetup_attendances WHERE schedule_id = ? AND aula_date = ?");
        $stmtCount->execute([$s['id'], $hoje]);
        $attendees = $stmtCount->fetchColumn();
        
        if ($attendees == 0) {
            $msg = "❌ *Meetup Cancelled*\n\n";
            $msg .= "Unfortunately, we didn't get any confirmations for the " . $classTime->format('H:i') . " session today. Registrations are now closed and the class is cancelled. See you next time! 👋";
            
            enviarWhatsApp($s['group_jid'], $msg, 'meetup_cancel');
            echo "Aula " . $s['start_time'] . " cancelada por falta de quórum.\n";
        } else {
            echo "Aula " . $s['start_time'] . " confirmada com $attendees presentes.\n";
        }
    }
}
echo "Deadline check finished.";
