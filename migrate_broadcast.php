<?php
require_once __DIR__ . '/config.php';

$conn = connectDB();

try {
    $sql = "
    -- Fila de disparos manuais
    CREATE TABLE IF NOT EXISTS wpp_broadcast_queue (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        titulo       VARCHAR(255) NOT NULL,
        mensagem     TEXT NOT NULL,
        filtro_categoria ENUM('todos', 'multi_idioma', 'especifico') DEFAULT 'todos',
        filtro_language_id INT NULL,           -- só se especifico
        status       ENUM('pendente','enviando','concluido','erro') DEFAULT 'pendente',
        total_grupos INT DEFAULT 0,
        enviados     INT DEFAULT 0,
        criado_em    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        iniciado_em  TIMESTAMP NULL,
        concluido_em TIMESTAMP NULL,
        FOREIGN KEY (filtro_language_id) REFERENCES languages(id)
    );

    -- Log de cada envio individual
    CREATE TABLE IF NOT EXISTS wpp_broadcast_log (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        broadcast_id INT NOT NULL,
        group_id     VARCHAR(100) NOT NULL,
        group_nome   VARCHAR(255),
        status       ENUM('enviado','erro') DEFAULT 'enviado',
        erro_msg     TEXT NULL,
        enviado_em   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (broadcast_id) REFERENCES wpp_broadcast_queue(id) ON DELETE CASCADE
    );
    ";

    $conn->exec($sql);
    echo "Tabelas wpp_broadcast_queue e wpp_broadcast_log criadas com sucesso.\n";
} catch (PDOException $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}
