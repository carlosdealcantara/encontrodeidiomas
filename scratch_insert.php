<?php
require_once __DIR__ . '/config.php';
$conn = connectDB();
try {
    $stmt = $conn->prepare("DELETE FROM mentoria_odysee_queue WHERE drive_file_name LIKE '%FEEDBACK%'");
    $stmt->execute();
    $stmt = $conn->prepare("UPDATE mentoria_odysee_queue SET status='pending', retry_count=0");
    $stmt->execute();
    echo "Fila resetada para pending. FEEDBACK deletado.";
    echo "Success";
} catch (Exception $e) {
    echo $e->getMessage();
}
?>
