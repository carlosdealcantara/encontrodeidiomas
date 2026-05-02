<?php
require_once __DIR__ . '/../config.php';
$conn = connectDB();

echo "<h2>Padronizando links do Instagram...</h2><pre>";

// 1. Tabela meetings
try {
    $stmt = $conn->query("SELECT id, instagram_link FROM meetings WHERE instagram_link LIKE '%instagr.am%'");
    $meetings = $stmt->fetchAll();
    
    $updateStmt = $conn->prepare("UPDATE meetings SET instagram_link = :new_link WHERE id = :id");
    
    foreach ($meetings as $m) {
        $newLink = str_replace('instagr.am', 'instagram.com', $m['instagram_link']);
        $updateStmt->execute([':new_link' => $newLink, ':id' => $m['id']]);
        echo "Meeting ID {$m['id']}: {$m['instagram_link']} -> $newLink\n";
    }
    echo "Total de meetings atualizados: " . count($meetings) . "\n\n";
} catch (PDOException $e) {
    echo "Erro em meetings: " . $e->getMessage() . "\n";
}

// 2. Tabela hosts (JSON)
try {
    $stmt = $conn->query("SELECT id, full_name, social_media_links FROM hosts WHERE social_media_links LIKE '%instagr.am%'");
    $hosts = $stmt->fetchAll();
    
    $updateStmt = $conn->prepare("UPDATE hosts SET social_media_links = :new_json WHERE id = :id");
    
    foreach ($hosts as $h) {
        $newJson = str_replace('instagr.am', 'instagram.com', $h['social_media_links']);
        $updateStmt->execute([':new_json' => $newJson, ':id' => $h['id']]);
        echo "Host ID {$h['id']} ({$h['full_name']}): Link padronizado no JSON.\n";
    }
    echo "Total de hosts atualizados: " . count($hosts) . "\n";
} catch (PDOException $e) {
    echo "Erro em hosts: " . $e->getMessage() . "\n";
}

echo "</pre>";
