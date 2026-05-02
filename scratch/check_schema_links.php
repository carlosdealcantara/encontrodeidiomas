<?php
require_once __DIR__ . '/../config.php';
$conn = connectDB();
$stmt = $conn->query("DESCRIBE useful_links");
print_r($stmt->fetchAll());
