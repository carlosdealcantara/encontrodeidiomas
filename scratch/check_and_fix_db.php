<?php
require_once dirname(__DIR__) . '/config.php';

try {
    // Check what is currently processing
    $stmt = $conn->query("SELECT id, language_id, status, titulo_final, created_at, updated_at FROM odysee_publish_queue WHERE status = 'processing'");
    $processing = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "=== Processing Tasks ===\n";
    print_r($processing);
    
    // Fix zombies (processing for more than 1 hour)
    $conn->exec("UPDATE odysee_publish_queue SET status = 'error' WHERE status = 'processing' AND updated_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    
    // Also check pending tasks
    $stmt2 = $conn->query("SELECT id, language_id, status, titulo_final, created_at, updated_at FROM odysee_publish_queue WHERE status = 'pending'");
    $pending = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    echo "\n=== Pending Tasks ===\n";
    print_r($pending);
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
