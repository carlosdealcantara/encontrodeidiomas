<?php
/**
 * DIAGNÓSTICO v2: Mostra config retornada por idioma + JIDs de grupos
 * Acesse: dev.viaei.com/admin/diag_ranking_phones.php
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/whatsapp_helper.php';

header('Content-Type: text/plain; charset=utf-8');

$hoje = date('Y-m-d');

if (isset($_GET['do_sync'])) {
    echo "=== EXECUTANDO SYNC DE GRUPOS ===\n";
    $res = sendBaileysRequest('/groups', null, 'GET');
    echo "Success? " . ($res['success'] ? 'SIM' : 'NÃO') . "\n";
    echo "HTTP Code: " . ($res['httpCode'] ?? 'N/A') . "\n";
    if (!$res['success']) {
        echo "Error: " . ($res['error'] ?? 'N/A') . "\n";
    } else {
        echo "Grupos retornados: " . count($res['data']) . "\n";
        $cacheFile = __DIR__ . '/groups_cache.json';
        file_put_contents($cacheFile, json_encode($res['data'], JSON_UNESCAPED_UNICODE));
        echo "Salvo em groups_cache.json com sucesso!\n";
    }
    echo "\n";
}

$cacheFile = __DIR__ . '/groups_cache.json';
echo "=== URL BAILEYS USADA PELA HOSTINGER ===\n";
echo "  URL: " . getBestBaileysUrl() . "\n";
echo "  Direct OK? " . (checkWhatsAppConnection(BAILEYS_API_URL_DIRECT) ? 'SIM' : 'NÃO') . "\n";
$mtime = file_exists($cacheFile) ? date('Y-m-d H:i:s', filemtime($cacheFile)) : 'N/A';
echo "  Cache modificado em: {$mtime}\n\n";

echo "=== GROUPS_CACHE.JSON ===\n";
if (file_exists($cacheFile)) {
    $raw = file_get_contents($cacheFile);
    $raw = preg_replace('/^[\xef\xbb\xbf]+/', '', $raw);
    $cData = json_decode($raw, true);
    if (is_array($cData)) {
        echo "  Total no cache: " . count($cData) . "\n";
        foreach ($cData as $g) {
            $subj = $g['subject'] ?? '';
            if (preg_match('/(rincon|reto|aula|mentoria|espanhol|adventuring)/ui', $subj) || strpos($subj, '120363414986456733') !== false) {
                echo "  MATCH: " . $g['id'] . " | " . $subj . "\n";
            }
        }
    } else {
        echo "  Cache existe mas json_decode retornou erro: " . json_last_error_msg() . "\n";
    }
} else {
    echo "  Arquivo não existe!\n";
}
echo "\n";

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
