<?php
/**
 * CRON: Ranking diário de interação com Ofensivas
 * Frequência: 1x/dia, todos os dias, às 00:00 BRT
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

// Garantir que a tabela de log existe
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

// Pega a data de *ontem*, pois o cron roda à meia-noite
$ontem = (new DateTime())->modify('-1 day')->format('Y-m-d');
$hoje_real = date('Y-m-d');

// Anti-duplicidade (garante que só roda uma vez por dia de execução real, mas checa a data de ontem para não repetir)
$check = $conn->prepare("SELECT id FROM mentoria_auto_logs WHERE tipo = 'ranking_diario' AND data_execucao = ?");
$check->execute([$ontem]);
if ($check->rowCount() > 0 && !isset($_GET['force'])) {
    die("Ranking já postado para esta data ($ontem). Use &force=1 para forçar o reenvio.");
}

$config = getMentoriaConfig();

$targetGroup = $config['groups']['the_lounge']['jid'] ?? null;
if (!$targetGroup) die("Grupo alvo (The Lounge) não configurado.");

$activity = fetchBaileysActivity($ontem); // Pega atividade de ontem!

if (empty($activity)) {
    die("Nenhuma atividade registrada ontem ($ontem) no servidor Baileys.");
}

$adminJid = $config['admin_jid'] ?? "556192666148@s.whatsapp.net";
$rankingMsgs = [];
$rankingReacts = [];

// Coleta apenas os JIDs dos grupos configurados na mentoria
$allowedGroups = [];
if (!empty($config['groups'])) {
    foreach ($config['groups'] as $groupKey => $groupData) {
        if (!empty($groupData['jid'])) {
            $allowedGroups[] = $groupData['jid'];
        }
    }
}

// Agrupa as interações apenas dos grupos configurados
foreach ($activity as $groupJid => $members) {
    if (!in_array($groupJid, $allowedGroups)) {
        continue;
    }
    
    foreach ($members as $memberJid => $data) {
        if ($memberJid === $adminJid) continue;
        
        // Inicializa se não existir
        if (!isset($rankingMsgs[$memberJid])) {
            $rankingMsgs[$memberJid] = ['name' => $data['name'], 'score' => 0];
        }
        if (!isset($rankingReacts[$memberJid])) {
            $rankingReacts[$memberJid] = ['name' => $data['name'], 'score' => 0];
        }
        
        // Soma os pontos
        $rankingMsgs[$memberJid]['score'] += $data['messages'] ?? 0;
        $rankingReacts[$memberJid]['score'] += $data['reactions_given'] ?? 0;
    }
}

// Filtra quem tem score 0
$rankingMsgs = array_filter($rankingMsgs, fn($item) => $item['score'] > 0);
$rankingReacts = array_filter($rankingReacts, fn($item) => $item['score'] > 0);

// Ordena e pega top 5 de mensagens
uasort($rankingMsgs, fn($a, $b) => $b['score'] <=> $a['score']);
$top5Msgs = array_slice($rankingMsgs, 0, 5, true);

// Ordena e pega top 5 de reações
uasort($rankingReacts, fn($a, $b) => $b['score'] <=> $a['score']);
$top5Reacts = array_slice($rankingReacts, 0, 5, true);

$medals = ['🥇', '🥈', '🥉'];

$msgList = '';
$i = 0;
$mentions = [];
foreach ($top5Msgs as $jid => $data) {
    $rankStr = ($i < 3) ? $medals[$i] : ($i + 1) . ".";
    $jidClean = explode('@', $jid)[0];
    $msgList .= $rankStr . " @{$jidClean} — {$data['score']} msgs\n";
    $mentions[] = $jid;
    $i++;
}

$reactList = '';
$i = 0;
foreach ($top5Reacts as $jid => $data) {
    $rankStr = ($i < 3) ? $medals[$i] : ($i + 1) . ".";
    $jidClean = explode('@', $jid)[0];
    $reactList .= $rankStr . " @{$jidClean} — {$data['score']} reacts\n";
    $mentions[] = $jid;
    $i++;
}

$template = $config['templates']['ranking_social'] ?? "🏆 *Daily Social Ranking* ({date})\n\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n🗣️ *Here are the Word Slingers of the day:*\n{word_slingers_list}\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n🔥 *And the Emoji Gang:*\n{emoji_gang_list}";

// Format date in English, e.g. "June 13th, 2026"
$enDate = date('F jS, Y', strtotime($ontem));

$message = str_replace(
    ['{date}', '{word_slingers_list}', '{emoji_gang_list}'],
    [$enDate, $msgList ?: "No messages yesterday.\n", $reactList ?: "No reactions yesterday.\n"],
    $template
);

$targetGroup = $config['groups']['the_lounge']['jid'] ?? null;
if (!$targetGroup) die("Grupo alvo (The Lounge) não configurado.");

$mentions = array_values(array_unique($mentions));
$result = enviarWhatsAppMention($targetGroup, $message, $mentions);
if ($result['httpCode'] >= 200 && $result['httpCode'] < 300) {
    $conn->prepare("INSERT INTO mentoria_auto_logs (tipo, data_execucao, detalhes) VALUES ('ranking_diario', ?, ?)")
         ->execute([$ontem, json_encode(['top5msgs' => $top5Msgs, 'top5reacts' => $top5Reacts])]);
    echo "✅ Ranking enviado!";
} else {
    echo "❌ Erro ao enviar ranking: HTTP " . $result['httpCode'] . " | Raw: " . json_encode($result);
}
