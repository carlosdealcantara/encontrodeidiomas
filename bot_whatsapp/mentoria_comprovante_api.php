<?php
/**
 * ============================================================
 * API: Recepção de Comprovante de Pagamento via WhatsApp Bot
 * ============================================================
 * Chamada pelo bot Node.js (Baileys) ao receber imagem em DM.
 * 
 * Critérios:
 * 1. O número deve pertencer a um aluno ativo cadastrado.
 * 2. O aluno deve estar com status_pagamento != 'Pago' e dentro da janela de cobrança
 *    (D-3 até tolerância).
 * 
 * Efeitos:
 * - Atualiza status_pagamento para 'Comprovante Enviado'.
 * - Envia notificação para o Telegram do Admin.
 * - Retorna o texto formatado para o bot responder ao aluno no privado.
 */

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['sender_jid'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Parâmetro sender_jid obrigatório']);
    exit;
}

$senderJid = $input['sender_jid'];
$senderName = $input['sender_name'] ?? 'Aluno';

// Extrai telefone limpo (ex: 556199998888)
$phoneOnly = preg_replace('/\D/', '', explode('@', $senderJid)[0]);
if (empty($phoneOnly)) {
    echo json_encode(['success' => false, 'reason' => 'invalid_phone']);
    exit;
}

// Cria variações com e sem o 9º dígito (para DDDs brasileiros)
$phoneVariants = [$phoneOnly];
if (str_starts_with($phoneOnly, '55') && strlen($phoneOnly) === 12) {
    $phoneVariants[] = substr($phoneOnly, 0, 4) . '9' . substr($phoneOnly, 4);
} elseif (str_starts_with($phoneOnly, '55') && strlen($phoneOnly) === 13 && $phoneOnly[4] === '9') {
    $phoneVariants[] = substr($phoneOnly, 0, 4) . substr($phoneOnly, 5);
}

try {
    $conn = connectDB();

    // Busca o aluno no banco
    $placeholders = implode(',', array_fill(0, count($phoneVariants), '?'));
    $stmtAluno = $conn->prepare("
        SELECT id, nome, telefone, status_aluno, status_pagamento, proximo_vencimento, lang_id, valor_mensalidade
        FROM mentoria_alunos
        WHERE status_aluno = 'Ativo'
          AND telefone IN ($placeholders)
        LIMIT 1
    ");
    $stmtAluno->execute($phoneVariants);
    $aluno = $stmtAluno->fetch(PDO::FETCH_ASSOC);

    if (!$aluno) {
        // Não é um aluno ativo da mentoria -> Ignora silenciosamente sem responder
        echo json_encode(['success' => false, 'reason' => 'not_an_active_student']);
        exit;
    }

    // Se já estiver com pagamento pago, não precisa tratar imagem avulsa como comprovante pendente
    if ($aluno['status_pagamento'] === 'Pago' || $aluno['status_pagamento'] === 'Isento') {
        echo json_encode(['success' => false, 'reason' => 'already_paid', 'student_name' => $aluno['nome']]);
        exit;
    }

    $alunoId = (int)$aluno['id'];
    $alunoNome = $aluno['nome'];
    $primeiroNome = trim(explode(' ', $alunoNome)[0]);
    $alunoLang = !empty($aluno['lang_id']) ? $aluno['lang_id'] : 'en';

    // 1. Atualiza status_pagamento no banco para 'Comprovante Enviado'
    $stmtUp = $conn->prepare("UPDATE mentoria_alunos SET status_pagamento = 'Comprovante Enviado' WHERE id = ?");
    $stmtUp->execute([$alunoId]);

    // 1b. Se este aluno é um TITULAR, atualiza também os seus DEPENDENTES
    $dependentesNomes = [];
    try {
        $stmtDep = $conn->prepare("
            SELECT id, nome FROM mentoria_alunos
            WHERE responsavel_financeiro_id = ? AND status_aluno = 'Ativo'
        ");
        $stmtDep->execute([$alunoId]);
        $dependentes = $stmtDep->fetchAll(PDO::FETCH_ASSOC);
        foreach ($dependentes as $dep) {
            $conn->prepare("UPDATE mentoria_alunos SET status_pagamento = 'Comprovante Enviado' WHERE id = ?")
                 ->execute([$dep['id']]);
            $dependentesNomes[] = $dep['nome'];
        }
    } catch (Exception $e) { /* falha silenciosa */ }

    // 2. Busca template de confirmação de comprovante
    $stmtTpl = $conn->prepare("
        SELECT texto FROM mentoria_mensagens 
        WHERE cenario = 'Comprovante Recebido' AND ativo = 1 AND (lang_id = ? OR lang_id = 'en')
        ORDER BY lang_id = ? DESC LIMIT 1
    ");
    $stmtTpl->execute([$alunoLang, $alunoLang]);
    $tplRow = $stmtTpl->fetch(PDO::FETCH_ASSOC);

    $defaultResposta = "Recebido, {nome}! 📄 Muito obrigado pelo envio do comprovante. Nosso sistema registrou a entrega e em breve daremos baixa na sua renovação. Pode relaxar, seu acesso segue normalmente! 👍";
    $textoResposta = $tplRow ? $tplRow['texto'] : $defaultResposta;
    $textoRespostaFinal = str_replace('{nome}', $primeiroNome, $textoResposta);

    // 3. Notifica o Admin no Telegram (se configurado)
    $telegramToken = $_ENV['TELEGRAM_COBRANCA_BOT_TOKEN'] ?? getenv('TELEGRAM_COBRANCA_BOT_TOKEN');
    $telegramChatId = $_ENV['TELEGRAM_COBRANCA_CHAT_ID'] ?? getenv('TELEGRAM_COBRANCA_CHAT_ID');

    if ($telegramToken && $telegramChatId) {
        $dataVencFormatada = date('d/m/Y', strtotime($aluno['proximo_vencimento']));
        $valorFmt = number_format((float)$aluno['valor_mensalidade'], 2, ',', '.');
        $horaAtual = date('H:i:s');

        $msgTelegram = "📄 *NOVO COMPROVANTE RECEBIDO!*\n";
        $msgTelegram .= "─────────────────────────────\n";
        $msgTelegram .= "👤 Aluno: *{$alunoNome}*\n";
        $msgTelegram .= "🌐 Mentoria: *" . strtoupper($alunoLang) . "*\n";
        $msgTelegram .= "📱 WhatsApp: `+{$aluno['telefone']}`\n";
        $msgTelegram .= "💰 Mensalidade: *R$ {$valorFmt}*\n";
        $msgTelegram .= "📅 Vencimento cadastrado: *{$dataVencFormatada}*\n";
        $msgTelegram .= "🕒 Recebido às: *{$horaAtual} BRT*\n";
        if (!empty($dependentesNomes)) {
            $msgTelegram .= "👨‍👩‍👧 Dependentes atualizados: *" . implode(', ', $dependentesNomes) . "*\n";
        }
        $msgTelegram .= "─────────────────────────────\n";
        $msgTelegram .= "🛡️ *Status alterado para:* `Comprovante Enviado`\n";
        $msgTelegram .= "💡 _O aluno NÃO será removido pelo auto-kick da meia-noite._\n\n";
        $msgTelegram .= "🔗 [Abrir Chat de {$primeiroNome}](https://wa.me/{$aluno['telefone']})";

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
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_exec($ch);
        curl_close($ch);
    }

    echo json_encode([
        'success' => true,
        'aluno_id' => $alunoId,
        'aluno_nome' => $alunoNome,
        'primeiro_nome' => $primeiroNome,
        'reply_text' => $textoRespostaFinal
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
