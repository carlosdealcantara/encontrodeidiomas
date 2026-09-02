<?php
/**
 * Migration: Cria a tabela ebook_palavras para catalogação dos áudios do e-book.
 * Acessar via browser: dev.viaEi.com/admin/meetup_db_migrate_ebook.php
 */
session_start();
require_once '../config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    die('Acesso negado.');
}

$conn = connectDB();
$log = [];

try {
    // Criar tabela ebook_palavras
    $conn->exec("
        CREATE TABLE IF NOT EXISTS ebook_palavras (
            id            INT AUTO_INCREMENT PRIMARY KEY,
            numero        INT NOT NULL UNIQUE,
            audio_path    VARCHAR(500) DEFAULT NULL,
            titulo        VARCHAR(255) DEFAULT NULL,
            descricao     TEXT DEFAULT NULL,
            ativo         TINYINT(1) DEFAULT 0,
            gravado_em    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $log[] = ['ok', 'Tabela <strong>ebook_palavras</strong> criada (ou já existia).'];
} catch (Exception $e) {
    $log[] = ['err', 'Erro ao criar tabela: ' . $e->getMessage()];
}

// Verificar estrutura criada
try {
    $cols = $conn->query("DESCRIBE ebook_palavras")->fetchAll(PDO::FETCH_ASSOC);
    $colNames = array_column($cols, 'Field');
    $log[] = ['ok', 'Colunas encontradas: <code>' . implode(', ', $colNames) . '</code>'];
} catch (Exception $e) {
    $log[] = ['err', 'Erro ao verificar estrutura: ' . $e->getMessage()];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Migration: E-book Palavras</title>
    <style>
        body { font-family: monospace; background: #0f172a; color: #f1f5f9; padding: 40px; }
        h1 { color: #38bdf8; margin-bottom: 20px; }
        .ok  { color: #10b981; }
        .err { color: #ef4444; }
        ul { list-style: none; padding: 0; }
        li { padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,0.05); }
        a { color: #38bdf8; }
    </style>
</head>
<body>
    <h1>🗄️ Migration: E-book Palavras</h1>
    <ul>
        <?php foreach ($log as [$type, $msg]): ?>
            <li class="<?= $type ?>">
                <?= $type === 'ok' ? '✅' : '❌' ?> <?= $msg ?>
            </li>
        <?php endforeach; ?>
    </ul>
    <p style="margin-top: 30px; color: #94a3b8;">
        <a href="mentoria.php?tab=ebook">← Ir para o painel do E-book</a>
    </p>
</body>
</html>
