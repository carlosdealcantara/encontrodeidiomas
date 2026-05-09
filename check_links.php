<?php
require_once 'config.php';
$conn = connectDB();
$links = $conn->query('SELECT id, title, title_en FROM useful_links')->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($links, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
