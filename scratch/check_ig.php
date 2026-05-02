<?php
require_once __DIR__ . '/../config.php';
$conn = connectDB();
$stmt = $conn->query("SELECT id, title, instagram_link FROM meetings WHERE instagram_link IS NOT NULL AND instagram_link != ''");
print_r($stmt->fetchAll());
