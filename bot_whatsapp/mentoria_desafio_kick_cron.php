<?php
/**
 * CRON: Auto-kick do Desafio
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
$ontem = (new DateTime())->modify('-1 day')->format('Y-m-d'); // Analisamos a atividade de ontem

try {
    $conn->exec("
    CREATE TABLE IF NOT EXISTS mentoria_auto_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tipo VARCHAR(50) NOT NULL,
        data_execucao DATE NOT NULL,
        membro_jid VARCHAR(50) NULL,
        detalhes TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (tipo, data_execucao)
    )");
} catch (Exception $e) {}

// Anti-duplicidade
$check = $conn->prepare("SELECT id FROM mentoria_auto_logs WHERE tipo = 'desafio_kick_run' AND data_execucao = ?");
$check->execute([$ontem]);
if ($check->rowCount() > 0 && !isset($_GET['force'])) {
    die("Verificação de kick do desafio já rodou para a data $ontem. Use &force=1 na URL para forçar.");
}

$config = getMentoriaConfig();
$desafioJid = $config['groups']['desafio']['jid'] ?? null;
$template = $config['templates']['kick_desafio'] ?? "⚠️ {name} has been removed for missing the daily activity.";
$adminJid = $config['admin_jid'] ?? "556192666148@s.whatsapp.net";

if (!$desafioJid) die("Grupo do desafio não configurado.");

$members = fetchGroupMembers($desafioJid);
$activity = fetchBaileysActivity($ontem);
$desafioActivity = $activity[$desafioJid] ?? [];

$kickedCount = 0;

foreach ($members as $memberData) {
    $memberJid = $memberData['id'];
    
    // Ignora admin e o próprio bot
    if ($memberJid === $adminJid || isset($memberData['isAdmin'])) continue;
    
    // Verifica se mandou mensagem no grupo ontem
    $interagiu = isset($desafioActivity[$memberJid]) && $desafioActivity[$memberJid]['messages'] > 0;
    
    if (!$interagiu) {
        // Envia mensagem NO GRUPO antes de remover
        $name = $memberJid; // Fallback, Baileys não dá o nome de todos facilmente no groupMetadata
        $msg = str_replace(['{name}', '@{name}'], ["@".explode('@', $memberJid)[0], "@".explode('@', $memberJid)[0]], $template);
        
        enviarWhatsAppMention($desafioJid, $msg, [$memberJid]);
        
        // Pausa de 5 segundos para garantir a entrega e visualização antes do kick
        sleep(5);
        
        // Remove do grupo
        $resRemove = removerDoGrupo($desafioJid, [$memberJid]);
        
        if (($resRemove['success'] ?? false) || ($resRemove['httpCode'] ?? 0) === 200) {
            $conn->prepare("INSERT INTO mentoria_auto_logs (tipo, data_execucao, membro_jid) VALUES ('desafio_kick', ?, ?)")
                 ->execute([$ontem, $memberJid]);
            $kickedCount++;
        }
    }
}

// Marca que a verificação rodou
$conn->prepare("INSERT INTO mentoria_auto_logs (tipo, data_execucao) VALUES ('desafio_kick_run', ?)")->execute([$ontem]);
echo "✅ Kick do Desafio concluído! $kickedCount removidos.";
