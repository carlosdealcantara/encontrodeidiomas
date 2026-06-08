<?php
require_once __DIR__ . '/config.php';
$conn = connectDB();

echo "=== TEMPLATES ===\n";
$stmt = $conn->query("SELECT * FROM meetup_whatsapp_templates");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\n=== MEETINGS DE HOJE (DIA 1) ===\n";
$stmt = $conn->query("SELECT * FROM meetings WHERE day_of_week = 1 AND active = 1");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "\n=== GRUPOS ===\n";
$stmt = $conn->query("SELECT * FROM meetup_whatsapp_groups WHERE ativo = 1");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
