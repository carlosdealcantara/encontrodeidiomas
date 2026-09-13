<?php
// MIGRACAO V14 - Reativa Resumo do Dia (ID 4) com tags {BR}/{GLOBAL} e comunidade global
require_once __DIR__ . '/../config.php';
header('Content-Type: text/html; charset=utf-8');

try {
    $conn = connectDB();

    $textoResumo = "📅 {BR}Encontros de hoje:{/BR}{GLOBAL}Today's meetups:{/GLOBAL}\n\n{LISTA_ENCONTROS}\n\n{BR}🕘 Horários no link:{/BR}{GLOBAL}🕘 Schedule at:{/GLOBAL}\n🌐 {SITE_LINK}";

    // Atualiza ID 4 para escopo diario, frequencia diario, ativo 1, comunidade_alvo global e novo texto
    $stmt = $conn->prepare("
        UPDATE meetup_whatsapp_templates 
        SET template_texto = ?, 
            comunidade_alvo = 'global', 
            escopo = 'diario',
            ativo = 1 
        WHERE id = 4
    ");
    $stmt->execute([$textoResumo]);

    echo "<h1>Migração V14 Concluída com Sucesso! 🚀</h1>";
    echo "<p>Template ID 4 (Resumo do Dia) reativado, configurado como Global e texto com tags bilíngues atualizado.</p>";

    // Exibe o estado atual de todos os templates
    $rows = $conn->query("SELECT id, cenario, escopo, minutos_antes, comunidade_alvo, ativo, template_texto FROM meetup_whatsapp_templates ORDER BY ativo DESC, minutos_antes DESC, id ASC")->fetchAll();
    echo "<table border='1' cellpadding='8' style='border-collapse:collapse;'>";
    echo "<tr><th>ID</th><th>Cenário</th><th>Escopo</th><th>Comunidade</th><th>Ativo</th><th>Texto</th></tr>";
    foreach ($rows as $r) {
        echo "<tr>";
        echo "<td>{$r['id']}</td>";
        echo "<td>" . htmlspecialchars($r['cenario']) . "</td>";
        echo "<td>{$r['escopo']}</td>";
        echo "<td>{$r['comunidade_alvo']}</td>";
        echo "<td>" . ($r['ativo'] ? '<b style=\"color:green;\">Sim</b>' : '<span style=\"color:red;\">Não</span>') . "</td>";
        echo "<td><pre style='white-space:pre-wrap; max-width:350px;'>" . htmlspecialchars($r['template_texto']) . "</pre></td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "<p><a href='meetup_templates.php'>Voltar para Templates</a></p>";

} catch (PDOException $e) {
    echo "<h1>Erro na Migração</h1>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
}
