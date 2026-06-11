<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/whatsapp_helper.php';

$token_secreto = '83x9aZ2pLQw1';
if (!isset($_GET['token']) || $_GET['token'] !== $token_secreto) {
    http_response_code(403);
    die('Acesso Negado.');
}

// Grupo de teste (usuário próprio)
$groupJid = '556192666148-1542376033@g.us';

$message = "🇨🇳 Teste de bandeira chinesa";

$result = enviarWhatsApp($groupJid, $message, 'test_chinese');

echo "Resultado: " . json_encode($result);
?>
