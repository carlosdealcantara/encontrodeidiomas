<?php
/**
 * ============================================================
 * MIGRAÇÃO v7 — Suporte a Comunidade Brasil / Global
 * ============================================================
 * Adiciona as colunas necessárias para o sistema de templates
 * por comunidade. Seguro para rodar múltiplas vezes (idempotente).
 *
 * Alterações:
 *  1. meetup_whatsapp_groups   → coluna `comunidade` (brasil|global)
 *  2. meetup_whatsapp_templates → coluna `comunidade_alvo` (brasil|global|ambos)
 *  3. languages                → coluna `welcome_native` (boas-vindas no idioma-alvo)
 */

session_start();
require_once '../config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$conn = connectDB();
$resultados = [];

// Helper para rodar ALTER TABLE silenciosamente
function tryAlter(PDO $conn, string $sql, string $descricao): array {
    try {
        $conn->exec($sql);
        return ['ok' => true, 'msg' => "✅ $descricao"];
    } catch (PDOException $e) {
        // 1060 = Duplicate column name (já existe) → não é erro real
        if (strpos($e->getMessage(), '1060') !== false || strpos($e->getMessage(), 'Duplicate column') !== false) {
            return ['ok' => true, 'msg' => "⏭️ $descricao (coluna já existia — OK)"];
        }
        return ['ok' => false, 'msg' => "❌ $descricao — ERRO: " . $e->getMessage()];
    }
}

// 1. meetup_whatsapp_groups → comunidade
$resultados[] = tryAlter(
    $conn,
    "ALTER TABLE meetup_whatsapp_groups ADD COLUMN comunidade ENUM('brasil','global') NOT NULL DEFAULT 'brasil' AFTER ativo",
    "meetup_whatsapp_groups.comunidade"
);

// 2. meetup_whatsapp_templates → comunidade_alvo
$resultados[] = tryAlter(
    $conn,
    "ALTER TABLE meetup_whatsapp_templates ADD COLUMN comunidade_alvo ENUM('brasil','global','ambos') NOT NULL DEFAULT 'brasil' AFTER ativo",
    "meetup_whatsapp_templates.comunidade_alvo"
);

// 3. languages → welcome_native
$resultados[] = tryAlter(
    $conn,
    "ALTER TABLE languages ADD COLUMN welcome_native VARCHAR(255) DEFAULT NULL AFTER greeting",
    "languages.welcome_native"
);

$allOk = array_reduce($resultados, fn($carry, $r) => $carry && $r['ok'], true);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Migração v7 | Admin</title>
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
    <h2>🗄️ Migração v7 — Comunidade Brasil / Global</h2>

    <?php foreach ($resultados as $r): ?>
        <div class="item <?= $r['ok'] ? 'ok' : 'err' ?>">
            <?= htmlspecialchars($r['msg']) ?>
        </div>
    <?php endforeach; ?>

    <div class="summary <?= $allOk ? 'ok' : 'err' ?>">
        <?= $allOk
            ? '🎉 Migração concluída com sucesso! Todos os campos estão prontos.'
            : '⚠️ Houve erros na migração. Verifique os itens acima.' ?>
    </div>

    <p style="margin-top: 20px; color: #94a3b8;">
        <a href="meetup_groups.php">← Voltar para Grupos</a> |
        <a href="meetup_templates.php">Templates</a> |
        <a href="languages.php">Idiomas</a>
    </p>
</body>
</html>
