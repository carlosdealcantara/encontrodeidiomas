<?php
/**
 * DIAGNÓSTICO v2: Mostra config retornada por idioma + JIDs de grupos
 * Acesse: dev.viaei.com/admin/diag_ranking_phones.php
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/whatsapp_helper.php';

header('Content-Type: text/plain; charset=utf-8');

$hoje = date('Y-m-d');

foreach (['en', 'es'] as $lang) {
    echo "=== CONFIG lang={$lang} ===\n";
    $config = getMentoriaConfig($lang);

    if (empty($config)) {
        echo "  ⚠ Config VAZIA ou erro ao buscar do Baileys.\n\n";
        continue;
    }

    $groups = $config['groups'] ?? [];
    if (empty($groups)) {
        echo "  ⚠ Nenhum grupo configurado.\n\n";
        continue;
    }

    foreach ($groups as $key => $gData) {
        $jid = $gData['jid'] ?? '';
        echo "  grupo={$key} | jid=" . (empty($jid) ? '(vazio)' : $jid) . "\n";
    }
    echo "\n";
}

// Mostra JIDs do activity.json hoje
echo "=== ACTIVITY.JSON hoje ({$hoje}) ===\n";
$activity = fetchBaileysActivity($hoje);
if (empty($activity)) {
    echo "  (nenhuma atividade hoje ou erro ao buscar)\n";
} else {
    foreach ($activity as $groupJid => $members) {
        $count = count($members);
        echo "  groupJid={$groupJid} | membros={$count}\n";
        foreach ($members as $memberJid => $stats) {
            if (str_ends_with($memberJid, '@g.us')) continue;
            echo "    memberJid={$memberJid}\n";
        }
    }
}

// Simulação do filtro
echo "\n=== SIMULAÇÃO DO FILTRO ===\n";
foreach (['en', 'es'] as $lang) {
    echo "\n-- lang={$lang} --\n";
    $config = getMentoriaConfig($lang);
    $langGroupJids = [];
    foreach ($config['groups'] ?? [] as $key => $gData) {
        if (!empty($gData['jid'])) $langGroupJids[$gData['jid']] = $key;
    }

    if (empty($langGroupJids)) {
        echo "  ⚠ Nenhum grupo configurado — ninguém apareceria no ranking.\n";
        continue;
    }

    echo "  Grupos do idioma: " . implode(', ', array_keys($langGroupJids)) . "\n";

    foreach ($activity as $groupJid => $members) {
        $pertence = isset($langGroupJids[$groupJid]);
        echo "  groupJid={$groupJid} → " . ($pertence ? "✅ INCLUSO ({$langGroupJids[$groupJid]})" : "❌ IGNORADO") . "\n";
    }
}
