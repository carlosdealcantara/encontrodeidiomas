<?php
/**
 * CRON: Aviso Matinal Meetups
 * Frequência: 1x/dia, às 10:00 BRT
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
$diaSemana = date('N'); // 1 = Segunda, 7 = Domingo

$stmt = $conn->prepare("SELECT * FROM meetup_schedule WHERE day_of_week = ? AND is_active = 1 LIMIT 1");
$stmt->execute([$diaSemana]);
$schedule = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$schedule) {
    die("Nenhum encontro agendado para hoje.");
}

$groupJid = $schedule['group_jid'];
$startTime = $schedule['start_time'];

// Calcula deadline (1 hora antes)
$startTimeObj = new DateTime($hoje . ' ' . $startTime);
$deadlineObj = clone $startTimeObj;
$deadlineObj->modify('-1 hour');

$config = getMentoriaConfig();
$tpl = $config['templates']['meetup_aviso'] ?? "📅 *English Meetup Today!*\n\nWe have a session scheduled for *{horario} BRT*.\nIf you want to participate, please reply with `!attend`.\n\n⏳ You must confirm your attendance before *{deadline} BRT*.";

$msg = str_replace(
    ['{horario}', '{deadline}'], 
    [$startTimeObj->format('H:i'), $deadlineObj->format('H:i')], 
    $tpl
);

$res = enviarWhatsApp($groupJid, $msg, 'meetup_aviso');
if ($res['success']) {
    echo "✅ Aviso enviado!";
} else {
    echo "❌ Erro: " . json_encode($res);
}
