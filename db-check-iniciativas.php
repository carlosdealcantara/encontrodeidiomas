<?php
require_once 'config.php';
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $res = $conn->query("SELECT DISTINCT initiative_label FROM hosts WHERE initiative_label IS NOT NULL AND initiative_label != ''");
    echo "<h1>Iniciativas no Banco:</h1><ul>";
    while($row = $res->fetch_assoc()) {
        echo "<li>" . htmlspecialchars($row['initiative_label']) . "</li>";
    }
    echo "</ul>";
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
?>
