<?php
/**
 * Migration: adiciona initiative_label e initiative_url na tabela hosts
 * Acesse este arquivo UMA vez pelo browser e depois delete-o.
 */
require_once 'config.php';
$conn = connectDB();

$results = [];

$columns = [
    'initiative_label' => "ALTER TABLE hosts ADD COLUMN initiative_label VARCHAR(100) DEFAULT NULL",
    'initiative_url'   => "ALTER TABLE hosts ADD COLUMN initiative_url VARCHAR(255) DEFAULT NULL",
];

foreach ($columns as $col => $sql) {
    // Verifica se a coluna já existe
    $check = $conn->query("SHOW COLUMNS FROM hosts LIKE '$col'")->fetch();
    if ($check) {
        $results[] = "✅ Coluna <strong>$col</strong> já existe — nenhuma alteração necessária.";
    } else {
        try {
            $conn->exec($sql);
            $results[] = "✅ Coluna <strong>$col</strong> adicionada com sucesso.";
        } catch (Exception $e) {
            $results[] = "❌ Erro ao adicionar <strong>$col</strong>: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Migration: Initiative Fields</title>
    <style>
        body { font-family: sans-serif; max-width: 600px; margin: 60px auto; padding: 20px; background: #0f172a; color: #f1f5f9; }
        h2 { color: #e31d1c; }
        p { background: #1e293b; padding: 15px; border-radius: 8px; margin: 8px 0; }
        a { color: #e31d1c; }
    </style>
</head>
<body>
    <h2>Migration: Botão de Iniciativa</h2>
    <?php foreach ($results as $r): ?>
        <p><?= $r ?></p>
    <?php endforeach; ?>
    <p style="margin-top: 30px; border: 1px solid #e31d1c;">
        ⚠️ <strong>Delete este arquivo</strong> após confirmar o resultado acima.<br>
        <a href="/admin/hosts.php">← Voltar ao Admin</a>
    </p>
</body>
</html>
