<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/whatsapp_helper.php';

$conn = connectDB();
$target_group = '556192666148-1542376033@g.us';

echo "Testing Chinês (ID 8)...\n";

$stmt = $conn->query("SELECT * FROM languages WHERE id = 8");
$lang = $stmt->fetch();
echo "Language: {$lang['name']} | Emoji: {$lang['flag_emoji']}\n\n";

// 1. Testar Resumo do Dia
$stmtTemplate = $conn->query("SELECT * FROM meetup_whatsapp_templates WHERE ativo = 1 AND cenario = 'Resumo do Dia' LIMIT 1");
$templateDiario = $stmtTemplate->fetch();

$listaFormatadaGlobal = "{$lang['flag_emoji']} {$lang['name_en']} | {$lang['name']}";
$textoResumo = str_replace('{LISTA_ENCONTROS}', $listaFormatadaGlobal, $templateDiario['template_texto']);

echo "=== RESUMO DO DIA ===\n";
echo $textoResumo . "\n\n";

$result1 = enviarWhatsApp($target_group, $textoResumo, 'test_resumo');
echo "Sent: " . json_encode($result1) . "\n\n";
sleep(5);

// 2. Testar Encontro Iniciando Agora
$stmtTemplate2 = $conn->query("SELECT * FROM meetup_whatsapp_templates WHERE ativo = 1 AND cenario = 'Encontro Iniciando Agora' LIMIT 1");
$templateStart = $stmtTemplate2->fetch();

$textoStart = $templateStart['template_texto'];
$textoStart = str_replace('{IDIOMA}', $lang['name'], $textoStart);
$textoStart = str_replace('{IDIOMA_EN}', $lang['name_en'] ?? $lang['name'], $textoStart);
$textoStart = str_replace('{EMOJI_FLAG}', $lang['flag_emoji'], $textoStart);
$textoStart = str_replace('{EMOJI_FLAGS}', str_repeat($lang['flag_emoji'], 5), $textoStart);
$textoStart = str_replace('{HORA_ENCONTRO}', '19h00', $textoStart);
$textoStart = str_replace('{LINK_ENCONTRO}', 'https://meet.google.com/test', $textoStart);

echo "=== ENCONTRO INICIANDO AGORA ===\n";
echo $textoStart . "\n\n";

$result2 = enviarWhatsApp($target_group, $textoStart, 'test_start');
echo "Sent: " . json_encode($result2) . "\n\n";

?>
