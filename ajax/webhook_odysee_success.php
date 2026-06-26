<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/hosts_notification.php';

$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!is_array($data) || !isset($data['apikey']) || $data['apikey'] !== 'SenhaMeetups2026') {
    http_response_code(403);
    exit("Unauthorized");
}

$lang_id = (int)($data['lang_id'] ?? 0);
if ($lang_id <= 0) {
    http_response_code(400);
    exit("Missing language ID");
}

$conn = connectDB();
$semana_atual = date('o-\WW');

// Reutiliza a lógica centralizada para gerar e disparar o mesmo resumo do portal
notificarAtualizacaoHosts($conn, $lang_id, $semana_atual, "publicou via robô e atualizou os dados");

echo json_encode(["status" => "ok"]);
