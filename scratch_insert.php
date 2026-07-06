<?php
require_once __DIR__ . '/config.php';
$conn = connectDB();
try {
    $stmt = $conn->prepare("UPDATE mentoria_odysee_queue SET status='pending', retry_count=0");
    $stmt->execute();
    echo "Fila resetada para pending.";
    echo "Success";
} catch (Exception $e) {
    echo $e->getMessage();
}
?>
