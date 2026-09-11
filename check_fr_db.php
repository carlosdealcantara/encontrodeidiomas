<?php
require_once 'config.php';
$conn = connectDB();
$stmt = $conn->query("SELECT group_id, nome, ativo, welcome_enabled, lang_code FROM meetup_whatsapp_groups WHERE lang_code = 'fr'");
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT);
