<?php
require_once 'config.php';
try {
    $conn = connectDB();
    $name = 'Ane';
    
    $stmt = $conn->prepare("UPDATE hosts SET status = 'inativo' WHERE full_name LIKE :name AND status = 'ativo'");
    $nameParam = "%$name%";
    $stmt->bindParam(':name', $nameParam);
    $stmt->execute();
    
    $count = $stmt->rowCount();
    echo "<h3>Resultado:</h3>";
    echo ($count > 0) ? "Ane inativada com sucesso ($count registro(s))." : "Nenhum registro ativo encontrado para 'Ane'.";
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
?>
