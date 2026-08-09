<?php
require_once dirname(__DIR__) . '/config.php';
$conn = connectDB();

try {
    // A chave pode estar na tabela settings
    $stmt = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'hosts_app_password'");
    $stmt->execute(['@saudemental26']);
    echo "Senha atualizada com sucesso no banco de dados!";
} catch (Exception $e) {
    echo "Erro ao atualizar a senha: " . $e->getMessage();
}
