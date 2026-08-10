<?php
/**
 * ============================================================
 * RELATÓRIO SEMANAL DO ODYSEE WORKER
 * ============================================================
 * Este arquivo deve ser chamado pelo servidor (Hostinger)
 * 1 VEZ POR SEMANA (ex: toda segunda-feira às 09:00).
 * Ele envia para o admin um resumo dos reinícios automáticos
 * do Odysee Worker que ocorreram nos últimos 7 dias.
 */

require_once __DIR__ . '/../config.php';

$token_secreto = 'worker_report_k8pZ1';
$is_cli = (php_sapi_name() === 'cli');

if (!$is_cli && (!isset($_GET['token']) || $_GET['token'] !== $token_secreto)) {
    http_response_code(403);
    die("Acesso Negado.");
}

require_once __DIR__ . '/../includes/whatsapp_helper.php';

$conn = connectDB();

// JID do admin que receberá o relatório
$adminJid = '556192666148@s.whatsapp.net';

// Verifica se já rodou hoje (evita loop do master_cron que roda a cada 5 minutos)
$hoje = date('Y-m-d');
$logFile = __DIR__ . '/../scratch/last_worker_report.txt';
if (file_exists($logFile)) {
    $lastRun = trim(file_get_contents($logFile));
    if ($lastRun === $hoje && !isset($_GET['force'])) {
        echo "⏭️ Relatório já enviado hoje. Pulando.";
        exit;
    }
}
// Intervalo de consulta
$dias = isset($_GET['dias']) ? (int)$_GET['dias'] : 7;
$desde = date('Y-m-d H:i:s', strtotime("-{$dias} days"));

// Busca reinícios registrados nos últimos N dias
try {
    $stmt = $conn->prepare("
        SELECT id, restart_time, reason
        FROM odysee_worker_restarts
        WHERE restart_time >= ?
        ORDER BY restart_time DESC
    ");
    $stmt->execute([$desde]);
    $restarts = $stmt->fetchAll();
} catch (Exception $e) {
    $restarts = [];
}

$total = count($restarts);
$periodo = date('d/m/Y', strtotime("-{$dias} days")) . ' até ' . date('d/m/Y');

// Monta a mensagem
if ($total === 0) {
    $mensagem = "✅ *Relatório Semanal do Worker (Odysee)*\n\n";
    $mensagem .= "📅 Período: {$periodo}\n\n";
    $mensagem .= "🟢 Nenhum travamento detectado! O worker operou de forma estável durante toda a semana.";
} else {
    $emoji = $total >= 5 ? "🔴" : ($total >= 2 ? "🟡" : "🟠");
    $mensagem = "{$emoji} *Relatório Semanal do Worker (Odysee)*\n\n";
    $mensagem .= "📅 Período: {$periodo}\n";
    $mensagem .= "⚠️ Reinícios automáticos: *{$total}*\n\n";
    $mensagem .= "*Detalhes:*\n";
    
    // Limita a exibição a 10 eventos para não poluir o WhatsApp
    $exibidos = array_slice($restarts, 0, 10);
    foreach ($exibidos as $r) {
        $dt = new DateTime($r['restart_time']);
        $mensagem .= "• " . $dt->format('d/m H:i') . " — " . ($r['reason'] ?? 'N/A') . "\n";
    }
    
    if ($total > 10) {
        $mensagem .= "_(e mais " . ($total - 10) . " eventos não exibidos)_\n";
    }
    
    $mensagem .= "\n💡 Se os reinícios forem frequentes, considere investigar os logs do servidor.";
}

// Envia a mensagem para o admin
$result = enviarWhatsApp($adminJid, $mensagem, 'worker_report_semanal');

if ($result['httpCode'] >= 200 && $result['httpCode'] < 300) {
    file_put_contents($logFile, $hoje);
    echo "✅ Relatório enviado com sucesso para o admin. ({$total} reinícios nos últimos {$dias} dias)";
} else {
    http_response_code(500);
    echo "❌ Falha ao enviar relatório. Código HTTP: " . $result['httpCode'] . " | Erro: " . ($result['error'] ?? 'N/A');
}
?>
