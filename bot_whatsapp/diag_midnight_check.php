<?php
/**
 * DIAGNÓSTICO TEMPORÁRIO - horário do cron meia-noite
 * Remover após uso.
 */
require_once __DIR__ . '/../config.php';
$token_secreto = '83x9aZ2pLQw1';
if (!isset($_GET['token']) || $_GET['token'] !== $token_secreto) {
    http_response_code(403); die("Acesso Negado.");
}
header('Content-Type: text/plain; charset=utf-8');
$conn = connectDB();

echo "=== class_aviso em mentoria_auto_logs (ultimas 10 entradas) ===\n";
$rows = $conn->query("SELECT id, tipo, data_execucao, membro_jid, created_at FROM mentoria_auto_logs WHERE tipo = 'class_aviso' ORDER BY id DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) { echo json_encode($r) . "\n"; }

echo "\n=== mentoria_cron_execucoes: janela 02:45 - 03:30 UTC em 2026-10-05 (meia-noite BRT segunda) ===\n";
try {
    $rows2 = $conn->query("SELECT id, cron_name, executado_em, acao, notas FROM mentoria_cron_execucoes WHERE executado_em BETWEEN '2026-10-05 02:45:00' AND '2026-10-05 03:30:00' ORDER BY executado_em ASC")->fetchAll(PDO::FETCH_ASSOC);
    if (empty($rows2)) { echo "NENHUM REGISTRO NESSA JANELA.\n"; }
    foreach ($rows2 as $r) { echo json_encode($r) . "\n"; }
} catch (Exception $e) { echo "Erro: " . $e->getMessage() . "\n"; }

echo "\n=== mentoria_cron_execucoes: janela 02:45 - 03:30 UTC em 2026-10-02 (meia-noite BRT sexta) ===\n";
try {
    $rows3 = $conn->query("SELECT id, cron_name, executado_em, acao, notas FROM mentoria_cron_execucoes WHERE executado_em BETWEEN '2026-10-02 02:45:00' AND '2026-10-02 03:30:00' ORDER BY executado_em ASC")->fetchAll(PDO::FETCH_ASSOC);
    if (empty($rows3)) { echo "NENHUM REGISTRO NESSA JANELA.\n"; }
    foreach ($rows3 as $r) { echo json_encode($r) . "\n"; }
} catch (Exception $e) { echo "Erro: " . $e->getMessage() . "\n"; }

echo "\n=== Ultimas 20 entradas na mentoria_cron_execucoes ===\n";
try {
    $rows4 = $conn->query("SELECT id, cron_name, executado_em, acao FROM mentoria_cron_execucoes ORDER BY id DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows4 as $r) { echo json_encode($r) . "\n"; }
} catch (Exception $e) { echo "Erro: " . $e->getMessage() . "\n"; }
