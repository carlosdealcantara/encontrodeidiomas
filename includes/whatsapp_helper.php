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
function enviarWhatsApp($to, string $message, string $source = 'sistema', ?array $linkPreview = null, string $priority = 'normal', ?string $dedupKey = null): array {
    if (is_array($to)) {
        // Bulk
        $payload = [
            'groups' => $to,
            'textMessage' => ['text' => $message],
            'source' => $source,
            'priority' => $priority
        ];
        if ($dedupKey !== null) {
            $payload['dedup_key'] = $dedupKey;
        }
        return sendBaileysRequest('/send-bulk', $payload);
    } else {
        // Unitário
        $payload = [
            'to' => $to,
            'message' => $message,
            'source' => $source,
            'priority' => $priority
        ];
        if ($linkPreview !== null) {
            $payload['linkPreview'] = $linkPreview;
        }
        if ($dedupKey !== null) {
            $payload['dedup_key'] = $dedupKey;
        }
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

function enviarWhatsAppMention(string $to, string $message, array $mentions, ?string $dedupKey = null): array {
    $payload = [
        'to' => $to,
        'message' => $message,
        'mentions' => $mentions
    ];
    if ($dedupKey !== null) {
        $payload['dedup_key'] = $dedupKey;
    }
    return sendBaileysRequest('/send-mention', $payload, 'POST');
}

/**
 * Resolve o nome de um participante da Mentoria pelo seu JID.
 * Testa múltiplos formatos de telefone (com/sem 55, com/sem nono dígito)
 * em mentoria_alunos, mentoria_desafio_streaks e class_attendances.
 *
 * @param PDO $conn Conexão com o banco
 * @param string $memberJid JID do participante (ex: 5511999998888@s.whatsapp.net ou com :device)
 * @param string|null $lang Idioma opcional para priorizar busca em alunos
 * @return string Nome resolvido ou 'Unknown'
 */
function resolveMentoriaMemberName(PDO $conn, string $memberJid, ?string $lang = null): string {
    $cleanJid = preg_replace('/:\d+@/', '@', $memberJid);
    $phoneOnly = preg_replace('/\D/', '', explode('@', $cleanJid)[0]);

    if (!empty($phoneOnly)) {
        // Gera variações comuns de telefone brasileiro/internacional
        $phoneCandidates = [$phoneOnly];

        // Se começa com 55 (Brasil)
        if (str_starts_with($phoneOnly, '55')) {
            $semDDI = substr($phoneOnly, 2);
            $phoneCandidates[] = $semDDI;
            // 55 + DDD + 8 dígitos (12 dígitos) -> gera versão com 9
            if (strlen($phoneOnly) === 12) {
                $phoneCandidates[] = substr($phoneOnly, 0, 4) . '9' . substr($phoneOnly, 4);
                $phoneCandidates[] = substr($semDDI, 0, 2) . '9' . substr($semDDI, 2);
            }
            // 55 + DDD + 9 dígitos (13 dígitos) -> gera versão sem o 9
            elseif (strlen($phoneOnly) === 13 && $phoneOnly[4] === '9') {
                $phoneCandidates[] = substr($phoneOnly, 0, 4) . substr($phoneOnly, 5);
                $phoneCandidates[] = substr($semDDI, 0, 2) . substr($semDDI, 3);
            }
        } else {
            // Não começou com 55, adiciona versão com 55
            $phoneCandidates[] = '55' . $phoneOnly;
            if (strlen($phoneOnly) === 10) { // DDD + 8 dígitos
                $phoneCandidates[] = substr($phoneOnly, 0, 2) . '9' . substr($phoneOnly, 2);
                $phoneCandidates[] = '55' . substr($phoneOnly, 0, 2) . '9' . substr($phoneOnly, 2);
            } elseif (strlen($phoneOnly) === 11 && $phoneOnly[2] === '9') { // DDD + 9 dígitos
                $phoneCandidates[] = substr($phoneOnly, 0, 2) . substr($phoneOnly, 3);
                $phoneCandidates[] = '55' . substr($phoneOnly, 0, 2) . substr($phoneOnly, 3);
            }
        }

        $phoneCandidates = array_values(array_unique(array_filter($phoneCandidates)));

        if (!empty($phoneCandidates)) {
            $inPlaceholders = implode(',', array_fill(0, count($phoneCandidates), '?'));

            // 1. Tenta buscar em mentoria_alunos
            if ($lang) {
                $sqlAlunos = "SELECT nome FROM mentoria_alunos WHERE telefone IN ($inPlaceholders) AND nome IS NOT NULL AND TRIM(nome) != '' AND (lang_id = ? OR lang_id IS NULL) ORDER BY (lang_id = ?) DESC LIMIT 1";
                $paramsAlunos = array_merge($phoneCandidates, [$lang, $lang]);
            } else {
                $sqlAlunos = "SELECT nome FROM mentoria_alunos WHERE telefone IN ($inPlaceholders) AND nome IS NOT NULL AND TRIM(nome) != '' LIMIT 1";
                $paramsAlunos = $phoneCandidates;
            }
            try {
                $stmt = $conn->prepare($sqlAlunos);
                $stmt->execute($paramsAlunos);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row && !empty(trim($row['nome']))) {
                    return trim($row['nome']);
                }
            } catch (Exception $e) {}
        }
    }

    // 2. Tenta em mentoria_desafio_streaks (por member_jid exato ou clean)
    try {
        $stmtStreak = $conn->prepare("SELECT member_name FROM mentoria_desafio_streaks WHERE (member_jid = ? OR member_jid = ?) AND member_name IS NOT NULL AND TRIM(member_name) != '' AND member_name != 'Unknown' AND member_name != 'Desconhecido' LIMIT 1");
        $stmtStreak->execute([$memberJid, $cleanJid]);
        $rowStreak = $stmtStreak->fetch(PDO::FETCH_ASSOC);
        if ($rowStreak && !empty(trim($rowStreak['member_name']))) {
            return trim($rowStreak['member_name']);
        }
    } catch (Exception $e) {}

    // 3. Tenta em class_attendances (presenças mais recentes)
    try {
        $stmtAtt = $conn->prepare("SELECT member_name FROM class_attendances WHERE (member_jid = ? OR member_jid = ?) AND member_name IS NOT NULL AND TRIM(member_name) != '' AND member_name != 'Unknown' AND member_name != 'Desconhecido' ORDER BY id DESC LIMIT 1");
        $stmtAtt->execute([$memberJid, $cleanJid]);
        $rowAtt = $stmtAtt->fetch(PDO::FETCH_ASSOC);
        if ($rowAtt && !empty(trim($rowAtt['member_name']))) {
            return trim($rowAtt['member_name']);
        }
    } catch (Exception $e) {}

    // 4. Tenta em mentoria_daily_scores (histórico de pontuação com nome já registrado)
    try {
        $stmtDaily = $conn->prepare("SELECT member_name FROM mentoria_daily_scores WHERE (member_jid = ? OR member_jid = ?) AND member_name IS NOT NULL AND TRIM(member_name) != '' AND member_name != 'Unknown' AND member_name != 'Desconhecido' ORDER BY score_date DESC LIMIT 1");
        $stmtDaily->execute([$memberJid, $cleanJid]);
        $rowDaily = $stmtDaily->fetch(PDO::FETCH_ASSOC);
        if ($rowDaily && !empty(trim($rowDaily['member_name']))) {
            return trim($rowDaily['member_name']);
        }
    } catch (Exception $e) {}

    return 'Unknown';
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

