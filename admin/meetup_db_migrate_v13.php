<?php
// MIGRACAO V13 - Template Unificado Hora Exata com {BR}/{GLOBAL}
require_once __DIR__ . '/../config.php';
try {
    $conn = connectDB();
    try {
        $conn->exec("ALTER TABLE meetup_whatsapp_templates MODIFY COLUMN comunidade_alvo ENUM('brasil','global','todas') NOT NULL DEFAULT 'brasil'");
        echo "<p>OK: ENUM atualizado.</p>";
    } catch (PDOException $e) {
        echo "<p>INFO: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    $novoTexto = "ð {SITE_LINK}\n\n{EMOJI_REPETIDO_5X} {SAUDACAO}\n{BR}O encontro de {IDIOMA} esta comecando *agora*! Venha praticar:{/BR}{GLOBAL}The {IDIOMA} meetup is starting *now*! Come practice:{/GLOBAL}\n\n{MEET_LINK}\nð¥ Replay OK";
    $stmt2 = $conn->prepare("UPDATE meetup_whatsapp_templates SET template_texto = ?, comunidade_alvo = 'todas', ativo = 1 WHERE minutos_antes = 0 AND escopo = 'por_encontro' AND (cenario LIKE '%Hora Exata%' OR cenario LIKE '%hora exata%') LIMIT 1");
    $stmt2->execute([$novoTexto]);
    $count = $stmt2->rowCount();
    echo "<p>Hora Exata: $count linha(s) atualizada(s).</p>";
    if ($count === 0) {
        $stmt2b = $conn->prepare("UPDATE meetup_whatsapp_templates SET template_texto = ?, comunidade_alvo = 'todas', ativo = 1 WHERE minutos_antes = 0 AND escopo = 'por_encontro' ORDER BY id ASC LIMIT 1");
        $stmt2b->execute([$novoTexto]);
        echo "<p>Fallback: " . $stmt2b->rowCount() . " linha(s).</p>";
    }
    $stmt3 = $conn->prepare("UPDATE meetup_whatsapp_templates SET ativo = 0 WHERE minutos_antes = 0 AND escopo = 'por_encontro' AND comunidade_alvo != 'todas' AND ativo = 1");
    $stmt3->execute();
    echo "<p>Desativados: " . $stmt3->rowCount() . " template(s) duplicado(s) de hora exata.</p>";
    $rows = $conn->query("SELECT id, cenario, comunidade_alvo, ativo FROM meetup_whatsapp_templates WHERE minutos_antes = 0 AND escopo = 'por_encontro' ORDER BY id")->fetchAll();
    echo "<table border=1 cellpadding=8><tr><th>ID</th><th>Cenario</th><th>Comunidade</th><th>Ativo</th></tr>";
    foreach ($rows as $r) {
        echo "<tr><td>".$r['id']."</td><td>".htmlspecialchars($r['cenario'])."</td><td>".$r['comunidade_alvo']."</td><td>".(($r['ativo']==1)?'Sim':'Nao')."</td></tr>";
    }
    echo "</table><h2>Migracao V13 OK!</h2><p><a href=meetup_templates.php>Voltar para Templates</a></p>";
} catch (PDOException $e) {
    echo "<h1>Erro</h1><pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
}
?>