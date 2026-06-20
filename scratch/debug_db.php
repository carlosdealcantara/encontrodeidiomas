<?php
require_once __DIR__ . '/config.php';
$conn = connectDB();
$res = $conn->query("SELECT * FROM class_attendances WHERE aula_date='2026-06-18'")->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($res, JSON_PRETTY_PRINT);
