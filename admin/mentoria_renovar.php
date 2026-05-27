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
    
    // Pega os dados atuais do aluno
    $stmt = $conn->prepare("SELECT nome, proximo_vencimento, valor_mensalidade, total_investido, status_aluno FROM mentoria_alunos WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $aluno = $stmt->fetch();
    
    if ($aluno) {
        $dataAtual = new DateTime($aluno['proximo_vencimento']);
        
        // Adiciona 1 mês
        $dataAtual->modify('+1 month');
        $novaData = $dataAtual->format('Y-m-d');
        
        // Incrementa o valor total investido
        $novoTotal = (float)$aluno['total_investido'] + (float)$aluno['valor_mensalidade'];
        
        // Checa se virou Vitalício (R$ 3.000,00)
        $novoStatusAluno = $aluno['status_aluno'];
        $mensagemExtra = "";
        if ($novoStatusAluno !== 'Vitalício' && $novoTotal >= 3000.00) {
            $novoStatusAluno = 'Vitalício';
            $mensagemExtra = " 🏆 PARABÉNS! O aluno atingiu R$ 3.000 e virou VITALÍCIO!";
        }
        
        // Renova: Joga a data pra frente, soma o LTV, mantém pendente pro novo ciclo e checa vitalício
        $stmtUpdate = $conn->prepare("UPDATE mentoria_alunos SET proximo_vencimento = :data, status_pagamento = 'Pendente', total_investido = :total, status_aluno = :status_aluno WHERE id = :id");
        $stmtUpdate->execute(['data' => $novaData, 'total' => $novoTotal, 'status_aluno' => $novoStatusAluno, 'id' => $id]);
        
        // ==========================================
        // DISPARO IMEDIATO DE MENSAGEM DE AGRADECIMENTO
        // ==========================================
        $EVOLUTION_API_URL = "http://136.248.92.126:8080/message/sendText/meetups";
        $EVOLUTION_API_KEY = "SenhaMeetups2026";
        
        $primeiroNome = trim(explode(' ', $aluno['nome'])[0]);
        $textoAgradecimento = "🤖 MENSAGEM AUTOMÁTICA:\n\nFala {$primeiroNome}! Passando para confirmar que o seu pagamento foi recebido e a sua renovação já está garantida no sistema! 🎉\n\nMuito obrigado por continuar com a gente. Seu próximo vencimento ficou para " . date('d/m/Y', strtotime($novaData)) . ".\n\nQualquer dúvida, é só me chamar!";
        
        $telefoneLimpo = preg_replace('/\D/', '', $aluno['telefone']);
        if (strlen($telefoneLimpo) <= 11) {
            $telefoneLimpo = "55" . $telefoneLimpo;
        }
        
        $payload = json_encode([
            "number" => $telefoneLimpo,
            "options" => [
                "delay" => 1500,
                "presence" => "composing"
            ],
            "textMessage" => [
                "text" => $textoAgradecimento
            ]
        ]);
        
        $ch = curl_init($EVOLUTION_API_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "apikey: " . $EVOLUTION_API_KEY
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_exec($ch);
        curl_close($ch);
        // ==========================================
        
        header('Location: mentoria.php?msg=Pagamento Registrado! O aluno ' . urlencode($aluno['nome']) . ' foi renovado. Novo vencimento: ' . date('d/m/Y', strtotime($novaData)) . '.' . urlencode($mensagemExtra));
        exit;
    }
}
header('Location: mentoria.php?msg=Erro ao registrar pagamento.');
exit;
?>
