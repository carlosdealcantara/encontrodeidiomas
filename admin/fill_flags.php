<?php
require '../config.php';
$conn = connectDB();
// Busca todos os idiomas com ambos os campos de bandeira
$stmt = $conn->query("SELECT id, name, flag_code, flag_emoji FROM languages ORDER BY name ASC");
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>
