<?php
require_once 'config.php';
try {
    $conn = connectDB();
    $newDesc = "Pratique japonês em ambiente acolhedor. Para todos os níveis.";
    
    $stmt = $conn->prepare("UPDATE events SET description = ? WHERE title = 'Japonês'");
    $stmt->execute([$newDesc]);
    
    echo "Descrição do Japonês encurtada com sucesso para 2 linhas!\n";
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}
?>
