<?php
require_once dirname(__DIR__) . '/config.php';
$conn = connectDB();
// Adicionar UNIQUE KEY para garantir anti-duplicidade atômica
try {
    $conn->exec("ALTER TABLE meetup_whatsapp_logs ADD UNIQUE KEY uniq_disparo (grupo_id, meeting_id, template_id, data_disparo)");
    echo "UNIQUE KEY adicionada com sucesso!\n";
} catch (Exception $e) {
    echo "Erro (provavelmente já existe): " . $e->getMessage() . "\n";
}
// Verifica o índice criado
$stmt = $conn->query("SHOW INDEX FROM meetup_whatsapp_logs WHERE Key_name != 'PRIMARY'");
print_r($stmt->fetchAll());
