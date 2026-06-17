<?php
require_once '../config.php';
$conn = connectDB();

if (isset($_GET['update_july'])) {
    $conn->query("UPDATE mentoria_desafio_streaks SET longest_streak = 4, total_completions = 4 WHERE member_name LIKE '%Julyana%' OR member_name LIKE '%July%'");
}

$res = $conn->query("SELECT * FROM mentoria_desafio_streaks ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($res, JSON_PRETTY_PRINT);
