<?php
require_once 'config.php';
try {
    $conn = connectDB();
    
    // 1. Garante que o idioma Japonês existe e está ativo
    $stmt = $conn->prepare("SELECT id FROM languages WHERE name LIKE '%Japonês%'");
    $stmt->execute();
    $lang = $stmt->fetch();
    
    if (!$lang) {
        $conn->prepare("INSERT INTO languages (name, flag_code, active) VALUES ('Japonês', 'jp', 1)")->execute();
        $langId = $conn->lastInsertId();
    } else {
        $langId = $lang['id'];
        $conn->prepare("UPDATE languages SET active = 1, flag_code = 'jp' WHERE id = ?")->execute([$langId]);
    }
    
    // 2. Insere ou Atualiza o evento de Segunda-feira
    $title = "Japonês";
    $desc  = "Pratique japonês com falantes de diferentes níveis. Comunicação direta e troca cultural.";
    
    $stmt = $conn->prepare("SELECT id FROM events WHERE language_id = ? AND day_of_week = 1");
    $stmt->execute([$langId]);
    $event = $stmt->fetch();
    
    $sqlData = [
        $langId, 
        1, // Segunda
        20, // 20h
        $title, 
        $desc, 
        'https://meet.google.com/hjt-eyny-sqm', 
        'https://odysee.com/@EncontrodeIdiomasJapones', 
        'https://chat.whatsapp.com/H3yeSgn3ff59hJBhTrLmet', 
        'https://www.instagram.com/encontrodeidiomasjapones/', 
        1
    ];
    
    if (!$event) {
        $sql = "INSERT INTO events (language_id, day_of_week, time_hour, title, description, meet_link, replay_link, whatsapp_group_link, instagram_link, active) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $conn->prepare($sql)->execute($sqlData);
        echo "Novo evento de Japonês criado!\n";
    } else {
        $sql = "UPDATE events SET 
                time_hour = ?, title = ?, description = ?, meet_link = ?, replay_link = ?, whatsapp_group_link = ?, instagram_link = ?, active = 1 
                WHERE id = ?";
        // Remove language_id e day_of_week da atualização, adiciona o ID do evento no fim
        array_shift($sqlData); array_shift($sqlData);
        $sqlData[] = $event['id'];
        $conn->prepare($sql)->execute($sqlData);
        echo "Evento de Japonês atualizado com novos links!\n";
    }
    
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}
?>
