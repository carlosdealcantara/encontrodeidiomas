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
        
        header('Location: mentoria.php?msg=Pagamento Registrado! O aluno ' . urlencode($aluno['nome']) . ' foi renovado. Novo vencimento: ' . date('d/m/Y', strtotime($novaData)) . '.' . urlencode($mensagemExtra));
        exit;
    }
}
header('Location: mentoria.php?msg=Erro ao registrar pagamento.');
exit;
?>
