<?php
require_once 'config.php';
try {
    $conn = connectDB();
    // 1. Renomeia a coluna youtube_link para replay_link
    $conn->exec("ALTER TABLE events CHANGE youtube_link replay_link VARCHAR(255) DEFAULT NULL");
    echo "Coluna youtube_link renomeada para replay_link com sucesso!\n";
} catch (Exception $e) {
    echo "Erro na renomeação: " . $e->getMessage() . "\n";
}
?>
