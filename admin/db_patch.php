<?php
require_once __DIR__ . '/../config.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);

$conn = connectDB();

echo "Iniciando migração de language_id para language_ids...\n";

try {
    $stmt = $conn->query("SHOW COLUMNS FROM meetup_whatsapp_groups LIKE 'language_ids'");
    if ($stmt->rowCount() == 0) {
        $conn->exec("ALTER TABLE meetup_whatsapp_groups ADD COLUMN language_ids JSON NULL AFTER categoria");
        echo "Coluna 'language_ids' adicionada com sucesso.\n";
        
        $grupos = $conn->query("SELECT id, language_id FROM meetup_whatsapp_groups WHERE categoria = 'especifico' AND language_id IS NOT NULL")->fetchAll();
        $stmtUpdate = $conn->prepare("UPDATE meetup_whatsapp_groups SET language_ids = ? WHERE id = ?");
        foreach ($grupos as $g) {
            $jsonArr = json_encode([(int)$g['language_id']]);
            $stmtUpdate->execute([$jsonArr, $g['id']]);
        }
        echo "Migrados " . count($grupos) . " grupos.\n";
        
        $dbName = DB_NAME;
        $fkQuery = $conn->prepare("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'meetup_whatsapp_groups' AND COLUMN_NAME = 'language_id' AND REFERENCED_TABLE_NAME IS NOT NULL");
        $fkQuery->execute([$dbName]);
        $fkName = $fkQuery->fetchColumn();
        
        if ($fkName) {
            $conn->exec("ALTER TABLE meetup_whatsapp_groups DROP FOREIGN KEY `$fkName`");
            echo "Foreign key '$fkName' removida.\n";
        }
        
        $conn->exec("ALTER TABLE meetup_whatsapp_groups DROP COLUMN language_id");
        echo "Coluna antiga 'language_id' removida.\n";
        
    } else {
        echo "A migração já foi realizada.\n";
    }
} catch (PDOException $e) {
    echo "Erro na migração: " . $e->getMessage() . "\n";
}
