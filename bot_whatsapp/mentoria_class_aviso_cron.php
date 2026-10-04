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

// Cache de configs por idioma e mapeamento de grupos
$configsByLang = [];
$getGroupJidForLang = function($l) use (&$configsByLang, $groupJid) {
    if (!isset($configsByLang[$l])) {
        $configsByLang[$l] = getMentoriaConfig($l);
    }
    return $configsByLang[$l]['groups']['our_classes']['jid'] ?? $groupJid;
};

// Atualiza o JID na tabela respeitando o idioma de cada aula agendada
$updateJidStmt = $conn->prepare("UPDATE class_schedule SET group_jid = ? WHERE id = ?");
foreach ($schedules as $s) {
    $sLang = !empty($s['lang_id']) ? $s['lang_id'] : 'en';
    $correctJid = $getGroupJidForLang($sLang);
    if ($correctJid && $correctJid !== ($s['group_jid'] ?? '')) {
        $updateJidStmt->execute([$correctJid, $s['id']]);
    }
}

// Agrupa schedules por grupo para calcular a posição relativa correta (caso haja múltiplas sessões no mesmo grupo)
$schedulesByGroup = [];
foreach ($schedules as $s) {
    $sLang = !empty($s['lang_id']) ? $s['lang_id'] : 'en';
    $tJid = $getGroupJidForLang($sLang);
    $schedulesByGroup[$tJid][] = $s['id'];
}

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

foreach ($schedules as $schedule) {
    $lang = !empty($schedule['lang_id']) ? $schedule['lang_id'] : 'en';
    if (!isset($configsByLang[$lang])) {
        $configsByLang[$lang] = getMentoriaConfig($lang);
    }
    $cfg = $configsByLang[$lang];
    $targetJid = $cfg['groups']['our_classes']['jid'] ?? $schedule['group_jid'] ?? $groupJid;
    if (!$targetJid) continue;

    $startTime = $schedule['start_time'];
    $sessionType = $schedule['session_type'] ?? 'teacher_class';

    // Determina a posição da sessão dentro do próprio grupo
    $groupList = $schedulesByGroup[$targetJid] ?? [$schedule['id']];
    $position = array_search($schedule['id'], $groupList) + 1;
    $hasMultipleInGroup = count($groupList) > 1;

    $startTimeObj = new DateTime($hoje . ' ' . $startTime);
    $deadlineObj = clone $startTimeObj;
    $deadlineObj->modify('-1 hour');

    $tplKey = ($sessionType === 'student_practice') ? 'practice_aviso' : 'class_aviso';
    $defaultTpl = ($lang === 'es')
        ? "📅 {date}\n\nTenemos una sesión programada para las {horario}.\nSi deseas participar, responde con !confirmar.\n\n⏳ Plazo límite para confirmar asistencia: {deadline}."
        : "📅 {date}\n\nWe have a session scheduled for {horario}.\nIf you want to participate, please reply with !attend.\n\n⏳ Deadline to confirm your attendance: {deadline}.";
    $tpl = $cfg['templates'][$tplKey] ?? $defaultTpl;

    // Formatação de data localizada
    $diasEs = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
    $mesesEs = [1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'];
    $dateFormatted = ($lang === 'es')
        ? ($diasEs[(int)$diaSemana] . ', ' . (int)date('j') . ' de ' . $mesesEs[(int)date('n')])
        : $dateEn;

    $msg = str_replace(
        ['{date}', '{horario}', '{deadline}'], 
        [$dateFormatted, formatTime($startTimeObj), formatTime($deadlineObj)], 
        $tpl
    );
    
    // Se o idioma for espanhol, assegura instrução de comando nativo em espanhol
    if ($lang === 'es') {
        $msg = str_replace('!attend', '!confirmar', $msg);
    }

    // Se há mais de 1 sessão no MESMO grupo, avisa a numeração correta
    if ($hasMultipleInGroup) {
        $cmdToReplace = ($lang === 'es') ? '!confirmar' : '!attend';
        $msg = str_replace($cmdToReplace, $cmdToReplace . ' ' . $position, $msg);
    }

    echo "📋 Enviando aviso para sessão $position (ID: {$schedule['id']}) - Tipo: $sessionType - Lang: $lang\n";
    echo "🕐 Horário: " . $startTimeObj->format('h:i A') . "\n";
    
    // Trava anti-duplicidade
    $check = $conn->prepare("SELECT id FROM mentoria_auto_logs WHERE tipo = 'class_aviso' AND data_execucao = ? AND membro_jid = ?");
    $check->execute([$hoje, $schedule['id']]);
    if ($check->rowCount() > 0 && !isset($_GET['force'])) {
        echo "✅ Aviso já enviado hoje. Ignorando...\n\n";
        continue;
    }

    $res = enviarWhatsApp($targetJid, $msg, 'class_aviso');
    if ($res['success']) {
        $conn->prepare("INSERT INTO mentoria_auto_logs (tipo, data_execucao, membro_jid) VALUES ('class_aviso', ?, ?)")->execute([$hoje, $schedule['id']]);
        echo "✅ Aviso enfileirado! (jobId: " . ($res['data']['jobId'] ?? 'n/a') . ")\n\n";
    } else {
        echo "❌ Erro ao enviar: " . json_encode($res) . "\n\n";
    }
}
