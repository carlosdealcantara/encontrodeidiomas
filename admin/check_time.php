<?php
require '../config.php';
try {
    $out = [
        'timezone' => date_default_timezone_get(),
        'time' => date('Y-m-d H:i:s')
    ];
    
    $stmt = $conn->query("SELECT id, cenario FROM meetup_whatsapp_templates");
    $out['templates'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmtLog = $conn->query("SELECT * FROM meetup_whatsapp_logs ORDER BY id DESC LIMIT 5");
    $out['logs'] = $stmtLog->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($out, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
