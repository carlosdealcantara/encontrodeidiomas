<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Acesso negado']);
    exit;
}

header('Content-Type: application/json');

$token = trim($_POST['token'] ?? '');
if (empty($token)) {
    echo json_encode(['success' => false, 'error' => 'Token vazio']);
    exit;
}

// Odysee REST API - endpoint correto para verificar autenticação
// Documentação: https://odysee.com/$/api/user/me retorna dados do usuário autenticado
$url = "https://api.odysee.com/user/me?auth_token=" . urlencode($token);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPGET, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($response === false || !empty($curl_error)) {
    echo json_encode(['success' => false, 'error' => 'Erro de rede: ' . $curl_error]);
    exit;
}

$data = json_decode($response, true);

// A API REST do Odysee retorna: { "success": true/false, "data": {...}, "error": "..." }
if (isset($data['success']) && $data['success'] === true && isset($data['data'])) {
    $email = $data['data']['primary_email'] ?? ($data['data']['email'] ?? 'E-mail oculto');
    echo json_encode([
        'success' => true,
        'message' => 'Token Válido',
        'email' => $email,
    ]);
} elseif (isset($data['error'])) {
    $errMsg = is_string($data['error']) ? $data['error'] : ($data['error']['message'] ?? json_encode($data['error']));
    echo json_encode(['success' => false, 'error' => 'Inválido/Expirado', 'details' => $errMsg]);
} elseif ($http_code === 401 || $http_code === 403) {
    echo json_encode(['success' => false, 'error' => 'Token rejeitado pelo servidor', 'details' => "HTTP $http_code"]);
} else {
    // Dump da resposta para debug
    echo json_encode(['success' => false, 'error' => 'Resposta inesperada', 'details' => substr($response, 0, 200)]);
}
