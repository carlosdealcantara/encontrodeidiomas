<?php
require_once 'config.php';
try {
    $conn = connectDB();
    $newDesc = "Mergulhe na cultura japonesa praticando o idioma. Todos os níveis.";
    
    $stmt = $conn->prepare("UPDATE events SET description = ? WHERE title = 'Japonês'");
    $stmt->execute([$newDesc]);
    
    echo "Descrição do Japonês atualizada para a versão final com sucesso!\n";
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}
?>
