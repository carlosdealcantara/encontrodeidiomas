<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/whatsapp_helper.php';

$config = getMentoriaConfig();
$hoje = date('Y-m-d');
$activity = fetchBaileysActivity($hoje);

echo "<h1>Relatório de Atividades - $hoje</h1>";

if (empty($activity)) {
    echo "<p>Nenhuma atividade registrada ainda para hoje.</p>";
    die();
}

foreach ($config['groups'] as $groupKey => $groupData) {
    $jid = $groupData['jid'] ?? '';
    $name = $groupData['nome'] ?? $groupKey;
    
    echo "<h3>Grupo: $name ($groupKey)</h3>";
    
    if (empty($jid) || !isset($activity[$jid])) {
        echo "<p>Sem atividade neste grupo hoje.</p>";
        continue;
    }
    
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>Usuário</th><th>Mensagens</th><th>Imagens</th><th>Áudios</th><th>Reações</th></tr>";
    
    foreach ($activity[$jid] as $memberJid => $stats) {
        if ($memberJid === ($config['admin_jid'] ?? '')) continue;
        
        echo "<tr>";
        echo "<td>{$stats['name']}</td>";
        echo "<td>" . ($stats['messages'] ?? 0) . "</td>";
        echo "<td>" . ($stats['images_sent'] ?? 0) . "</td>";
        echo "<td>" . ($stats['audios_sent'] ?? 0) . "</td>";
        echo "<td>" . ($stats['reactions_given'] ?? 0) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}
