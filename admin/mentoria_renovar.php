<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = (int)$_POST['id'];
    $conn = connectDB();
    
    // Pega os dados atuais do aluno (incluindo telefone!)
    $stmt = $conn->prepare("SELECT * FROM mentoria_alunos WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $aluno = $stmt->fetch();
    
    if ($aluno) {
        $dataAtual = new DateTime($aluno['proximo_vencimento']);
        
        // Adiciona 1 mês
        $dataAtual->modify('+1 month');
        $novaData = $dataAtual->format('Y-m-d');
        
        // Incrementa o valor total investido
        $novoTotal = (float)$aluno['total_investido'] + (float)$aluno['valor_mensalidade'];
        
        // Checa se virou Vitalício (R$ 5.000,00)
        $novoStatusAluno = $aluno['status_aluno'];
        $mensagemExtra = "";
        if ($novoStatusAluno !== 'Vitalício' && $novoTotal >= 5000.00) {
            $novoStatusAluno = 'Vitalício';
            $mensagemExtra = " 🏆 PARABÉNS! O aluno atingiu R$ 5.000 e virou VITALÍCIO!";
        }
        
        // Renova: Joga a data pra frente, soma o LTV, deixa como PAGO e checa vitalício
        $stmtUpdate = $conn->prepare("UPDATE mentoria_alunos SET proximo_vencimento = :data, status_pagamento = 'Pago', total_investido = :total, status_aluno = :status_aluno WHERE id = :id");
        $stmtUpdate->execute(['data' => $novaData, 'total' => $novoTotal, 'status_aluno' => $novoStatusAluno, 'id' => $id]);
        
        // ==========================================
        // DISPARO IMEDIATO DE MENSAGEM DE AGRADECIMENTO
        // ==========================================
        // ==========================================
        require_once '../includes/whatsapp_helper.php';
        
        $primeiroNome = trim(explode(' ', $aluno['nome'])[0]);
        $novaDataFormatada = date('d/m/Y', strtotime($novaData));
        
        // Busca a mensagem de Agradecimento no BD
        $stmtMsg = $conn->query("SELECT * FROM mentoria_mensagens WHERE cenario = 'Confirmação de Pagamento'");
        $msgConfig = $stmtMsg->fetch();
        
        $ativo_whats = 1;
        $ativo_telegram = 1;
        
        if ($msgConfig) {
            $textoAgradecimento = str_replace(['{nome}', '{data}'], [$primeiroNome, $novaDataFormatada], $msgConfig['texto']);
            $ativo_whats = (int)$msgConfig['ativo'];
            $ativo_telegram = isset($msgConfig['ativo_telegram']) ? (int)$msgConfig['ativo_telegram'] : 1;
        } else {
            // Se não existir, cria a mensagem no BD
            $textoPadrao = "🤖 MENSAGEM AUTOMÁTICA:\n\nFala {nome}! Passando para confirmar que o seu pagamento foi recebido e a sua renovação já está garantida no sistema! 🎉\n\nMuito obrigado por continuar com a gente. Seu próximo vencimento ficou para {data}.\n\nQualquer dúvida, é só me chamar!";
            $stmtInsert = $conn->prepare("INSERT INTO mentoria_mensagens (cenario, dias_antes, texto, ativo, ativo_telegram) VALUES ('Confirmação de Pagamento', -999, ?, 1, 1)");
            $stmtInsert->execute([$textoPadrao]);
            $textoAgradecimento = str_replace(['{nome}', '{data}'], [$primeiroNome, $novaDataFormatada], $textoPadrao);
        }
        
        $telefoneLimpo = preg_replace('/\D/', '', $aluno['telefone']);
        if (strlen($telefoneLimpo) <= 11) {
            $telefoneLimpo = "55" . $telefoneLimpo;
        }
        
        // Disparo via WhatsApp
        if ($ativo_whats === 1) {
            enviarWhatsApp($telefoneLimpo, $textoAgradecimento, 'mentoria_renovar');
        }
        
        // Disparo via Telegram (Relay Manual)
        if ($ativo_telegram === 1) {
            $telegramToken = $_ENV['TELEGRAM_COBRANCA_BOT_TOKEN'] ?? getenv('TELEGRAM_COBRANCA_BOT_TOKEN');
            $telegramChatId = $_ENV['TELEGRAM_COBRANCA_CHAT_ID'] ?? getenv('TELEGRAM_COBRANCA_CHAT_ID');
            $masterToggle = (int)getSetting('telegram_cobranca_ativo', '0');
            
            if ($masterToggle === 1 && $telegramToken && $telegramChatId) {
                $pix_footer = getSetting('mentoria_pix_footer', "🔑 Chave PIX: 01811018157\nCarlos");
                $textoFinalWhats = $textoAgradecimento . "\n\n" . trim($pix_footer);
                
                $msgTelegram = "🔔 *AVISO DE COBRANÇA — Relay Manual*\n";
                $msgTelegram .= "─────────────────────────────\n";
                $msgTelegram .= "👤 Aluno: *{$aluno['nome']}*\n";
                $msgTelegram .= "📱 WhatsApp: `+{$telefoneLimpo}`\n";
                $msgTelegram .= "📅 Vencimento: {$novaDataFormatada} (RENOVADO)\n";
                $msgTelegram .= "📋 Cenário: *Confirmação de Pagamento*\n";
                $msgTelegram .= "─────────────────────────────\n";
                $msgTelegram .= "*Texto para copiar e enviar:*\n\n";
                $msgTelegram .= "```\n" . $textoFinalWhats . "\n```\n";
                $msgTelegram .= "─────────────────────────────\n";
                $msgTelegram .= "💡 _Copie o texto acima e o número de telefone para enviar pelo seu WhatsApp pessoal._\n";
                $msgTelegram .= "🔗 [Abrir Chat de {$primeiroNome}](https://wa.me/{$telefoneLimpo})";
                
                $url = "https://api.telegram.org/bot{$telegramToken}/sendMessage";
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
                    'chat_id' => $telegramChatId,
                    'text' => $msgTelegram,
                    'parse_mode' => 'Markdown',
                    'disable_web_page_preview' => true
                ]));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_exec($ch);
                curl_close($ch);
            }
        }
        // ==========================================
        
        header('Location: mentoria.php?msg=Pagamento Registrado! O aluno ' . urlencode($aluno['nome']) . ' foi renovado. Novo vencimento: ' . date('d/m/Y', strtotime($novaData)) . '.' . urlencode($mensagemExtra));
        exit;
    }
}
header('Location: mentoria.php?msg=Erro ao registrar pagamento.');
exit;
?>
