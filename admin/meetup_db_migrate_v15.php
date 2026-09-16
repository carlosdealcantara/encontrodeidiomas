<?php
// ============================================================
// MIGRAÇÃO V15 - Atualiza template "Hora Exata" para usar
// *{IDIOMA}* (negrito) em vez de {IDIOMA} em caixa alta.
// O PHP agora resolve {IDIOMA} com o nome correto por comunidade:
//   - Grupos Brasil → nome em português (ex: Inglês)
//   - Grupos Global → nome em inglês (ex: English)
// ============================================================
require_once __DIR__ . '/../config.php';

try {
    $conn = connectDB();

    // Novo texto do template unificado (hora exata, comunidade 'todas')
    $novoTexto = "🌐 {SITE_LINK}\n\n{EMOJI_REPETIDO_5X} {SAUDACAO}\n{BR}O encontro de *{IDIOMA}* está começando *agora*! Venha praticar:{/BR}{GLOBAL}The *{IDIOMA}* meetup is starting *now*! Come practice:{/GLOBAL}\n\n{MEET_LINK}\n🎥 Replay ✅";

    // Atualiza o template "Hora Exata" (minutos_antes = 0, escopo = 'por_encontro', comunidade_alvo = 'todas')
    $stmt = $conn->prepare("UPDATE meetup_whatsapp_templates SET template_texto = ? WHERE minutos_antes = 0 AND escopo = 'por_encontro' AND comunidade_alvo = 'todas' AND ativo = 1 LIMIT 1");
    $stmt->execute([$novoTexto]);
    $count = $stmt->rowCount();

    echo "<p>✅ Template 'Hora Exata' (comunidade=todas): $count linha(s) atualizada(s).</p>";

    if ($count === 0) {
        // Fallback: qualquer template de hora exata ativo
        $stmt2 = $conn->prepare("UPDATE meetup_whatsapp_templates SET template_texto = ? WHERE minutos_antes = 0 AND escopo = 'por_encontro' AND ativo = 1 LIMIT 1");
        $stmt2->execute([$novoTexto]);
        echo "<p>⚠️ Fallback aplicado: " . $stmt2->rowCount() . " linha(s).</p>";
    }

    // Mostra estado atual dos templates de hora exata
    $rows = $conn->query("SELECT id, cenario, comunidade_alvo, ativo, LEFT(template_texto, 100) as preview FROM meetup_whatsapp_templates WHERE minutos_antes = 0 AND escopo = 'por_encontro' ORDER BY id")->fetchAll();
    echo "<table border=1 cellpadding=8><tr><th>ID</th><th>Cenário</th><th>Comunidade</th><th>Ativo</th><th>Preview</th></tr>";
    foreach ($rows as $r) {
        echo "<tr><td>{$r['id']}</td><td>" . htmlspecialchars($r['cenario']) . "</td><td>{$r['comunidade_alvo']}</td><td>" . (($r['ativo']==1)?'✅':'❌') . "</td><td>" . htmlspecialchars($r['preview']) . "...</td></tr>";
    }
    echo "</table><h2>✅ Migração V15 OK!</h2><p><a href='meetup_templates.php'>← Voltar para Templates</a></p>";

} catch (PDOException $e) {
    echo "<h1>Erro</h1><pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
}
?>
