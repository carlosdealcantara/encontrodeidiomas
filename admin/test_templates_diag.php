<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: text/html; charset=utf-8');

try {
    $conn = connectDB();
    $stmt = $conn->query("SELECT id, cenario, minutos_antes, escopo, frequencia, comunidade_alvo, ativo, template_texto FROM meetup_whatsapp_templates ORDER BY id ASC");
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<h1>Templates Cadastrados (" . count($templates) . ")</h1>";
    foreach ($templates as $t) {
        echo "<div style='border:1px solid #ccc; margin-bottom:15px; padding:10px;'>";
        echo "<b>ID:</b> {$t['id']} | <b>Cenário:</b> " . htmlspecialchars($t['cenario']) . " | <b>Min:</b> {$t['minutos_antes']} | <b>Escopo:</b> {$t['escopo']} | <b>Comunidade:</b> {$t['comunidade_alvo']} | <b>Ativo:</b> " . ($t['ativo'] ? 'SIM' : 'NÃO');
        echo "<pre style='background:#f4f4f4; padding:8px;'>" . htmlspecialchars($t['template_texto']) . "</pre>";
        echo "</div>";
    }
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
