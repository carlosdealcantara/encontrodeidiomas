<?php
// Script de migração: cria tabela in_person_events
require_once __DIR__ . '/../config.php';

$conn = connectDB();

$sql = "
CREATE TABLE IF NOT EXISTS in_person_events (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    title         VARCHAR(255) NOT NULL,
    city          VARCHAR(100) NOT NULL,
    state         VARCHAR(5),
    description   TEXT,
    host_id       INT,
    whatsapp_link VARCHAR(500),
    instagram_link VARCHAR(500),
    active        TINYINT(1) DEFAULT 1,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (host_id) REFERENCES hosts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

try {
    $conn->exec($sql);
    echo "<p style='color:green; font-family:monospace;'>✅ Tabela <strong>in_person_events</strong> criada/verificada com sucesso!</p>";
} catch (PDOException $e) {
    echo "<p style='color:red; font-family:monospace;'>❌ Erro: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Verificar se a tabela existe e quantas colunas tem
try {
    $r = $conn->query("DESCRIBE in_person_events")->fetchAll();
    echo "<p style='font-family:monospace;'>📋 Colunas: " . implode(', ', array_column($r, 'Field')) . "</p>";
} catch (PDOException $e) {
    echo "<p style='color:red;'>Erro ao descrever: " . $e->getMessage() . "</p>";
}
?>
