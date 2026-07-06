<?php
require_once __DIR__ . '/config.php';
$conn = connectDB();
try {
    $stmt = $conn->prepare("DELETE FROM mentoria_odysee_queue WHERE id = 17 OR titulo_final LIKE '%FEEDBACK%' OR drive_file_name LIKE '%FEEDBACK%'");
    $stmt->execute();
    $stmt = $conn->prepare("UPDATE mentoria_odysee_queue SET status='pending', retry_count=0");
    $stmt->execute();
    echo "Fila resetada. Itens excluídos.";
    echo "Success";
} catch (Exception $e) {
    echo $e->getMessage();
}
?>
