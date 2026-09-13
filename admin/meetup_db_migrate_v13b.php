<?php
// CORRECAO V13b - Reverte todas para global, corrige emoji no template ID 1
require_once __DIR__ . '/../config.php';
try {
    $conn = connectDB();

    // 1. Atualiza template ID 1: texto correto + comunidade_alvo=global
    $novoTexto = "🌐 {SITE_LINK}\n\n{EMOJI_REPETIDO_5X} {SAUDACAO}\n{BR}O encontro de {IDIOMA} esta comecando *agora*! Venha praticar:{/BR}{GLOBAL}The {IDIOMA} meetup is starting *now*! Come practice:{/GLOBAL}\n\n{MEET_LINK}\n🎥 Replay ✅";
    $stmt = $conn->prepare("UPDATE meetup_whatsapp_templates SET template_texto = ?, comunidade_alvo = 'global' WHERE id = 1");
    $stmt->execute([$novoTexto]);
    echo "<p>OK: Template ID 1 atualizado (comunidade=global, texto correto). Linhas: " . $stmt->rowCount() . "</p>";

    // 2. Remove todas do ENUM (volta para brasil|global)
    try {
        $conn->exec("ALTER TABLE meetup_whatsapp_templates MODIFY COLUMN comunidade_alvo ENUM('brasil','global') NOT NULL DEFAULT 'brasil'");
        echo "<p>OK: ENUM restaurado para brasil|global.</p>";
    } catch (PDOException $e) {
        echo "<p>INFO ENUM: " . htmlspecialchars($e->getMessage()) . "</p>";
    }

    // 3. Estado final
    $rows = $conn->query("SELECT id, cenario, comunidade_alvo, ativo, SUBSTRING(template_texto, 1, 60) as preview FROM meetup_whatsapp_templates WHERE minutos_antes = 0 AND escopo = 'por_encontro' ORDER BY id")->fetchAll();
    echo "<table border=1 cellpadding=8><tr><th>ID</th><th>Cenario</th><th>Comunidade</th><th>Ativo</th><th>Preview</th></tr>";
    foreach ($rows as $r) {
        echo "<tr><td>".$r["id"]."</td><td>".htmlspecialchars($r["cenario"])."</td><td>".$r["comunidade_alvo"]."</td><td>".($r["ativo"]?"Sim":"Nao")."</td><td>".htmlspecialchars($r["preview"])."</td></tr>";
    }
    echo "</table><h2>Correcao V13b OK!</h2><p><a href=meetup_templates.php>Voltar para Templates</a></p>";
} catch (PDOException $e) {
    echo "<h1>Erro</h1><pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
}
?>