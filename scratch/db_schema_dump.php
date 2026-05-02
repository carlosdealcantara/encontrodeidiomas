<?php
require_once __DIR__ . '/../config.php';
$conn = connectDB();

echo "<h2>Tabelas e Colunas</h2><pre>";
$tables = $conn->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $table) {
    echo "<b>$table</b>\n";
    $cols = $conn->query("DESCRIBE $table")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $col) {
        echo "  - {$col['Field']} ({$col['Type']})\n";
    }
    echo "\n";
}

echo "<h2>Conteúdo de Meetings</h2>\n";
$meetings = $conn->query("SELECT id, title, whatsapp_group_link FROM meetings LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
print_r($meetings);

echo "<h2>Conteúdo de Hosts (JSON links)</h2>\n";
$hosts = $conn->query("SELECT id, full_name, social_media_links FROM hosts LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
print_r($hosts);

echo "</pre>";
