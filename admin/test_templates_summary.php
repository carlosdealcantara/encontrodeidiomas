<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    $conn = connectDB();
    $stmt = $conn->query("SELECT id, cenario, minutos_antes, escopo, frequencia, comunidade_alvo, ativo, template_texto FROM meetup_whatsapp_templates WHERE ativo = 1 ORDER BY id ASC");
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($templates, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
