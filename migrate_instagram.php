<?php
require_once 'config.php';
$conn = connectDB();

try {
    // Busca o primeiro instagram_link preenchido para cada idioma na tabela meetings
    $stmt = $conn->query("
        SELECT language_id, instagram_link 
        FROM meetings 
        WHERE instagram_link IS NOT NULL AND instagram_link != ''
        GROUP BY language_id
    ");
    $links = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $count = 0;
    foreach ($links as $link) {
        $update = $conn->prepare("UPDATE languages SET instagram_link = ? WHERE id = ?");
        $update->execute([$link['instagram_link'], $link['language_id']]);
        $count++;
    }

    echo "Sucesso! Migrados $count links de Instagram para a tabela de idiomas.";
} catch (PDOException $e) {
    echo "Erro na migração: " . $e->getMessage();
}
