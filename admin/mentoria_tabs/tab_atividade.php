<h2>Atividade em Tempo Real (Hoje)</h2>
<p>Abaixo você pode acompanhar quantas mensagens e reações cada participante já enviou hoje (<b><?= date('d/m/Y') ?></b>) nos grupos monitorados pelo robô. Esses dados serão usados pelo cron da meia-noite para fechar o ranking.</p>

<?php
$hoje = date('Y-m-d');
$activityToday = fetchBaileysActivity($hoje);

if (empty($activityToday)) {
    echo "<div class='alert alert-warning'>Nenhuma atividade registrada ainda para o dia de hoje ($hoje). Se o robô acabou de ser configurado, aguarde os alunos começarem a enviar mensagens.</div>";
} else {
    // Pegar nomes dos grupos do config (que inclui mentoria e global)
    $groupNames = [];
    foreach (($config['groups'] ?? []) as $g) {
        if (!empty($g['jid'])) {
            $groupNames[$g['jid']] = $g['name'] ?? 'Grupo Desconhecido';
        }
    }

    echo "<div style='display:flex; flex-wrap:wrap; gap:20px; margin-top:20px;'>";
    foreach ($activityToday as $groupJid => $members) {
        $gName = $groupNames[$groupJid] ?? "JID: $groupJid";
        
        // Pula se não houver membros
        if (empty($members)) continue;
        
        // Sort members by total interactions (msgs + reacts)
        uasort($members, function($a, $b) {
            $totalA = ($a['messages'] ?? 0) + ($a['reactions_given'] ?? 0) + ($a['images_sent'] ?? 0) + ($a['audios_sent'] ?? 0);
            $totalB = ($b['messages'] ?? 0) + ($b['reactions_given'] ?? 0) + ($b['images_sent'] ?? 0) + ($b['audios_sent'] ?? 0);
            return $totalB <=> $totalA;
        });

        echo "<div style='background:#1e1e1e; padding:15px; border-radius:8px; border:1px solid #333; flex: 1 1 300px;'>";
        echo "<h3 style='margin-top:0; color:#38bdf8; font-size:16px;'>{$gName}</h3>";
        
        echo "<table style='width:100%; border-collapse:collapse; margin-top:10px;'>";
        echo "<tr style='border-bottom:1px solid #444; color:#aaa; font-size:12px;'>
                <th style='text-align:left; padding:5px 0;'>Participante</th>
                <th style='text-align:center; padding:5px 0;'>💬 Msgs</th>
                <th style='text-align:center; padding:5px 0;'>❤️ Reacts</th>
              </tr>";
              
        foreach ($members as $jid => $data) {
            $nome = $data['name'] ?? 'Desconhecido';
            $msgs = ($data['messages'] ?? 0) + ($data['images_sent'] ?? 0) + ($data['audios_sent'] ?? 0);
            $reacts = $data['reactions_given'] ?? 0;
            
            // Destaque se for admin do painel
            $isAdminMarker = '';
            if (strpos($jid, preg_replace('/:\d+@/', '@', $config['admin_jid'] ?? '')) !== false) {
                $isAdminMarker = ' <span style="font-size:10px; background:#444; padding:2px 4px; border-radius:4px;">Admin</span>';
            }

            echo "<tr style='border-bottom:1px solid #2a2a2a;'>";
            echo "<td style='padding:8px 0; font-size:14px;'>" . htmlspecialchars($nome) . $isAdminMarker . "</td>";
            echo "<td style='padding:8px 0; font-size:14px; text-align:center; color:#10b981; font-weight:bold;'>{$msgs}</td>";
            echo "<td style='padding:8px 0; font-size:14px; text-align:center; color:#f43f5e; font-weight:bold;'>{$reacts}</td>";
            echo "</tr>";
        }
        echo "</table>";
        echo "</div>";
    }
    echo "</div>";
}
?>
