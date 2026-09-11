<?php
require_once '../config.php';
try {
    $conn = connectDB();
    $stmt = $conn->prepare("UPDATE meetup_whatsapp_groups SET ativo = 0 WHERE nome LIKE 'Bahasa Indonesia%' AND nome NOT LIKE 'Ei |%'");
    $stmt->execute();
    echo "Grupos indonésios antigos desativados: " . $stmt->rowCount() . "<br>\n";
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
