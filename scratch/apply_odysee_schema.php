<?php
require_once __DIR__ . '/config.php';
$conn = connectDB();

try {
    $conn->exec("
        ALTER TABLE languages
        ADD COLUMN IF NOT EXISTS odysee_channel_id VARCHAR(255) DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS odysee_channel_name VARCHAR(255) DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS whatsapp_group_id VARCHAR(255) DEFAULT NULL
    ");
    echo "Tabela languages atualizada.\n";

    $conn->exec("
        CREATE TABLE IF NOT EXISTS odysee_publish_queue (
            id INT AUTO_INCREMENT PRIMARY KEY,
            language_id INT NOT NULL,
            drive_file_id VARCHAR(255) NOT NULL,
            drive_file_name VARCHAR(500) NOT NULL,
            topico VARCHAR(500) NOT NULL,
            titulo_final VARCHAR(700) DEFAULT NULL,
            odysee_slug VARCHAR(100) DEFAULT NULL,
            odysee_url VARCHAR(500) DEFAULT NULL,
            status ENUM('pending','processing','done','error') DEFAULT 'pending',
            error_message TEXT DEFAULT NULL,
            retry_count TINYINT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            processed_at TIMESTAMP NULL DEFAULT NULL,
            FOREIGN KEY (language_id) REFERENCES languages(id)
        )
    ");
    echo "Tabela odysee_publish_queue criada.\n";

} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
