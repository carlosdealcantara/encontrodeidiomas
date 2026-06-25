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
        $stmtMsg = $conn->query("SELECT texto FROM mentoria_mensagens WHERE cenario = 'Confirmação de Pagamento'");
        $msgConfig = $stmtMsg->fetch();
        
        if ($msgConfig) {
            $textoAgradecimento = str_replace(['{nome}', '{data}'], [$primeiroNome, $novaDataFormatada], $msgConfig['texto']);
        } else {
            // Se não existir, cria a mensagem no BD para que ele possa editar no painel depois
            $textoPadrao = "🤖 MENSAGEM AUTOMÁTICA:\n\nFala {nome}! Passando para confirmar que o seu pagamento foi recebido e a sua renovação já está garantida no sistema! 🎉\n\nMuito obrigado por continuar com a gente. Seu próximo vencimento ficou para {data}.\n\nQualquer dúvida, é só me chamar!";
            $stmtInsert = $conn->prepare("INSERT INTO mentoria_mensagens (cenario, dias_antes, texto, ativo) VALUES ('Confirmação de Pagamento', -999, ?, 1)");
            $stmtInsert->execute([$textoPadrao]);
            $textoAgradecimento = str_replace(['{nome}', '{data}'], [$primeiroNome, $novaDataFormatada], $textoPadrao);
        }
        
        $telefoneLimpo = preg_replace('/\D/', '', $aluno['telefone']);
        if (strlen($telefoneLimpo) <= 11) {
            $telefoneLimpo = "55" . $telefoneLimpo;
        }
        
        enviarWhatsApp($telefoneLimpo, $textoAgradecimento, 'mentoria_renovar');
        // ==========================================
        
        header('Location: mentoria.php?msg=Pagamento Registrado! O aluno ' . urlencode($aluno['nome']) . ' foi renovado. Novo vencimento: ' . date('d/m/Y', strtotime($novaData)) . '.' . urlencode($mensagemExtra));
        exit;
    }
}
header('Location: mentoria.php?msg=Erro ao registrar pagamento.');
exit;
?>
