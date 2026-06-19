<?php
require_once dirname(__DIR__) . '/config.php';
$conn = connectDB();

try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS meetup_replays (
            language_id INT PRIMARY KEY,
            numero VARCHAR(20),
            link VARCHAR(255),
            titulo VARCHAR(255),
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "Tabela meetup_replays criada com sucesso.\n";
} catch (PDOException $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}
