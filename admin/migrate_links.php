<?php
require_once __DIR__ . '/../config.php';

try {
    $conn = connectDB();
    
    echo "Iniciando atualização de esquema...\n";

    // Adicionar colunas se não existirem
    $queries = [
        "ALTER TABLE useful_links ADD COLUMN subtitle VARCHAR(255) DEFAULT NULL",
        "ALTER TABLE useful_links ADD COLUMN badge VARCHAR(50) DEFAULT NULL",
        "ALTER TABLE useful_links ADD COLUMN layout_type ENUM('standard', 'twin') DEFAULT 'standard'"
    ];

    foreach ($queries as $sql) {
        try {
            $conn->exec($sql);
            echo "Executado: $sql\n";
        } catch (PDOException $e) {
            echo "Nota: " . $e->getMessage() . " (Provavelmente a coluna já existe)\n";
        }
    }

    // Popular dados iniciais para não quebrar o layout atual
    $conn->exec("UPDATE useful_links SET layout_type = 'twin', badge = 'Comece por aqui', subtitle = 'Clique para entrar' WHERE id = 1");
    $conn->exec("UPDATE useful_links SET subtitle = 'Confira os dias e horários atuais' WHERE id = 2");
    $conn->exec("UPDATE useful_links SET subtitle = 'O maior grupo do projeto' WHERE id = 3");
    $conn->exec("UPDATE useful_links SET subtitle = 'Comunidade com diversos idiomas' WHERE id = 4");
    $conn->exec("UPDATE useful_links SET layout_type = 'twin', subtitle = 'Assista agora' WHERE id = 5");
    $conn->exec("UPDATE useful_links SET subtitle = 'Comunidade principal e grupos regionais' WHERE id = 6");

    echo "Sucesso! Banco de dados atualizado.\n";

} catch (Exception $e) {
    die("Erro fatal: " . $e->getMessage());
}
