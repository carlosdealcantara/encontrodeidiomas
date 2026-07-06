<?php
require_once __DIR__ . '/../config.php';

$conn = connectDB();
echo "Iniciando migração de banco de dados para Mentoria Odysee...<br><br>";

try {
    // 1. Criar tabela da fila
    $conn->exec("
        CREATE TABLE IF NOT EXISTS mentoria_odysee_queue (
            id INT AUTO_INCREMENT PRIMARY KEY,
            drive_file_id VARCHAR(255) NOT NULL UNIQUE,
            drive_file_name VARCHAR(500) NOT NULL,
            titulo_final VARCHAR(700) DEFAULT NULL,
            odysee_slug VARCHAR(100) DEFAULT NULL,
            odysee_url VARCHAR(500) DEFAULT NULL,
            whatsapp_message TEXT DEFAULT NULL,
            status ENUM('pending', 'processing', 'done', 'error') DEFAULT 'pending',
            error_message TEXT DEFAULT NULL,
            retry_count TINYINT DEFAULT 0,
            last_screenshot LONGTEXT DEFAULT NULL,
            last_screenshot_time DATETIME DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            processed_at TIMESTAMP NULL DEFAULT NULL
        )
    ");
    echo "1. Tabela 'mentoria_odysee_queue' criada ou já existente.<br>";

    // 2. Modificar ENUM na tabela meetup_whatsapp_groups
    $conn->exec("
        ALTER TABLE meetup_whatsapp_groups 
        MODIFY COLUMN categoria ENUM('multi_idioma', 'especifico', 'mentoria') NOT NULL DEFAULT 'multi_idioma'
    ");
    echo "2. Categoria 'mentoria' adicionada ao ENUM de 'meetup_whatsapp_groups'.<br>";
    
    // 3. Garantir coluna whatsapp_message caso a tabela já existisse
    try {
        $conn->exec("ALTER TABLE mentoria_odysee_queue ADD COLUMN whatsapp_message TEXT DEFAULT NULL AFTER odysee_url");
        echo "3. Coluna 'whatsapp_message' adicionada à tabela 'mentoria_odysee_queue'.<br>";
    } catch (PDOException $e) {
        // Coluna provavelmente já existe, ignora o erro
        echo "3. Coluna 'whatsapp_message' já existe na tabela 'mentoria_odysee_queue'.<br>";
    }

    echo "<br><strong>Migração concluída com sucesso!</strong>";

} catch (Exception $e) {
    echo "<br><strong style='color:red;'>ERRO:</strong> " . $e->getMessage();
}
?>
