<?php
require_once dirname(__DIR__) . '/config.php';

/**
 * MOTOR UNIFICADO DE WHATSAPP
 * Todas as funções de disparo devem usar este helper.
 * Nunca crie conexões curl diretas para WhatsApp fora deste arquivo.
 */

define('BAILEYS_API_URL_DIRECT', 'http://136.248.92.126:3000');
define('BAILEYS_API_URL_TUNNEL_FALLBACK', 'https://forests-hydrogen-transcripts-iowa.trycloudflare.com');
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
    
    // Tenta buscar a URL do túnel na configuração do banco (atualizada pelo worker)
    $tunnelUrl = rtrim(getSetting('baileys_tunnel_url', BAILEYS_API_URL_TUNNEL_FALLBACK), '/');
    
    if (strpos($tunnelUrl, 'instant-record-existence-encounter') !== false) {
        $tunnelUrl = rtrim(BAILEYS_API_URL_TUNNEL_FALLBACK, '/');
        // Opcional: Atualiza o banco para corrigir definitivamente
        updateSetting('baileys_tunnel_url', $tunnelUrl);
    }
    
    // Fallback para o túnel
    return $tunnelUrl;
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

function getMentoriaConfig(string $langId = 'en'): array {
    $endpoint = ($langId === 'en') ? '/mentoria-config' : '/mentoria-config?lang=' . urlencode($langId);
    $res = sendBaileysRequest($endpoint, null, 'GET');
    return $res['success'] ? ($res['data'] ?? []) : [];
}

function saveMentoriaConfig(array $config, string $langId = 'en'): array {
    $endpoint = ($langId === 'en') ? '/mentoria-config' : '/mentoria-config?lang=' . urlencode($langId);
    return sendBaileysRequest($endpoint, $config, 'POST');
}

function fetchBaileysActivity(string $date): array {
    $res = sendBaileysRequest('/activity?date=' . urlencode($date), null, 'GET');
    return $res['success'] ? ($res['data'] ?? []) : [];
}

function getCommunityConfig(): array {
    $res = sendBaileysRequest('/community-config', null, 'GET');
    return $res['success'] ? ($res['data'] ?? []) : [];
}

