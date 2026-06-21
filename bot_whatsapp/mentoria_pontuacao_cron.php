<?php
/**
 * CRON: Ranking Unificado da Mentoria (Student of the Day + Social)
 * Frequência: 1x/dia à meia-noite
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

$conn->exec("
    CREATE TABLE IF NOT EXISTS mentoria_auto_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tipo VARCHAR(50) NOT NULL,
        data_execucao DATE NOT NULL,
        membro_jid VARCHAR(100) NULL,
        detalhes TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_execucao (tipo, data_execucao, membro_jid)
    )
");

$ontem = (new DateTime())->modify('-1 day')->format('Y-m-d');

$check = $conn->prepare("SELECT id FROM mentoria_auto_logs WHERE tipo = 'ranking_unificado' AND data_execucao = ?");
$check->execute([$ontem]);
if ($check->rowCount() > 0 && !isset($_GET['force'])) {
    die("Ranking já postado para esta data ($ontem). Use &force=1 para forçar o reenvio.");
}

$config = getMentoriaConfig();

$targetGroup = $config['groups']['the_lounge']['jid'] ?? null;
if (!$targetGroup) {
    // Debug: mostra o config completo para diagnóstico
    echo "❌ Grupo alvo (The Lounge) não configurado.\n\n";
    echo "Chaves de 'groups' disponíveis no config:\n";
    foreach (($config['groups'] ?? []) as $key => $val) {
        echo "  - '{$key}' => jid: " . ($val['jid'] ?? '(vazio)') . "\n";
    }
    echo "\nConfig completo recebido:\n";
    echo json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    die();
}

$adminJid = $config['admin_jid'] ?? "556192666148@s.whatsapp.net";

$activity = fetchBaileysActivity($ontem);
$memberStats = [];
$rankingMsgs = [];
$rankingReacts = [];

$SCORING_RULES = [
    'pronunciation' => [ ['field' => 'audios_sent', 'pts' => 5, 'emoji' => '🗣️'] ],
    'desafio'       => [ ['field' => 'images_sent', 'pts' => 5, 'emoji' => '📚'] ],
    'music'         => [ ['field' => 'images_sent', 'pts' => 4, 'emoji' => '🎶'] ],
    'games'         => [ ['field' => 'images_sent', 'pts' => 2, 'emoji' => '🧩'] ],
    'vocabulary'    => [ ['field' => 'images_sent', 'pts' => 1, 'emoji' => '📒'] ]
];

if (!empty($config['groups'])) {
    foreach ($config['groups'] as $groupKey => $groupData) {
        $groupJid = $groupData['jid'] ?? '';
        if (!$groupJid) continue;
        
        if (isset($activity[$groupJid])) {
            foreach ($activity[$groupJid] as $memberJid => $data) {
                // Ignora admin e JIDs de grupos (fantasmas)
                if ($memberJid === $adminJid) continue;
                if (str_ends_with($memberJid, '@g.us')) continue;
                
                // Track Social (Messages & Reactions across ALL groups)
                $interactions = ($data['messages'] ?? 0) + ($data['images_sent'] ?? 0) + ($data['audios_sent'] ?? 0) + ($data['reactions_given'] ?? 0);
                if ($interactions > 0) {
                    if (!isset($rankingMsgs[$memberJid])) {
                        $rankingMsgs[$memberJid] = [
                            'name'  => $data['name'] ?? 'Unknown',
                            'score' => 0
                        ];
                    }
                    // Para o Word Slingers, contamos todas as mensagens físicas e comandos (messages) + mídias (images/audios)
                    $rankingMsgs[$memberJid]['score'] += ($data['messages'] ?? 0) + ($data['images_sent'] ?? 0) + ($data['audios_sent'] ?? 0);
                }
                
                if (!isset($rankingReacts[$memberJid])) {
                    $rankingReacts[$memberJid] = ['name' => $data['name'] ?? 'Unknown', 'score' => 0];
                }
                $rankingReacts[$memberJid]['score'] += $data['reactions_given'] ?? 0;

                // Track Dedication Points (Only for groups in SCORING_RULES)
                if (isset($SCORING_RULES[$groupKey])) {
                    if (!isset($memberStats[$memberJid])) {
                        $memberStats[$memberJid] = ['name' => $data['name'] ?? 'Unknown', 'total_pts' => 0, 'emojis' => []];
                    }
                    
                    foreach ($SCORING_RULES[$groupKey] as $rule) {
                        $field = $rule['field'];
                        if (!empty($data[$field]) && $data[$field] > 0) {
                            $memberStats[$memberJid]['total_pts'] += $rule['pts'];
                            $memberStats[$memberJid]['emojis'][] = $rule['emoji'];
                        }
                    }
                }
            }
        }
    }
}

// Aula / Attendance (20 pts)
$stmt = $conn->prepare("SELECT member_jid, member_name FROM class_attendances WHERE aula_date = ?");
$stmt->execute([$ontem]);
$attendees = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($attendees as $att) {
    $mJid = $att['member_jid'];
    if ($mJid === $adminJid) continue;
    if (!isset($memberStats[$mJid])) {
        $memberStats[$mJid] = ['name' => $att['member_name'], 'total_pts' => 0, 'emojis' => []];
    }
    // Adiciona no início do array para o 🖥️ aparecer primeiro
    $memberStats[$mJid]['total_pts'] += 20;
    array_unshift($memberStats[$mJid]['emojis'], '🖥️');
}

// -----------------------------------------------------
// 1. DEDICAÇÃO (Student of the Day)
// -----------------------------------------------------
$memberStats = array_filter($memberStats, fn($m) => $m['total_pts'] > 0);
uasort($memberStats, fn($a, $b) => $b['total_pts'] <=> $a['total_pts']);

$studentOfTheDayStr = '';
$othersStr = '';
$i = 0;

if (!empty($memberStats)) {
    // Detectar pontuação máxima e checar empates
    $maxPts = reset($memberStats)['total_pts'];
    $winners = array_filter($memberStats, fn($m) => $m['total_pts'] === $maxPts);
    $losers  = array_filter($memberStats, fn($m) => $m['total_pts'] < $maxPts);

    if (count($winners) === 1) {
        // Vencedor único
        $w = reset($winners);
        $studentOfTheDayStr = "🏆 *{$w['name']}* — " . implode('', $w['emojis']) . " — *{$w['total_pts']} pts*";
    } else {
        // Empate no topo — lista todos
        $tiedNames = [];
        foreach ($winners as $w) {
            $tiedNames[] = "*{$w['name']}* — " . implode('', $w['emojis']) . " — *{$w['total_pts']} pts*";
        }
        $studentOfTheDayStr = "🏆 *It's a tie!*\n" . implode("\n", $tiedNames);
    }

    $startPos = count($winners) + 1;
    $i = $startPos;
    foreach ($losers as $jid => $data) {
        $emojisStr = implode('', $data['emojis']);
        $nomeStr = trim($data['name']) ?: 'Unknown';
        $othersStr .= "{$i}. *{$nomeStr}* — {$emojisStr} — {$data['total_pts']} pts\n";
        $i++;
    }
} else {
    $studentOfTheDayStr = "No participants yesterday.";
}

$othersStr = $othersStr ?: "No other participants yesterday.";

// Nova legenda
$legendStr = "🖥️ Attended Class (20 pts)\n🗣️ Reading out loud (5 pts)\n📚 Challenge (5 pts)\n🎶 Music Lab (4 pts)\n🧩 Games (2 pts)\n📒 New word! (1 pt)";


// -----------------------------------------------------
// 2. SOCIAL (Word Slingers & Emoji Gang)
// -----------------------------------------------------
$rankingMsgs = array_filter($rankingMsgs, fn($item) => $item['score'] > 0);
$rankingReacts = array_filter($rankingReacts, fn($item) => $item['score'] > 0);

uasort($rankingMsgs, fn($a, $b) => $b['score'] <=> $a['score']);
$top5Msgs = array_slice($rankingMsgs, 0, 5, true);

uasort($rankingReacts, fn($a, $b) => $b['score'] <=> $a['score']);
$top5Reacts = array_slice($rankingReacts, 0, 5, true);

$medals = ['🥇', '🥈', '🥉'];

$msgList = '';
$i = 0;
foreach ($top5Msgs as $jid => $data) {
    $rankStr = ($i < 3) ? $medals[$i] : ($i + 1) . ".";
    $nomeStr = trim($data['name']) ?: 'Unknown';
    $msgList .= $rankStr . " *{$nomeStr}* — {$data['score']} msgs\n";
    $i++;
}

$reactList = '';
$i = 0;
foreach ($top5Reacts as $jid => $data) {
    $rankStr = ($i < 3) ? $medals[$i] : ($i + 1) . ".";
    $nomeStr = trim($data['name']) ?: 'Unknown';
    $reactList .= $rankStr . " *{$nomeStr}* — {$data['score']} reacts\n";
    $i++;
}

$wordSlingersList = $msgList ?: "No messages yesterday.\n";
$emojiGangList = $reactList ?: "No reactions yesterday.\n";

// -----------------------------------------------------
// 3. MONTAGEM FINAL DA MENSAGEM
// -----------------------------------------------------
// Tenta pegar o template novo (ranking_dedicados)
$template = $config['templates']['ranking_dedicados'] ?? "⭐ *STUDENT OF THE DAY*\n📅 {date}\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n{student_of_the_day}\n\n─────────────────────\n*Other participants:*\n{other_participants}\n\n\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n📖 *Legend:*\n{legend}\n\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n🗣️ *Here are the Word Slingers of the day:*\n{word_slingers_list}\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n🔥 *And the Emoji Gang:*\n{emoji_gang_list}";

$enDate = date('F jS, Y', strtotime($ontem));

$message = str_replace(
    ['{date}', '{student_of_the_day}', '{other_participants}', '{legend}', '{word_slingers_list}', '{emoji_gang_list}'],
    [$enDate, $studentOfTheDayStr, $othersStr, $legendStr, $wordSlingersList, $emojiGangList],
    $template
);

// Disparo simples (sem mentions de @numero)
$result = enviarWhatsApp($targetGroup, $message, 'mentoria_ranking');

if ($result['httpCode'] >= 200 && $result['httpCode'] < 300) {
    $conn->prepare("INSERT INTO mentoria_auto_logs (tipo, data_execucao, detalhes) VALUES ('ranking_unificado', ?, ?)")
         ->execute([$ontem, json_encode(['stats' => $memberStats])]);
    echo "✅ Ranking Unificado enviado!";
} else {
    echo "❌ Erro ao enviar ranking: HTTP " . $result['httpCode'];
}
