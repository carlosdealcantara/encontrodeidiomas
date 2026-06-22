<?php
/**
 * Script temporário de correção - executar uma vez via URL e deletar
 */
require_once __DIR__ . '/../../config.php';

$token = $_GET['token'] ?? '';
if ($token !== '83x9aZ2pLQw1') { die('Acesso negado'); }

$conn = connectDB();

// Rayza: reverter streak de 9 para 8, last_completed_date para ontem (2026-06-20)
// Ela NÃO enviou imagem hoje, foi chamada erroneamente a API
$stmt = $conn->prepare("UPDATE mentoria_desafio_streaks SET current_streak = 8, last_completed_date = '2026-06-20', total_completions = total_completions - 1 WHERE member_jid = '90370440462475@lid'");
$stmt->execute();
echo "Rayza revertida: " . $stmt->rowCount() . " linha(s) afetada(s)\n";

// Verificar estado atual de todas as alunas
$stmt2 = $conn->query("SELECT member_jid, member_name, current_streak, last_completed_date FROM mentoria_desafio_streaks ORDER BY current_streak DESC");
while ($row = $stmt2->fetch(PDO::FETCH_ASSOC)) {
    echo $row['member_name'] . " | streak: " . $row['current_streak'] . " | last: " . $row['last_completed_date'] . "\n";
}
