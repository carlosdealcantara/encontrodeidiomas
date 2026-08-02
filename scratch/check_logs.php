<?php
require_once dirname(__DIR__) . '/config.php';
$conn = connectDB();
$stmt = $conn->query("SHOW EVENTS");
print_r($stmt->fetchAll());
