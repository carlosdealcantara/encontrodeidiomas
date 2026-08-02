<?php
require_once dirname(__DIR__) . '/config.php';
$conn = connectDB();
// Verifica se já existe UNIQUE KEY na tabela meetup_whatsapp_logs
$stmt = $conn->query("SHOW INDEX FROM meetup_whatsapp_logs WHERE Key_name != 'PRIMARY'");
print_r($stmt->fetchAll());
