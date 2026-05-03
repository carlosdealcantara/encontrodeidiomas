<?php
require_once 'config.php';
$conn = connectDB();

try {
    echo "Iniciando remoção das colunas obsoletas...\n";
    
    // Tenta remover whatsapp_group_link
    try {
        $conn->exec("ALTER TABLE meetings DROP COLUMN whatsapp_group_link");
        echo "- Coluna 'whatsapp_group_link' removida com sucesso.\n";
    } catch (PDOException $e) {
        echo "- Aviso: Coluna 'whatsapp_group_link' não encontrada ou já removida.\n";
    }

    // Tenta remover instagram_link
    try {
        $conn->exec("ALTER TABLE meetings DROP COLUMN instagram_link");
        echo "- Coluna 'instagram_link' removida com sucesso.\n";
    } catch (PDOException $e) {
        echo "- Aviso: Coluna 'instagram_link' não encontrada ou já removida.\n";
    }

    echo "\nLimpeza concluída!";
} catch (Exception $e) {
    echo "Erro fatal: " . $e->getMessage();
}
