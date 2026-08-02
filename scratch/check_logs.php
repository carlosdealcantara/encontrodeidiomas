<?php
require_once dirname(__DIR__) . '/config.php';
$conn = connectDB();
$hoje = new DateTime();
$diaDaSemanaAtual = (int)$hoje->format('N');
echo "Dia: $diaDaSemanaAtual\n";
$stmtMeetings = $conn->prepare("SELECT * FROM meetings WHERE active = 1 AND day_of_week = ?");
$stmtMeetings->execute([$diaDaSemanaAtual]);
print_r($stmtMeetings->fetchAll());
