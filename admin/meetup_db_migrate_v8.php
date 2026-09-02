<?php
/**
 * ============================================================
 * MIGRAÇÃO v8 — Fluxo Unilateral de Comunidades
 * ============================================================
 * Adiciona a coluna `comunidade` na tabela `meetings` para permitir
 * classificar cada encontro como 'brasil' ou 'global' (ou 'ambos').
 * 
 * Idempotente — seguro para rodar múltiplas vezes.
 */

session_start();
require_once '../config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$conn = connectDB();
$resultados = [];

function tryAlter(PDO $conn, string $sql, string $descricao): array {
    try {
        $conn->exec($sql);
        return ['ok' => true, 'msg' => "✅ $descricao"];
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), '1060') !== false || strpos($e->getMessage(), 'Duplicate column') !== false) {
            return ['ok' => true, 'msg' => "⏭️ $descricao (coluna já existia — OK)"];
        }
        return ['ok' => false, 'msg' => "❌ $descricao — ERRO: " . $e->getMessage()];
    }
}

// 1. meetings → comunidade
$resultados[] = tryAlter(
    $conn,
    "ALTER TABLE meetings ADD COLUMN comunidade ENUM('brasil','global','ambos') NOT NULL DEFAULT 'brasil' AFTER active",
    "meetings.comunidade"
);

$allOk = array_reduce($resultados, fn($carry, $r) => $carry && $r['ok'], true);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Migração v8 | Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; background: #0f172a; color: #f1f5f9; padding: 40px; }
        h2 { margin-bottom: 20px; }
        .item { padding: 12px 18px; border-radius: 8px; margin-bottom: 10px; background: #1e293b; font-size: 1rem; }
        .ok  { border-left: 4px solid #10b981; }
        .err { border-left: 4px solid #e31d1c; }
        .summary { margin-top: 25px; padding: 18px; border-radius: 12px; font-weight: bold; font-size: 1.1rem; }
        .summary.ok  { background: rgba(16,185,129,0.1); color: #10b981; border: 1px solid rgba(16,185,129,0.3); }
        .summary.err { background: rgba(227,29,28,0.1);  color: #e31d1c; border: 1px solid rgba(227,29,28,0.3);  }
        a { color: #38bdf8; }
    </style>
</head>
<body>
    <h2>🗄️ Migração v8 — Coluna de Comunidade nos Encontros</h2>

    <?php foreach ($resultados as $r): ?>
        <div class="item <?= $r['ok'] ? 'ok' : 'err' ?>">
            <?= htmlspecialchars($r['msg']) ?>
        </div>
    <?php endforeach; ?>

    <div class="summary <?= $allOk ? 'ok' : 'err' ?>">
        <?= $allOk
            ? '🎉 Migração v8 concluída com sucesso! (Tabela meetings atualizada)'
            : '⚠️ Houve erros na migração. Verifique os itens acima.' ?>
    </div>

    <p style="margin-top: 20px; color: #94a3b8;">
        <a href="meetings.php">← Voltar para Encontros</a>
    </p>
</body>
</html>
