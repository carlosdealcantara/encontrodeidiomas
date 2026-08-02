<?php
require_once dirname(__DIR__) . '/config.php';
$conn = connectDB();
// Verifica quantos grupos existem e suas categorias
$stmt = $conn->query("SELECT id, nome, categoria, language_id, ativo FROM meetup_whatsapp_groups WHERE ativo = 1");
print_r($stmt->fetchAll());
