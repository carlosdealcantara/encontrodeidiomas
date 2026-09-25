<?php
/**
 * Script one-shot para criar a tabela community_welcome_queue.
 * Execute uma vez e delete depois.
 */
require_once __DIR__ . '/../config.php';

$token = $_GET['token'] ?? '';
if ($token !== '83x9aZ2pLQw1') {
    http_response_code(403);
    die('Acesso negado.');
}

try {
    $conn = connectDB();
    $conn->exec("
        CREATE TABLE IF NOT EXISTS community_welcome_queue (
            id              INT AUTO_INCREMENT PRIMARY KEY,
            group_jid       VARCHAR(120)                      NOT NULL,
            participant_jid VARCHAR(120)                      NOT NULL,
            status          ENUM('pending','sent','failed')   NOT NULL DEFAULT 'pending',
            scheduled_at    DATETIME                          NOT NULL,
            sent_at         DATETIME                          NULL,
            attempts        TINYINT UNSIGNED                  NOT NULL DEFAULT 0,
            created_at      DATETIME                          NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_pending (status, scheduled_at)
        ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
    ");
    echo "✅ Tabela community_welcome_queue criada (ou já existia).";
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage();
}
