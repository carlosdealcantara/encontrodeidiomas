<?php
require_once 'config.php';
$conn = connectDB();
$stmt = $conn->query("SELECT title, description FROM events LIMIT 3");
while($row = $stmt->fetch()) {
    echo "TITULO: " . $row['title'] . " | DESC: " . $row['description'] . "\n";
}
?>
