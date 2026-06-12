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

// Fonte única de verdade para o JID: config do Baileys
$config = getMentoriaConfig();
$groupJid = $config['groups']['our_meetups']['jid'] ?? null;

if (!$groupJid) {
    die("❌ Erro: JID do grupo Our Meetups não configurado no painel de Mensagens e Grupos.");
}

$conn = connectDB();
$hoje = date('Y-m-d');
$diaSemana = date('N'); // 1 = Segunda, 7 = Domingo

// Busca horário do dia na agenda (para montar a mensagem com o horário certo)
$stmt = $conn->prepare("SELECT * FROM meetup_schedule WHERE day_of_week = ? AND is_active = 1 LIMIT 1");
$stmt->execute([$diaSemana]);
$schedule = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$schedule) {
    die("ℹ️ Nenhum encontro agendado para hoje (dia da semana: $diaSemana). Nenhuma mensagem enviada.");
}

$startTime = $schedule['start_time'];

// Atualiza o JID na tabela para manter consistência
$conn->prepare("UPDATE meetup_schedule SET group_jid = ? WHERE day_of_week = ? AND is_active = 1")
     ->execute([$groupJid, $diaSemana]);

// Calcula deadline (1 hora antes)
$startTimeObj = new DateTime($hoje . ' ' . $startTime);
$deadlineObj = clone $startTimeObj;
$deadlineObj->modify('-1 hour');

$tpl = $config['templates']['meetup_aviso'] ?? "📅 *English Meetup Today!*\n\nWe have a session scheduled for *{horario} BRT*.\nIf you want to participate, please reply with `!attend`.\n\n⏳ You must confirm your attendance before *{deadline} BRT*.";

$msg = str_replace(
    ['{horario}', '{deadline}'], 
    [$startTimeObj->format('H:i'), $deadlineObj->format('H:i')], 
    $tpl
);

echo "📋 JID usado: $groupJid\n";
echo "🕐 Horário do encontro: " . $startTimeObj->format('H:i') . "\n";
echo "⏳ Deadline: " . $deadlineObj->format('H:i') . "\n\n";

$res = enviarWhatsApp($groupJid, $msg, 'meetup_aviso');
if ($res['success']) {
    echo "✅ Aviso enfileirado! (jobId: " . ($res['data']['jobId'] ?? 'n/a') . ")";
} else {
    echo "❌ Erro ao enviar: " . json_encode($res);
}
