<?php
// Arquivo de diagnóstico — use para verificar o estado do sistema
require_once __DIR__ . '/config.php';
$conn = connectDB();
require_once __DIR__ . '/includes/whatsapp_helper.php';

$hoje = date('Y-m-d');
$ontem = (new DateTime())->modify('-1 day')->format('Y-m-d');

echo "<h3>Status da conexão Baileys:</h3>";
$status = statusWhatsApp();
echo "Conectado: " . ($status['connected'] ? '✅ Sim' : '❌ Não') . "<br>";

echo "<h3>Últimos 20 registros de mentoria_auto_logs:</h3>";
$stmt = $conn->query("SELECT * FROM mentoria_auto_logs ORDER BY id DESC LIMIT 20");
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<table border='1' cellpadding='4'><tr><th>ID</th><th>Tipo</th><th>Data Exec</th><th>Membro JID</th><th>Created At</th></tr>";
foreach($logs as $l) {
    echo "<tr><td>{$l['id']}</td><td>{$l['tipo']}</td><td>{$l['data_execucao']}</td><td>{$l['membro_jid']}</td><td>{$l['created_at']}</td></tr>";
}
echo "</table>";
