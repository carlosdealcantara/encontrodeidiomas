<?php
require_once __DIR__ . '/../config.php';
$conn = connectDB();
$stmt = $conn->query("SELECT id, name, flag_code, flag_emoji FROM languages WHERE name LIKE '%Libras%'");
print_r($stmt->fetchAll());
