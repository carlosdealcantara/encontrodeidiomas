<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Acesso negado']);
    exit;
}

header('Content-Type: application/json');

$token = $_POST['token'] ?? '';
if (empty($token)) {
    echo json_encode(['success' => false, 'error' => 'Token vazio']);
    exit;
}

// Odysee Backend API
$url = "https://api.na-backend.odysee.com/api/v1/proxy";
$payload = json_encode([
    "jsonrpc" => "2.0",
    "method" => "user_me",
    "id" => 1
]);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Cookie: auth_token=' . trim($token)
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false) {
    echo json_encode(['success' => false, 'error' => 'Erro de rede ao conectar com Odysee']);
    exit;
}

$data = json_decode($response, true);

if (isset($data['error'])) {
    echo json_encode([
        'success' => false, 
        'error' => 'Token Inválido ou Expirado',
        'details' => $data['error']['message'] ?? 'Desconhecido'
    ]);
} elseif (isset($data['result']) && !empty($data['result']['id'])) {
    // Token válido
    $email = $data['result']['primary_email'] ?? 'E-mail Oculto';
    echo json_encode([
        'success' => true, 
        'message' => 'Token Válido!',
        'email' => $email
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Resposta inesperada da API']);
}
