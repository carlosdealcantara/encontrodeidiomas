<?php
require_once __DIR__ . '/config.php';
$conn = connectDB();
require_once __DIR__ . '/includes/whatsapp_helper.php';

$ontem = (new DateTime())->modify('-1 day')->format('Y-m-d');
echo "Ontem: $ontem<br>";
$stmt = $conn->prepare("SELECT * FROM mentoria_auto_logs WHERE tipo = 'ranking_unificado' AND data_execucao = ?");
$stmt->execute([$ontem]);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<h3>Últimos 40 registros de mentoria_auto_logs:</h3>";
$stmt = $conn->query("SELECT * FROM mentoria_auto_logs ORDER BY id DESC LIMIT 40");
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<table border='1' cellpadding='4'><tr><th>ID</th><th>Tipo</th><th>Data Exec</th><th>Membro JID</th><th>Created At</th><th>Detalhes</th></tr>";
foreach($logs as $l) {
    echo "<tr><td>{$l['id']}</td><td>{$l['tipo']}</td><td>{$l['data_execucao']}</td><td>{$l['membro_jid']}</td><td>{$l['created_at']}</td><td>" . htmlspecialchars(substr($l['detalhes'] ?? '', 0, 80)) . "</td></tr>";
}
echo "</table>";

echo "<h3>Registros em mentoria_cron_execucoes entre 14:50 e 15:30 hoje:</h3>";
try {
    $stmtCancel = $conn->query("SELECT * FROM mentoria_cron_execucoes WHERE executado_em >= '2026-09-24 14:50:00' AND executado_em <= '2026-09-24 15:30:00' ORDER BY id ASC");
    $cancels = $stmtCancel->fetchAll(PDO::FETCH_ASSOC);
    echo "<table border='1' cellpadding='4'><tr><th>ID</th><th>Cron</th><th>Executado Em</th><th>Sched ID</th><th>Acao</th><th>Attendees</th><th>Notas</th></tr>";
    foreach($cancels as $l) {
        echo "<tr><td>{$l['id']}</td><td>{$l['cron_name']}</td><td>{$l['executado_em']}</td><td>{$l['schedule_id']}</td><td>{$l['acao']}</td><td>{$l['attendees_count']}</td><td>" . htmlspecialchars($l['notas'] ?? '') . "</td></tr>";
    }
    echo "</table>";
} catch(Exception $e) {
    echo "Erro: " . $e->getMessage() . "<br>";
}

echo "<h3>Detalhes de class_schedule para ID 28 e de hoje:</h3>";
try {
    $stmtSched = $conn->query("SELECT * FROM class_schedule WHERE id = 28 OR day_of_week = DAYOFWEEK(CURRENT_DATE())");
    $scheds = $stmtSched->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>" . print_r($scheds, true) . "</pre>";
} catch(Exception $e) {
    echo "Erro: " . $e->getMessage() . "<br>";
}


echo "<h3>Últimos 20 registros de meetup_whatsapp_logs:</h3>";
try {
    $stmt3 = $conn->query("SELECT * FROM meetup_whatsapp_logs ORDER BY id DESC LIMIT 20");
    $logs3 = $stmt3->fetchAll(PDO::FETCH_ASSOC);
    echo "<table border='1' cellpadding='4'><tr><th>ID</th><th>Grupo ID</th><th>Meeting ID</th><th>Template ID</th><th>Data Disparo</th><th>Enviado Em</th></tr>";
    foreach($logs3 as $l) {
        $env = $l['enviado_em'] ?? $l['created_at'] ?? 'n/a';
        echo "<tr><td>{$l['id']}</td><td>{$l['grupo_id']}</td><td>{$l['meeting_id']}</td><td>{$l['template_id']}</td><td>{$l['data_disparo']}</td><td>{$env}</td></tr>";
    }
    echo "</table>";
} catch(Exception $e) {
    echo "Erro meetup_whatsapp_logs: " . $e->getMessage() . "<br>";
}




