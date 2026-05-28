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
$EVOLUTION_API_URL = "http://136.248.92.126:8080/message/sendText/meetups";
$EVOLUTION_API_KEY = "SenhaMeetups2026";

$payload = json_encode([
    "number" => $groupId,
    "textMessage" => ["text" => "🔧 Teste de Diagnóstico do Sistema (Pode ignorar)"]
]);

$ch = curl_init($EVOLUTION_API_URL);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 60); // 60s para ver se trava
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "apikey: " . $EVOLUTION_API_KEY
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    echo json_encode(["status" => 0, "error" => curl_error($ch)]);
} else {
    echo json_encode(["status" => $httpcode, "response" => $response]);
}
curl_close($ch);
?>
