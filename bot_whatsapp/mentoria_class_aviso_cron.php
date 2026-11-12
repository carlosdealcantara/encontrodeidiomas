<?php
/**
 * CRON: Aviso de Classes (Meia-noite + Catch-up horário)
 * Frequência: chamado pelo master_cron às 00:00 BRT e a cada hora como catch-up.
 *
 * LÓGICA DE IDEMPOTÊNCIA (3 estados):
 *   - 'processing': trava adquirida antes do envio. Impede disparo paralelo.
 *   - 'sent':       confirmação gravada APENAS após HTTP 200 do Baileys.
 *   - 'failed':     erro registrado. Permite retentativa na próxima rodada.
 *
 * O catch-up horário reprocessa registros 'failed' cujo deadline ainda não passou.
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

$agora = new DateTime();

// Busca TODOS os horários do dia na agenda (sem filtro de hora).
// O filtro de deadline ainda-não-expirado é feito por sessão individualmente,
// permitindo que o catch-up horário recupere avisos que falharam mais cedo.
$stmt = $conn->prepare("SELECT * FROM class_schedule WHERE day_of_week = ? AND is_active = 1 ORDER BY start_time ASC");
$stmt->execute([$diaSemana]);
$schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($schedules)) {
    die("ℹ️ Nenhum encontro agendado para hoje (dia da semana: $diaSemana). Nenhuma mensagem enviada.");
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

    // === FILTRO DE DEADLINE PARA CATCH-UP HORÁRIO ===
    // O aviso só faz sentido enquanto o deadline ainda não expirou.
    // Na rodada da meia-noite, todas as aulas do dia passam (deadline está no futuro).
    // No catch-up horário, aulas cujo deadline já passou são ignoradas.
    if (!isset($_GET['force']) && $agora >= $deadlineObj) {
        echo "⏭️ Sessão $position (ID: {$schedule['id']}) — deadline das " . $deadlineObj->format('H:i') . " já expirou. Pulando.\n\n";
        continue;
    }

    echo "📋 Processando aviso para sessão $position (ID: {$schedule['id']}) - Tipo: $sessionType - Lang: $lang\n";
    echo "🕐 Horário: " . $startTimeObj->format('h:i A') . " | Deadline: " . $deadlineObj->format('h:i A') . "\n";

    // === MÁQUINA DE ESTADOS (processing → sent | failed) ===
    // 1ª fase: tenta adquirir a trava como 'processing'.
    //   - Retorna false se já existe registro 'sent' (enviado com sucesso antes) → pula.
    //   - Retorna false se já existe 'processing' recente → outra instância em curso → pula.
    //   - Retorna false se já existe 'failed' e ON DUPLICATE KEY não atualizou → não deveria ocorrer.
    //   - Registros 'failed' são reprocessados: a limpeza do timeout na função os permite.
    $identificador = (string)$schedule['id'];

    if (!isset($_GET['force'])) {
        $travou = registrarInicioDisparo($conn, 'class_aviso', $hoje, $identificador, 15);
        if (!$travou) {
            // Verifica o estado atual para dar o feedback correto
            $stateStmt = $conn->prepare("SELECT detalhes FROM mentoria_auto_logs WHERE tipo = 'class_aviso' AND data_execucao = ? AND membro_jid = ?");
            $stateStmt->execute([$hoje, $identificador]);
            $stateRow = $stateStmt->fetch(PDO::FETCH_ASSOC);
            $stateData = json_decode($stateRow['detalhes'] ?? '{}', true);
            $currentState = $stateData['status'] ?? 'sent';

            if ($currentState === 'sent') {
                echo "✅ Aviso já confirmado como enviado (status: sent). Ignorando.\n\n";
            } else {
                echo "⏳ Aviso em processamento por outra instância (status: $currentState). Ignorando.\n\n";
            }
            continue;
        }
    }

    // Registra a chave de dedup APENAS no Baileys — não no banco PHP ainda.
    // O banco PHP só é atualizado para 'sent' após confirmação do Baileys.
    $dedupKey = "class_aviso_{$schedule['id']}_{$hoje}";
    $res = enviarWhatsApp($targetJid, $msg, 'class_aviso', null, 'normal', $dedupKey);

    if ($res['success'] && !($res['data']['skipped'] ?? false)) {
        // 2ª fase: confirma envio bem-sucedido
        registrarConclusaoDisparo($conn, 'class_aviso', $hoje, $identificador, [
            'jobId'    => $res['data']['jobId'] ?? null,
            'targetJid'=> $targetJid,
        ]);
        echo "✅ Aviso enfileirado com sucesso! (jobId: " . ($res['data']['jobId'] ?? 'n/a') . ")\n\n";
    } elseif ($res['data']['skipped'] ?? false) {
        // Baileys ignorou por dedup_key já presente — a mensagem já foi para a fila antes
        registrarConclusaoDisparo($conn, 'class_aviso', $hoje, $identificador, [
            'skipped_by_baileys_dedup' => true,
        ]);
        echo "✅ Aviso já estava na fila do Baileys (dedup_key). Marcado como enviado.\n\n";
    } else {
        // Falha: marca como 'failed' para que a próxima execução do cron retente
        $erroMsg = $res['error'] ?? ('HTTP ' . ($res['httpCode'] ?? '?'));
        registrarFalhaDisparo($conn, 'class_aviso', $hoje, $identificador, $erroMsg);
        echo "❌ Falha ao enviar (marcado para retentativa): " . $erroMsg . "\n\n";
    }
}
