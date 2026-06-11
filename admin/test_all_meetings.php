<?php
/**
 * TESTE ÚNICO: Disparo de mensagem para TODOS os encontros ativos
 * Destino: grupo de teste privado
 * Rota: acesso via URL com token
 * !! APAGAR APÓS O TESTE !!
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/whatsapp_helper.php';

$token_secreto = '83x9aZ2pLQw1';
if (!isset($_GET['token']) || $_GET['token'] !== $token_secreto) {
    http_response_code(403);
    die('Acesso Negado.');
}

// Grupo de teste privado (só você)
$grupoTeste = '556192666148-1542376033@g.us';

$conn = connectDB();

// Busca TODOS os encontros ativos com dados do idioma
$stmt = $conn->query("
    SELECT m.id, m.time_hour, m.meet_link, m.day_of_week,
           l.name as lang_pt, l.name_en as lang_en, l.flag_emoji
    FROM meetings m
    JOIN languages l ON m.language_id = l.id
    WHERE m.active = 1
    ORDER BY m.day_of_week ASC, m.time_hour ASC
");
$encontros = $stmt->fetchAll(PDO::FETCH_ASSOC);

$dias = [1 => 'Segunda', 2 => 'Terça', 3 => 'Quarta', 4 => 'Quinta', 5 => 'Sexta', 6 => 'Sábado', 7 => 'Domingo'];

$total = count($encontros);
$enviados = 0;
$erros = 0;

echo "<pre>\n";
echo "===== TESTE DE DISPARO - TODOS OS ENCONTROS =====\n";
echo "Total de encontros ativos: $total\n";
echo "Grupo de destino: $grupoTeste\n\n";

// Espaçamento entre envios para não ser bloqueado
$intervalo = $total > 10 ? 3 : 2; // 3s se > 10 encontros, 2s se menos

foreach ($encontros as $i => $m) {
    $emoji = $m['flag_emoji'] ?: '🌐';
    $dia   = $dias[$m['day_of_week']] ?? 'Outro dia';
    $hora  = str_pad($m['time_hour'], 2, '0', STR_PAD_LEFT) . 'h';
    $link  = $m['meet_link'] ?: 'https://encontrodeidiomas.com.br';

    $msg = "{$emoji} *{$m['lang_en']} | {$m['lang_pt']}*\n"
         . "📅 {$dia} às {$hora}\n"
         . "🔗 {$link}\n\n"
         . "_Teste de bandeira e disparo — encontro #{$m['id']}_";

    $res = enviarWhatsApp($grupoTeste, $msg, 'teste_bandeiras');
    $ok = $res['success'] && $res['httpCode'] >= 200 && $res['httpCode'] < 300;

    echo "[" . ($i + 1) . "/$total] {$emoji} {$m['lang_pt']} → " . ($ok ? "✅ OK (HTTP {$res['httpCode']})" : "❌ ERRO: " . ($res['error'] ?? $res['httpCode'])) . "\n";

    if ($ok) $enviados++;
    else $erros++;

    flush();

    if ($i < $total - 1) {
        echo "   ⏳ Aguardando {$intervalo}s...\n";
        flush();
        sleep($intervalo);
    }
}

echo "\n===== FIM DO TESTE =====\n";
echo "✅ Enviados com sucesso: $enviados\n";
echo "❌ Erros: $erros\n";
echo "</pre>\n";
?>
