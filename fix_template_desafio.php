<?php
/**
 * Script temporário: Corrige o template aviso_desafio para incluir a variável {pendentes}
 * Executar uma vez via: https://dev.encontrodeidiomas.com.br/fix_template_desafio.php
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/whatsapp_helper.php';

$config = getMentoriaConfig();

// Atualiza apenas o template aviso_desafio, mantendo todos os outros intactos
$config['templates']['aviso_desafio'] = "⚠️ *Challenge Alert!*\n{pendentes}\nYou have until midnight to post your activity! ⏳";

// Salva de volta via API do Baileys
$res = sendBaileysRequest('/mentoria-config', $config, 'POST');

if ($res['success']) {
    echo "✅ Template aviso_desafio atualizado!\n";
    echo "Novo template:\n" . $config['templates']['aviso_desafio'];
} else {
    echo "❌ Erro ao salvar config: " . json_encode($res);
}
?>
