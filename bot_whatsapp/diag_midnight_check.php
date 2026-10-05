<?php
require_once __DIR__ . '/../config.php';
$token_secreto = '83x9aZ2pLQw1';
if (!isset($_GET['token']) || $_GET['token'] !== $token_secreto) { http_response_code(403); die("Acesso Negado."); }
header('Content-Type: text/plain; charset=utf-8');
$conn = connectDB();

// O quorum cron registra TUDO na mentoria_cron_execucoes.
// Se o master_cron rodou às 00:00, 00:05, 00:10 -> deve ter entradas 03:00, 03:05, 03:10 UTC.
// Se não tem entradas nessa janela -> o cron da Hostinger simplesmente não foi chamado.
echo "=== mentoria_cron_execucoes: 02:50 a 03:20 UTC de 2026-10-05 (meia-noite BRT segunda) ===\n";
try {
    $r = $conn->query("SELECT executado_em, acao, notas FROM mentoria_cron_execucoes WHERE executado_em BETWEEN '2026-10-05 02:50:00' AND '2026-10-05 03:25:00' ORDER BY executado_em ASC");
    $rows = $r->fetchAll(PDO::FETCH_ASSOC);
    echo "Total de registros: " . count($rows) . "\n";
    foreach ($rows as $row) { echo $row['executado_em'] . " | " . $row['acao'] . " | " . $row['notas'] . "\n"; }
} catch (Exception $e) { echo "ERRO: " . $e->getMessage() . "\n"; }

// Comparar com quinta-feira (ultima que foi certinha)
echo "\n=== mentoria_cron_execucoes: 02:50 a 03:20 UTC de 2026-10-02 (meia-noite BRT quinta) ===\n";
try {
    $r = $conn->query("SELECT executado_em, acao, notas FROM mentoria_cron_execucoes WHERE executado_em BETWEEN '2026-10-02 02:50:00' AND '2026-10-02 03:20:00' ORDER BY executado_em ASC");
    $rows = $r->fetchAll(PDO::FETCH_ASSOC);
    echo "Total de registros: " . count($rows) . "\n";
    foreach ($rows as $row) { echo $row['executado_em'] . " | " . $row['acao'] . " | " . $row['notas'] . "\n"; }
} catch (Exception $e) { echo "ERRO: " . $e->getMessage() . "\n"; }