function fetchCommunityActivity(string $date): array {
    $res = sendBaileysRequest('/community-activity?date=' . urlencode($date), null, 'GET');
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

/**
 * Processa as tags de bloco de comunidade {BR}...{/BR} e {GLOBAL}...{/GLOBAL}.
 * - Para comunidade 'global': remove blocos {BR} e desempacota {GLOBAL}
 * - Para comunidade 'brasil' (ou padrão): desempacota {BR} e remove blocos {GLOBAL}
 * Limpa quebras de linha duplas residuais de forma elegante.
 *
 * @param string $texto Texto original com as tags
 * @param string $comunidade 'brasil' ou 'global'
 * @return string Texto processado pronto para envio
 */
function aplicarTagsComunidade(string $texto, string $comunidade = 'brasil'): string {
    if (empty($texto)) return '';

    if ($comunidade === 'global') {
        // Remove todo o bloco {BR} e seu conteúdo
        $texto = preg_replace('/\{BR\}(.*?)\{\/BR\}/s', '', $texto);
        // Mantém apenas o conteúdo interno de {GLOBAL}
        $texto = preg_replace('/\{GLOBAL\}(.*?)\{\/GLOBAL\}/s', '$1', $texto);
    } else {
        // Mantém apenas o conteúdo interno de {BR}
        $texto = preg_replace('/\{BR\}(.*?)\{\/BR\}/s', '$1', $texto);
        // Remove todo o bloco {GLOBAL} e seu conteúdo
        $texto = preg_replace('/\{GLOBAL\}(.*?)\{\/GLOBAL\}/s', '', $texto);
    }

    // Normaliza excesso de quebras de linha que possam surgir da remoção de blocos
    $texto = preg_replace("/\n{3,}/", "\n\n", $texto);

    return trim($texto);
}

/**
 * GERENCIADOR DE TRAVA E IDEMPOTÊNCIA PARA CRONS / DISPAROS
 * Implementa máquina de estados para evitar duplicatas e nunca engolir envios legítimos:
 * - 'processing': Travado antes do envio. Impede outro cron de disparar em paralelo.
 * - 'sent': Confirmado após sucesso da API do WhatsApp.
 * - 'failed': Falha reportada. Permite nova tentativa na próxima execução.
 * Se ficar preso em 'processing' por mais de $timeoutMinutes (ex: timeout fatal do PHP),
 * a trava expira e permite retentativa automática.
 */
function registrarInicioDisparo(PDO $conn, string $tipo, string $dataExecucao, ?string $identificador = null, int $timeoutMinutes = 15): bool {
    try {
        // Limpa bloqueios expirados que ficaram 'processing' por timeout do PHP anterior
        $stmtClean = $conn->prepare("
            DELETE FROM mentoria_auto_logs 
            WHERE tipo = ? 
              AND data_execucao = ? 
              AND (membro_jid = ? OR (membro_jid IS NULL AND ? IS NULL))
              AND detalhes LIKE '%\"status\":\"processing\"%'
              AND created_at < (NOW() - INTERVAL ? MINUTE)
        ");
        $stmtClean->execute([$tipo, $dataExecucao, $identificador, $identificador, $timeoutMinutes]);

        // Verifica se já existe registro
        $stmtCheck = $conn->prepare("
            SELECT id, detalhes FROM mentoria_auto_logs 
            WHERE tipo = ? 
              AND data_execucao = ? 
              AND (membro_jid = ? OR (membro_jid IS NULL AND ? IS NULL))
        ");
        $stmtCheck->execute([$tipo, $dataExecucao, $identificador, $identificador]);
        $row = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $det = json_decode($row['detalhes'] ?? '', true);
            $status = $det['status'] ?? 'sent'; // Registros legados contam como 'sent'
            if ($status === 'sent' || $status === 'processing') {
                return false; // Já enviado ou em processamento ativo
            }
        }

        // Tenta adquirir a trava como 'processing'
        $detalhesInit = json_encode([
            'status' => 'processing',
            'started_at' => date('Y-m-d H:i:s')
        ]);

        $stmtLock = $conn->prepare("
            INSERT INTO mentoria_auto_logs (tipo, data_execucao, membro_jid, detalhes)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                detalhes = IF(detalhes LIKE '%\"status\":\"failed\"%', VALUES(detalhes), detalhes)
        ");
        $stmtLock->execute([$tipo, $dataExecucao, $identificador, $detalhesInit]);

        return ($stmtLock->rowCount() > 0);
    } catch (Exception $e) {
        error_log("registrarInicioDisparo error: " . $e->getMessage());
        return false;
    }
}

function registrarConclusaoDisparo(PDO $conn, string $tipo, string $dataExecucao, ?string $identificador = null, array $extraInfo = []): void {
    try {
        $extraInfo['status'] = 'sent';
        $extraInfo['finished_at'] = date('Y-m-d H:i:s');
        $detalhesSent = json_encode($extraInfo);

        $stmt = $conn->prepare("
            UPDATE mentoria_auto_logs 
            SET detalhes = ? 
            WHERE tipo = ? 
              AND data_execucao = ? 
              AND (membro_jid = ? OR (membro_jid IS NULL AND ? IS NULL))
        ");
        $stmt->execute([$detalhesSent, $tipo, $dataExecucao, $identificador, $identificador]);
    } catch (Exception $e) {
        error_log("registrarConclusaoDisparo error: " . $e->getMessage());
    }
}

function registrarFalhaDisparo(PDO $conn, string $tipo, string $dataExecucao, ?string $identificador = null, string $erro = ''): void {
    try {
        $detalhesFailed = json_encode([
            'status' => 'failed',
            'error' => $erro,
            'failed_at' => date('Y-m-d H:i:s')
        ]);

        $stmt = $conn->prepare("
            UPDATE mentoria_auto_logs 
            SET detalhes = ? 
            WHERE tipo = ? 
              AND data_execucao = ? 
              AND (membro_jid = ? OR (membro_jid IS NULL AND ? IS NULL))
        ");
        $stmt->execute([$detalhesFailed, $tipo, $dataExecucao, $identificador, $identificador]);
    } catch (Exception $e) {
        error_log("registrarFalhaDisparo error: " . $e->getMessage());
    }
}

