<?php
require_once 'config.php';
try {
    $conn = connectDB();
    
    // 1. Garante que o idioma Servo-Croata existe
    $stmt = $conn->prepare("SELECT id FROM languages WHERE name LIKE '%Servo-Croata%'");
    $stmt->execute();
    $lang = $stmt->fetch();
    
    if (!$lang) {
        $conn->prepare("INSERT INTO languages (name, flag_code, active) VALUES ('Servo-Croata', 'rs', 1)")->execute();
        $langId = $conn->lastInsertId();
    } else {
        $langId = $lang['id'];
        $conn->prepare("UPDATE languages SET active = 1, flag_code = 'rs' WHERE id = ?")->execute([$langId]);
    }
    
    // 2. Insere/Atualiza o evento de Sábado (6) às 19h (20h no seu padrão se for 8pm, mas você disse 7 da noite = 19h)
    $title = "Servo-Croata";
    $desc  = "Explore a cultura dos Bálcãs praticando o idioma. Aberto a todos.";
    
    $stmt = $conn->prepare("SELECT id FROM events WHERE language_id = ? AND day_of_week = 6");
    $stmt->execute([$langId]);
    $event = $stmt->fetch();
    
    $sqlData = [
        $langId, 
        6, // Sábado
        19, // 19h
        $title, 
        $desc, 
        'https://meet.google.com/yxi-ndqz-jwv', 
        'https://odysee.com/@EncontrodeIdiomasservocroata', 
        'https://chat.whatsapp.com/JjJhr7WnTcz0YQhOhO6pdS', 
        'https://instagram.com/encontrodeidiomasservocroata', 
        1
    ];
    
    if (!$event) {
        $sql = "INSERT INTO events (language_id, day_of_week, time_hour, title, description, meet_link, replay_link, whatsapp_group_link, instagram_link, active) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $conn->prepare($sql)->execute($sqlData);
        echo "Servo-Croata cadastrado para Sábado às 19h!\n";
    } else {
        $sql = "UPDATE events SET 
                time_hour = ?, title = ?, description = ?, meet_link = ?, replay_link = ?, whatsapp_group_link = ?, instagram_link = ?, active = 1 
                WHERE id = ?";
        array_shift($sqlData); array_shift($sqlData);
        $sqlData[] = $event['id'];
        $conn->prepare($sql)->execute($sqlData);
        echo "Evento de Servo-Croata atualizado!\n";
    }
    
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}
?>
