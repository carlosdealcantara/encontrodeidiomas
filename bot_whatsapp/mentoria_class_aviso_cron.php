<?php
/**
 * CRON: Aviso de Classes (Meia-noite)
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
$groupJid = $config['groups']['our_classes']['jid'] ?? null;

if (!$groupJid) {
    die("❌ Erro: JID do grupo Our Classes não configurado no painel de Mensagens e Grupos.");
}

$conn = connectDB();
$hoje = date('Y-m-d');
$diaSemana = date('N'); // 1 = Segunda, 7 = Domingo

// Calcula o horário limite (agora + 1 hora) para garantir que não pega uma aula que já expirou
$limiteObj = new DateTime();
$limiteObj->modify('+1 hour');
$limiteStr = $limiteObj->format('H:i:s');

// Busca todos os horários válidos do dia na agenda
$stmt = $conn->prepare("SELECT * FROM class_schedule WHERE day_of_week = ? AND is_active = 1 AND start_time >= ? ORDER BY start_time ASC");
$stmt->execute([$diaSemana, $limiteStr]);
$schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($schedules)) {
    die("ℹ️ Nenhum encontro agendado para hoje (dia da semana: $diaSemana) a partir de agora. Nenhuma mensagem enviada.");
}

// Atualiza o JID na tabela para manter consistência
$conn->prepare("UPDATE class_schedule SET group_jid = ? WHERE day_of_week = ? AND is_active = 1")
     ->execute([$groupJid, $diaSemana]);

// Função para formatar a hora estilo "1 PM" ou "1:30 PM"
function formatTime($dtObj) {
    $h = (int)$dtObj->format('g');
    $m = $dtObj->format('i');
    $ampm = $dtObj->format('A');
    if ($m === '00') {
        return "$h $ampm";
    }
    return "$h:$m $ampm";
}

$dateEn = date('l, F jS'); // Ex: Friday, June 13th

foreach ($schedules as $index => $schedule) {
    $startTime = $schedule['start_time'];
    $sessionType = $schedule['session_type'] ?? 'teacher_class';
    $position = $index + 1;

    $startTimeObj = new DateTime($hoje . ' ' . $startTime);
    $deadlineObj = clone $startTimeObj;
    $deadlineObj->modify('-1 hour');

    $tplKey = ($sessionType === 'student_practice') ? 'practice_aviso' : 'class_aviso';
    $defaultTpl = "📅 {date}\n\nWe have a session scheduled for {horario}.\nIf you want to participate, please reply with !attend.\n\n⏳ Deadline to confirm your attendance: {deadline}.";
    $tpl = $config['templates'][$tplKey] ?? $defaultTpl;

    $msg = str_replace(
        ['{date}', '{horario}', '{deadline}'], 
        [$dateEn, formatTime($startTimeObj), formatTime($deadlineObj)], 
        $tpl
    );
    
    // Se há mais de 1 sessão, avise qual comando usar
    if (count($schedules) > 1) {
        $msg = str_replace('!attend', '!attend ' . $position, $msg);
    }

    echo "📋 Enviando aviso para sessão $position (ID: {$schedule['id']}) - Tipo: $sessionType\n";
    echo "🕐 Horário: " . $startTimeObj->format('h:i A') . "\n";
    
    // Trava anti-duplicidade
    $check = $conn->prepare("SELECT id FROM mentoria_auto_logs WHERE tipo = 'class_aviso' AND data_execucao = ? AND membro_jid = ?");
    $check->execute([$hoje, $schedule['id']]);
    if ($check->rowCount() > 0 && !isset($_GET['force'])) {
        echo "✅ Aviso já enviado hoje. Ignorando...\n\n";
        continue;
    }

    $res = enviarWhatsApp($groupJid, $msg, 'class_aviso');
    if ($res['success']) {
        $conn->prepare("INSERT INTO mentoria_auto_logs (tipo, data_execucao, membro_jid) VALUES ('class_aviso', ?, ?)")->execute([$hoje, $schedule['id']]);
        echo "✅ Aviso enfileirado! (jobId: " . ($res['data']['jobId'] ?? 'n/a') . ")\n\n";
    } else {
        echo "❌ Erro ao enviar: " . json_encode($res) . "\n\n";
    }
}
