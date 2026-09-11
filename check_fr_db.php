<?php
require_once 'config.php';
require_once 'includes/whatsapp_helper.php';

$conn = connectDB();
$config = getCommunityConfig();
$stmtGlob = $conn->query("SELECT group_id as jid, nome, welcome_enabled FROM meetup_whatsapp_groups WHERE comunidade = 'global' AND ativo = 1");
$globalGroups = $stmtGlob->fetchAll(PDO::FETCH_ASSOC);

foreach ($globalGroups as $gg) {
    $key = 'global_' . preg_replace('/[^a-z0-9]/', '', strtolower($gg['nome']));
    $config['groups'][$key] = [
        'jid' => $gg['jid'],
        'name' => $gg['nome'],
        'is_community_group' => true,
        'ranking_enabled' => true,
        'welcome_enabled' => (bool)$gg['welcome_enabled']
    ];
}

$res = sendBaileysRequest('/community-config', $config, 'POST');
echo json_encode([
    'sent_groups' => count($config['groups']),
    'baileys_res' => $res,
    'french_in_groups' => isset($config['groups']['global_eifranaisfrench'])
]);
