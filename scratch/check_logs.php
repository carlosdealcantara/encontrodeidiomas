<?php
require_once dirname(__DIR__) . '/config.php';
$conn = connectDB();
$hoje = new DateTime();
$diaDaSemanaAtual = (int)$hoje->format('N');
$dataDisparo = $hoje->format('Y-m-d');
$m = ['id' => 4, 'language_name' => 'Servo Croata', 'flag_emoji' => '🇷🇸']; // Mock meeting
$t = ['id' => 3, 'cenario' => 'Hora Exata']; // Mock template
$g = ['id' => 62, 'nome' => 'Servo Croata VIP', 'group_id' => '123@g.us']; // Mock group
try {
    $stmtLog = $conn->prepare("INSERT INTO meetup_whatsapp_logs (grupo_id, meeting_id, template_id, data_disparo) VALUES (?, ?, ?, ?)");
    $stmtLog->execute([$g['id'], $m['id'], $t['id'], $dataDisparo]);
    echo "Simulated insert success. ID: " . $conn->lastInsertId() . "\n";
    $conn->exec("DELETE FROM meetup_whatsapp_logs WHERE id = " . $conn->lastInsertId());
} catch (Exception $e) {
    echo "Simulated insert failed: " . $e->getMessage() . "\n";
}
