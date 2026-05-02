<?php
require_once __DIR__ . '/../config.php';

function migrateEvents() {
    $conn = connectDB();
    
    echo "Iniciando migração de 'events' para 'meetings'...\n";
    
    try {
        // Busca eventos antigos
        $stmt = $conn->query("SELECT * FROM events");
        $events = $stmt->fetchAll();
        
        foreach ($events as $ev) {
            // Verifica se já existe para evitar duplicatas
            $check = $conn->prepare("SELECT id FROM meetings WHERE language_id = ? AND day_of_week = ? AND time_hour = ?");
            $check->execute([$ev['language_id'], $ev['day_of_week'], $ev['time_hour']]);
            
            if (!$check->fetch()) {
                $insert = $conn->prepare("
                    INSERT INTO meetings (language_id, day_of_week, time_hour, title, description, meet_link, replay_link, whatsapp_group_link, active)
                    VALUES (:lang, :day, :hour, :title, :desc, :meet, :replay, :wa, :active)
                ");
                $insert->execute([
                    'lang'   => $ev['language_id'],
                    'day'    => $ev['day_of_week'],
                    'hour'   => $ev['time_hour'],
                    'title'  => $ev['title'],
                    'desc'   => $ev['description'],
                    'meet'   => $ev['meet_link'],
                    'replay' => $ev['replay_link'],
                    'wa'     => $ev['whatsapp_group_link'],
                    'active' => $ev['active']
                ]);
                echo "Migrado: {$ev['title']}\n";
            } else {
                echo "Pulado (já existe): {$ev['title']}\n";
            }
        }
        echo "Migração concluída!\n";
    } catch (PDOException $e) {
        echo "Erro na migração: " . $e->getMessage() . "\n";
    }
}

migrateEvents();
