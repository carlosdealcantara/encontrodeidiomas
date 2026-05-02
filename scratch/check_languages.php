<?php
require_once __DIR__ . '/../config.php';
$conn = connectDB();
$stmt = $conn->query("DESCRIBE languages");
$cols = $stmt->fetchAll();
echo "<pre>";
print_r($cols);
echo "</pre>";
