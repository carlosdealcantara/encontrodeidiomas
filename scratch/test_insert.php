<?php
require_once dirname(__DIR__) . '/config.php';
$conn = connectDB();
try {
    $stmtLog = $conn->prepare("INSERT INTO meetup_whatsapp_logs (grupo_id, meeting_id, template_id, data_disparo) VALUES (1, 1, 1, '2026-08-01')");
    $stmtLog->execute();
    echo "Sucesso! ID inserido: " . $conn->lastInsertId();
    $conn->exec("DELETE FROM meetup_whatsapp_logs WHERE id = " . $conn->lastInsertId());
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
