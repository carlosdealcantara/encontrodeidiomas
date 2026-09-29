<?php
/**
 * CRON: Ranking Periódico (Semanal, Mensal, Anual) — Multi-idioma
 * CLI: php mentoria_ranking_periodico_cron.php weekly
 * Web: mentoria_ranking_periodico_cron.php?token=83x9aZ2pLQw1&period=weekly
 * Itera sobre todos os idiomas ativos e posta no Lounge de cada um.
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

$dry_run = isset($_GET['dry_run']) && $_GET['dry_run'] == '1';
if ($dry_run) {
    echo "🔍 MODO DRY-RUN ATIVADO: Nenhuma mensagem será disparada no WhatsApp.\n\n";
}

$conn = connectDB();

$period = 'weekly';
if ($is_cli && isset($argv[1])) {
    $period = $argv[1];
} elseif (isset($_GET['period'])) {
    $period = $_GET['period'];
}

// -------------------------------------------------------
// CÁLCULO DO PERÍODO
// -------------------------------------------------------
$startDate    = '';
$endDate      = (new DateTime())->modify('-1 day')->format('Y-m-d');
$periodTitle  = '';

if ($period === 'weekly') {
    $start       = new DateTime('last week monday');
    $end         = new DateTime('last week sunday');
    $startDate   = $start->format('Y-m-d');
    $endDate     = $end->format('Y-m-d');
    $periodTitle = 'WEEKLY';
} elseif ($period === 'monthly') {
    if (date('j') == 1) {
        $start = new DateTime('first day of last month');
        $end   = new DateTime('last day of last month');
    } else {
        $start = new DateTime('first day of this month');
        $end   = new DateTime();
        $end->modify('-1 day');
    }
    $startDate   = $start->format('Y-m-d');
    $endDate     = $end->format('Y-m-d');
    $periodTitle = 'MONTHLY';
} elseif ($period === 'yearly') {
    $start       = new DateTime('first day of January this year');
    $startDate   = $start->format('Y-m-d');
    $periodTitle = 'YEARLY';
} else {
    die("Período inválido. Use weekly, monthly ou yearly.\n");
}

// Busca todos os idiomas ativos
$langs = $conn->query("SELECT lang_id FROM mentoria_langs WHERE ativo = 1 ORDER BY lang_id ASC")->fetchAll(PDO::FETCH_COLUMN);

if (empty($langs)) {
    die("Nenhum idioma ativo encontrado em mentoria_langs.\n");
}

foreach ($langs as $lang) {
    echo "\n🌐 Processando ranking periódico [{$lang}] — {$periodTitle}\n";

    $config      = getMentoriaConfig($lang);
    $targetGroup = $config['groups']['the_lounge']['jid'] ?? null;

    if (!$targetGroup) {
        echo "  ❌ Grupo alvo (The Lounge) não configurado para [{$lang}]. Pulando.\n";
        continue;
    }

    // Anti-duplicidade por idioma e período (ignorado em modo dry_run)
    $logType = "ranking_{$period}_{$lang}";
    $check   = $conn->prepare("SELECT id FROM mentoria_auto_logs WHERE tipo = ? AND data_execucao = ?");
    $check->execute([$logType, $endDate]);
    if ($check->rowCount() > 0 && !isset($_GET['force']) && !$dry_run) {
        echo "  ⏭️ Ranking {$period} [{$lang}] já postado para a data final ($endDate). Use &force=1 para forçar.\n";
        continue;
    }

    // -------------------------------------------------------
    // BUSCAR DADOS filtrados por lang_id
    // -------------------------------------------------------
    $stmt = $conn->prepare("
        SELECT member_jid, MAX(member_name) as member_name,
               SUM(dedication_pts) as total_ded,
               SUM(social_msgs)    as total_msgs,
               SUM(social_reacts)  as total_reacts
        FROM mentoria_daily_scores
        WHERE score_date BETWEEN ? AND ?
          AND (lang_id = ? OR lang_id IS NULL)
        GROUP BY member_jid
    ");
    $stmt->execute([$startDate, $endDate, $lang]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $dedication = [];
    $msgs       = [];
    $reacts     = [];

    foreach ($rows as $r) {
        if ($r['total_ded']    > 0) $dedication[] = $r;
        if ($r['total_msgs']   > 0) $msgs[]       = $r;
        if ($r['total_reacts'] > 0) $reacts[]     = $r;
    }

    // Se NÃO houver NENHUM dado para este idioma no período, não envia nada
    if (empty($dedication) && empty($msgs) && empty($reacts)) {
        echo "  ℹ️ Nenhuma pontuação registrada para o idioma [{$lang}] no período ({$startDate} a {$endDate}). Disparo cancelado.\n";
        if (!$dry_run) {
            $conn->prepare("INSERT INTO mentoria_auto_logs (tipo, data_execucao, detalhes) VALUES (?, ?, ?)")
                 ->execute([$logType, $endDate, json_encode(['period' => $period, 'lang' => $lang, 'status' => 'skipped_empty'])]);
        }
        continue;
    }

    usort($dedication, fn($a, $b) => $b['total_ded']    <=> $a['total_ded']);
    usort($msgs,       fn($a, $b) => $b['total_msgs']   <=> $a['total_msgs']);
    usort($reacts,     fn($a, $b) => $b['total_reacts'] <=> $a['total_reacts']);

    $topMsgs   = array_slice($msgs,   0, 5);
    $topReacts = array_slice($reacts, 0, 5);

    // -------------------------------------------------------
    // MONTAGEM DAS STRINGS por idioma
    // -------------------------------------------------------
    $medals = ['🥇', '🥈', '🥉'];

    $noPointsStr = ($lang === 'es') ? "Sin puntos registrados en este período." : "No points recorded in this period.";
    $noMsgsStr   = ($lang === 'es') ? "Sin mensajes registrados.\n"            : "No messages recorded.\n";
    $noReactsStr = ($lang === 'es') ? "Sin reacciones registradas.\n"          : "No reactions recorded.\n";
    $msgsLabel   = ($lang === 'es') ? "pts"                                     : "pts";
    $msgsUnit    = ($lang === 'es') ? "mensajes"                                : "messages";
    $reactUnit   = ($lang === 'es') ? "reacciones"                              : "reactions";

    $studentStr = '';
    if (!empty($dedication)) {
        $i = 0;
        foreach ($dedication as $d) {
            $rankStr    = ($i < 3) ? $medals[$i] : ($i + 1) . ".";
            $nomeStr    = trim($d['member_name']) ?: 'Unknown';
            $studentStr .= $rankStr . " *{$nomeStr}* — {$d['total_ded']} pts\n";
            $i++;
        }
    } else {
        $studentStr = $noPointsStr;
    }

    $msgList = '';
    if (!empty($topMsgs)) {
        $i = 0;
        foreach ($topMsgs as $d) {
            $rankStr  = ($i < 3) ? $medals[$i] : ($i + 1) . ".";
            $nomeStr  = trim($d['member_name']) ?: 'Unknown';
            $msgList .= $rankStr . " *{$nomeStr}* — {$d['total_msgs']} {$msgsUnit}\n";
            $i++;
        }
    } else {
        $msgList = $noMsgsStr;
    }

    $reactList = '';
    if (!empty($topReacts)) {
        $i = 0;
        foreach ($topReacts as $d) {
            $rankStr    = ($i < 3) ? $medals[$i] : ($i + 1) . ".";
            $nomeStr    = trim($d['member_name']) ?: 'Unknown';
            $reactList .= $rankStr . " *{$nomeStr}* — {$d['total_reacts']} {$reactUnit}\n";
            $i++;
        }
    } else {
        $reactList = $noReactsStr;
    }

    // -------------------------------------------------------
    // FORMATAÇÃO DA DATA DO PERÍODO por idioma
    // -------------------------------------------------------
    $mesesEs = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];

    if ($period === 'weekly') {
        $titleDateStr = ($lang === 'es')
            ? (new DateTime($startDate))->format('j') . ' – ' . (new DateTime($endDate))->format('j') . ' de ' . $mesesEs[(int)(new DateTime($endDate))->format('n')] . ' de ' . (new DateTime($endDate))->format('Y')
            : (new DateTime($startDate))->format('F jS') . ' – ' . (new DateTime($endDate))->format('F jS, Y');
    } elseif ($period === 'monthly') {
        $titleDateStr = ($lang === 'es')
            ? $mesesEs[(int)(new DateTime($startDate))->format('n')] . ' ' . (new DateTime($startDate))->format('Y')
            : (new DateTime($startDate))->format('F Y');
    } else { // yearly
        $titleDateStr = (new DateTime($startDate))->format('Y');
    }

    // -------------------------------------------------------
    // TEMPLATE (lê do config, ou usa defaults por idioma)
    // -------------------------------------------------------
    $sep = "━━━━━━━━━━━━━━━━━━━━━━";

    $defaults = [
        'weekly' => [
            'es' => "🗓️🗓️🗓️ *RANKING SEMANAL* 🗓️🗓️🗓️\n📅 _{period_date}_\n{sep}\n\n🌟 *ESTUDIANTE DE LA SEMANA*\n{students}\n\n{sep}\n\n💬 *TOP MENSAJEROS DE LA SEMANA*\n_¿Quién envió más mensajes?_\n{messages}\n\n{sep}\n\n❤️ *EMOJI GANG DE LA SEMANA*\n_¿Quién dio más reacciones?_\n{reactions}\n\n{sep}\n\n✨ *¡Una nueva semana ha comenzado!*\n_Sigue apareciendo, sigue practicando, sigue destacando. El podio de la próxima semana aún está disponible. ¿Será tuyo?_ 💪",
            'en' => "🗓️🗓️🗓️ *RANKING SEMANAL* 🗓️🗓️🗓️\n📅 _{period_date}_\n{sep}\n\n🌟 *STUDENT OF THE WEEK*\n{students}\n\n{sep}\n\n💬 *WORD SLINGERS DA SEMANA*\n_Who sent the most messages?_\n{messages}\n\n{sep}\n\n❤️ *EMOJI GANG DA SEMANA*\n_Who gave the most reactions?_\n{reactions}\n\n{sep}\n\n✨ *A new week has just begun!*\n_Keep showing up, keep practicing, keep standing out. Next week's podium is still up for grabs — will it be yours?_ 💪",
        ],
        'monthly' => [
            'es' => "🗃️🗃️🗃️ *RANKING MENSUAL* 🗃️🗃️🗃️\n📅 _{period_date}_\n{sep}\n\n🌟 *ESTUDIANTE DEL MES*\n{students}\n\n{sep}\n\n💬 *TOP MENSAJEROS DEL MES*\n_¿Quién envió más mensajes?_\n{messages}\n\n{sep}\n\n❤️ *EMOJI GANG DEL MES*\n_¿Quién dio más reacciones?_\n{reactions}\n\n{sep}\n\n🌙 *¡Un nuevo mes comienza!*\n_¿Puedes superar tu puntaje del mes pasado? Esfuérzate un poco más — cada mensaje, cada reacción, cada clase te acerca a la cima. ¡Adelante!_ 🏆",
            'en' => "🗃️🗃️🗃️ *RANKING MENSAL* 🗃️🗃️🗃️\n📅 _{period_date}_\n{sep}\n\n🌟 *STUDENT OF THE MONTH*\n{students}\n\n{sep}\n\n💬 *WORD SLINGERS DO MÊS*\n_Who sent the most messages?_\n{messages}\n\n{sep}\n\n❤️ *EMOJI GANG DO MÊS*\n_Who gave the most reactions?_\n{reactions}\n\n{sep}\n\n🌙 *A new month begins!*\n_Can you beat your score from last month? Push yourself a little further — every message, every reaction, every class gets you closer to the top. Go for it!_ 🏆",
        ],
        'yearly' => [
            'es' => "🏅🏅🏅 *RANKING ANUAL* 🏅🏅🏅\n📅 _{period_date}_\n{sep}\n\n🌟 *ESTUDIANTE DEL AÑO*\n{students}\n\n{sep}\n\n💬 *TOP MENSAJEROS DEL AÑO*\n_¿Quién envió más mensajes?_\n{messages}\n\n{sep}\n\n❤️ *EMOJI GANG DEL AÑO*\n_¿Quién dio más reacciones?_\n{reactions}\n\n{sep}\n\n🌅 *El año ha girado. El camino continúa.*\n_El español que aprendes, nadie te lo quita. En este nuevo año, que cada palabra nueva sea un paso más hacia tu crecimiento. ¡Feliz Año Nuevo! 🎉_",
            'en' => "🏅🏅🏅 *RANKING ANUAL* 🏅🏅🏅\n📅 _{period_date}_\n{sep}\n\n🌟 *STUDENT OF THE YEAR*\n{students}\n\n{sep}\n\n💬 *WORD SLINGERS DO ANO*\n_Who sent the most messages?_\n{messages}\n\n{sep}\n\n❤️ *EMOJI GANG DO ANO*\n_Who gave the most reactions?_\n{reactions}\n\n{sep}\n\n🌅 *The year has turned. The journey continues.*\n_The English you learn, nobody can take from you. In this new year, may every new word be one more step towards your growth — and greater fluency for those who are already there. Happy New Year! 🎉_",
        ],
    ];

    $templateKey = "ranking_{$period}";
    $langDefault = ($defaults[$period][$lang] ?? $defaults[$period]['en'] ?? $defaults['weekly']['en']);
    $template    = !empty($config['templates'][$templateKey])
        ? $config['templates'][$templateKey]
        : $langDefault;

    $msg = str_replace(
        ['{period_date}', '{sep}', '{students}', '{messages}', '{reactions}'],
        [$titleDateStr, $sep, rtrim($studentStr), rtrim($msgList), rtrim($reactList)],
        $template
    );

    if ($dry_run) {
        echo "  [DRY-RUN] Ranking {$periodTitle} [{$lang}] seria enviado para {$targetGroup}:\n" . str_repeat('-', 40) . "\n{$msg}\n" . str_repeat('-', 40) . "\n";
        continue;
    }

    // Disparo único
    $result1 = enviarWhatsApp($targetGroup, $msg, "mentoria_ranking_{$period}_{$lang}");

    if ($result1['httpCode'] >= 200 && $result1['httpCode'] < 300) {
        $conn->prepare("INSERT INTO mentoria_auto_logs (tipo, data_execucao, detalhes) VALUES (?, ?, ?)")
             ->execute([$logType, $endDate, json_encode(['period' => $period, 'lang' => $lang])]);
        echo "  ✅ Ranking {$periodTitle} [{$lang}] enviado com sucesso!\n";
    } else {
        echo "  ❌ Erro ao enviar ranking {$periodTitle} [{$lang}]: HTTP " . $result1['httpCode'] . "\n";
    }
}

echo "\n🏁 Ranking Periódico Multi-idioma ({$periodTitle}) concluído.\n";
