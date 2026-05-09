<?php
require_once 'config.php';

try {
    $conn = connectDB();
    
    // 1. Add columns to useful_links table
    $conn->exec("ALTER TABLE useful_links ADD COLUMN IF NOT EXISTS title_en VARCHAR(255)");
    $conn->exec("ALTER TABLE useful_links ADD COLUMN IF NOT EXISTS subtitle_en VARCHAR(255)");
    $conn->exec("ALTER TABLE useful_links ADD COLUMN IF NOT EXISTS badge_en VARCHAR(255)");
    
    // 2. Prepare translations
    $translations = [
        "Online" => [
            "title" => "Online",
            "subtitle" => "Click here to join",
            "badge" => "Group with Multiple Language Meetings"
        ],
        "Presencial" => [
            "title" => "In-Person",
            "subtitle" => "Watch it now",
            "badge" => "Presentation with event filming"
        ],
        "Encontro Presencial na Sua Cidade" => [
            "title" => "In-Person Meetings in Your City",
            "subtitle" => "Entry community and regional groups"
        ],
        "Inglês" => [
            "title" => "English",
            "subtitle" => "Language exclusive community"
        ],
        "Todos os Outros Idiomas" => [
            "title" => "All Other Languages",
            "subtitle" => "Community with dozens of languages"
        ],
        "Agenda dos Encontros Online" => [
            "title" => "Online Meeting Schedule",
            "subtitle" => "Check current days and times"
        ],
        "Mentoria de Inglês" => [
            "title" => "English Mentoring",
            "subtitle" => "Exclusive and personal classes"
        ]
    ];
    
    // 3. Update links
    $stmt = $conn->prepare("UPDATE useful_links SET title_en = :title, subtitle_en = :subtitle, badge_en = :badge WHERE title = :orig_title");
    
    foreach ($translations as $orig => $trans) {
        $stmt->execute([
            'title'    => $trans['title'] ?? null,
            'subtitle' => $trans['subtitle'] ?? null,
            'badge'    => $trans['badge'] ?? null,
            'orig_title' => $orig
        ]);
    }
    
    echo "Database migrated and useful links updated successfully.";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
