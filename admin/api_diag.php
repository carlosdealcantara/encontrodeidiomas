<?php
/**
 * DIAGNÓSTICO TEMPORÁRIO: Testa endpoints de grupo da Evolution API.
 * Protegido por chave na URL. REMOVER APÓS USO.
 */
if (($_GET['key'] ?? '') !== 'diag2026x') {
    http_response_code(403);
    die('Acesso negado.');
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

// 1. Pegar JIDs de grupo via findChats
echo "<h2>1. Buscando grupos via findChats (GET)...</h2>";
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

$testJid = $group_jids[0];
echo "<p>JID de teste: <code>$testJid</code></p>";

// 2. findGroupInfos (GET)
echo "<h2>2. findGroupInfos (GET)</h2>";
$r = api_call("$BASE/group/findGroupInfos/$INST?groupJid=$testJid");
echo "<p>HTTP {$r['code']}</p><pre>" . htmlspecialchars(substr($r['body'] ?: '(vazio)', 0, 500)) . "</pre>";

// 3. fetchAllGroups
echo "<h2>3. fetchAllGroups (GET, getParticipants=false)</h2>";
$r = api_call("$BASE/group/fetchAllGroups/$INST?getParticipants=false");
echo "<p>HTTP {$r['code']}</p><pre>" . htmlspecialchars(substr($r['body'] ?: '(vazio)', 0, 500)) . "</pre>";

// 4. fetchInviteCode (GET)
echo "<h2>4. fetchInviteCode (GET)</h2>";
$r = api_call("$BASE/group/fetchInviteCode/$INST?groupJid=$testJid");
echo "<p>HTTP {$r['code']}</p><pre>" . htmlspecialchars(substr($r['body'] ?: '(vazio)', 0, 500)) . "</pre>";

// 5. fetchInviteCode (POST)
echo "<h2>5. fetchInviteCode (POST)</h2>";
$r = api_call("$BASE/group/fetchInviteCode/$INST", 'POST', ['groupJid' => $testJid]);
echo "<p>HTTP {$r['code']}</p><pre>" . htmlspecialchars(substr($r['body'] ?: '(vazio)', 0, 500)) . "</pre>";

// 6. inviteInfo (GET)
echo "<h2>6. inviteInfo (GET, code=test)</h2>";
$r = api_call("$BASE/group/inviteInfo/$INST?inviteCode=test");
echo "<p>HTTP {$r['code']}</p><pre>" . htmlspecialchars(substr($r['body'] ?: '(vazio)', 0, 500)) . "</pre>";

// 7. participants (GET) — controle
echo "<h2>7. participants (GET) — CONTROLE</h2>";
$r = api_call("$BASE/group/participants/$INST?groupJid=$testJid");
echo "<p>HTTP {$r['code']}</p><pre>" . htmlspecialchars(substr($r['body'] ?: '(vazio)', 0, 300)) . "</pre>";

// 8. findContacts filtrando grupo
echo "<h2>8. findContacts (POST, filtro por JID grupo)</h2>";
$r = api_call("$BASE/chat/findContacts/$INST", 'POST', ['where' => ['id' => $testJid]]);
echo "<p>HTTP {$r['code']}</p><pre>" . htmlspecialchars(substr($r['body'] ?: '(vazio)', 0, 500)) . "</pre>";

// 9. findContacts TODOS — filtrar por @g.us
echo "<h2>9. findContacts (POST, todos) — filtrando @g.us</h2>";
$r = api_call("$BASE/chat/findContacts/$INST", 'POST', (object)[]);
if ($r['code'] === 200 && $r['body']) {
    $contacts = json_decode($r['body'], true);
    $gc = [];
    if (is_array($contacts)) {
        foreach ($contacts as $c) {
            if (strpos($c['id'] ?? '', '@g.us') !== false) {
                $gc[] = $c;
            }
        }
    }
    echo "<p>Total contatos: " . count($contacts) . " | Grupos: <strong>" . count($gc) . "</strong></p>";
    if (!empty($gc)) {
        echo "<pre>" . htmlspecialchars(json_encode(array_slice($gc, 0, 5), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . "</pre>";
    }
} else {
    echo "<p>HTTP {$r['code']}</p>";
}

// 10. findChats completo — ver se algum grupo tem name
echo "<h2>10. findChats — dados completos dos primeiros 3 grupos</h2>";
if ($chats['code'] === 200) {
    $decoded = json_decode($chats['body'], true);
    $gdata = [];
    if (is_array($decoded)) {
        foreach ($decoded as $chat) {
            if (strpos($chat['id'] ?? '', '@g.us') !== false) {
                $gdata[] = $chat;
                if (count($gdata) >= 3) break;
            }
        }
    }
    echo "<pre>" . htmlspecialchars(json_encode($gdata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . "</pre>";
}

echo "<hr><p>Concluído: " . date('Y-m-d H:i:s') . "</p></body></html>";
