<?php
require_once __DIR__ . '/config.php';
$conn = connectDB();
require_once __DIR__ . '/includes/whatsapp_helper.php';

$ontem = (new DateTime())->modify('-1 day')->format('Y-m-d');
echo "Ontem: $ontem<br>";
$check = $conn->prepare("SELECT id FROM mentoria_auto_logs WHERE tipo = 'ranking_unificado' AND data_execucao = ?");
$check->execute([$ontem]);
if ($check->rowCount() === 0) {
    $stmt = $conn->prepare("INSERT INTO mentoria_auto_logs (tipo, data_execucao, detalhes) VALUES ('ranking_unificado', ?, 'trava_manual_loop')");
    $stmt->execute([$ontem]);
    echo "<b>TRAVA APLICADA:</b> Log de ranking_unificado para $ontem inserido com sucesso!<br>";
} else {
    echo "Log de ranking_unificado para $ontem já existia!<br>";
}

// Limpar fila do Baileys para cancelar qualquer disparo pendente em memória
$resClear = sendBaileysRequest('/clear-queue', null, 'POST');
echo "Clear queue response:<br>";
var_dump($resClear);


