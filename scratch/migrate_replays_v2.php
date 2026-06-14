<?php
require_once dirname(__DIR__) . '/config.php';
$conn = connectDB();

try {
    // Add semana column and change primary key
    $conn->exec("ALTER TABLE meetup_replays ADD COLUMN semana VARCHAR(10) NOT NULL DEFAULT '' AFTER language_id");
    echo "Coluna 'semana' adicionada.\n";
    
    // Drop old primary key and add composite key
    $conn->exec("ALTER TABLE meetup_replays DROP PRIMARY KEY, ADD PRIMARY KEY (language_id, semana)");
    echo "Chave primária atualizada para (language_id, semana).\n";
    
    echo "\nMigração concluída!\n";
} catch (PDOException $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}
