<?php
require_once __DIR__ . '/config.php';
$conn = connectDB();
try {
    $stmt = $conn->prepare("UPDATE mentoria_odysee_queue SET status='error', error_message='Cancelado pelo Admin' WHERE id=20");
    $stmt->execute();
    $stmt = $conn->prepare("UPDATE mentoria_odysee_queue SET status='pending', retry_count=0 WHERE status='processing'");
    $stmt->execute();
    echo "Tarefa 20 cancelada. Fila processing resetada para pending.";
    echo "Success";
} catch (Exception $e) {
    echo $e->getMessage();
}
?>
