<?php
require_once __DIR__ . '/config.php';

try {
    $conn = connectDB();
    
    $sql = "
    CREATE TABLE IF NOT EXISTS system_settings (
        chave VARCHAR(100) NOT NULL PRIMARY KEY,
        valor VARCHAR(255) NOT NULL DEFAULT '',
        descricao TEXT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    INSERT INTO system_settings (chave, valor, descricao) VALUES
    ('wpp_meetups_hourly_ativo', '0', 'Avisos automáticos de início de meetup. DESLIGADO no modo contenção.'),
    ('wpp_meetups_daily_ativo',  '0', 'Resumo diário de meetups (09:00). DESLIGADO no modo contenção.'),
    ('wpp_mentoria_ativo',       '1', 'Automações da mentoria (streaks, kickoff, ranking, cobrança). Manter ativo.')
    ON DUPLICATE KEY UPDATE descricao = VALUES(descricao);
    ";
    
    $conn->exec($sql);
    echo "SQL Executed Successfully\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
