<?php
require_once __DIR__ . '/../admin/config.php';

$conn = getDbConnection();
$stmt = $conn->prepare("UPDATE mentoria_odysee_queue SET status='pending' WHERE id=51");
$stmt->execute();
echo "<h1>Tarefa 51 revertida para PENDING com sucesso!</h1>";
echo "<p>O robô irá processá-la automaticamente na próxima varredura e o WhatsApp será disparado.</p>";
