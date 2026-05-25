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
    $stmt = $conn->prepare("SELECT nome, proximo_vencimento FROM mentoria_alunos WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $aluno = $stmt->fetch();
    
    if ($aluno) {
        $dataAtual = new DateTime($aluno['proximo_vencimento']);
        
        // Adiciona 1 mês
        $dataAtual->modify('+1 month');
        $novaData = $dataAtual->format('Y-m-d');
        
        // Renova: Joga a data pra frente e volta o status pra Pendente para o próximo ciclo
        $stmtUpdate = $conn->prepare("UPDATE mentoria_alunos SET proximo_vencimento = :data, status_pagamento = 'Pendente' WHERE id = :id");
        $stmtUpdate->execute(['data' => $novaData, 'id' => $id]);
        
        header('Location: mentoria.php?msg=O aluno ' . urlencode($aluno['nome']) . ' foi renovado com sucesso! Novo vencimento: ' . date('d/m/Y', strtotime($novaData)));
        exit;
    }
}
header('Location: mentoria.php?msg=Erro ao renovar aluno.');
exit;
?>
