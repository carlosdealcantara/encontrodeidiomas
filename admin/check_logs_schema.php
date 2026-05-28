<?php
require '../config.php';
try {
    $stmt = $conn->query("SHOW CREATE TABLE meetup_whatsapp_logs");
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode($res, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
