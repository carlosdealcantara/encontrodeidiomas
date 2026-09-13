<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    $conn = connectDB();
    $stmt = $conn->query("SELECT id, cenario, minutos_antes, escopo, frequencia, comunidade_alvo, ativo, template_texto FROM meetup_whatsapp_templates ORDER BY id ASC");
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "=== TEMPLATES CADASTRADOS (" . count($templates) . ") ===\n\n";
    foreach ($templates as $t) {
        echo "ID: " . $t['id'] . "\n";
        echo "Cenario: " . $t['cenario'] . "\n";
        echo "Minutos Antes: " . $t['minutos_antes'] . "\n";
        echo "Escopo: " . $t['escopo'] . "\n";
        echo "Frequencia: " . $t['frequencia'] . "\n";
        echo "Comunidade Alvo: " . $t['comunidade_alvo'] . "\n";
        echo "Ativo: " . ($t['ativo'] ? 'SIM' : 'NAO') . "\n";
        echo "Texto:\n" . $t['template_texto'] . "\n";
        echo str_repeat('-', 60) . "\n\n";
    }
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
