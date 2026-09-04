<?php
// ============================================================
// MIGRAÇÃO V9 - Limpeza de Templates (Remoção do "Ambos" e Variáveis Antigas)
// ============================================================
require_once __DIR__ . '/../config.php';

try {
    $conn = connectDB();
    
    // 1. Atualizar templates que ainda estão com comunidade_alvo = 'ambos' para 'brasil'
    // Como a opção 'ambos' foi removida, o comportamento padrão/seguro para os legados é 'brasil'.
    $stmt1 = $conn->prepare("UPDATE meetup_whatsapp_templates SET comunidade_alvo = 'brasil' WHERE comunidade_alvo = 'ambos'");
    $stmt1->execute();
    $countAmbos = $stmt1->rowCount();
    
    // 2. Atualizar variáveis antigas no texto dos templates
    // {EMOJI_FLAGS} -> {EMOJI_REPETIDO_5X}
    // {BANDEIRAS_DO_DIA} -> {TODAS_BANDEIRAS_HOJE}
    $stmt2 = $conn->prepare("UPDATE meetup_whatsapp_templates SET template_texto = REPLACE(template_texto, '{EMOJI_FLAGS}', '{EMOJI_REPETIDO_5X}') WHERE template_texto LIKE '%{EMOJI_FLAGS}%'");
    $stmt2->execute();
    $countEmojiFlags = $stmt2->rowCount();

    $stmt3 = $conn->prepare("UPDATE meetup_whatsapp_templates SET template_texto = REPLACE(template_texto, '{BANDEIRAS_DO_DIA}', '{TODAS_BANDEIRAS_HOJE}') WHERE template_texto LIKE '%{BANDEIRAS_DO_DIA}%'");
    $stmt3->execute();
    $countBandeirasDia = $stmt3->rowCount();

    // 3. Opcional: Limpar encontros que tenham ficado com 'ambos'
    // Encontros 'ambos' funcionavam igual ao 'global', então migramos para 'global'
    try {
        $stmt4 = $conn->prepare("UPDATE meetings SET comunidade = 'global' WHERE comunidade = 'ambos'");
        $stmt4->execute();
        $countMeetingsAmbos = $stmt4->rowCount();
    } catch (Exception $e) {
        $countMeetingsAmbos = 0;
    }

    echo "<h1>Migração V9 Concluída com Sucesso! 🚀</h1>";
    echo "<ul>";
    echo "<li>Templates com 'ambos' migrados para 'brasil': <b>$countAmbos</b></li>";
    echo "<li>Templates com {EMOJI_FLAGS} atualizados: <b>$countEmojiFlags</b></li>";
    echo "<li>Templates com {BANDEIRAS_DO_DIA} atualizados: <b>$countBandeirasDia</b></li>";
    echo "<li>Encontros com 'ambos' migrados para 'global': <b>$countMeetingsAmbos</b></li>";
    echo "</ul>";
    echo "<p><a href='meetup_templates.php'>Voltar para Templates</a></p>";

} catch (PDOException $e) {
    echo "<h1>Erro na Migração</h1>";
    echo "<pre>" . $e->getMessage() . "</pre>";
}
?>
