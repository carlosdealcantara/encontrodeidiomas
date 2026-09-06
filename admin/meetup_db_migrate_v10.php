<?php
// ============================================================
// MIGRAÇÃO V10 - Injetando Tags {BR} no template do Resumo Semanal
// ============================================================
require_once __DIR__ . '/../config.php';

try {
    $conn = connectDB();
    
    // Atualiza o template atual salvo nas configurações para ter as tags
    $novo_template = "*Replays!* https://encontrodeidiomas.com.br\n\n{REPLAYS_LIST}\n*Nº: Max simultaneous participants{BR} | Máximo de participantes simultâneos{/BR}.*\n*🚀 Stay tuned for the next one!{BR} | Fique de olho para participar do próximo!{/BR}*";

    $stmt = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'weekly_summary_template'");
    $stmt->execute([$novo_template]);
    
    echo "<h1>Migração V10 Concluída com Sucesso! 🚀</h1>";
    echo "<p>Template do Resumo Semanal atualizado com as tags mágicas {BR}.</p>";
    echo "<p><a href='wpp_resumo_semanal.php'>Voltar para Resumo Semanal</a></p>";

} catch (PDOException $e) {
    echo "<h1>Erro na Migração</h1>";
    echo "<pre>" . $e->getMessage() . "</pre>";
}
?>
