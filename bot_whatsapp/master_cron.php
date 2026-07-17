<?php
/**
 * ============================================================
 * MASTER CRON - ENCONTRO DE IDIOMAS & MENTORIA
 * ============================================================
 * Este arquivo deve ser configurado na Hostinger para rodar a
 * cada 1 HORA (ex: 00:00, 01:00, 02:00...).
 * Ele gerencia a execução de todos os outros scripts internamente,
 * chamando-os via HTTP para isolar falhas (die, exits).
 */

require_once __DIR__ . '/../config.php';

$token_secreto = '83x9aZ2pLQw1'; 
$is_cli = (php_sapi_name() === 'cli');

if (!$is_cli && (!isset($_GET['token']) || $_GET['token'] !== $token_secreto)) {
    http_response_code(403);
    die("Acesso Negado.");
}

$hoje = new DateTime();
$horaAtual = isset($_GET['mock_hora']) ? (int)$_GET['mock_hora'] : (int)$hoje->format('H');

echo "<h1>MASTER CRON: Rodando na hora {$horaAtual}:00</h1><hr>";

// Em ambiente web, SITE_URL funcionará perfeitamente. No CLI, pode usar localhost ou o domínio real.
$baseUrl = SITE_URL . '/bot_whatsapp/';
// Força HTTPs para ambiente online caso SITE_URL não pegue direito na CLI
if ($is_cli) {
    $baseUrl = "https://encontrodeidiomas.com.br/bot_whatsapp/";
    // Opcionalmente, pode-se injetar o prefixo dev. se estiver rodando o dev
    if (strpos(__DIR__, 'dev.') !== false || strpos(__DIR__, 'dev_') !== false) {
        $baseUrl = "https://dev.encontrodeidiomas.com.br/bot_whatsapp/";
    }
}

/**
 * Função helper para rodar um cron interno usando cURL.
 */
function rodarSubCron($arquivo, $baseUrl, $token_secreto) {
    $url = $baseUrl . escapeshellarg($arquivo) . "?token=" . urlencode($token_secreto);
    // Remove as aspas do escapeshellarg se houver, pois url precisa ser limpa
    $url = $baseUrl . $arquivo . "?token=" . urlencode($token_secreto);
    
    echo "<li>Disparando: <strong>$arquivo</strong> ... ";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120); // 2 minutos máx
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode >= 200 && $httpCode < 300) {
        echo "<span style='color:green'>OK (HTTP $httpCode)</span></li>";
        echo "<div style='background:#f0f0f0; padding:10px; margin-bottom:10px; font-size:12px; max-height:200px; overflow-y:auto; border: 1px solid #ccc;'>" . nl2br(htmlspecialchars($result)) . "</div>";
    } else {
        echo "<span style='color:red'>FALHA (HTTP $httpCode)</span></li>";
    }
}

// =========================================================================
// 1. ROTINAS HORÁRIAS
// (Rodam todas as horas, e decidem internamente se é hora de enviar algo)
// =========================================================================
echo "<h2>>>> Crons Horários <<<</h2><ul>";
rodarSubCron('ei_meetups_hourly.php', $baseUrl, $token_secreto);
rodarSubCron('ei_telegram_cron.php', str_replace('/bot_whatsapp/', '/bot_telegram/', $baseUrl), $token_secreto);
rodarSubCron('mentoria_class_quorum_cron.php', $baseUrl, $token_secreto);
rodarSubCron('mentoria_class_kickoff_cron.php', $baseUrl, $token_secreto);
echo "</ul>";

// =========================================================================
// 2. ROTINAS DIÁRIAS (Rodam apenas em um horário específico)
// =========================================================================
echo "<h2>>>> Crons Diários <<<</h2>";

// 00:00 - Processamento de Rankings e Kicks (Baseado no dia anterior)
if ($horaAtual === 0) {
    echo "<h3>Rotina da Meia-Noite (00:00)</h3><ul>";
    rodarSubCron('mentoria_class_aviso_cron.php', $baseUrl, $token_secreto);
    rodarSubCron('mentoria_pontuacao_cron.php', $baseUrl, $token_secreto);
    rodarSubCron('mentoria_desafio_kick_cron.php', $baseUrl, $token_secreto);
    rodarSubCron('mentoria_aniversario_cron.php', $baseUrl, $token_secreto);
    echo "</ul>";
}

// 09:00 - Resumo do Dia (Global)
if ($horaAtual === 9) {
    echo "<h3>Rotina da Manhã (09:00)</h3><ul>";
    rodarSubCron('ei_meetups_daily.php', $baseUrl, $token_secreto);
    echo "</ul>";
}

// 21:00 - Aviso de Desafio (Mentoria)
if ($horaAtual === 21) {
    echo "<h3>Rotina da Noite (21:00)</h3><ul>";
    rodarSubCron('mentoria_desafio_aviso_cron.php', $baseUrl, $token_secreto);
    echo "</ul>";
}

// 08:00 - Cobrança de Mensalidades
if ($horaAtual === 8) {
    echo "<h3>Rotina de Faturamento Mensalidades (08:00 BRT)</h3><ul>";
    rodarSubCron('mentoria_cobranca_cron.php', $baseUrl, $token_secreto);
    rodarSubCron('mentoria_telegram_cron.php', str_replace('/bot_whatsapp/', '/bot_telegram/', $baseUrl), $token_secreto);
    echo "</ul>";
}

echo "<hr><h1>MASTER CRON FINALIZADO</h1>";
?>
