<?php
require_once "config.php";
$conn = connectDB();
$stmt = $conn->query("SELECT * FROM mentoria_desafio_streaks ORDER BY id DESC LIMIT 5");
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
