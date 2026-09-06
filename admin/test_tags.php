<?php
// Teste visual das tags mágicas {BR}
$mensagem_original = "*Replays!*\n\nNº: Max simultaneous participants{BR} | Máximo de participantes simultâneos{/BR}.\n*🚀 Stay tuned!{BR} | Fique de olho!{/BR}*";

// Simula o motor BR
$mensagem_br = preg_replace('/\{BR\}(.*?)\{\/BR\}/s', '$1', $mensagem_original);

// Simula o motor Global
$mensagem_global = preg_replace('/\{BR\}(.*?)\{\/BR\}/s', '', $mensagem_original);

echo "<h2>1. O que você digita no painel (Original):</h2>";
echo "<pre style='background:#f4f4f4; padding:10px; border-radius:5px;'>$mensagem_original</pre>";

echo "<h2>2. O que os grupos do Brasil recebem:</h2>";
echo "<pre style='background:#d4edda; padding:10px; border-radius:5px;'>$mensagem_br</pre>";

echo "<h2>3. O que os grupos Globais recebem:</h2>";
echo "<pre style='background:#cce5ff; padding:10px; border-radius:5px;'>$mensagem_global</pre>";
?>
