<?php
require_once 'config.php';
try {
    $conn = connectDB();
    $stmt = $conn->prepare("SELECT full_name, email, whatsapp, instagram, linkedin FROM hosts WHERE full_name LIKE '%Carlos de Alcântara%'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<pre>";
    print_r($result);
    echo "</pre>";
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
?>
