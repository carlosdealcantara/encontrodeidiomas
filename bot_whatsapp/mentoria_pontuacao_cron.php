<?php
/**
 * CRON: Ranking dos Dedicados (Student of the Day)
 * Frequência: 1x/dia, logo após o ranking social
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

// Garantir que a tabela existe
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
$hoje_real = date('Y-m-d');

// Anti-duplicidade
$check = $conn->prepare("SELECT id FROM mentoria_auto_logs WHERE tipo = 'ranking_dedicados' AND data_execucao = ?");
$check->execute([$ontem]);
if ($check->rowCount() > 0 && !isset($_GET['force'])) {
    die("Ranking de dedicados já postado para esta data ($ontem). Use &force=1 para forçar o reenvio.");
}

$config = getMentoriaConfig();

$targetGroup = $config['groups']['the_lounge']['jid'] ?? null;
if (!$targetGroup) die("Grupo alvo (The Lounge) não configurado.");

$adminJid = $config['admin_jid'] ?? "556192666148@s.whatsapp.net";

$activity = fetchBaileysActivity($ontem);
$memberStats = [];

$SCORING_RULES = [
    'pronunciation' => [ ['field' => 'audios_sent', 'pts' => 4, 'emoji' => '🗣️'] ],
    'desafio'       => [ ['field' => 'images_sent', 'pts' => 3, 'emoji' => '📚'] ],
    'music'         => [ ['field' => 'images_sent', 'pts' => 2, 'emoji' => '🎶'] ],
    'vocabulary'    => [ ['field' => 'images_sent', 'pts' => 2, 'emoji' => '📒'] ],
    'games'         => [ ['field' => 'images_sent', 'pts' => 2, 'emoji' => '🧩'] ],
    'the_lounge'    => [ 
        ['field' => 'messages', 'pts' => 1, 'emoji' => '💬'], 
        ['field' => 'reactions_given', 'pts' => 1, 'emoji' => '❤️'] 
    ]
];

if (!empty($config['groups'])) {
    foreach ($config['groups'] as $groupKey => $groupData) {
        $groupJid = $groupData['jid'] ?? '';
        if (!$groupJid || !isset($SCORING_RULES[$groupKey])) continue;
        
        if (isset($activity[$groupJid])) {
            foreach ($activity[$groupJid] as $memberJid => $data) {
                if ($memberJid === $adminJid) continue;
                
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

// Aula / Attendance (15 pts)
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
    $memberStats[$mJid]['total_pts'] += 15;
    array_unshift($memberStats[$mJid]['emojis'], '🖥️');
}

// Filtra apenas quem tem pontos e ordena DESC
$memberStats = array_filter($memberStats, fn($m) => $m['total_pts'] > 0);
uasort($memberStats, fn($a, $b) => $b['total_pts'] <=> $a['total_pts']);

if (empty($memberStats)) {
    die("Nenhum estudante qualificado para pontuação ontem.");
}

$top1 = null;
$top1Jid = null;
$others = '';
$i = 0;
$mentions = [];

foreach ($memberStats as $jid => $data) {
    $mentions[] = $jid;
    $jidClean = explode('@', $jid)[0];
    $emojisStr = implode('', $data['emojis']);
    
    if ($i === 0) {
        $top1 = $data;
        $top1Jid = $jidClean;
        $i++;
        continue;
    }
    
    $pos = $i + 1;
    $others .= "{$pos}. @{$jidClean} — {$emojisStr} — {$data['total_pts']} pts\n";
    $i++;
}

$studentOfTheDayStr = "🏆 @{$top1Jid} — " . implode('', $top1['emojis']) . " — *{$top1['total_pts']} pts*";
$othersStr = $others ?: "No other participants yesterday.";

$legendStr = "🖥️ Class (15 pts) · 🗣️ Audio/Pronunciation (4 pts)\n📚 Challenge (3 pts) · 🎶 Music Lab (2 pts)\n📒 New word! (2 pts) · 🧩 Games (2 pts)\n💬 Lounge Msg (1 pt) · ❤️ Reaction (1 pt)";

$template = $config['templates']['ranking_dedicados'] ?? "⭐ *STUDENT OF THE DAY*\n📅 {date}\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n{student_of_the_day}\n\n─────────────────────\n*Other participants:*\n{other_participants}\n\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n📖 *Legend:*\n{legend}";

$enDate = date('F jS, Y', strtotime($ontem));

$message = str_replace(
    ['{date}', '{student_of_the_day}', '{other_participants}', '{legend}'],
    [$enDate, $studentOfTheDayStr, $othersStr, $legendStr],
    $template
);

$mentions = array_values(array_unique($mentions));
$result = enviarWhatsAppMention($targetGroup, $message, $mentions);

if ($result['httpCode'] >= 200 && $result['httpCode'] < 300) {
    $conn->prepare("INSERT INTO mentoria_auto_logs (tipo, data_execucao, detalhes) VALUES ('ranking_dedicados', ?, ?)")
         ->execute([$ontem, json_encode(['stats' => $memberStats])]);
    echo "✅ Ranking dos Dedicados enviado!";
} else {
    echo "❌ Erro ao enviar ranking de dedicados: HTTP " . $result['httpCode'];
}
