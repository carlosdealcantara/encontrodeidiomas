<?php
require '../config.php';
$conn = connectDB();
$stmt = $conn->prepare('SELECT id, name FROM languages WHERE flag_emoji = "" OR flag_emoji IS NULL');
$stmt->execute();
$missing = $stmt->fetchAll(PDO::FETCH_ASSOC);
header('Content-Type: application/json');
echo json_encode($missing);
?>
