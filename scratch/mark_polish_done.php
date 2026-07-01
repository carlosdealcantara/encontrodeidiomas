<?php
require_once dirname(__DIR__) . '/config.php';
try {
    $conn = connectDB();
    // Marca o polonês como done
    $stmt = $conn->prepare("UPDATE odysee_publish_queue SET status = 'done', error_message = 'Manual override' WHERE language_id = 10 AND status IN ('pending', 'processing')");
    $stmt->execute();
    echo "Atualizado: " . $stmt->rowCount() . " linha(s).\n";
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
