<?php
/**
 * ============================================================
 * MOTOR DA MENTORIA - RELAY TELEGRAM PARA COBRANÇAS
 * ============================================================
 * Envia avisos de vencimento para o Admin via Telegram.
 */

require_once __DIR__ . '/../config.php';

$token_secreto = '83x9aZ2pLQw1';
$is_cli = (php_sapi_name() === 'cli');

if (!$is_cli && (!isset($_GET['token']) || $_GET['token'] !== $token_secreto)) {
    http_response_code(403);
    die("Acesso Negado.");
}

$conn = connectDB();

// A função getSetting já está no config.php

// Verifica se o relay do Telegram está ativo no sistema
try {
    $ativo = (int)getSetting('telegram_cobranca_ativo', '0');
    
    if (!$ativo && !isset($_GET['force'])) {
        die("Relay de cobranças via Telegram está desativado no painel Admin.");
    }
} catch (Exception $e) {
    die("Relay de cobranças via Telegram está desativado (config não encontrada).");
}

$telegramToken = $_ENV['TELEGRAM_COBRANCA_BOT_TOKEN'] ?? getenv('TELEGRAM_COBRANCA_BOT_TOKEN');
$telegramChatId = $_ENV['TELEGRAM_COBRANCA_CHAT_ID'] ?? getenv('TELEGRAM_COBRANCA_CHAT_ID');

if (!$telegramToken || !$telegramChatId) {
    die("TELEGRAM_COBRANCA_BOT_TOKEN ou TELEGRAM_COBRANCA_CHAT_ID não configurados no .env");
}

// Cria tabela de logs para o Telegram se não existir
$conn->exec("
    CREATE TABLE IF NOT EXISTS telegram_cobranca_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        aluno_id INT NOT NULL,
        mensagem_id INT NOT NULL,
        data_disparo DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_disparo (aluno_id, mensagem_id, data_disparo)
    )
");

if (isset($_GET['clear_logs']) && $_GET['clear_logs'] == '1') {
    $conn->exec("DELETE FROM telegram_cobranca_logs WHERE data_disparo = CURRENT_DATE");
    echo "<div style='background: #ffeb3b; padding: 15px; margin-bottom: 20px;'>⚠️ Logs de hoje foram APAGADOS! O sistema tentará reenviar todas as mensagens pendentes de hoje.</div>";
}

echo "<h2>Iniciando Varredura do Motor de Cobranças (Telegram Relay)</h2>";

// Pega todas as mensagens e usa o novo campo 'ativo_telegram' 
// para decidir quais vão disparar no relay.
$stmtMsgs = $conn->query("SELECT * FROM mentoria_mensagens");
$mensagensTotais = $stmtMsgs->fetchAll();
if(count($mensagensTotais) === 0) {
    die("Nenhuma mensagem cadastrada no painel. Abortando.");
}

$mensagensMap = [];
foreach($mensagensTotais as $m) {
    // Agora o painel gerencia explicitly o telegram
    $ativo_telegram = isset($m['ativo_telegram']) ? (int)$m['ativo_telegram'] : 1;
    if ($ativo_telegram === 1) {
        $mensagensMap[$m['dias_antes']] = $m;
    }
}

