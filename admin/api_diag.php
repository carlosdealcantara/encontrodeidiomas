<?php
/**
 * DIAGNÓSTICO: Testa todos os endpoints de grupo da Evolution API
 * para descobrir como obter os nomes dos grupos.
 * 
 * ATENÇÃO: Arquivo temporário. Remover após uso.
 */
session_start();
require_once '../config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

header('Content-Type: text/html; charset=utf-8');

$BASE = 'http://136.248.92.126:8080';
$KEY  = 'SenhaMeetups2026';
$INST = 'meetups';

function api_call($url, $method = 'GET', $body = null) {
    global $KEY;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["apikey: $KEY", "Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($body) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    return ['code' => $code, 'body' => $res, 'error' => $err];
}

echo "<html><head><title>API Diag</title>
<style>body{font-family:monospace;background:#111;color:#eee;padding:20px;}
.ok{color:#0f0;}.err{color:#f55;}.warn{color:#fa0;}
pre{background:#222;padding:10px;border-radius:5px;overflow-x:auto;max-height:300px;}
h2{border-bottom:1px solid #444;padding-bottom:5px;}
</style></head><body>";

echo "<h1>🔍 Diagnóstico API Evolution v1.8.2</h1>";

// 1. Pegar um JID de grupo de exemplo via findChats
echo "<h2>1. Buscando grupo de exemplo via findChats...</h2>";
$chats = api_call("$BASE/chat/findChats/$INST");
$group_jids = [];
if ($chats['code'] === 200 && $chats['body']) {
    $decoded = json_decode($chats['body'], true);
    if (is_array($decoded)) {
        foreach ($decoded as $chat) {
            $id = $chat['id'] ?? '';
            if (strpos($id, '@g.us') !== false) {
                $group_jids[] = $id;
            }
        }
    }
}
echo "<p>Grupos encontrados: <strong>" . count($group_jids) . "</strong></p>";
if (empty($group_jids)) {
    echo "<p class='err'>Nenhum grupo encontrado. Abortando.</p></body></html>";
    exit;
}

// Pegar os primeiros 3 como amostra
$sample = array_slice($group_jids, 0, 3);
echo "<p>Amostra de JIDs: " . implode(', ', $sample) . "</p>";

$testJid = $sample[0];

// 2. Testar findGroupInfos GET (query param)
echo "<h2>2. findGroupInfos (GET, query param)</h2>";
$r = api_call("$BASE/group/findGroupInfos/$INST?groupJid=$testJid");
echo "<p>HTTP {$r['code']} | Corpo: </p><pre>" . htmlspecialchars(substr($r['body'], 0, 500)) . "</pre>";

// 3. Testar fetchAllGroups com getParticipants=false
echo "<h2>3. fetchAllGroups (getParticipants=false)</h2>";
$r = api_call("$BASE/group/fetchAllGroups/$INST?getParticipants=false");
echo "<p>HTTP {$r['code']} | Corpo (primeiros 500 chars): </p><pre>" . htmlspecialchars(substr($r['body'], 0, 500)) . "</pre>";

// 4. Testar fetchInviteCode como GET (query param)
echo "<h2>4. fetchInviteCode (GET, query)</h2>";
$r = api_call("$BASE/group/fetchInviteCode/$INST?groupJid=$testJid");
echo "<p>HTTP {$r['code']} | Corpo: </p><pre>" . htmlspecialchars(substr($r['body'], 0, 500)) . "</pre>";

// 5. Testar fetchInviteCode como POST (body)
echo "<h2>5. fetchInviteCode (POST, body)</h2>";
$r = api_call("$BASE/group/fetchInviteCode/$INST", 'POST', ['groupJid' => $testJid]);
echo "<p>HTTP {$r['code']} | Corpo: </p><pre>" . htmlspecialchars(substr($r['body'], 0, 500)) . "</pre>";

// 6. Testar inviteInfo como GET
echo "<h2>6. inviteInfo (GET, query)</h2>";
$r = api_call("$BASE/group/inviteInfo/$INST?inviteCode=test");
echo "<p>HTTP {$r['code']} | Corpo: </p><pre>" . htmlspecialchars(substr($r['body'], 0, 500)) . "</pre>";

// 7. Testar participants (GET) — que sabemos funcionar
echo "<h2>7. participants (GET, query) — controle</h2>";
$r = api_call("$BASE/group/participants/$INST?groupJid=$testJid");
echo "<p>HTTP {$r['code']} | Corpo (primeiros 300 chars): </p><pre>" . htmlspecialchars(substr($r['body'], 0, 300)) . "</pre>";

// 8. Testar findGroupInfos como POST 
echo "<h2>8. findGroupInfos (POST, body)</h2>";
$r = api_call("$BASE/group/findGroupInfos/$INST", 'POST', ['groupJid' => $testJid]);
echo "<p>HTTP {$r['code']} | Corpo: </p><pre>" . htmlspecialchars(substr($r['body'], 0, 500)) . "</pre>";

// 9. Testar findChats filtrando por grupo — ver se traz name/subject
echo "<h2>9. findChats (POST, filtro por grupo)</h2>";
$r = api_call("$BASE/chat/findChats/$INST", 'POST', ['where' => ['id' => $testJid]]);
echo "<p>HTTP {$r['code']} | Corpo: </p><pre>" . htmlspecialchars(substr($r['body'], 0, 500)) . "</pre>";

// 10. Testar findContacts filtrando por grupo JID
echo "<h2>10. findContacts (POST, filtro por grupo JID)</h2>";
$r = api_call("$BASE/chat/findContacts/$INST", 'POST', ['where' => ['id' => $testJid]]);
echo "<p>HTTP {$r['code']} | Corpo: </p><pre>" . htmlspecialchars(substr($r['body'], 0, 500)) . "</pre>";

// 11. Testar findContacts buscando TODOS e filtrar localmente por @g.us
echo "<h2>11. findContacts (POST, vazio) — filtrando grupos</h2>";
$r = api_call("$BASE/chat/findContacts/$INST", 'POST', (object)[]);
if ($r['code'] === 200 && $r['body']) {
    $contacts = json_decode($r['body'], true);
    $group_contacts = [];
    if (is_array($contacts)) {
        foreach ($contacts as $c) {
            $cid = $c['id'] ?? '';
            if (strpos($cid, '@g.us') !== false) {
                $group_contacts[] = $c;
            }
        }
    }
    echo "<p>Total contatos: " . count($contacts) . " | Contatos de grupo (@g.us): <strong>" . count($group_contacts) . "</strong></p>";
    if (!empty($group_contacts)) {
        echo "<pre>" . htmlspecialchars(json_encode(array_slice($group_contacts, 0, 5), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . "</pre>";
    } else {
        echo "<p class='warn'>Nenhum contato de grupo encontrado na lista de contatos.</p>";
    }
} else {
    echo "<p>HTTP {$r['code']} | Erro: {$r['error']}</p>";
}

// 12. TENTATIVA FINAL: Buscar metadados via mensagem (último recurso)
echo "<h2>12. Resumo — Lista completa de JIDs de grupo</h2>";
echo "<p>Total de grupos: " . count($group_jids) . "</p>";
echo "<pre>" . htmlspecialchars(json_encode($group_jids, JSON_PRETTY_PRINT)) . "</pre>";

echo "<hr><p><em>Diagnóstico concluído em " . date('Y-m-d H:i:s') . "</em></p>";
echo "</body></html>";
