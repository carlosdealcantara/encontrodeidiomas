<?php
require_once '../config.php';
$conn = connectDB();

if (isset($_GET['delete_phantom'])) {
    $conn->query("DELETE FROM mentoria_desafio_streaks WHERE member_jid LIKE '%@g.us%'");
}

$res = $conn->query("SELECT * FROM mentoria_desafio_streaks ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($res, JSON_PRETTY_PRINT);
