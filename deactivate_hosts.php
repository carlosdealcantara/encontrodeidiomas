<?php
require_once 'config.php';
try {
    $conn = connectDB();
    $names = ['Wellington', 'Michele', 'Jackelynne', 'Anne', 'Alyce'];
    
    echo "<h3>Atualizando status dos hosts:</h3><ul>";
    foreach ($names as $name) {
        // Busca por nomes que contenham essas strings
        $stmt = $conn->prepare("UPDATE hosts SET status = 'inativo' WHERE full_name LIKE :name");
        $nameParam = "%$name%";
        $stmt->bindParam(':name', $nameParam);
        $stmt->execute();
        
        $count = $stmt->rowCount();
        echo "<li><strong>$name:</strong> " . ($count > 0 ? "Inativado com sucesso ($count registro(s))" : "Nenhum registro encontrado ou já estava inativo") . "</li>";
    }
    echo "</ul>";
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
?>
