<?php
require_once dirname(__DIR__) . '/config.php';
$conn = connectDB();
$stmt = $conn->query("SELECT id, name, odysee_auth_token, odysee_channel_name FROM languages ORDER BY id");
$res = $stmt->fetchAll(PDO::FETCH_ASSOC);
header('Content-Type: application/json');
echo json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
