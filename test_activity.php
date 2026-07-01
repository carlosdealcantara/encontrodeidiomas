<?php
require_once "config.php";
require_once "includes/whatsapp_helper.php";

$payload = [
    'apikey' => 'SenhaMeetups2026',
    'date' => '2026-06-30',
    'group_jid' => '120363246518434750@g.us',
    'member_jid' => '277583904125013@lid',
    'field' => 'images_sent',
    'value' => 1
];

$res = sendBaileysRequest('/mentoria-edit-activity', $payload, 'POST');
echo json_encode($res, JSON_PRETTY_PRINT);
