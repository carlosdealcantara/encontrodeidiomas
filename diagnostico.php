<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/whatsapp_helper.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== DIAGNÓSTICO DA AUTOMAÇÃO - " . date('Y-m-d H:i:s') . " ===\n\n";

// 1. Estado da tabela meetup_schedule
echo "--- 1. TABELA meetup_schedule ---\n";
try {
    $conn = connectDB();
    $rows = $conn->query("SELECT id, group_jid, day_of_week, start_time, meet_link, is_active FROM meetup_schedule")->fetchAll(PDO::FETCH_ASSOC);
    if (empty($rows)) {
        echo "AVISO: Tabela vazia!\n";
    } else {
        foreach ($rows as $r) {
            echo "ID:{$r['id']} | dia:{$r['day_of_week']} | hora:{$r['start_time']} | jid:{$r['group_jid']} | ativo:{$r['is_active']}\n";
        }
    }
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
}

// 2. Config do Baileys
echo "\n--- 2. CONFIG DO BAILEYS (getMentoriaConfig) ---\n";
$config = getMentoriaConfig();
if (empty($config)) {
    echo "ERRO: Config vazia ou Baileys inacessível!\n";
} else {
    $ourMeetupsJid = $config['groups']['our_meetups']['jid'] ?? 'NÃO CONFIGURADO';
    $desafioJid    = $config['groups']['desafio']['jid']    ?? 'NÃO CONFIGURADO';
    $loungeJid     = $config['groups']['the_lounge']['jid'] ?? 'NÃO CONFIGURADO';
    $adminJid      = $config['admin_jid']                   ?? 'NÃO CONFIGURADO';
    echo "admin_jid       : $adminJid\n";
    echo "our_meetups.jid : $ourMeetupsJid\n";
    echo "desafio.jid     : $desafioJid\n";
    echo "the_lounge.jid  : $loungeJid\n";
    echo "\nTemplates disponíveis: " . implode(', ', array_keys($config['templates'] ?? [])) . "\n";
    echo "\nTemplate aviso_desafio:\n" . ($config['templates']['aviso_desafio'] ?? 'NÃO DEFINIDO') . "\n";
    echo "\nTemplate meetup_aviso:\n" . ($config['templates']['meetup_aviso'] ?? 'NÃO DEFINIDO') . "\n";
}

// 3. Comparação — o JID da tabela bate com o config?
echo "\n--- 3. COMPARAÇÃO JID ---\n";
if (!empty($rows) && !empty($config)) {
    $jidNaTabela = $rows[0]['group_jid'] ?? '???';
    $jidNaConfig = $config['groups']['our_meetups']['jid'] ?? '???';
    echo "JID na tabela meetup_schedule : $jidNaTabela\n";
    echo "JID na config Baileys         : $jidNaConfig\n";
    echo "BATEM? " . ($jidNaTabela === $jidNaConfig ? "✅ SIM" : "❌ NÃO — este é o problema!") . "\n";
}

// 4. Membros do grupo Desafio e quantos são admin
echo "\n--- 4. MEMBROS DO GRUPO DESAFIO ---\n";
if (!empty($config)) {
    $desafioJid = $config['groups']['desafio']['jid'] ?? null;
    if ($desafioJid) {
        $members = fetchGroupMembers($desafioJid);
        echo "Total de membros: " . count($members) . "\n";
        $admins = 0;
        foreach ($members as $m) {
            $isAdmin = !empty($m['admin']);
            if ($isAdmin) $admins++;
            echo "  " . ($m['id'] ?? '?') . " | admin=" . ($isAdmin ? 'SIM' : 'não') . "\n";
        }
        echo "Total admins: $admins / " . count($members) . " — ";
        if ($admins === count($members)) {
            echo "⚠️ TODOS SÃO ADMINS! O filtro vai pular todo mundo!\n";
        } else {
            echo "✅ OK, há membros não-admins que seriam marcados.\n";
        }
    } else {
        echo "JID do desafio não configurado.\n";
    }
}

// 5. Atividade de hoje no grupo Desafio
echo "\n--- 5. ATIVIDADE DE HOJE NO GRUPO DESAFIO ---\n";
$hoje = date('Y-m-d');
$activity = fetchBaileysActivity($hoje);
$desafioJid = $config['groups']['desafio']['jid'] ?? null;
if ($desafioJid && isset($activity[$desafioJid])) {
    foreach ($activity[$desafioJid] as $jid => $data) {
        $imgs = $data['images_sent'] ?? 0;
        echo "  $jid | imgs=$imgs | msgs=" . ($data['messages'] ?? 0) . "\n";
    }
} else {
    $keys = array_keys($activity);
    echo "Nenhuma atividade para JID '$desafioJid' hoje.\n";
    echo "JIDs com atividade hoje: " . (empty($keys) ? "nenhum" : implode(', ', $keys)) . "\n";
}

echo "\n=== FIM DO DIAGNÓSTICO ===\n";
?>
