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
// Analisamos a atividade de ontem, a menos que estejamos testando hoje
if (isset($_GET['test_hoje']) && $_GET['test_hoje'] == '1') {
    $ontem = date('Y-m-d');
} else {
    $ontem = (new DateTime())->modify('-1 day')->format('Y-m-d');
}

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
    
    // Remove o sufixo multi-device caso exista (ex: 55119999:12@s.whatsapp.net -> 55119999@s.whatsapp.net)
    $cleanMemberJid = preg_replace('/:\d+@/', '@', $memberJid);
    $cleanAdminJid = preg_replace('/:\d+@/', '@', $adminJid);
    
    // Ignora admin e o próprio bot (Baileys usa a propriedade 'admin' valendo 'admin' ou 'superadmin')
    $isAdmin = !empty($memberData['admin']);
    if ($cleanMemberJid === $cleanAdminJid || $isAdmin) continue;
    
    // Verifica se mandou IMAGEM no grupo ontem no JSON
    $interagiu = isset($desafioActivity[$memberJid]) && ($desafioActivity[$memberJid]['images_sent'] ?? 0) > 0;
    
    // Escudo MySQL: Se o JSON diz que NÃO interagiu, cruza com o banco de dados como dupla checagem
    if (!$interagiu) {
        $stmtShield = $conn->prepare("SELECT last_completed_date FROM mentoria_desafio_streaks WHERE member_jid = ?");
        $stmtShield->execute([$memberJid]);
        $rowShield = $stmtShield->fetch(PDO::FETCH_ASSOC);
        if ($rowShield && $rowShield['last_completed_date'] === $ontem) {
            $interagiu = true; // Salvo pelo escudo! O banco tem o registro correto.
        }
    }
    
    if (!$interagiu) {
        // Arruma o nome (se o template tem @{name}, trocamos por @numero. Se tem só {name}, trocamos pelo numero)
        $numero = explode('@', $memberJid)[0];
        $msg = str_replace(['@{name}', '{name}'], ["@".$numero, $numero], $template);
        
        enviarWhatsAppMention($desafioJid, $msg, [$memberJid]);
        
        // Pausa de 5 segundos para garantir a entrega e visualização antes do kick
        sleep(5);
        
        // Remove do grupo
        $resRemove = removerDoGrupo($desafioJid, [$memberJid]);
        
        if (($resRemove['success'] ?? false) || ($resRemove['httpCode'] ?? 0) === 200) {
            $conn->prepare("INSERT INTO mentoria_auto_logs (tipo, data_execucao, membro_jid) VALUES ('desafio_kick', ?, ?)")
                 ->execute([$ontem, $memberJid]);
            
            // Reset streak
            try {
                $conn->prepare("UPDATE mentoria_desafio_streaks SET current_streak = 0 WHERE member_jid = ?")
                     ->execute([$memberJid]);
            } catch (Exception $e) {}
                 
            $kickedCount++;
        }
    }
}

// Marca que a verificação rodou
$conn->prepare("INSERT INTO mentoria_auto_logs (tipo, data_execucao) VALUES ('desafio_kick_run', ?)")->execute([$ontem]);
echo "✅ Kick do Desafio concluído! $kickedCount removidos.";
