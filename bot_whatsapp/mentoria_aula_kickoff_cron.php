<?php
/**
 * CRON: Kickoff Meetups
 * Frequência: 1x/hora (ex: 20:00 para abrir a aula de 20:00)
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

// Encontra aulas que começam AGORA
$stmt = $conn->prepare("SELECT * FROM meetup_schedule WHERE day_of_week = ? AND is_active = 1");
$stmt->execute([$diaSemana]);
$schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

$now = new DateTime();

foreach ($schedules as $s) {
    $classTime = new DateTime($hoje . ' ' . $s['start_time']);
    
    // Verifica se estamos no exato momento do kickoff
    $diff = $now->getTimestamp() - $classTime->getTimestamp();
    if (abs($diff) <= 300) { 
        
        // Conta confirmações
        $stmtCount = $conn->prepare("SELECT COUNT(*) FROM meetup_attendances WHERE schedule_id = ? AND aula_date = ?");
        $stmtCount->execute([$s['id'], $hoje]);
        $attendees = $stmtCount->fetchColumn();
        
        if ($attendees > 0) {
            // Remove o https:// caso exista no banco
            $cleanLink = str_replace(['https://', 'http://'], '', $s['meet_link']);
            
            $config = getMentoriaConfig();
            $tpl = $config['templates']['meetup_kickoff'] ?? "🎉 *The Meetup is starting NOW!*\n\nJoin the room here: {link}\n\nHave a great session! 🗣️";
            $msg = str_replace('{link}', $cleanLink, $tpl);
            
            enviarWhatsApp($s['group_jid'], $msg, 'meetup_kickoff');
            echo "Aula " . $s['start_time'] . " iniciada!\n";
        }
    }
}
echo "Kickoff check finished.";
