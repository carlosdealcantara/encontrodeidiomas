<?php
/**
 * ============================================================
 * MOTOR TELEGRAM - AVISOS DE ENCONTROS (Uso Pessoal)
 * ============================================================
 * Dispara notificações no minuto 0, 5, 10 e 20 da hora atual.
 */

require_once __DIR__ . '/../config.php';

$token_secreto = '83x9aZ2pLQw1'; 
$is_cli = (php_sapi_name() === 'cli');

if (!$is_cli && (!isset($_GET['token']) || $_GET['token'] !== $token_secreto)) {
    http_response_code(403);
    die("Acesso Negado.");
}

$telegramToken = $_ENV['TELEGRAM_BOT_TOKEN'] ?? getenv('TELEGRAM_BOT_TOKEN');
$telegramChatId = $_ENV['TELEGRAM_CHAT_ID'] ?? getenv('TELEGRAM_CHAT_ID');

if (!$telegramToken || !$telegramChatId) {
    die("TELEGRAM_BOT_TOKEN ou TELEGRAM_CHAT_ID não configurados no .env");
}

$conn = connectDB();
$hoje = new DateTime();
$diaDaSemanaAtual = (int)$hoje->format('N'); // 1 = Segunda, 7 = Domingo
$horaAtualReal = (int)$hoje->format('H'); // 0 a 23
$minutoAtual = (int)$hoje->format('i'); // 0 a 59
$dataDisparo = $hoje->format('Y-m-d');

// Função helper para envio Telegram
function sendTelegramMsg($msg, $token, $chatId) {
    $url = "https://api.telegram.org/bot{$token}/sendMessage";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'chat_id' => $chatId,
        'text' => $msg,
        'parse_mode' => 'Markdown',
        'disable_web_page_preview' => true
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $result = curl_exec($ch);
    curl_close($ch);
    return json_decode($result, true);
}

// Verifica se é para forçar um tipo específico para testes manuais
$tipoAviso = null;
if (isset($_GET['force_tipo'])) {
    $tipoAviso = $_GET['force_tipo'];
} else {
    // Tolerância de minutos (caso o cron atrase uns segundos e rode no minuto 01)
    if ($minutoAtual >= 0 && $minutoAtual < 4) {
        $tipoAviso = 'inicio';
    } elseif ($minutoAtual >= 5 && $minutoAtual < 9) {
        $tipoAviso = '5min';
    } elseif ($minutoAtual >= 10 && $minutoAtual < 14) {
        $tipoAviso = '10min';
    } elseif ($minutoAtual >= 20 && $minutoAtual < 24) {
        $tipoAviso = '20min';
    } else {
        echo "Minuto atual ($minutoAtual) não é momento de disparo (0, 5, 10, 20). Ignorando.";
        exit;
    }
}

echo "<h2>[TELEGRAM] Varredura tipo: {$tipoAviso} (Hora: {$horaAtualReal}h)</h2>";

// 1. Busca os templates
$stmtTpl = $conn->prepare("SELECT texto FROM telegram_bot_templates WHERE tipo = ? AND ativo = 1");
$stmtTpl->execute([$tipoAviso]);
$templateRow = $stmtTpl->fetch();

if (!$templateRow) {
    die("Template para '{$tipoAviso}' não encontrado ou está desativado.");
}
$textoTemplate = $templateRow['texto'];

// 2. Busca meetings ativados para hoje e esta hora
$stmtMeetings = $conn->prepare("
    SELECT m.*, l.name as language_name, l.flag_emoji 
    FROM meetings m
    JOIN languages l ON m.language_id = l.id
    JOIN telegram_bot_slots tbs ON m.id = tbs.meeting_id
    WHERE m.active = 1 
      AND m.day_of_week = ? 
      AND m.time_hour = ?
      AND tbs.ativo = 1
");
$stmtMeetings->execute([$diaDaSemanaAtual, $horaAtualReal]);
$meetings = $stmtMeetings->fetchAll();

if (count($meetings) === 0) {
    die("Nenhum encontro ativado para agora.");
}

$sucessos = 0;

// 3. Processa cada meeting
foreach ($meetings as $m) {
    $meetingId = $m['id'];
    
    // Checa anti-duplicidade
    $check = $conn->prepare("SELECT id FROM telegram_bot_logs WHERE meeting_id = ? AND data_disparo = ? AND tipo = ?");
    $check->execute([$meetingId, $dataDisparo, $tipoAviso]);
    
    if ($check->rowCount() === 0 || isset($_GET['force'])) {
        
        $textoFinal = $textoTemplate;
        $textoFinal = str_replace('{IDIOMA}', $m['language_name'], $textoFinal);
        $textoFinal = str_replace('{EMOJI_FLAG}', $m['flag_emoji'], $textoFinal);
        $textoFinal = str_replace('{DIA}', getDayName($m['day_of_week']), $textoFinal);
        $textoFinal = str_replace('{HORA}', $m['time_hour'], $textoFinal);
        
        $linkLimpo = str_replace(['https://', 'http://'], '', $m['meet_link'] ?? '');
        $textoFinal = str_replace('{MEET_LINK}', $linkLimpo ?: 'Link não definido', $textoFinal);
        
        // Envia
        $res = sendTelegramMsg($textoFinal, $telegramToken, $telegramChatId);
        
        if (isset($res['ok']) && $res['ok']) {
            $logStmt = $conn->prepare("INSERT INTO telegram_bot_logs (meeting_id, data_disparo, tipo) VALUES (?, ?, ?)");
            $logStmt->execute([$meetingId, $dataDisparo, $tipoAviso]);
            echo "<p>✅ Mensagem enviada para {$m['language_name']}</p>";
            $sucessos++;
        } else {
            echo "<p style='color:red;'>❌ Erro ao enviar: " . json_encode($res) . "</p>";
        }
    } else {
        echo "<p>⏭️ Pulando {$m['language_name']}: já disparado hoje para {$tipoAviso}.</p>";
    }
}

echo "<h3>Concluído! {$sucessos} disparos realizados.</h3>";
?>
