<?php
require_once '../config.php';
$conn = connectDB();
$groups = $conn->query("SELECT nome FROM meetup_whatsapp_groups WHERE nome LIKE '%Bahasa%' AND ativo = 1")->fetchAll(PDO::FETCH_ASSOC);
foreach($groups as $g) {
    echo $g['nome'] . "\n";
}
