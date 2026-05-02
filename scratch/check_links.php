<?php
require_once __DIR__ . '/../config.php';
$conn = connectDB();

echo "<h2>Links Úteis no Banco</h2><pre>";
$stmt = $conn->query("SELECT * FROM useful_links ORDER BY title ASC");
$links = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($links);
echo "</pre>";
