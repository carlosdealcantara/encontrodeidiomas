<?php
require_once __DIR__ . '/config.php';
$conn = connectDB();
require_once __DIR__ . '/includes/whatsapp_helper.php';

$ontem = (new DateTime())->modify('-1 day')->format('Y-m-d');
echo "Ontem: $ontem<br>";
$check = $conn->prepare("SELECT * FROM mentoria_auto_logs WHERE tipo = 'ranking_unificado' AND data_execucao = ?");
$check->execute([$ontem]);
$r = $check->fetchAll(PDO::FETCH_ASSOC);
echo "Registros de ranking_unificado para ontem: " . count($r) . "<br>";
var_dump($r);

echo "<br><br>Status Baileys:<br>";
$bestUrl = getBestBaileysUrl();
echo "Best URL: $bestUrl<br>";
$status = statusWhatsApp();
var_dump($status);

$config = getMentoriaConfig();
echo "<br>Config groups count: " . count($config['groups'] ?? []) . "<br>";
echo "Lounge JID: " . ($config['groups']['the_lounge']['jid'] ?? 'NÃO ENCONTRADO') . "<br>";

