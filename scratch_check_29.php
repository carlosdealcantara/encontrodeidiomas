<?php
require_once __DIR__ . '/config.php';
$conn = connectDB();
$stmt = $conn->prepare("SELECT * FROM class_schedule WHERE id = 29");
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo "ID: {$row['id']}<br>";
echo "Hora: {$row['start_time']}<br>";
echo "Grupo JID: {$row['group_jid']}<br>";
echo "Tipo: {$row['session_type']}<br>";
