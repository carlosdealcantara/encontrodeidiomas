<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/whatsapp_helper.php';

header('Content-Type: text/plain; charset=utf-8');

$conn = connectDB();

echo "=== TESTE DE RESOLUÇÃO DE NOMES (PROVA REAL) ===\n\n";

// Exemplos de JIDs com diferentes cenários (com dispositivo, sem nono dígito, etc.)
$testJids = [
    // Se passar via GET: ?jid=55...
    $_GET['jid'] ?? null,
    // Exemplos históricos
    '5511988887777@s.whatsapp.net',
    '551188887777:2@s.whatsapp.net',
];

$testJids = array_filter($testJids);

foreach ($testJids as $jid) {
    echo "Testando JID: {$jid}\n";
    $nome = resolveMentoriaMemberName($conn, $jid);
    echo "Resultado: [{$nome}]\n";
    echo str_repeat('-', 40) . "\n";
}

// Mostra os últimos 5 pontos manuais atribuídos em mentoria_dedicated_pts
echo "\n=== ÚLTIMOS PONTOS MANUAIS (!pontos) REGISTRADOS NO BANCO ===\n";
try {
    $stmt = $conn->query("SELECT id, date, member_jid, member_name, points, group_key, awarded_at FROM mentoria_dedicated_pts ORDER BY id DESC LIMIT 5");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($rows)) {
        echo "Nenhum ponto registrado recentemente.\n";
    } else {
        foreach ($rows as $r) {
            echo "#{$r['id']} | Data: {$r['date']} | JID: {$r['member_jid']} | Nome gravado: [{$r['member_name']}] | Pts: {$r['points']} ({$r['group_key']})\n";
        }
    }
} catch (Exception $e) {
    echo "Erro ao consultar: " . $e->getMessage() . "\n";
}
