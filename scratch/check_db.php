<?php
require_once dirname(__DIR__) . '/config.php';
$conn = connectDB();
try {
    $conn->exec("UPDATE odysee_publish_queue SET status = 'waiting_host' WHERE language_id = (SELECT id FROM languages WHERE name = 'Inglês' LIMIT 1)");
    echo "Fila de inglês FORÇADA para waiting_host.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
