<?php
require_once __DIR__ . '/../config.php';

$conn = connectDB();

$defaultTemplate = "Estamos começando nosso encontro de {IDIOMA} neste exato momento!
{EMOJI_FLAGS}
{SAUDACAO}

{EMOJI_FLAG} Encontro Online de {IDIOMA}
{MEET_LINK}

*Ao entrar na chamada, clique no botão CC e selecione o idioma para ativar legendas.* Digite seu Instagram no chat! Se chegou depois, tranquilo! Teremos o replay da chamada.

Quer ficar por dentro? Página de {idioma} no Instagram {EMOJI_FLAG}
{INSTAGRAM_LINK}";

try {
    $stmt = $conn->query("SELECT id FROM meetup_whatsapp_templates WHERE minutos_antes = 0 LIMIT 1");
    $existing = $stmt->fetchColumn();
    
    if ($existing) {
        $update = $conn->prepare("UPDATE meetup_whatsapp_templates SET template_texto = ? WHERE id = ?");
        $update->execute([$defaultTemplate, $existing]);
        echo "Template atualizado com sucesso!";
    } else {
        $insert = $conn->prepare("INSERT INTO meetup_whatsapp_templates (cenario, minutos_antes, template_texto, ativo) VALUES ('Aviso de Início (Hora Exata)', 0, ?, 1)");
        $insert->execute([$defaultTemplate]);
        echo "Template inserido com sucesso!";
    }
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
?>
