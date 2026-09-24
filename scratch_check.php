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

echo "<br>Testando chamada direta em getMentoriaConfig...<br>";
$t0 = microtime(true);
$cfg = getMentoriaConfig();
$t1 = microtime(true);
echo "getMentoriaConfig demorou: " . round($t1 - $t0, 3) . "s<br>";

echo "Testando fetchBaileysActivity...<br>";
$act = fetchBaileysActivity($ontem);
$t2 = microtime(true);
echo "fetchBaileysActivity demorou: " . round($t2 - $t1, 3) . "s<br>";



