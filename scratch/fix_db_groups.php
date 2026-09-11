<?php
require_once '../config.php';
try {
    $conn = connectDB();
    
    // Fix French group language code
    $stmt1 = $conn->prepare("UPDATE meetup_whatsapp_groups SET lang_code = 'fr' WHERE nome LIKE '%Français%' OR nome LIKE '%French%'");
    $stmt1->execute();
    echo "Grupos de Francês atualizados: " . $stmt1->rowCount() . "<br>\n";
    
    // Fix duplicate Indonesian group
    $stmt2 = $conn->prepare("UPDATE meetup_whatsapp_groups SET ativo = 0 WHERE nome = 'Bahasa Indonesia ID'");
    $stmt2->execute();
    echo "Grupo indonésio duplicado desativado: " . $stmt2->rowCount() . "<br>\n";
    
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
