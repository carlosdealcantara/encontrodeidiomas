<?php
require_once dirname(__DIR__) . '/config.php';
$conn = connectDB();
$stmt = $conn->query("SELECT id, cenario FROM meetup_whatsapp_templates WHERE ativo = 1 AND cenario != 'Resumo do Dia'");
print_r($stmt->fetchAll());
