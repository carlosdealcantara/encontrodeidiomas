<?php
require_once dirname(__DIR__) . '/config.php';
$conn = connectDB();

$nova_senha = '@eiagora26';

$stmt = $conn->prepare("
    INSERT INTO settings (setting_key, category, label, type, setting_value)
    VALUES ('hosts_app_password', 'Portal', 'Senha do Portal dos Hosts', 'text', ?)
    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
");
$stmt->execute([$nova_senha]);

echo "Senha atualizada com sucesso! Valor definido: " . htmlspecialchars($nova_senha);
