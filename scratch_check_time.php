<?php
require_once __DIR__ . '/config.php';
$conn = connectDB();
$stmt = $conn->prepare("SELECT * FROM mentoria_auto_logs WHERE data_execucao = ? AND membro_jid = '29'");
$stmt->execute([date('Y-m-d')]);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Logs para a aula 29:<br>";
foreach($logs as $log) {
    echo "ID: {$log['id']} | Tipo: {$log['tipo']} | Criado em: {$log['created_at']}<br>";
}
