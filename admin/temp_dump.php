<?php
require_once __DIR__ . '/../config.php';
$conn = connectDB();
$stmt = $conn->query("SELECT * FROM slugs WHERE type='anchor'");
echo "<pre>";
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
echo "</pre>";
