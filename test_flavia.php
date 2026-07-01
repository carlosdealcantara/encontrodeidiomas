<?php
require_once "config.php";
$conn = connectDB();
$stmt = $conn->query("SELECT * FROM mentoria_desafio_streaks WHERE member_name LIKE '%Fl_via%' OR member_name LIKE '%Flavia%'");
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
