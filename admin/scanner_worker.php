<?php
require_once '../config.php';
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    die(json_encode(["status" => 403, "error" => "Acesso Negado"]));
}

if (!isset($_GET['group_id'])) {
    die(json_encode(["status" => 400, "error" => "Sem group_id"]));
}

$groupId = trim($_GET['group_id']);
require_once '../includes/whatsapp_helper.php';

$result = enviarWhatsApp($groupId, '🔧 Teste de Diagnóstico do Sistema (Pode ignorar)', 'diagnostico');

if (!$result['success']) {
    echo json_encode(["status" => 0, "error" => $result['error']]);
} else {
    echo json_encode(["status" => $result['httpCode'], "response" => json_encode($result['data'])]);
}
?>
