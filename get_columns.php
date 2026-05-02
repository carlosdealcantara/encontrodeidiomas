<?php
require_once 'config.php';
$conn = connectDB();
$stmt = $conn->query("DESCRIBE hosts");
$columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo implode(", ", $columns);
?>
