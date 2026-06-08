<?php
/**
 * CRON: Lembrete diário de aula (Our Meetups)
 * Frequência: 1x/dia, seg-qui, às 8h BRT (11h UTC)
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/whatsapp_helper.php';

$token_secreto = '83x9aZ2pLQw1'; 
$is_cli = (php_sapi_name() === 'cli');

if (!$is_cli && (!isset($_GET['token']) || $_GET['token'] !== $token_secreto)) {
    http_response_code(403);
    die("Acesso Negado.");
}

// Verifica se hoje é seg-qui (1-4)
$diaSemana = (int)(new DateTime())->format('N');
if ($diaSemana < 1 || $diaSemana > 4) {
    die("Hoje não é dia de aula (seg-qui). Abortando.");
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

$hoje = date('Y-m-d');

// Anti-duplicidade
$check = $conn->prepare("SELECT id FROM mentoria_auto_logs WHERE tipo = 'lembrete_aula' AND data_execucao = ?");
$check->execute([$hoje]);
if ($check->rowCount() > 0) {
    die("Lembrete já enviado hoje. Abortando.");
}

// Busca config
$config = getMentoriaConfig();
$groupJid = $config['groups']['our_meetups']['jid'] ?? null;
$template = $config['templates']['lembrete_aula'] ?? null;

if (!$groupJid || !$template) {
    die("Configuração de grupo ou template não encontrada.");
}

$result = enviarWhatsApp($groupJid, $template, 'mentoria_lembrete');
if ($result['httpCode'] >= 200 && $result['httpCode'] < 300) {
    $conn->prepare("INSERT INTO mentoria_auto_logs (tipo, data_execucao) VALUES ('lembrete_aula', ?)")->execute([$hoje]);
    echo "✅ Lembrete enviado para Our Meetups!";
} else {
    echo "❌ Erro ao enviar lembrete: HTTP " . $result['httpCode'] . " - " . json_encode($result);
}
