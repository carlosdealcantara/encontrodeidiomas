<?php
/**
 * MOTOR UNIFICADO DE WHATSAPP
 * Todas as funções de disparo devem usar este helper.
 * Nunca crie conexões curl diretas para WhatsApp fora deste arquivo.
 */

define('BAILEYS_API_URL_DIRECT', 'http://136.248.92.126:3000');
define('BAILEYS_API_URL_TUNNEL', 'https://instant-record-existence-encounter.trycloudflare.com');
define('BAILEYS_API_KEY', 'SenhaMeetups2026');

function checkWhatsAppConnection($url) {
    $ch = curl_init("$url/connection-status");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($httpCode === 200 && $response);
}

function getBestBaileysUrl() {
    // Tenta a conexão direta primeiro (pela Hostinger que não bate na porta 3000, mas vamos tentar mesmo assim para o futuro)
    if (checkWhatsAppConnection(BAILEYS_API_URL_DIRECT)) {
        return BAILEYS_API_URL_DIRECT;
    }
    // Fallback para o túnel
    return BAILEYS_API_URL_TUNNEL;
}

function sendBaileysRequest($endpoint, $payload = null, $method = 'POST') {
    $url = getBestBaileysUrl() . $endpoint;
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $headers = [
        "apikey: " . BAILEYS_API_KEY
    ];
    
    if ($payload !== null) {
        $headers[] = "Content-Type: application/json";
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['success' => false, 'error' => "cURL Error: " . $error, 'httpCode' => 0];
    }
    
    $decoded = json_decode($response, true);
    if ($httpCode >= 200 && $httpCode < 300) {
        return ['success' => true, 'data' => $decoded, 'httpCode' => $httpCode];
    } else {
        $msg = $decoded['error'] ?? "HTTP Error $httpCode";
        return ['success' => false, 'error' => $msg, 'httpCode' => $httpCode];
    }
}

/**
 * Envia uma mensagem para o WhatsApp.
 * Se for Bulk (array), usa /send-bulk.
 * Se for Unitário, usa /send.
 */
function enviarWhatsApp($to, string $message, string $source = 'sistema'): array {
    if (is_array($to)) {
        // Bulk
        $payload = [
            'groups' => $to,
            'textMessage' => ['text' => $message],
            'source' => $source
        ];
        return sendBaileysRequest('/send-bulk', $payload);
    } else {
        // Unitário
        $payload = [
            'to' => $to,
            'message' => $message,
            'source' => $source
        ];
        return sendBaileysRequest('/send', $payload);
    }
}

function statusWhatsApp(): array {
    $res = sendBaileysRequest('/connection-status', null, 'GET');
    if ($res['success']) {
        return ['connected' => $res['data']['connected'] ?? false];
    }
    return ['connected' => false];
}

// === FUNÇÕES DA MENTORIA ===

function getMentoriaConfig(): array {
    $res = sendBaileysRequest('/mentoria-config', null, 'GET');
    return $res['success'] ? ($res['data'] ?? []) : [];
}

function fetchBaileysActivity(string $date): array {
    $res = sendBaileysRequest('/activity?date=' . urlencode($date), null, 'GET');
    return $res['success'] ? ($res['data'] ?? []) : [];
}

function fetchGroupMembers(string $groupId): array {
    $res = sendBaileysRequest('/group-members?groupId=' . urlencode($groupId), null, 'GET');
    return $res['success'] ? ($res['data'] ?? []) : [];
}

function enviarWhatsAppMention(string $to, string $message, array $mentions): array {
    $payload = [
        'to' => $to,
        'message' => $message,
        'mentions' => $mentions
    ];
    return sendBaileysRequest('/send-mention', $payload, 'POST');
}

function removerDoGrupo(string $groupId, array $participants): array {
    $payload = [
        'groupId' => $groupId,
        'participants' => $participants
    ];
    return sendBaileysRequest('/group-remove', $payload, 'POST');
}

/**
 * Lê uma configuração do sistema na tabela system_settings.
 * @param PDO $conn Conexão já aberta
 * @param string $chave
 * @param string $default Valor padrão se não encontrar
 * @return string
 */
function getSystemSetting(PDO $conn, string $chave, string $default = ''): string {
    try {
        $stmt = $conn->prepare("SELECT valor FROM system_settings WHERE chave = ? LIMIT 1");
        $stmt->execute([$chave]);
        $row = $stmt->fetch();
        return $row ? $row['valor'] : $default;
    } catch (Exception $e) {
        return $default; // Falha silenciosa — nunca quebra o cron
    }
}

/**
 * Formata um objeto DateTime para o estilo AM/PM em inglês (ex: "1 PM" ou "1:30 PM").
 */
function formatTime12h(DateTime $dtObj): string {
    $h = (int)$dtObj->format('g');
    $m = $dtObj->format('i');
    $ampm = $dtObj->format('A');
    if ($m === '00') {
        return "$h $ampm";
    }
    return "$h:$m $ampm";
}
