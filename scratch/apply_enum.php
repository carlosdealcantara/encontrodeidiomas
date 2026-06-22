<?php
require_once dirname(__DIR__) . '/config.php';
$conn = connectDB();

try {
    $conn->exec("ALTER TABLE odysee_publish_queue MODIFY COLUMN status ENUM('waiting_host', 'pending', 'processing', 'done', 'error') DEFAULT 'waiting_host'");
    echo "Enum updated.";
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
