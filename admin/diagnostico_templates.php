<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: text/html; charset=utf-8');

try {
    $conn = connectDB();
    $rows = $conn->query("SELECT id, cenario, frequencia, escopo, minutos_antes, comunidade_alvo, ativo, template_texto FROM meetup_whatsapp_templates ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

    echo "<h1>Templates Cadastrados no Banco</h1>";
    echo "<table border='1' cellpadding='8' style='border-collapse:collapse;'>";
    echo "<tr><th>ID</th><th>Cenário</th><th>Escopo</th><th>Frequência</th><th>Minutos</th><th>Comunidade</th><th>Ativo</th><th>Texto</th></tr>";
    foreach ($rows as $r) {
        echo "<tr>";
        echo "<td>{$r['id']}</td>";
        echo "<td>" . htmlspecialchars($r['cenario']) . "</td>";
        echo "<td>{$r['escopo']}</td>";
        echo "<td>{$r['frequencia']}</td>";
        echo "<td>{$r['minutos_antes']}</td>";
        echo "<td>{$r['comunidade_alvo']}</td>";
        echo "<td>" . ($r['ativo'] ? '<b>Sim</b>' : 'Não') . "</td>";
        echo "<td><pre style='white-space:pre-wrap; max-width:400px;'>" . htmlspecialchars($r['template_texto']) . "</pre></td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (PDOException $e) {
    echo "Erro: " . htmlspecialchars($e->getMessage());
}
