<?php
/**
 * CRON: Aviso de Desafio (21h)
 * Frequência: 1x/dia, todos os dias, às 21:00 BRT
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
$hoje = date('Y-m-d');

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
$check = $conn->prepare("SELECT id FROM mentoria_auto_logs WHERE tipo = 'desafio_aviso' AND data_execucao = ?");
$check->execute([$hoje]);
if ($check->rowCount() > 0 && !isset($_GET['force'])) {
    die("Aviso do desafio já enviado hoje. Use &force=1 na URL para forçar o reenvio.");
}

$config = getMentoriaConfig();
$desafioJid = $config['groups']['desafio']['jid'] ?? null;

if (!$desafioJid) die("Grupo do desafio não configurado.");


$members = fetchGroupMembers($desafioJid);
$activity = fetchBaileysActivity($hoje);
$desafioActivity = $activity[$desafioJid] ?? [];

$adminJid = $config['admin_jid'] ?? "556192666148@s.whatsapp.net";
$cleanAdminJid = preg_replace('/:\d+@/', '@', $adminJid);

$pendentes = [];
$mentions = [];

foreach ($members as $memberData) {
    $memberJid = $memberData['id'];
    $cleanMemberJid = preg_replace('/:\d+@/', '@', $memberJid);
    
    // Ignora admin e o próprio bot
    $isAdmin = !empty($memberData['admin']);
    if ($cleanMemberJid === $cleanAdminJid || $isAdmin) continue;
    
    // Verifica se enviou IMAGEM hoje no JSON
    $enviouImagem = isset($desafioActivity[$memberJid]) && ($desafioActivity[$memberJid]['images_sent'] ?? 0) > 0;
    
    // Escudo MySQL: Se o JSON diz que NÃO enviou, cruza com o banco de dados como dupla checagem
    if (!$enviouImagem) {
        $stmtShield = $conn->prepare("SELECT last_completed_date FROM mentoria_desafio_streaks WHERE member_jid = ?");
        $stmtShield->execute([$memberJid]);
        $rowShield = $stmtShield->fetch(PDO::FETCH_ASSOC);
        if ($rowShield && $rowShield['last_completed_date'] === $hoje) {
            $enviouImagem = true; // Salvo pelo escudo!
        }
    }
    
    if (!$enviouImagem) {
        $numero = explode('@', $memberJid)[0];
        $pendentes[] = "@" . $numero;
        $mentions[] = $memberJid;
    }
}

if (empty($pendentes)) {
    echo "Nenhum pendente hoje. Nenhuma mensagem enviada.\n";
    $conn->prepare("INSERT INTO mentoria_auto_logs (tipo, data_execucao, detalhes) VALUES ('desafio_aviso', ?, 'Nenhum pendente')")->execute([$hoje]);
    exit;
}

$listaPendentes = implode(", ", $pendentes);
$template = $config['templates']['aviso_desafio'] ?? "⚠️ *Challenge Alert!*\n{pendentes}\nThis is a friendly reminder that you haven't posted your daily activity yet. You have until midnight! ⏳";

$msg = str_replace('{pendentes}', $listaPendentes, $template);

// Envia mensagem com menção real
$result = enviarWhatsAppMention($desafioJid, $msg, $mentions);

if ($result['httpCode'] >= 200 && $result['httpCode'] < 300) {
    $conn->prepare("INSERT INTO mentoria_auto_logs (tipo, data_execucao, detalhes) VALUES ('desafio_aviso', ?, ?)")->execute([$hoje, count($pendentes) . ' pendentes avisados']);
    echo "✅ Aviso de 21h enviado no grupo Desafio marcando " . count($pendentes) . " pessoas!";
} else {
    echo "❌ Erro ao enviar aviso: HTTP " . $result['httpCode'];
}
