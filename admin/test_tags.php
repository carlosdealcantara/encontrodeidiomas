<?php
// Teste visual das tags mágicas {BR} e {GLOBAL}
require_once __DIR__ . '/../config.php';

require_once __DIR__ . '/../includes/whatsapp_helper.php';


$mensagem_original = "🌐 {SITE_LINK}

{EMOJI_REPETIDO_5X} {SAUDACAO}
{BR}O encontro de {IDIOMA} está começando *agora*! Venha praticar:{/BR}{GLOBAL}The {IDIOMA} meetup is starting *now*! Come practice:{/GLOBAL}

{MEET_LINK}
🎥 Replay ✅";

$mensagem_br = aplicarTagsComunidade($mensagem_original, 'brasil');
$mensagem_global = aplicarTagsComunidade($mensagem_original, 'global');

echo "<h2>1. O que você digita no painel (Template Único Centralizado):</h2>";
echo "<pre style='background:#f4f4f4; padding:15px; border-radius:8px; font-size:14px;'>" . htmlspecialchars($mensagem_original) . "</pre>";

echo "<h2>2. O que os grupos do Brasil (🇧🇷) recebem:</h2>";
echo "<pre style='background:#d4edda; padding:15px; border-radius:8px; font-size:14px; color:#155724;'>" . htmlspecialchars($mensagem_br) . "</pre>";

echo "<h2>3. O que os grupos Globais (🌐) recebem:</h2>";
echo "<pre style='background:#cce5ff; padding:15px; border-radius:8px; font-size:14px; color:#004085;'>" . htmlspecialchars($mensagem_global) . "</pre>";
