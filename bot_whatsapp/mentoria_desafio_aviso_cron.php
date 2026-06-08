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

// Anti-duplicidade
$check = $conn->prepare("SELECT id FROM mentoria_auto_logs WHERE tipo = 'desafio_aviso' AND data_execucao = ?");
$check->execute([$hoje]);
if ($check->rowCount() > 0) {
    die("Aviso do desafio já enviado hoje.");
}

$config = getMentoriaConfig();
$desafioJid = $config['groups']['desafio']['jid'] ?? null;
$template = $config['templates']['aviso_desafio'] ?? "⚠️ *Challenge Alert!*\n\nThis is a friendly reminder that some of you haven't posted your daily activity yet. You have until midnight! ⏳";

if (!$desafioJid) die("Grupo do desafio não configurado.");

// Aqui, poderíamos ser genéricos e mandar para o grupo todo, ou marcar quem não fez.
// Para simplificar e evitar expor quem está devendo antes da meia-noite, vamos enviar um aviso genérico no grupo.

$result = enviarWhatsApp($desafioJid, $template, 'mentoria_aviso_desafio');
if ($result['httpCode'] >= 200 && $result['httpCode'] < 300) {
    $conn->prepare("INSERT INTO mentoria_auto_logs (tipo, data_execucao) VALUES ('desafio_aviso', ?)")->execute([$hoje]);
    echo "✅ Aviso de 21h enviado no grupo Desafio!";
} else {
    echo "❌ Erro ao enviar aviso: HTTP " . $result['httpCode'];
}
