<?php
require_once __DIR__ . '/../config.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);

$conn = connectDB();
try {
    $stmt = $conn->query("SHOW COLUMNS FROM meetup_whatsapp_groups LIKE 'bot_presente'");
    if ($stmt->rowCount() == 0) {
        $conn->exec("ALTER TABLE meetup_whatsapp_groups ADD COLUMN bot_presente TINYINT(1) NOT NULL DEFAULT 1 AFTER ativo");
        echo "Coluna 'bot_presente' adicionada com sucesso!\n";
    } else {
        echo "Coluna 'bot_presente' ja existe.\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
