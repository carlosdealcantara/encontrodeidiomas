<?php
/**
 * CRON: Ranking Diário da Comunidade Global (Grupos de Idiomas)
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

// Garantir que a tabela existe
$conn->exec("
    CREATE TABLE IF NOT EXISTS community_daily_scores (
        id              INT AUTO_INCREMENT PRIMARY KEY,
        group_jid       VARCHAR(100) NOT NULL,
        group_name      VARCHAR(100) NOT NULL,
        member_jid      VARCHAR(100) NOT NULL,
        member_name     VARCHAR(255) NOT NULL,
        score_date      DATE NOT NULL,
        total_msgs      INT DEFAULT 0,
        total_reacts    INT DEFAULT 0,
        created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_member_group_date (member_jid, group_jid, score_date),
        INDEX idx_score_date (score_date),
        INDEX idx_group_date (group_jid, score_date)
    )
");

$ontem = (new DateTime())->modify('-1 day')->format('Y-m-d');

$check = $conn->prepare("SELECT id FROM mentoria_auto_logs WHERE tipo = 'community_ranking_daily' AND data_execucao = ?");
$check->execute([$ontem]);
if ($check->rowCount() > 0 && !isset($_GET['force'])) {
    die("Ranking da comunidade já processado para esta data ($ontem). Use &force=1 para forçar o reenvio.");
}

$config = getMentoriaConfig();

// Default templates se não houver customizado
$tplMsg = $config['templates']['community_ranking_messenger'] ?? "📊 *DAILY RANKING — {group_name}*\n📅 _{date}_\n━━━━━━━━━━━━━━━━━━━━━━\n\n💬 *TOP TALKERS*\n_Who sent the most messages today?_\n\n{msg_ranking_list}\n\n━━━━━━━━━━━━━━━━━━━━━━\n✨ _Keep the conversation going! Tomorrow's ranking starts now._ 🚀";

$tplReact = $config['templates']['community_ranking_reactor'] ?? "❤️ *REACTION STARS — {group_name}*\n📅 _{date}_\n━━━━━━━━━━━━━━━━━━━━━━\n\n_Who spread the most love today?_\n\n{react_ranking_list}\n\n━━━━━━━━━━━━━━━━━━━━━━\n_React to others and climb the ranking! 🙌_";

$enDate = date('F jS, Y', strtotime($ontem));
$activity = fetchBaileysActivity($ontem);
$medals = ['🥇', '🥈', '🥉'];

// Vamos iterar sobre os grupos informados pelo admin (hardcoded fallback):
$fallbackGroups = [
    'general'    => ['jid' => '120363376534962322@g.us', 'name' => 'General Global'],
    'portugues'  => ['jid' => '120363431497887859@g.us', 'name' => 'Portuguese'],
    'ingles'     => ['jid' => '120363429593063763@g.us', 'name' => 'English'],
    'alemao'     => ['jid' => '120363411676595116@g.us', 'name' => 'German'],
    'russo'      => ['jid' => '120363428648918342@g.us', 'name' => 'Russian'],
    'japones'    => ['jid' => '120363413358157100@g.us', 'name' => 'Japanese'],
    'espanhol'   => ['jid' => '120363415481117708@g.us', 'name' => 'Spanish'],
    'chines'     => ['jid' => '120363414819628495@g.us', 'name' => 'Chinese'],
    'indonesio'  => ['jid' => '120363275425256422@g.us', 'name' => 'Indonesian'],
    'italiano'   => ['jid' => '120363430096350808@g.us', 'name' => 'Italian'],
];

$communityGroups = [];

// Checa config se há grupos com is_community_group = true e ranking_enabled = true
foreach (($config['groups'] ?? []) as $key => $gData) {
    if (!empty($gData['is_community_group']) && !empty($gData['ranking_enabled'])) {
        $communityGroups[$key] = [
            'jid' => $gData['jid'],
            'name' => $gData['name'] ?? ucfirst($key)
        ];
    }
}

// Se não houver nenhum configurado dinamicamente no JSON, usa a base hardcoded
// E processaremos todos eles assumindo que o toggle de desligar ainda não foi acionado.
if (empty($communityGroups)) {
    $communityGroups = $fallbackGroups;
}

$adminJid = $config['admin_jid'] ?? "556192666148@s.whatsapp.net";

foreach ($communityGroups as $groupKey => $gData) {
    $groupJid = $gData['jid'];
    $groupName = $gData['name'];
    
    if (!isset($activity[$groupJid])) {
        echo "Sem atividade para o grupo $groupName ($groupJid) em $ontem.<br>";
        continue;
    }
    
    $rankingMsgs = [];
    $rankingReacts = [];
    
    foreach ($activity[$groupJid] as $memberJid => $data) {
        $cleanMemberJid = preg_replace('/:\d+@/', '@', $memberJid);
        
        // Excluir APENAS o admin principal e a conta do bot (mentoria.js vai permitir outros admins agora)
        if ($cleanMemberJid === preg_replace('/:\d+@/', '@', $adminJid)) continue;
        if (str_ends_with($memberJid, '@g.us')) continue;
        
        $nome = trim($data['name'] ?? 'Unknown');
        if ($nome === 'Unknown' || empty($nome) || $nome === 'Desconhecido') {
            $phoneOnly = preg_replace('/\D/', '', explode('@', $memberJid)[0]);
            $stmtName = $conn->prepare("SELECT nome FROM mentoria_alunos WHERE telefone = ? AND nome IS NOT NULL AND nome != '' LIMIT 1");
            $stmtName->execute([$phoneOnly]);
            $rowName = $stmtName->fetch(PDO::FETCH_ASSOC);
            if ($rowName) $nome = $rowName['nome'];
        }

        if (stripos($nome, 'Staff') !== false || stripos($nome, 'Test') !== false) continue;

        $msgs = ($data['messages'] ?? 0) + ($data['images_sent'] ?? 0) + ($data['audios_sent'] ?? 0);
        $reacts = $data['reactions_given'] ?? 0;
        
        if ($msgs > 0) {
            $rankingMsgs[$memberJid] = ['name' => $nome, 'score' => $msgs];
        }
        if ($reacts > 0) {
            $rankingReacts[$memberJid] = ['name' => $nome, 'score' => $reacts];
        }
        
        if ($msgs > 0 || $reacts > 0) {
            $stmtSave = $conn->prepare("
                INSERT INTO community_daily_scores (group_jid, group_name, member_jid, member_name, score_date, total_msgs, total_reacts)
                VALUES (?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    member_name  = VALUES(member_name),
                    total_msgs   = VALUES(total_msgs),
                    total_reacts = VALUES(total_reacts)
            ");
            $stmtSave->execute([$groupJid, $groupName, $memberJid, $nome, $ontem, $msgs, $reacts]);
        }
    }
    
    uasort($rankingMsgs, fn($a, $b) => $b['score'] <=> $a['score']);
    $top10Msgs = array_slice($rankingMsgs, 0, 10, true);
    
    uasort($rankingReacts, fn($a, $b) => $b['score'] <=> $a['score']);
    $top10Reacts = array_slice($rankingReacts, 0, 10, true);
    
    $msgList = '';
    $i = 0;
    foreach ($top10Msgs as $jid => $data) {
        $rankStr = ($i < 3) ? $medals[$i] : ($i + 1) . ".";
        $nomeStr = trim($data['name']) ?: 'Unknown';
        $msgList .= $rankStr . " *{$nomeStr}* — {$data['score']} msgs\n";
        $i++;
    }
    
    $reactList = '';
    $i = 0;
    foreach ($top10Reacts as $jid => $data) {
        $rankStr = ($i < 3) ? $medals[$i] : ($i + 1) . ".";
        $nomeStr = trim($data['name']) ?: 'Unknown';
        $reactList .= $rankStr . " *{$nomeStr}* — {$data['score']} reacts\n";
        $i++;
    }
    
    if (!empty($msgList)) {
        $msgToSend = str_replace(
            ['{date}', '{group_name}', '{msg_ranking_list}'],
            [$enDate, $groupName, rtrim($msgList)],
            $tplMsg
        );
        enviarWhatsApp($groupJid, $msgToSend, 'community_ranking_messenger');
        echo "Ranking de mensagens enviado para $groupName.<br>";
        sleep(3);
    }
    
    if (!empty($reactList)) {
        $reactToSend = str_replace(
            ['{date}', '{group_name}', '{react_ranking_list}'],
            [$enDate, $groupName, rtrim($reactList)],
            $tplReact
        );
        enviarWhatsApp($groupJid, $reactToSend, 'community_ranking_reactor');
        echo "Ranking de reações enviado para $groupName.<br>";
        sleep(5);
    }
}

$conn->prepare("INSERT INTO mentoria_auto_logs (tipo, data_execucao, detalhes) VALUES ('community_ranking_daily', ?, ?)")
     ->execute([$ontem, json_encode(['processed_groups' => count($communityGroups)])]);

echo "<hr>✅ Processamento diário da comunidade finalizado.";
?>
