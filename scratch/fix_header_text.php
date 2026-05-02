<?php
require_once __DIR__ . '/../config.php';
$conn = connectDB();

echo "Restaurando descrição original do site...\n";
$stmt = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'site_description'");
$stmt->execute(['Aprenda se divertindo!']);

echo "Pronto! Descrição restaurada.\n";
