<?php
/**
 * CRON: Aviso de Meetups (Meia-noite)
 * Frequência: 1x/dia, às 00:00 BRT
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

// Calcula o horário limite (agora + 1 hora) para garantir que não pega uma aula que já expirou
$limiteObj = new DateTime();
$limiteObj->modify('+1 hour');
$limiteStr = $limiteObj->format('H:i:s');

// Busca o PRÓXIMO horário válido do dia na agenda
$stmt = $conn->prepare("SELECT * FROM meetup_schedule WHERE day_of_week = ? AND is_active = 1 AND start_time >= ? ORDER BY start_time ASC LIMIT 1");
$stmt->execute([$diaSemana, $limiteStr]);
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

$tpl = $config['templates']['meetup_aviso'] ?? "📅 *Meetup Today!*\n\nToday is the day! Our English session is scheduled for *{horario} BRT*.\nReply with `!attend` to save your spot.\n\n⏳ You have until *{deadline} BRT* to confirm.";

$msg = str_replace(
    ['{horario}', '{deadline}'], 
    [$startTimeObj->format('H:i'), $deadlineObj->format('H:i')], 
    $tpl
);

echo "📋 JID usado: $groupJid\n";
echo "🕐 Horário do encontro: " . $startTimeObj->format('H:i') . "\n";
echo "⏳ Deadline: " . $deadlineObj->format('H:i') . "\n\n";

// Trava anti-duplicidade
$check = $conn->prepare("SELECT id FROM mentoria_auto_logs WHERE tipo = 'meetup_aviso' AND data_execucao = ? AND membro_jid = ?");
$check->execute([$hoje, $schedule['id']]);
if ($check->rowCount() > 0 && !isset($_GET['force'])) {
    die("✅ Aviso para a aula {$schedule['id']} já foi enviado hoje! Use &force=1 na URL para forçar um reenvio.");
}

$res = enviarWhatsApp($groupJid, $msg, 'meetup_aviso');
if ($res['success']) {
    $conn->prepare("INSERT INTO mentoria_auto_logs (tipo, data_execucao, membro_jid) VALUES ('meetup_aviso', ?, ?)")->execute([$hoje, $schedule['id']]);
    echo "✅ Aviso enfileirado! (jobId: " . ($res['data']['jobId'] ?? 'n/a') . ")";
} else {
    echo "❌ Erro ao enviar: " . json_encode($res);
}
