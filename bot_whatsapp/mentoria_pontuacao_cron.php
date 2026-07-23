<?php
/**
 * CRON: Ranking Unificado da Mentoria (Student of the Day + Social)
 * Frequência: 1x/dia à meia-noite
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

$conn->exec("
    CREATE TABLE IF NOT EXISTS mentoria_daily_scores (
        id INT AUTO_INCREMENT PRIMARY KEY,
        member_jid VARCHAR(100) NOT NULL,
        member_name VARCHAR(255) NOT NULL,
        score_date DATE NOT NULL,
        dedication_pts INT DEFAULT 0,
        social_msgs INT DEFAULT 0,
        social_reacts INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_member_date (member_jid, score_date),
        INDEX idx_score_date (score_date)
    )
");

try {
    $conn->exec("ALTER TABLE mentoria_desafio_streaks ADD COLUMN member_name VARCHAR(255) NULL");
} catch (Exception $e) {}

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

$GROUP_EMOJIS = [
    'pronunciation' => '🗣️',
    'desafio'       => '📚',
    'music'         => '🎶',
    'games'         => '🧩',
    'vocabulary'    => '📒'
];

if (!empty($config['groups'])) {
    foreach ($config['groups'] as $groupKey => $groupData) {
        $groupJid = $groupData['jid'] ?? '';
        if (!$groupJid) continue;
        
        $groupMembers = fetchGroupMembers($groupJid);
        $groupAdmins = [];
        foreach ($groupMembers as $m) {
            if (!empty($m['admin'])) $groupAdmins[] = preg_replace('/:\d+@/', '@', $m['id']);
        }
        
        if (isset($activity[$groupJid])) {
            foreach ($activity[$groupJid] as $memberJid => $data) {
                // Ignora admin e JIDs de grupos (fantasmas)
                $cleanMemberJid = preg_replace('/:\d+@/', '@', $memberJid);
                if ($cleanMemberJid === preg_replace('/:\d+@/', '@', $adminJid)) continue;
                if (in_array($cleanMemberJid, $groupAdmins)) continue;
                if (str_ends_with($memberJid, '@g.us')) continue;
                
                $nome = trim($data['name'] ?? 'Unknown');
                if ($nome === 'Unknown' || empty($nome)) {
                    $stmtName = $conn->prepare("SELECT nome FROM mentoria_alunos WHERE telefone = ? AND nome IS NOT NULL AND nome != '' LIMIT 1");
                    $phoneOnly = preg_replace('/\D/', '', explode('@', $memberJid)[0]);
                    $stmtName->execute([$phoneOnly]);
                    $rowName = $stmtName->fetch(PDO::FETCH_ASSOC);
                    if ($rowName) {
                        $nome = $rowName['nome'];
                    } else {
                        $stmtName2 = $conn->prepare("SELECT member_name FROM mentoria_desafio_streaks WHERE member_jid = ? AND member_name IS NOT NULL AND member_name != '' LIMIT 1");
                        $stmtName2->execute([$memberJid]);
                        $rowName2 = $stmtName2->fetch(PDO::FETCH_ASSOC);
                        if ($rowName2) {
                            $nome = $rowName2['member_name'];
                        }
                    }
                }

                // Ignora contas da Staff / Testes (devido a limitações de @lid no WhatsApp Business)
                if (stripos($nome, 'Staff') !== false || stripos($nome, 'Test') !== false) continue;

                // Track Social (Messages & Reactions across ALL groups)
                if (!isset($rankingMsgs[$memberJid])) {
                    $rankingMsgs[$memberJid] = [
                        'name'  => $nome,
                        'score' => 0
                    ];
                }
                $rankingMsgs[$memberJid]['score'] += ($data['messages'] ?? 0) + ($data['images_sent'] ?? 0) + ($data['audios_sent'] ?? 0);

                if (!isset($rankingReacts[$memberJid])) {
                    $rankingReacts[$memberJid] = ['name' => $nome, 'score' => 0];
                }
                $rankingReacts[$memberJid]['score'] += $data['reactions_given'] ?? 0;

                // Dedication points are now fetched separately from mentoria_dedicated_pts
            }
        }
    }
}

// -----------------------------------------------------
// 1. DEDICAÇÃO: Base Desafio (5 pts garantidos)
// -----------------------------------------------------

$stmtStreak = $conn->prepare("SELECT member_jid, member_name FROM mentoria_desafio_streaks WHERE last_completed_date = ?");
$stmtStreak->execute([$ontem]);
$streakCompleters = $stmtStreak->fetchAll(PDO::FETCH_ASSOC);

foreach ($streakCompleters as $completer) {
    $mJid = $completer['member_jid'];
    $cleanMJid = preg_replace('/:\d+@/', '@', $mJid);
    
    // Ignora admin
    if ($cleanMJid === preg_replace('/:\d+@/', '@', $adminJid)) continue;
    
    $mName = $completer['member_name'] ?? 'Unknown';
    if ($mName === 'Unknown' || empty(trim($mName))) {
        $stmtName = $conn->prepare("SELECT nome FROM mentoria_alunos WHERE telefone = ? AND nome IS NOT NULL AND nome != '' LIMIT 1");
        $phoneOnly = preg_replace('/\D/', '', explode('@', $mJid)[0]);
        $stmtName->execute([$phoneOnly]);
        $rowName = $stmtName->fetch(PDO::FETCH_ASSOC);
        if ($rowName) $mName = $rowName['nome'];
    }

    if (stripos($mName, 'Staff') !== false || stripos($mName, 'Test') !== false) continue;

    if (!isset($memberStats[$mJid])) {
        $memberStats[$mJid] = ['name' => $mName, 'total_pts' => 0, 'emojis' => []];
    }
    
    // Adiciona 5 pontos base e o emoji
    $memberStats[$mJid]['total_pts'] += 5;
    if (!in_array('📚', $memberStats[$mJid]['emojis'])) {
        $memberStats[$mJid]['emojis'][] = '📚';
    }
    
    if ($memberStats[$mJid]['name'] === 'Unknown' && $mName !== 'Unknown' && trim($mName) !== '') {
        $memberStats[$mJid]['name'] = $mName;
    }
}

// -----------------------------------------------------
// 2. DEDICAÇÃO: Pontos Manuais (!1 a !5)
// -----------------------------------------------------

$stmtPts = $conn->prepare("
    SELECT member_jid, member_name, group_key, SUM(points) as group_pts
    FROM mentoria_dedicated_pts
    WHERE date = ?
    GROUP BY member_jid, group_key
");
$stmtPts->execute([$ontem]);
$manualPoints = $stmtPts->fetchAll(PDO::FETCH_ASSOC);

foreach ($manualPoints as $row) {
    $mJid = $row['member_jid'];
    $cleanMJid = preg_replace('/:\d+@/', '@', $mJid);
    if ($cleanMJid === preg_replace('/:\d+@/', '@', $adminJid)) continue;

    $mName = $row['member_name'] ?: 'Unknown';
    if ($mName === 'Unknown') {
        $stmtName = $conn->prepare("SELECT nome FROM mentoria_alunos WHERE telefone = ? AND nome IS NOT NULL AND nome != '' LIMIT 1");
        $phoneOnly = preg_replace('/\D/', '', explode('@', $mJid)[0]);
        $stmtName->execute([$phoneOnly]);
        $rowName = $stmtName->fetch(PDO::FETCH_ASSOC);
        if ($rowName) $mName = $rowName['nome'];
    }

    if (stripos($mName, 'Staff') !== false || stripos($mName, 'Test') !== false) continue;

    if (!isset($memberStats[$mJid])) {
        $memberStats[$mJid] = ['name' => $mName, 'total_pts' => 0, 'emojis' => []];
    }
    
    $memberStats[$mJid]['total_pts'] += (int)$row['group_pts'];
    $emoji = $GROUP_EMOJIS[$row['group_key']] ?? '⭐';
    if (!in_array($emoji, $memberStats[$mJid]['emojis'])) {
        $memberStats[$mJid]['emojis'][] = $emoji;
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
    $msgList .= $rankStr . " *{$nomeStr}* — {$data['score']} messages\n";
    $i++;
}

$reactList = '';
$i = 0;
foreach ($top5Reacts as $jid => $data) {
    $rankStr = ($i < 3) ? $medals[$i] : ($i + 1) . ".";
    $nomeStr = trim($data['name']) ?: 'Unknown';
    $reactList .= $rankStr . " *{$nomeStr}* — {$data['score']} reactions\n";
    $i++;
}

$wordSlingersList = $msgList ?: "No messages yesterday.\n";
$emojiGangList = $reactList ?: "No reactions yesterday.\n";

// -----------------------------------------------------
// 3. SALVAR PONTOS DO DIA NO BANCO
// -----------------------------------------------------
foreach ($memberStats as $jid => $data) {
    $msgs   = $rankingMsgs[$jid]['score']   ?? 0;
    $reacts = $rankingReacts[$jid]['score'] ?? 0;
    
    $stmtSave = $conn->prepare("
        INSERT INTO mentoria_daily_scores (member_jid, member_name, score_date, dedication_pts, social_msgs, social_reacts)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            member_name    = VALUES(member_name),
            dedication_pts = VALUES(dedication_pts),
            social_msgs    = VALUES(social_msgs),
            social_reacts  = VALUES(social_reacts)
    ");
    $stmtSave->execute([$jid, $data['name'], $ontem, $data['total_pts'], $msgs, $reacts]);
}

// Para garantir que quem não pontuou em dedicação mas pontuou em social também seja salvo:
$allJids = array_unique(array_merge(array_keys($rankingMsgs), array_keys($rankingReacts)));
foreach ($allJids as $jid) {
    if (isset($memberStats[$jid])) continue; // já salvo acima
    
    $msgs   = $rankingMsgs[$jid]['score']   ?? 0;
    $reacts = $rankingReacts[$jid]['score'] ?? 0;
    $name   = $rankingMsgs[$jid]['name'] ?? $rankingReacts[$jid]['name'] ?? 'Unknown';
    
    $stmtSave = $conn->prepare("
        INSERT INTO mentoria_daily_scores (member_jid, member_name, score_date, dedication_pts, social_msgs, social_reacts)
        VALUES (?, ?, ?, 0, ?, ?)
        ON DUPLICATE KEY UPDATE
            member_name    = VALUES(member_name),
            social_msgs    = VALUES(social_msgs),
            social_reacts  = VALUES(social_reacts)
    ");
    $stmtSave->execute([$jid, $name, $ontem, $msgs, $reacts]);
}

// -----------------------------------------------------
// 4. MONTAGEM FINAL DAS MENSAGENS
// -----------------------------------------------------
$enDate = date('F jS, Y', strtotime($ontem));

$msg1 = "📅 {$enDate}\n\n⭐ *STUDENT OF THE DAY*\n\n{$studentOfTheDayStr}\n\n*Other students:*\n{$othersStr}\n\n📖 *Legend:*\n{$legendStr}";

$msg2 = "📅 {$enDate}\n\n💬 *TOP MESSENGER*\n_Who sent the most messages today?_\n\n{$wordSlingersList}";

$msg3 = "📅 {$enDate}\n\n❤️ *TOP REACTOR*\n_Who gave the most reactions today?_\n\n{$emojiGangList}";

// Disparo simples (sem mentions de @numero)
$result1 = enviarWhatsApp($targetGroup, $msg1, 'mentoria_ranking_student');
sleep(1);
$result2 = enviarWhatsApp($targetGroup, $msg2, 'mentoria_ranking_messenger');
sleep(1);
$result3 = enviarWhatsApp($targetGroup, $msg3, 'mentoria_ranking_reactor');

if ($result1['httpCode'] >= 200 && $result1['httpCode'] < 300) {
    $conn->prepare("INSERT INTO mentoria_auto_logs (tipo, data_execucao, detalhes) VALUES ('ranking_unificado', ?, ?)")
         ->execute([$ontem, json_encode(['stats' => $memberStats])]);
    echo "✅ Rankings enviados com sucesso (em 3 mensagens separadas)!";
} else {
    echo "❌ Erro ao enviar ranking: HTTP " . $result1['httpCode'];
}
