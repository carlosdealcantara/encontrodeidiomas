<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/whatsapp_helper.php';

$conn = connectDB();

// 1. Pegar idioma Coreano
$stmtLang = $conn->query("SELECT * FROM languages WHERE name = 'Coreano'");
$korean = $stmtLang->fetch(PDO::FETCH_ASSOC);

if (!$korean) {
    die("Idioma Coreano não encontrado no banco de dados.");
}

// 2. Pegar o Template "Hora Exata"
$stmtTpl = $conn->query("SELECT * FROM meetup_whatsapp_templates WHERE cenario = 'Hora Exata' LIMIT 1");
$template = $stmtTpl->fetch(PDO::FETCH_ASSOC);

// 3. Pegar um grupo qualquer (vamos pegar apenas 1 para não fazer spam)
$stmtGroup = $conn->query("SELECT * FROM meetup_whatsapp_groups WHERE ativo = 1 ORDER BY id ASC LIMIT 1");
$group = $stmtGroup->fetch(PDO::FETCH_ASSOC);

echo "Alvo: Grupo '{$group['nome']}' ({$group['group_id']})\n";

// 4. Preparar o texto final
$textoFinal = $template['template_texto'];
$textoFinal = str_replace('{IDIOMA}', strtoupper($korean['name']), $textoFinal);
$textoFinal = str_replace('{idioma}', $korean['name'], $textoFinal);
$textoFinal = str_replace('{EMOJI_FLAG}', $korean['flag_emoji'], $textoFinal);
$textoFinal = str_replace('{EMOJI_FLAGS}', str_repeat($korean['flag_emoji'], 5), $textoFinal);
$textoFinal = str_replace('{SAUDACAO}', $korean['greeting'], $textoFinal);
$textoFinal = str_replace('{MEET_LINK}', 'https://meet.google.com/abc-defg-hij (LINK DE TESTE)', $textoFinal);
$textoFinal = str_replace('{INSTAGRAM_LINK}', 'https://instagram.com/teste', $textoFinal);

echo "\nMensagem a ser enviada:\n";
echo "==========================\n";
echo $textoFinal . "\n";
echo "==========================\n";

// 5. Enviar
echo "\nEnviando...\n";
$result = enviarWhatsApp($group['group_id'], $textoFinal, 'teste_manual');
echo "Status HTTP: " . $result['httpCode'] . "\n";
if (isset($result['error'])) {
    echo "Erro: " . $result['error'] . "\n";
} else {
    echo "Sucesso!\n";
}
?>
