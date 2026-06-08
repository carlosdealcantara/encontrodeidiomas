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
if ($check->rowCount() > 0) {
    die("Ranking já postado para esta data ($ontem). Abortando.");
}

$config = getMentoriaConfig();
$activity = fetchBaileysActivity($ontem); // Pega atividade de ontem!

$adminJid = $config['admin_jid'] ?? "556192666148@s.whatsapp.net";
$ranking = [];

foreach ($activity as $groupJid => $members) {
    foreach ($members as $memberJid => $data) {
        if ($memberJid === $adminJid) continue;
        if (!isset($ranking[$memberJid])) {
            $ranking[$memberJid] = ['name' => $data['name'], 'msgs' => 0, 'reactions' => 0, 'total' => 0];
        }
        $ranking[$memberJid]['msgs'] += $data['messages'];
        $ranking[$memberJid]['reactions'] += $data['reactions_given'];
        
        // Peso (Msgs = 2, Reações = 1)
        $pts = ($data['messages'] * 2) + ($data['reactions_given'] * 1);
        $ranking[$memberJid]['total'] += $pts;
    }
}

// Ordena e pega top 5
uasort($ranking, fn($a, $b) => $b['total'] <=> $a['total'] ? -($b['total'] <=> $a['total']) : 0);
$top5 = array_slice($ranking, 0, 5, true);

if (empty($top5)) {
    die("Nenhuma atividade registrada ontem ($ontem).");
}

$medals = ['🥇', '🥈', '🥉', '4️⃣', '5️⃣'];
$rankingList = '';
$i = 0;
foreach ($top5 as $jid => $data) {
    $rankingList .= $medals[$i] . " *{$data['name']}* — {$data['total']} pts ({$data['msgs']} msgs, {$data['reactions']} react)\n";
    $i++;
}

$template = $config['templates']['ranking_diario'] ?? "🏆 *Ranking do Dia* ({date})\n\n{ranking_list}";
$message = str_replace(
    ['{date}', '{ranking_list}'],
    [date('d/m/Y', strtotime($ontem)), $rankingList],
    $template
);

$targetGroup = $config['groups']['the_lounge']['jid'] ?? null;
if (!$targetGroup) die("Grupo alvo (The Lounge) não configurado.");

$result = enviarWhatsApp($targetGroup, $message, 'mentoria_ranking');
if ($result['httpCode'] >= 200 && $result['httpCode'] < 300) {
    $conn->prepare("INSERT INTO mentoria_auto_logs (tipo, data_execucao, detalhes) VALUES ('ranking_diario', ?, ?)")
         ->execute([$ontem, json_encode($top5)]);
    echo "✅ Ranking enviado!";
} else {
    echo "❌ Erro ao enviar ranking: HTTP " . $result['httpCode'];
}
