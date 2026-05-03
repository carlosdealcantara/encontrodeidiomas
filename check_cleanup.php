<?php
require_once 'config.php';
$conn = connectDB();

try {
    $stmt = $conn->query("DESCRIBE meetings");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $wa_exists = in_array('whatsapp_group_link', $columns);
    $ig_exists = in_array('instagram_link', $columns);
    
    if (!$wa_exists && !$ig_exists) {
        echo "SUCESSO: As colunas obsoletas foram removidas!";
    } else {
        echo "ALERTA: As colunas ainda existem. WA: ".($wa_exists?'Sim':'Não')." | IG: ".($ig_exists?'Sim':'Não');
    }
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
