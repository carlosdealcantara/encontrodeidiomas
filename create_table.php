<?php
require 'config.php';
$sql = "CREATE TABLE IF NOT EXISTS odysee_worker_restarts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    restart_time DATETIME DEFAULT CURRENT_TIMESTAMP,
    reason VARCHAR(255)
)";
$pdo->exec($sql);
echo "Tabela criada com sucesso!\n";
?>
