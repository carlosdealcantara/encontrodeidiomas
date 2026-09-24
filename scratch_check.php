<?php
require_once __DIR__ . '/config.php';
$conn = connectDB();
$hoje = date('Y-m-d');
$diaSemana = date('N');
$stmt = $conn->prepare("SELECT * FROM class_schedule WHERE day_of_week = ?");
$stmt->execute([$diaSemana]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Schedules para hoje ($diaSemana):<br>";
foreach($rows as $row) {
    echo "ID: {$row['id']} | Hora: {$row['start_time']} | Tipo: {$row['session_type']} | Ativa: {$row['is_active']}<br>";
}
$stmt = $conn->prepare("SELECT * FROM class_attendances WHERE aula_date = ?");
$stmt->execute([$hoje]);
$atts = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<br>Presenças hoje:<br>";
foreach($atts as $att) {
    echo "Schedule ID: {$att['schedule_id']} | Nome: {$att['member_name']}<br>";
}
$stmt = $conn->prepare("SELECT * FROM mentoria_auto_logs ORDER BY id DESC LIMIT 30");
$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<br>Últimos 30 logs de mentoria_auto_logs:<br>";
foreach($logs as $log) {
    echo "ID: {$log['id']} | Tipo: {$log['tipo']} | Data: {$log['data_execucao']} | Data/Hora: {$log['created_at']} | Membro/JID: {$log['membro_jid']}<br>";
}