// Atualiza status pendente para <= 3 dias (mesma lógica do original, como precaução)
$conn->exec("
    UPDATE mentoria_alunos 
    SET status_pagamento = 'Pendente' 
    WHERE status_aluno = 'Ativo' 
    AND status_pagamento = 'Pago' 
    AND DATEDIFF(proximo_vencimento, CURRENT_DATE) <= 3
");

$pix_footer = getSetting('mentoria_pix_footer', "🔑 Chave PIX: 01811018157\nCarlos");

$stmtAlunos = $conn->query("
    SELECT * FROM mentoria_alunos 
    WHERE status_aluno = 'Ativo' 
    AND status_pagamento IN ('Pendente', 'Suspenso')
");
$alunos = $stmtAlunos->fetchAll();

$hoje = new DateTime();
$hoje->setTime(0,0,0);
$dataDisparo = $hoje->format('Y-m-d');

$sucessos = 0;

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


foreach ($alunos as $aluno) {
    if(strpos($aluno['proximo_vencimento'], '-0001') !== false || substr($aluno['proximo_vencimento'], 0, 4) == '1900') {
        continue;
    }
    
    $vencimento = new DateTime($aluno['proximo_vencimento']);
    $vencimento->setTime(0,0,0);
    
    $diff = $hoje->diff($vencimento);
    $diasFaltando = (int)$diff->format('%R%a'); 
    
    if (isset($mensagensMap[$diasFaltando])) {
        $msgConfig = $mensagensMap[$diasFaltando];
        $msgId = $msgConfig['id'];
        $alunoId = $aluno['id'];
        
        $stmtCheck = $conn->prepare("SELECT id FROM telegram_cobranca_logs WHERE aluno_id = ? AND mensagem_id = ? AND data_disparo = ?");
        $stmtCheck->execute([$alunoId, $msgId, $dataDisparo]);
        
        if ($stmtCheck->rowCount() === 0 || isset($_GET['force'])) {
            $primeiroNome = trim(explode(' ', $aluno['nome'])[0]);
            $textoBase = str_replace('{nome}', $primeiroNome, $msgConfig['texto']);
            $textoFinalWhats = $textoBase . "\n\n" . trim($pix_footer);
            
            $telefoneLimpo = preg_replace('/\D/', '', $aluno['telefone']);
            if (strlen($telefoneLimpo) <= 11) {
                $telefoneLimpo = "55" . $telefoneLimpo;
            }
            
            // Monta a notificação pro Telegram
            $dataFormatada = date('d/m/Y', strtotime($aluno['proximo_vencimento']));
            $msgTelegram = "🔔 *AVISO DE COBRANÇA — Relay Manual*\n";
            $msgTelegram .= "─────────────────────────────\n";
            $msgTelegram .= "👤 Aluno: *{$aluno['nome']}*\n";
            $msgTelegram .= "📱 WhatsApp: `+{$telefoneLimpo}`\n";
            $msgTelegram .= "📅 Vencimento: {$dataFormatada} (em {$diasFaltando} dias)\n";
            $msgTelegram .= "📋 Cenário: *{$msgConfig['cenario']}*\n";
            $msgTelegram .= "─────────────────────────────\n";
            $msgTelegram .= "*Texto para copiar e enviar:*\n\n";
            $msgTelegram .= "```\n" . $textoFinalWhats . "\n```\n";
            $msgTelegram .= "─────────────────────────────\n";
            $msgTelegram .= "💡 _Copie o texto acima e o número de telefone para enviar pelo seu WhatsApp pessoal._\n";
            $msgTelegram .= "🔗 [Abrir Chat de {$primeiroNome}](https://wa.me/{$telefoneLimpo})";
            
            $res = sendTelegramMsg($msgTelegram, $telegramToken, $telegramChatId);
            
            if (isset($res['ok']) && $res['ok']) {
                if (!isset($_GET['force'])) {
                    $stmtLog = $conn->prepare("INSERT IGNORE INTO telegram_cobranca_logs (aluno_id, mensagem_id, data_disparo) VALUES (?, ?, ?)");
                    $stmtLog->execute([$alunoId, $msgId, $dataDisparo]);
                }
                echo "<p>✅ Notificação Telegram ({$msgConfig['cenario']}) enviada para {$aluno['nome']}</p>";
                $sucessos++;
            } else {
                echo "<p>❌ Erro ao notificar Telegram para {$aluno['nome']}. Resposta: " . json_encode($res) . "</p>";
            }
        } else {
            echo "<p>⏭️ Pulando {$aluno['nome']}: Notificação Telegram ({$msgConfig['cenario']}) já foi enviada hoje.</p>";
        }
    }
}

if ($sucessos === 0 && isset($_GET['force'])) {
    $msgTelegram = "🧪 *TESTE DE CONEXÃO DO SISTEMA*\n─────────────────────────────\n✅ A integração com o Telegram está funcionando perfeitamente!\n\nℹ️ Nenhuma cobrança real atendeu aos critérios para envio (nenhum aluno com vencimento para os dias configurados). Fique tranquilo, quando for o dia exato, os avisos chegarão aqui com os dados do aluno.";
    $res = sendTelegramMsg($msgTelegram, $telegramToken, $telegramChatId);
    if (isset($res['ok']) && $res['ok']) {
        echo "<p style='color:green; font-weight:bold;'>✅ Teste de Conexão bem-sucedido! Verifique seu Telegram.</p>";
    } else {
        echo "<p style='color:red;'>❌ Ocorreu um erro ao enviar o teste de conexão. Resposta: " . json_encode($res) . "</p>";
    }
}

echo "<h3>Varredura de Cobrança (Telegram) concluída! {$sucessos} notificações reais enviadas hoje.</h3>";
?>
