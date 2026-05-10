<?php
/**
 * SCRIPT DE MIGRAÇÃO TEMPORÁRIO
 * Adiciona colunas de iniciativas que podem estar faltando no banco de produção.
 * APAGAR ESTE ARQUIVO APÓS EXECUTAR.
 */
require_once 'config.php';

$conn = connectDB();
$results = [];

$columns = [
    'initiative_label'       => "ALTER TABLE hosts ADD COLUMN initiative_label VARCHAR(255) DEFAULT NULL",
    'initiative_label_en'    => "ALTER TABLE hosts ADD COLUMN initiative_label_en VARCHAR(255) DEFAULT NULL",
    'initiative_url'         => "ALTER TABLE hosts ADD COLUMN initiative_url VARCHAR(500) DEFAULT NULL",
    'initiative_description' => "ALTER TABLE hosts ADD COLUMN initiative_description TEXT DEFAULT NULL",
    'initiative_description_en' => "ALTER TABLE hosts ADD COLUMN initiative_description_en TEXT DEFAULT NULL",
];

foreach ($columns as $col => $sql) {
    // Verifica se a coluna já existe
    $check = $conn->query("SHOW COLUMNS FROM hosts LIKE '$col'");
    if ($check->rowCount() > 0) {
        $results[] = "✅ Coluna <strong>$col</strong> já existe — nenhuma ação necessária.";
    } else {
        try {
            $conn->exec($sql);
            $results[] = "🆕 Coluna <strong>$col</strong> adicionada com sucesso!";
        } catch (Exception $e) {
            $results[] = "❌ Erro ao adicionar <strong>$col</strong>: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>Migração DB</title></head>
<body style="font-family:monospace;padding:30px;background:#111;color:#0f0;">
<h2>Resultado da Migração</h2>
<?php foreach ($results as $r): ?>
    <p><?= $r ?></p>
<?php endforeach; ?>
<hr>
<p style="color:red;font-weight:bold;">⚠️ APAGUE ESTE ARQUIVO AGORA: migrate_initiative_label_en.php</p>
</body>
</html>
