<?php
require_once __DIR__ . '/config.php';
$conn = connectDB();
require_once __DIR__ . '/includes/whatsapp_helper.php';

$ontem = (new DateTime())->modify('-1 day')->format('Y-m-d');
echo "Ontem: $ontem<br>";
$stmt = $conn->prepare("SELECT * FROM mentoria_auto_logs WHERE tipo = 'ranking_unificado' AND data_execucao = ?");
$stmt->execute([$ontem]);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Detalhes do log de ranking_unificado:<br>";
foreach($logs as $l) {
    echo "ID: {$l['id']} | Created: {$l['created_at']} | Detalhes: {$l['detalhes']}<br>";
}

// Limpar fila do Baileys para cancelar qualquer disparo pendente em memória
$resClear = sendBaileysRequest('/clear-queue', null, 'POST');
echo "Clear queue response:<br>";
var_dump($resClear);


