<?php
require_once __DIR__ . '/config.php';
$conn = connectDB();
$res = $conn->query("SELECT * FROM mentoria_desafio_streaks")->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($res, JSON_PRETTY_PRINT);
