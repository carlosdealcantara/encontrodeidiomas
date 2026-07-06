<?php
require_once __DIR__ . '/config.php';
$conn = connectDB();
try {
    $stmt = $conn->query("SELECT id, titulo_final FROM mentoria_odysee_queue WHERE status='processing'");
    while($row = $stmt->fetch()) {
        echo "Processando: " . $row['titulo_final'] . " (ID " . $row['id'] . ")<br>";
    }
    echo "Fim.";
    echo "Success";
} catch (Exception $e) {
    echo $e->getMessage();
}
?>
