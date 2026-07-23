<?php
/**
 * CRON: Ranking Periódico (Semanal, Mensal, Anual)
 * CLI: php mentoria_ranking_periodico_cron.php weekly
 * Web: mentoria_ranking_periodico_cron.php?token=83x9aZ2pLQw1&period=weekly
 */
require_once __DIR__ . '/../config.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../includes/whatsapp_helper.php';

$token_secreto = '83x9aZ2pLQw1'; 
$is_cli = (php_sapi_name() === 'cli');

if (!$is_cli && (!isset($_GET['token']) || $_GET['token'] !== $token_secreto)) {
    http_response_code(403);
    die("Acesso Negado.");
}

$conn = connectDB();
$config = getMentoriaConfig();
$targetGroup = $config['groups']['the_lounge']['jid'] ?? null;

if (!$targetGroup) {
    die("❌ Grupo alvo (The Lounge) não configurado.\n");
}

$period = 'weekly';
if ($is_cli && isset($argv[1])) {
    $period = $argv[1];
} elseif (isset($_GET['period'])) {
    $period = $_GET['period'];
}

$startDate = '';
$endDate = (new DateTime())->modify('-1 day')->format('Y-m-d');
$titleDateStr = '';
$periodTitle = '';

if ($period === 'weekly') {
    // Ultimos 7 dias
    $start = (new DateTime())->modify('-7 days');
    $startDate = $start->format('Y-m-d');
    $titleDateStr = $start->format('F jS') . ' – ' . date('F jS, Y', strtotime($endDate));
    $periodTitle = 'WEEKLY';
} elseif ($period === 'monthly') {
    // Mês atual ou mês passado se rodar dia 1
    if (date('j') == 1) {
        $start = new DateTime('first day of last month');
        $end   = new DateTime('last day of last month');
    } else {
        $start = new DateTime('first day of this month');
        $end   = new DateTime();
        $end->modify('-1 day');
    }
    $startDate = $start->format('Y-m-d');
    $endDate   = $end->format('Y-m-d');
    $titleDateStr = $start->format('F Y');
    $periodTitle = 'MONTHLY';
} elseif ($period === 'yearly') {
    $start = new DateTime('first day of January this year');
    $startDate = $start->format('Y-m-d');
    $titleDateStr = $start->format('Y');
    $periodTitle = 'YEARLY';
} else {
    die("Período inválido. Use weekly, monthly ou yearly.");
}

$check = $conn->prepare("SELECT id FROM mentoria_auto_logs WHERE tipo = ? AND data_execucao = ?");
$logType = "ranking_{$period}";
$check->execute([$logType, $endDate]);
if ($check->rowCount() > 0 && !isset($_GET['force'])) {
    die("Ranking $period já postado para a data final ($endDate). Use &force=1 para forçar o reenvio.");
}

// -----------------------------------------------------
// 1. BUSCAR DADOS
// -----------------------------------------------------
$stmt = $conn->prepare("
    SELECT member_jid, MAX(member_name) as member_name,
           SUM(dedication_pts) as total_ded,
           SUM(social_msgs)    as total_msgs,
           SUM(social_reacts)  as total_reacts
    FROM mentoria_daily_scores
    WHERE score_date BETWEEN ? AND ?
    GROUP BY member_jid
");
$stmt->execute([$startDate, $endDate]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$dedication = [];
$msgs = [];
$reacts = [];

foreach ($rows as $r) {
    if ($r['total_ded'] > 0) $dedication[] = $r;
    if ($r['total_msgs'] > 0) $msgs[] = $r;
    if ($r['total_reacts'] > 0) $reacts[] = $r;
}

usort($dedication, fn($a, $b) => $b['total_ded'] <=> $a['total_ded']);
usort($msgs, fn($a, $b) => $b['total_msgs'] <=> $a['total_msgs']);
usort($reacts, fn($a, $b) => $b['total_reacts'] <=> $a['total_reacts']);

$topMsgs = array_slice($msgs, 0, 5);
$topReacts = array_slice($reacts, 0, 5);

// -----------------------------------------------------
// 2. MONTAGEM DAS STRINGS
// -----------------------------------------------------
$medals = ['🥇', '🥈', '🥉'];

$studentStr = '';
if (!empty($dedication)) {
    $i = 0;
    foreach ($dedication as $d) {
        $rankStr = ($i < 3) ? $medals[$i] : ($i + 1) . ".";
        $nomeStr = trim($d['member_name']) ?: 'Unknown';
        $studentStr .= $rankStr . " *{$nomeStr}* — {$d['total_ded']} pts\n";
        $i++;
    }
} else {
    $studentStr = "No points recorded in this period.";
}

$msgList = '';
if (!empty($topMsgs)) {
    $i = 0;
    foreach ($topMsgs as $d) {
        $rankStr = ($i < 3) ? $medals[$i] : ($i + 1) . ".";
        $nomeStr = trim($d['member_name']) ?: 'Unknown';
        $msgList .= $rankStr . " *{$nomeStr}* — {$d['total_msgs']} messages\n";
        $i++;
    }
} else {
    $msgList = "No messages recorded.\n";
}

$reactList = '';
if (!empty($topReacts)) {
    $i = 0;
    foreach ($topReacts as $d) {
        $rankStr = ($i < 3) ? $medals[$i] : ($i + 1) . ".";
        $nomeStr = trim($d['member_name']) ?: 'Unknown';
        $reactList .= $rankStr . " *{$nomeStr}* — {$d['total_reacts']} reactions\n";
        $i++;
    }
} else {
    $reactList = "No reactions recorded.\n";
}

// -----------------------------------------------------
// 3. MONTAGEM DAS MENSAGENS
// -----------------------------------------------------

$msg1 = "📅 {$titleDateStr}\n\n⭐ *STUDENT OF THE {$periodTitle}*\n\n{$studentStr}";
$msg2 = "📅 {$titleDateStr}\n\n💬 *TOP MESSENGER*\n_Who sent the most messages?_\n\n{$msgList}";
$msg3 = "📅 {$titleDateStr}\n\n❤️ *TOP REACTOR*\n_Who gave the most reactions?_\n\n{$reactList}";

// Disparo
$result1 = enviarWhatsApp($targetGroup, $msg1, 'mentoria_ranking_student_' . $period);
sleep(1);
$result2 = enviarWhatsApp($targetGroup, $msg2, 'mentoria_ranking_messenger_' . $period);
sleep(1);
$result3 = enviarWhatsApp($targetGroup, $msg3, 'mentoria_ranking_reactor_' . $period);

if ($result1['httpCode'] >= 200 && $result1['httpCode'] < 300) {
    $conn->prepare("INSERT INTO mentoria_auto_logs (tipo, data_execucao, detalhes) VALUES (?, ?, ?)")
         ->execute([$logType, $endDate, json_encode(['period' => $period])]);
    echo "✅ Ranking {$periodTitle} enviado com sucesso (em 3 mensagens)!";
} else {
    echo "❌ Erro ao enviar ranking: HTTP " . $result1['httpCode'];
}
